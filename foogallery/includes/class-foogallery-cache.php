<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class used to cache gallery HTML output to save requests to the database
 * Date: 20/03/2017
 */
if ( ! class_exists( 'FooGallery_Cache' ) ) {

	class FooGallery_Cache {
		/**
		 * Version of the complete rendered-output cache boundary.
		 */
		const BOUNDARY_VERSION = '2';

		/**
		 * Request-local cache state, keyed by gallery object hash.
		 *
		 * @var array
		 */
		private $render_states = array();

		function __construct() {
			if ( is_admin() ) {
				//intercept the gallery save and save the html output to post meta
				add_action( 'foogallery_after_save_gallery', array( $this, 'cache_gallery_html_output_after_save' ), 10, 2 );

				//add some settings to allow the clearing and disabling of the cache
				add_filter( 'foogallery_admin_settings_override', array( $this, 'add_cache_settings' ) );

				//render the clear HTML cache button
				add_action( 'foogallery_admin_settings_custom_type_render_setting', array( $this, 'render_settings' ) );

				// Ajax call for clearing HTML cache
				add_action( 'wp_ajax_foogallery_clear_html_cache', array( $this, 'ajax_clear_all_caches' ) );

				add_action( 'foogallery_admin_new_version_detected', array( $this, 'clear_cache_on_update' ) );

				//clear the gallery caches when settings are updated or reset
				add_action( 'foogallery_settings_updated', array( $this, 'clear_all_gallery_caches' ) );
				add_action( 'foogallery_settings_reset', array( $this, 'clear_all_gallery_caches' ) );
			}

			add_filter( 'foogallery_load_gallery_template', array( $this, 'fetch_gallery_html_from_cache' ), 10, 3 );
			add_filter( 'foogallery_rendered_gallery_output', array( $this, 'filter_rendered_gallery_output' ), 10, 2 );
			add_filter( 'foogallery_rendering_cached_output', array( $this, 'is_rendering_cached_output' ), 10, 2 );

			add_filter( 'foogallery_html_cache_disabled', array( $this, 'disable_html_cache' ), 10, 3 );

			add_filter( 'foogallery_render_template_clear_globals' , array( $this, 'render_template_clear_globals' ) );

			// Complete output can include extension-provided markup, so extension changes invalidate it.
			add_action( 'foogallery_extension_activated', array( $this, 'clear_all_gallery_caches' ) );
			add_action( 'foogallery_extension_deactivated', array( $this, 'clear_all_gallery_caches' ) );
			add_action( 'activated_plugin', array( $this, 'clear_all_gallery_caches' ) );
			add_action( 'deactivated_plugin', array( $this, 'clear_all_gallery_caches' ) );
			add_action( 'switch_theme', array( $this, 'clear_all_gallery_caches' ) );
			add_action( 'upgrader_process_complete', array( $this, 'clear_all_gallery_caches' ) );
		}

		/**
		 * Override if the gallery html cache is disabled
		 *
		 * @param $disabled bool
		 * @param $gallery FooGallery
		 * @return bool
		 */
		function disable_html_cache( $disabled, $gallery ) {

			//check if the gallery sorting is random
			if ( 'rand' === $gallery->sorting ) {
				$disabled = true;
			}

			// FooGallery User Uploads renders a capability-sensitive form with a
			// request nonce inside the gallery boundary. A shared gallery cache
			// would either hide the form from eligible users or expose one user's
			// form and nonce to visitors who cannot upload.
			if (
				defined( 'FGFUU_FILE' ) &&
				'feu-after-gallery' === foogallery_gallery_template_setting( 'show_upload_form', 'feu-no' )
			) {
				$disabled = true;
			}

			if ( defined( 'FG_SOCIAL_FILE' ) ) {
				$social_enabled   = 'yes' === foogallery_gallery_template_setting( 'social_enabled', 'no' );
				$likes_enabled    = $social_enabled && 'yes' === foogallery_gallery_template_setting( 'likes_enabled', 'no' );
				$comments_enabled = $social_enabled && 'yes' === foogallery_gallery_template_setting( 'comments_enabled', 'no' );
				$current_page     = 'yes' === foogallery_gallery_template_setting( 'share_enabled', 'no' ) &&
					'current_page' === foogallery_gallery_template_setting( 'share_target', 'attachment_page' );

				// Likes/comments include mutable counts and visitor state in the
				// server-rendered data. Current-page sharing varies by embedding URL
				// and is active independently of the Social master toggle. Static
				// attachment-page sharing remains safe to cache.
				if ( $likes_enabled || $comments_enabled || $current_page ) {
					$disabled = true;
				}
			}

			return $disabled;
		}

		/**
		 * Save the HTML output of the gallery after the gallery has been saved
		 *
		 * @param $post_id
		 * @param $form_post
		 */
		function cache_gallery_html_output_after_save( $post_id, $form_post ) {
			$this->cache_gallery_html_output( $post_id );
		}

		/**
		 * Invalidate saved HTML so a frontend request rebuilds it in the correct context.
		 *
		 * @param $foogallery_id
		 */
		function cache_gallery_html_output( $foogallery_id ) {
			delete_post_meta( $foogallery_id, FOOGALLERY_META_CACHE );
			delete_post_meta( $foogallery_id, FOOGALLERY_META_CACHE . '_mobile_access' );
			delete_post_meta( $foogallery_id, FOOGALLERY_META_CACHE . '_boundary_version' );
			delete_post_meta( $foogallery_id, FOOGALLERY_META_CACHE . '_container_id' );
		}

		function is_caching_enabled() {
            global $current_foogallery_arguments;

			//do some checks if we are using arguments
			if ( isset( $current_foogallery_arguments ) ) {

                //never cache if showing a preview
                if ( array_key_exists( 'preview', $current_foogallery_arguments ) &&
                    true === $current_foogallery_arguments['preview'] ) {
                    return false;
                }

                //never cache if we are passing in extra arguments via the shortcode
                $array_keys = array_keys( $current_foogallery_arguments );
			    if ( $array_keys != array( 'id', 'gallery' ) ) {
                    return false;
                }
            }

			//next, check the settings
			if ( 'on' === foogallery_get_setting( 'enable_html_cache' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Override the template output
		 *
		 * @param $override
		 * @param $gallery
		 * @param $template_location
		 *
		 * @return bool
		 */
		function fetch_gallery_html_from_cache( $override, $gallery, $template_location ) {
			global $foogallery_force_gallery_cache;
			if ( isset( $foogallery_force_gallery_cache ) && $foogallery_force_gallery_cache ) {
				return $override;
			}

			//check if caching is disabled and quit early
			if ( !$this->is_caching_enabled() ) {
				return $override;
			}

			//allow extensions of others disable the html cache
			if ( apply_filters( 'foogallery_html_cache_disabled', false, $gallery ) ) {
				return $override;
			}

			$gallery_cache         = get_post_meta( $gallery->ID, FOOGALLERY_META_CACHE, true );
			$mobile_access         = get_post_meta( $gallery->ID, FOOGALLERY_META_CACHE . '_mobile_access', true );
			$boundary_version      = get_post_meta( $gallery->ID, FOOGALLERY_META_CACHE . '_boundary_version', true );
			$cached_container_id   = get_post_meta( $gallery->ID, FOOGALLERY_META_CACHE . '_container_id', true );
			$current_mobile_access = foogallery_mobile_settings_is_entitled() ? '1' : '0';
			if ( $current_mobile_access !== (string) $mobile_access || self::BOUNDARY_VERSION !== (string) $boundary_version || empty( $cached_container_id ) ) {
				// Older caches lack an entitlement marker, while upgraded and
				// downgraded installs carry the opposite marker. Rebuild once even
				// if a declaration was removed during that same transition.
				delete_post_meta( $gallery->ID, FOOGALLERY_META_CACHE );
				delete_post_meta( $gallery->ID, FOOGALLERY_META_CACHE . '_mobile_access' );
				delete_post_meta( $gallery->ID, FOOGALLERY_META_CACHE . '_boundary_version' );
				delete_post_meta( $gallery->ID, FOOGALLERY_META_CACHE . '_container_id' );
				$gallery_cache = '';
			}

			if ( !empty( $gallery_cache ) && is_string( $gallery_cache ) ) {
				$this->render_states[ spl_object_hash( $gallery ) ] = array(
					'cached_html'         => $gallery_cache,
					'cached_container_id' => $cached_container_id,
				);
				return true; //return that we will override
			} else {
				$this->render_states[ spl_object_hash( $gallery ) ] = array( 'cache_miss' => true );
			}

			return $override;
		}

		/**
		 * Tell output-producing extensions that the complete response will be
		 * replaced by a cache hit. Public lifecycle hooks still run, but expensive
		 * request output can be skipped safely because it already exists in cache.
		 *
		 * @param bool       $cached  Existing cached-render state.
		 * @param FooGallery $gallery Gallery being rendered.
		 * @return bool
		 */
		function is_rendering_cached_output( $cached, $gallery ) {
			if ( ! $gallery instanceof FooGallery ) {
				return $cached;
			}

			$state_key = spl_object_hash( $gallery );
			return $cached || ( isset( $this->render_states[ $state_key ]['cached_html'] ) );
		}

		/**
		 * Replace the complete live render with a cache hit, or persist a complete
		 * cache miss after all public render hooks have run.
		 *
		 * @param string     $output  Complete rendered gallery output.
		 * @param FooGallery $gallery Gallery being rendered.
		 * @return string
		 */
		function filter_rendered_gallery_output( $output, $gallery ) {
			$state_key = spl_object_hash( $gallery );
			if ( ! isset( $this->render_states[ $state_key ] ) ) {
				return $output;
			}

			$state = $this->render_states[ $state_key ];
			unset( $this->render_states[ $state_key ] );

			if ( isset( $state['cached_html'] ) ) {
				return str_replace( $state['cached_container_id'], $gallery->container_id(), $state['cached_html'] );
			}

			update_post_meta( $gallery->ID, FOOGALLERY_META_CACHE, wp_slash( $output ) );
			update_post_meta( $gallery->ID, FOOGALLERY_META_CACHE . '_mobile_access', foogallery_mobile_settings_is_entitled() ? '1' : '0' );
			update_post_meta( $gallery->ID, FOOGALLERY_META_CACHE . '_boundary_version', self::BOUNDARY_VERSION );
			update_post_meta( $gallery->ID, FOOGALLERY_META_CACHE . '_container_id', $gallery->container_id() );

			return $output;
		}

		/**
		 * Add some caching settings
		 * @param $settings
		 *
		 * @return array
		 */
		function add_cache_settings( $settings ) {

			$cache_settings[] = array(
				'id'      => 'enable_html_cache',
				'title'   => __( 'Enable HTML Cache', 'foogallery' ),
				'desc'    => __( 'The gallery HTML that is generated can be cached. This can reduce the number of calls to the database when displaying a gallery and can increase site performance.', 'foogallery' ),
				'type'    => 'checkbox',
				'tab'     => 'general',
				'section' => __( 'Performance', 'foogallery' )
			);

			$cache_settings[] = array(
				'id'      => 'clear_html_cache',
				'title'   => __( 'Clear HTML Cache', 'foogallery' ),
				'desc'    => __( 'If you enable the HTML cache, then you can use this button to clear the gallery HTML that has been cached for all galleries.', 'foogallery' ),
				'type'    => 'clear_gallery_cache_button',
				'tab'     => 'general',
				'section' => __( 'Performance', 'foogallery' )
			);

			$new_settings = array_merge( $cache_settings, $settings['settings'] );

			$settings['settings'] = $new_settings;

			return $settings;
		}

		/**
		 * Render any custom setting types to the settings page
		 */
		function render_settings( $args ) {
			if ('clear_gallery_cache_button' === $args['type'] ) { ?>
				<div id="foogallery_clear_html_cache_container">
					<input type="button" data-nonce="<?php echo esc_attr( wp_create_nonce( 'foogallery_clear_html_cache' ) ); ?>" class="button-primary foogallery_clear_html_cache" value="<?php esc_attr_e( 'Clear Gallery HTML Cache', 'foogallery' ); ?>">
					<span id="foogallery_clear_html_cache_spinner" style="position: absolute" class="spinner"></span>
				</div>
			<?php }
		}

		/**
		 * AJAX endpoint for clearing all gallery caches
		 */
		function ajax_clear_all_caches() {
			if ( check_admin_referer( 'foogallery_clear_html_cache' ) ) {

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array(
						'message' => __( 'You do not have permission!', 'foogallery' ),
					), 403 );
				}

				$this->clear_all_gallery_caches();

				esc_html_e('The cache for all galleries has been cleared!', 'foogallery' );
				wp_die( '', '', array( 'response' => null ) );
			}
		}

		/**
		 * Clears all caches for all galleries
		 */
		function clear_all_gallery_caches() {
			delete_post_meta_by_key( FOOGALLERY_META_CACHE );
			delete_post_meta_by_key( FOOGALLERY_META_CACHE . '_mobile_access' );
			delete_post_meta_by_key( FOOGALLERY_META_CACHE . '_boundary_version' );
			delete_post_meta_by_key( FOOGALLERY_META_CACHE . '_container_id' );
		}

		/**
		 * Clear all caches when the plugin has been updated. This is to account for changes in the HTML when a new version is released.
		 */
		function clear_cache_on_update() {
			$this->clear_all_gallery_caches();
		}

		/**
		 * Determine if the globals should be cleared when rendering a gallery
		 *
		 * @param $clear
		 *
		 * @return bool
		 */
		function render_template_clear_globals( $clear ) {
			global $foogallery_force_gallery_cache;
			if ( $foogallery_force_gallery_cache ) {
				return false;
			}

			return $clear;
		}
	}
}
