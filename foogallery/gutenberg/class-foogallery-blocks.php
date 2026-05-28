<?php
/**
 * FooGallery Blocks Initializer
 *
 * Enqueue CSS/JS of all the FooGallery blocks.
 *
 * @since   1.0.0
 * @package CGB
 */

if ( ! class_exists( 'FooGallery_Blocks' ) ) {
	class FooGallery_Blocks {

		function __construct() {
			//Backend editor block assets.
			//add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
			add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_editor_assets' ) );

			add_action( 'init', array( $this, 'php_block_init' ) );

			add_filter( 'foogallery_build_container_data_options', array( $this, 'add_data_options_for_block_editor' ), 10, 3 );
		}

		/**
		 * Enqueue Gutenberg block assets for backend editor.
		 *
		 * `wp-blocks`: includes block type registration and related functions.
		 * `wp-element`: includes the WordPress Element abstraction for describing the structure of your blocks.
		 * `wp-i18n`: To internationalize the block's text.
		 *
		 * @since 1.0.0
		 */
		function enqueue_block_editor_assets() {

			if ( !apply_filters( 'foogallery_gutenberg_enabled', true ) ) {
				return;
			}

			if ( !is_admin() ) {
				return;
			}

			//enqueue foogallery dependencies
			wp_enqueue_script( 'lodash' );
			wp_enqueue_script( 'masonry' );
			foogallery_enqueue_core_gallery_template_script( array('jquery', 'masonry' ) );
			foogallery_enqueue_core_gallery_template_style();

            $path = FOOGALLERY_PATH . 'gutenberg/assets/blocks';
            $url = FOOGALLERY_URL . 'gutenberg/assets/blocks';
            $asset = require( $path . '.asset.php' );
            if ( ! is_array( $asset ) ) {
                $asset = array(
                    'dependencies' => array(),
                    'version' => false,
                );
            }

            if ( isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ){
                $asset['dependencies'][] = 'foogallery-core';
            }

			// Scripts.
			wp_enqueue_script(
				'foogallery-block-js', // Handle.
                $url . '.js', // Block.build.js: We register the block here. Built with Webpack.
                $asset[ 'dependencies' ], // Dependencies, defined above.
                $asset[ 'version' ],
				true // Enqueue the script in the footer.
			);

			// Styles.
			wp_enqueue_style(
				'foogallery-block-editor-css', // Handle.
				$url . '.css', // Block editor CSS.
				array( 'dashicons', 'wp-components', 'wp-edit-blocks', 'foogallery-core' ), // Dependency to include the CSS after it.
                $asset[ 'version' ]
			);

			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'foogallery-block-js', 'foogallery' );
			}

			$block_js_data = apply_filters('foogallery_gutenberg_block_js_data', array(
				"editGalleryUrl" => $this->get_edit_gallery_url(),
				"dynamicOptions" => $this->get_dynamic_gallery_options()
			));

			$inline_script = 'if ( typeof window.lodash === "undefined" && typeof window._ !== "undefined" ) { window.lodash = window._; }';
			$inline_script .= PHP_EOL . 'if ( typeof window._ === "undefined" && typeof window.lodash !== "undefined" ) { window._ = window.lodash; }';
			$inline_script .= PHP_EOL . 'window.FOOGALLERY_BLOCK = ' . json_encode( $block_js_data ) . ';';

			wp_add_inline_script(
				'foogallery-block-js',
				$inline_script,
				'before'
			);
		}


		/**
		 * Get options used by the dynamic block inspector controls.
		 *
		 * @return array
		 */
		function get_dynamic_gallery_options() {
			$templates = array();
			$gallery_templates = foogallery_gallery_templates();

			if ( is_array( $gallery_templates ) ) {
				foreach ( $gallery_templates as $gallery_template ) {
					if ( ! is_array( $gallery_template ) || ! isset( $gallery_template['slug'] ) ) {
						continue;
					}

					$slug = sanitize_key( $gallery_template['slug'] );
					if ( empty( $slug ) ) {
						continue;
					}

					$templates[ $slug ] = isset( $gallery_template['name'] ) ? sanitize_text_field( $gallery_template['name'] ) : $slug;
				}
			}

			$default_template = foogallery_default_gallery_template();
			if ( empty( $default_template ) || ! isset( $templates[ $default_template ] ) ) {
				$default_template = 'default';
			}

			$lightboxes = foogallery_gallery_template_field_lightbox_choices();
			if ( ! is_array( $lightboxes ) ) {
				$lightboxes = array();
			}

			return array(
				'templates' => $templates,
				'lightboxes' => $lightboxes,
				'defaultTemplate' => $default_template,
			);
		}

		function get_edit_gallery_url() {
			$post_type_object = get_post_type_object( "foogallery" );
			if ( !$post_type_object )
				return '';

			if ( $post_type_object->_edit_link ) {
				$link = admin_url( $post_type_object->_edit_link . '&action=edit' );
			} else {
				$link = '';
			}

			return apply_filters( 'foogallery_gutenberg_edit_gallery_url', $link );
		}

		/**
		 * Register our block and shortcode.
		 */
		function php_block_init() {
			if ( !apply_filters( 'foogallery_gutenberg_enabled', true ) ) {
				return;
			}

			//get out quickly if no Gutenberg
			if ( !function_exists( 'register_block_type' ) ) {
				return;
			}

			if ( function_exists( 'register_block_type_from_metadata' ) ) {
				register_block_type_from_metadata(
					FOOGALLERY_PATH . 'gutenberg',
					array(
						'render_callback' => array( $this, 'render_block' ),
					)
				);
			} else {
				// Register our block, and explicitly define the attributes we accept.
				register_block_type(
					'fooplugins/foogallery', array(
					'attributes' => array(
							'id' => array(
								'type' => 'number',
								'default' => 0
							),
							'attachment_ids' => array(
								'type' => 'array',
								'default' => array()
							),
							'template' => array(
								'type' => 'string',
								'default' => ''
							),
							'lightbox' => array(
								'type' => 'string',
								'default' => ''
							),
							'paging_type' => array(
								'type' => 'string',
								'default' => ''
							),
							'filtering_type' => array(
								'type' => 'string',
								'default' => ''
							),
							'className' => array(
								'type' => 'string'
							),
						),
						'render_callback' => array( $this, 'render_block' ),
				));
			}
		}

		/**
		 * Render the contents of the block
		 *
		 * @param $attributes
		 *
		 * @return false|string|null
		 */
		function render_block( $attributes ) {
			$attributes = is_array( $attributes ) ? $attributes : array();
			$attributes['id'] = isset( $attributes['id'] ) ? absint( $attributes['id'] ) : 0;
			$attachment_ids = array();
			if ( isset( $attributes['attachment_ids'] ) && is_array( $attributes['attachment_ids'] ) ) {
				$attachment_ids = array_values( array_filter( array_map( 'absint', $attributes['attachment_ids'] ) ) );
			}

			$is_dynamic_gallery = ! empty( $attachment_ids );

			if ( $is_dynamic_gallery ) {
				$attributes['attachment_ids'] = $attachment_ids;

				foreach ( array( 'template', 'lightbox', 'paging_type', 'filtering_type' ) as $key ) {
					if ( ! array_key_exists( $key, $attributes ) ) {
						continue;
					}

					$attributes[ $key ] = sanitize_key( $attributes[ $key ] );
					if ( '' === $attributes[ $key ] ) {
						unset( $attributes[ $key ] );
					}
				}
			} else {
				unset( $attributes['attachment_ids'], $attributes['template'], $attributes['lightbox'], $attributes['paging_type'], $attributes['filtering_type'] );
			}

			//create new instance of template engine
			$engine = new FooGallery_Template_Loader();

			ob_start();

			$engine->render_template( $attributes );

			$output_string = ob_get_contents();
			ob_end_clean();
			return !empty($output_string) ? $output_string : null;
		}

		/**
		 * Returns true if the block editor is being used
		 *
		 * @return bool
		 */
		function is_being_rendered_in_block_editor() {
			return defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $_REQUEST['context'] ) && 'edit' === $_REQUEST['context'];
		}

		/**
		 * Add data options needed for lazy loading to work with the block editor
		 *
		 * @param $options
		 * @param $gallery    FooGallery
		 * @param $attributes array
		 *
		 * @return array
		 */
		function add_data_options_for_block_editor( $options, $gallery, $attributes ) {
			if ( $this->is_being_rendered_in_block_editor() ) {
				$options['scrollParent'] = '.edit-post-layout__content';
			}

			return $options;
		}
	}
}
