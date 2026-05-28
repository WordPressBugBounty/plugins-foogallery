<?php
/**
 * FooGallery_CSS_Load_Optimizer class which enqueues CSS in the head
 */
if (!class_exists('class-css-load-optimizer.php')) {

    class FooGallery_CSS_Load_Optimizer {

        function __construct() {
            add_action( 'wp_enqueue_scripts', array( $this, 'include_gallery_css' ) );
            add_action( 'foogallery_enqueue_style', array( $this, 'enqueue_style_to_persist' ), 10, 5 );
            add_action( 'wp_footer', array( $this, 'persist_enqueued_styles' ) );
        }

		/**
		 * Persist any styles that are enqueued to be persisted
		 */
        function persist_enqueued_styles() {
			global $wp_query, $foogallery_styles_to_persist;

			//we only want to do this if we are looking at a single post
			if ( ! is_singular() ) {
				return;
			}

			$post_id = $wp_query->post->ID;
			if ( $post_id  && is_array( $foogallery_styles_to_persist ) ) {
				foreach( $foogallery_styles_to_persist as $style_handle => $style ) {
					add_post_meta( $post_id, FOOGALLERY_META_POST_USAGE_CSS, array( $style_handle => $style ), false );
				}
			}
		}

        /**
         * Get the current post ids for the view that is being shown
         */
        function get_post_ids_from_query() {
            global $wp_query;

            if ( is_singular() ) {
                return array( $wp_query->post->ID );
            } else if ( is_array( $wp_query->posts ) ) {
                return wp_list_pluck( $wp_query->posts, 'ID' );
            } else {
                return array();
            }
        }

        /**
         * Checks the post meta for any FooGallery CSS that needs to be added to the head
         */
        function include_gallery_css() {
            global $enqueued_foogallery_styles;

            $enqueued_foogallery_styles = array();

            foreach( $this->get_post_ids_from_query() as $post_id ) {
                $this->include_gallery_stylesheets_for_post( $post_id );
            }
        }

        /**
         * includes any CSS that needs to be added for a post
         *
         * @param $post_id int ID of the post
         */
        function include_gallery_stylesheets_for_post( $post_id ) {
            global $enqueued_foogallery_styles;

            if ( $post_id ) {
                //get any foogallery stylesheets that the post might need to include
                $css = get_post_meta($post_id, FOOGALLERY_META_POST_USAGE_CSS);

				if ( empty( $css ) || !is_array( $css ) ) return;

                foreach ($css as $css_item) {
                    if ( !$css_item ) continue;
	                if ( empty( $css_item ) || !is_array( $css_item ) ) return; //make sure we are dealing with an array
                    foreach ($css_item as $handle => $style) {
                        //only enqueue the stylesheet once
                        if ( !array_key_exists( $handle, $enqueued_foogallery_styles ) ) {
                            $cache_buster_key = $handle;
                            if ( is_array( $style ) ) {
                                $cache_buster_key = $this->create_cache_buster_key( $handle, $style['ver'], array_key_exists( 'site', $style ) ? $style['site'] : '' );
                                wp_enqueue_style( $handle, $this->normalize_stylesheet_src_scheme( $style['src'] ), $style['deps'], $style['ver'], $style['media'] );
                            } else {
                                wp_enqueue_style( $handle, $this->normalize_stylesheet_src_scheme( $style ) );
                            }

                            $enqueued_foogallery_styles[$handle] = $cache_buster_key;
                        }
                    }
                }
            }
        }

        /**
         * Check to make sure we have added the stylesheets to our custom post meta field,
         * so that on next render the stylesheet will be added to the page header
         *
         * @param $style_handle string The stylesheet handle
         * @param $src string The location for the stylesheet
         * @param array $deps
         * @param bool $ver
         * @param string $media
         */
        function enqueue_style_to_persist($style_handle, $src, $deps = array(), $ver = false, $media = 'all') {
            global $wp_query, $enqueued_foogallery_styles, $foogallery_styles_to_persist;

            //we only want to do this if we are looking at a single post
            if ( ! is_singular() ) {
                return;
            }

            $post_id = $wp_query->post->ID;
            if ( $post_id ) {

                //check if the saved stylesheet needs to be cache busted
                if ( is_array( $enqueued_foogallery_styles ) && array_key_exists( $style_handle, $enqueued_foogallery_styles ) ) {
                    $registered_cache_buster_key = $enqueued_foogallery_styles[$style_handle];

                    //generate the key we want
                    $cache_buster_key = $this->create_cache_buster_key( $style_handle, $ver, home_url() );

                    if ( $registered_cache_buster_key !== $cache_buster_key ) {
                        //we need to bust this cached stylesheet!
                        $style = $this->get_old_style_post_meta_value( $post_id, $style_handle );

                        if ( false !== $style ) {
                        	//delete it from the post
                            delete_post_meta( $post_id, FOOGALLERY_META_POST_USAGE_CSS, array( $style_handle => $style ) );

                            //unset the handle, to force the save of the post meta
                            unset( $enqueued_foogallery_styles[$style_handle] );
                        }
                    }
                }

                //first check that the template has not been enqueued before
                if ( is_array( $enqueued_foogallery_styles ) && ! array_key_exists( $style_handle, $enqueued_foogallery_styles ) ) {

                    $style = array(
                        'src'   => $this->normalize_stylesheet_src_scheme( $src ),
                        'deps'  => $deps,
                        'ver'   => $ver,
                        'media' => $media,
                        'site'  => home_url()
                    );

                    if ( !is_array( $foogallery_styles_to_persist ) ) {
						$foogallery_styles_to_persist = array();
					}

					if ( !array_key_exists( $style_handle, $foogallery_styles_to_persist ) ) {
						$foogallery_styles_to_persist[$style_handle] = $style;
					}
                }
            }
        }

	    /**
	     * Create a key that will be used to cache
	     *
	     * @param        $name
	     * @param        $version
	     * @param string $site
	     *
	     * @return string
	     */
        function create_cache_buster_key( $name, $version, $site = '' ) {
            return "{$site}::{$name}_{$version}";
        }

	    /**
	     * Normalize cached stylesheet URLs to the configured front-end site scheme.
	     *
	     * The optimizer stores full URLs in post meta. If the same page is ever rendered under
	     * a different request scheme, WordPress can build plugin URLs with that scheme and the
	     * stale cached URL may later be emitted on the canonical front end.
	     *
	     * @param string $src The stylesheet URL.
	     *
	     * @return string
	     */
        function normalize_stylesheet_src_scheme( $src ) {
            if ( ! is_string( $src ) || ! preg_match( '#^https?://#i', $src ) ) {
                return $src;
            }

            if ( 'on' === foogallery_get_setting( 'force_https' ) ) {
                return set_url_scheme( $src, 'https' );
            }

            $home_url_parts = wp_parse_url( home_url( '/' ) );
            if ( ! is_array( $home_url_parts ) || empty( $home_url_parts['scheme'] ) ) {
                return $src;
            }

            $scheme = strtolower( $home_url_parts['scheme'] );
            if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
                return $src;
            }

            if ( ! $this->matches_site_url_host_and_port( $src ) || ! $this->matches_local_asset_url_root( $src ) ) {
                return $src;
            }

            return set_url_scheme( $src, $scheme );
        }

	    /**
	     * Check if a stylesheet URL is on the configured home or site host.
	     *
	     * @param string $src The stylesheet URL.
	     *
	     * @return bool
	     */
        function matches_site_url_host_and_port( $src ) {
            $src_parts = wp_parse_url( $src );
            if ( ! is_array( $src_parts ) || empty( $src_parts['host'] ) ) {
                return false;
            }

            foreach ( array_unique( array( home_url( '/' ), site_url( '/' ) ) ) as $site_url ) {
                $site_url_parts = wp_parse_url( $site_url );
                if ( ! is_array( $site_url_parts ) || empty( $site_url_parts['host'] ) ) {
                    continue;
                }

                if ( strtolower( $src_parts['host'] ) !== strtolower( $site_url_parts['host'] ) ) {
                    continue;
                }

                $src_port      = array_key_exists( 'port', $src_parts ) ? (int) $src_parts['port'] : null;
                $site_url_port = array_key_exists( 'port', $site_url_parts ) ? (int) $site_url_parts['port'] : null;
                if ( $src_port === $site_url_port ) {
                    return true;
                }
            }

            return false;
        }

	    /**
	     * Check if a stylesheet URL is under a local WordPress asset root.
	     *
	     * @param string $src The stylesheet URL.
	     *
	     * @return bool
	     */
        function matches_local_asset_url_root( $src ) {
            $roots = array(
                plugins_url( '/' ),
                get_stylesheet_directory_uri(),
                get_template_directory_uri(),
            );

            if ( defined( 'FOOGALLERY_URL' ) ) {
                $roots[] = FOOGALLERY_URL;
            }

            $upload_dir = wp_upload_dir();
            if ( ! empty( $upload_dir['baseurl'] ) ) {
                $roots[] = trailingslashit( $upload_dir['baseurl'] ) . 'foogallery/';
            }

            foreach ( array_unique( $roots ) as $root ) {
                if ( $this->matches_url_root_ignoring_scheme( $src, $root ) ) {
                    return true;
                }
            }

            return false;
        }

	    /**
	     * Check if a URL matches a root URL while ignoring the scheme only.
	     *
	     * @param string $url  The URL to check.
	     * @param string $root The root URL.
	     *
	     * @return bool
	     */
        function matches_url_root_ignoring_scheme( $url, $root ) {
            $url_parts  = wp_parse_url( $url );
            $root_parts = wp_parse_url( $root );

            if ( ! is_array( $url_parts ) || ! is_array( $root_parts ) || empty( $url_parts['host'] ) || empty( $root_parts['host'] ) ) {
                return false;
            }

            if ( strtolower( $url_parts['host'] ) !== strtolower( $root_parts['host'] ) ) {
                return false;
            }

            $url_port  = array_key_exists( 'port', $url_parts ) ? (int) $url_parts['port'] : null;
            $root_port = array_key_exists( 'port', $root_parts ) ? (int) $root_parts['port'] : null;
            if ( $url_port !== $root_port ) {
                return false;
            }

            $url_path  = isset( $url_parts['path'] ) ? $url_parts['path'] : '/';
            $root_path = isset( $root_parts['path'] ) ? trailingslashit( $root_parts['path'] ) : '/';

            if ( '/' === $root_path ) {
                return false;
            }

            return 0 === strpos( $url_path, $root_path );
        }

	    /**
	     * Get the old style handle that was linked to the post
	     *
	     * @param $post_id
	     * @param $handle_to_find
	     *
	     * @return false|mixed
	     */
        function get_old_style_post_meta_value( $post_id, $handle_to_find ) {
            $css = get_post_meta($post_id, FOOGALLERY_META_POST_USAGE_CSS);

            foreach ($css as $css_item) {
                if ( ! $css_item ) {
                    continue;
                }
                foreach ( $css_item as $handle => $style ) {
                    //only enqueue the stylesheet once
                    if ( $handle_to_find === $handle ) {
                        return $style;
                    }
                }
            }

            return false;
        }
    }
}
