<?php
/**
 * Divi compatibility.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FooGallery_Divi_Compatibility' ) ) {

	/**
	 * Integrates FooGallery with the Divi Builder.
	 */
	class FooGallery_Divi_Compatibility {

		/**
		 * Whether the Divi module has been registered during this request.
		 *
		 * @var bool
		 */
		private $module_registered = false;

		/**
		 * Number of module shortcodes rendered without Divi's legacy PHP runtime.
		 *
		 * @var int
		 */
		private $fallback_render_count = 0;

		/**
		 * Register Divi compatibility hooks.
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'register_shortcode_fallback' ), 5 );
			add_action( 'et_builder_ready', array( $this, 'register_module' ) );
			add_action( 'et_builder_module_lazy_shortcodes_registered', array( $this, 'register_module' ) );
			add_action( 'divi_visual_builder_assets_after_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'et_fb_enqueue_assets', array( $this, 'enqueue_assets' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_classic_builder_assets' ), 20 );

			add_filter( 'foogallery_find_galleries_in_post', array( $this, 'find_galleries_in_divi_modules' ), 10, 2 );
		}

		/**
		 * Ensure Divi's preview request recognizes the module shortcode.
		 *
		 * Divi's lazy shortcode manager persists third-party module slugs when a
		 * plugin is activated. A module added by a plugin update is not present in
		 * that cache yet, so Divi 5 can otherwise leave the shortcode unprocessed
		 * in its hidden preview document.
		 *
		 * @return void
		 */
		public function register_shortcode_fallback() {
			if ( ! shortcode_exists( 'foogallery_divi_gallery' ) ) {
				add_shortcode( 'foogallery_divi_gallery', array( $this, 'render_module_shortcode_fallback' ) );
			}
		}

		/**
		 * Load and delegate to the Divi module if its lazy loader missed the slug.
		 *
		 * @param array|string $attributes Shortcode attributes.
		 * @param string|null  $content    Enclosed shortcode content.
		 * @param string       $tag        Shortcode tag.
		 *
		 * @return string
		 */
		public function render_module_shortcode_fallback( $attributes, $content = null, $tag = 'foogallery_divi_gallery' ) {
			$content = is_string( $content ) ? $content : '';

			if ( ! class_exists( 'ET_Builder_Module' ) && function_exists( 'et_builder_add_main_elements' ) ) {
				et_builder_add_main_elements();
			}

			if ( class_exists( 'ET_Builder_Module' ) ) {
				require_once FOOGALLERY_PATH . 'includes/compatibility/divi/class-foogallery-divi-module.php';

				if ( class_exists( 'FooGallery_Divi_Module' ) ) {
					$module                  = new FooGallery_Divi_Module();
					$this->module_registered = true;

					return $module->_render( $attributes, $content, $tag );
				}
			}

			$attributes = shortcode_atts(
				array(
					'gallery_id' => 0,
				),
				is_array( $attributes ) ? $attributes : array(),
				$tag
			);

			$gallery_id = absint( $attributes['gallery_id'] );

			if ( $gallery_id > 0 ) {
				ob_start();
				foogallery_render_gallery( $gallery_id );
				$gallery_output = ob_get_clean();
			} else {
				$gallery_output = '<p>' . esc_html__( 'Please select a gallery to display.', 'foogallery' ) . '</p>';
			}

			$order_class = 'foogallery_divi_gallery_' . $this->fallback_render_count;
			++$this->fallback_render_count;

			return sprintf(
				'<div class="et_pb_module et_d4_element foogallery_divi_gallery %1$s">%2$s</div>',
				esc_attr( $order_class ),
				$gallery_output // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FooGallery renders escaped gallery markup.
			);
		}

		/**
		 * Register the FooGallery module after Divi has loaded its module API.
		 *
		 * @return void
		 */
		public function register_module() {
			if ( $this->module_registered || ! class_exists( 'ET_Builder_Module' ) ) {
				return;
			}

			require_once FOOGALLERY_PATH . 'includes/compatibility/divi/class-foogallery-divi-module.php';

			if ( class_exists( 'FooGallery_Divi_Module' ) ) {
				new FooGallery_Divi_Module();
				$this->module_registered = true;
			}
		}

		/**
		 * Load FooGallery assets before Divi renders shortcode or module previews.
		 *
		 * @return void
		 */
		public function enqueue_assets() {
			foogallery_enqueue_core_gallery_template_script();
			foogallery_enqueue_core_gallery_template_style();

			wp_enqueue_script(
				'foogallery-divi',
				FOOGALLERY_URL . 'js/admin-foogallery-divi.js',
				array( FooGallery_Delayed_Runtime_Loader::READY_HANDLE ),
				FOOGALLERY_VERSION,
				true
			);
		}

		/**
		 * Load assets through the standard frontend queue in the classic builder.
		 *
		 * @return void
		 */
		public function maybe_enqueue_classic_builder_assets() {
			if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
				$this->enqueue_assets();
			}
		}

		/**
		 * Find gallery IDs selected in FooGallery Divi modules.
		 *
		 * This augments the normal FooGallery shortcode scan so the existing post
		 * usage metadata remains correct when a gallery is inserted as a module.
		 *
		 * @param int[]   $gallery_ids Gallery IDs already found in the post.
		 * @param WP_Post $post        Post being scanned.
		 *
		 * @return int[]
		 */
		public function find_galleries_in_divi_modules( $gallery_ids, $post ) {
			if ( ! is_array( $gallery_ids ) ) {
				$gallery_ids = array();
			}

			if ( ! is_object( $post ) || empty( $post->post_content ) || false === strpos( $post->post_content, '[foogallery_divi_gallery' ) ) {
				return $gallery_ids;
			}

			$pattern = get_shortcode_regex( array( 'foogallery_divi_gallery' ) );

			if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches ) ) {
				foreach ( $matches[3] as $attribute_string ) {
					$attributes = shortcode_parse_atts( $attribute_string );

					if ( is_array( $attributes ) && ! empty( $attributes['gallery_id'] ) ) {
						$gallery_id = absint( $attributes['gallery_id'] );

						if ( $gallery_id > 0 ) {
							$gallery_ids[] = $gallery_id;
						}
					}
				}
			}

			return array_values( array_unique( array_map( 'absint', $gallery_ids ) ) );
		}
	}
}
