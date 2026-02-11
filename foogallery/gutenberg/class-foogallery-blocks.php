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
				"editGalleryUrl" => $this->get_edit_gallery_url()
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
