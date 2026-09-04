<?php
/**
 * FooGallery Divi Builder module.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'ET_Builder_Module' ) && ! class_exists( 'FooGallery_Divi_Module' ) ) {

	/**
	 * Lets Divi users select and render an existing FooGallery gallery.
	 */
	class FooGallery_Divi_Module extends ET_Builder_Module {

		/**
		 * Configure the module.
		 *
		 * @return void
		 */
		public function init() {
			$this->name       = esc_html__( 'FooGallery', 'foogallery' );
			$this->plural     = esc_html__( 'FooGalleries', 'foogallery' );
			$this->slug       = 'foogallery_divi_gallery';
			$this->vb_support = 'partial';

			$this->settings_modal_toggles = array(
				'general' => array(
					'toggles' => array(
						'main_content' => esc_html__( 'Gallery', 'foogallery' ),
					),
				),
			);

			$this->advanced_fields = array(
				'background' => false,
				'button'     => false,
				'fonts'      => false,
				'text'       => false,
			);
		}

		/**
		 * Build the gallery choices shown by Divi.
		 *
		 * @return string[]
		 */
		public static function get_gallery_options() {
			$options = array(
				'' => esc_html__( 'Select a gallery', 'foogallery' ),
			);

			$galleries = foogallery_get_all_galleries();

			if ( empty( $galleries ) ) {
				return $options;
			}

			foreach ( $galleries as $gallery ) {
				$name = $gallery->name;

				if ( empty( $name ) ) {
					/* translators: %d: Gallery post ID. */
					$name = sprintf( esc_html__( 'Gallery #%d', 'foogallery' ), $gallery->ID );
				}

				$options[ (string) $gallery->ID ] = wp_strip_all_tags( $name );
			}

			return $options;
		}

		/**
		 * Define the module settings.
		 *
		 * @return array
		 */
		public function get_fields() {
			return array(
				'gallery_id' => array(
					'label'           => esc_html__( 'Gallery', 'foogallery' ),
					'type'            => 'select',
					'option_category' => 'basic_option',
					'options'         => self::get_gallery_options(),
					'toggle_slug'     => 'main_content',
					'description'     => esc_html__( 'Choose an existing FooGallery gallery to display.', 'foogallery' ),
				),
			);
		}

		/**
		 * Render the selected gallery.
		 *
		 * @param array  $attrs       Module attributes.
		 * @param string $content     Module content.
		 * @param string $render_slug Module slug used during rendering.
		 *
		 * @return string
		 */
		public function render( $attrs, $content, $render_slug ) {
			$gallery_id = isset( $this->props['gallery_id'] ) ? absint( $this->props['gallery_id'] ) : 0;

			if ( 0 === $gallery_id ) {
				if ( is_admin() || ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) ) {
					return '<p>' . esc_html__( 'Please select a gallery to display.', 'foogallery' ) . '</p>';
				}

				return '';
			}

			ob_start();
			foogallery_render_gallery( $gallery_id );
			$gallery_output = ob_get_clean();

			return sprintf(
				'<div%1$s class="%2$s">%3$s</div>',
				$this->module_id(),
				$this->module_classname( $render_slug ),
				$gallery_output // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FooGallery renders its own escaped gallery markup.
			);
		}
	}
}
