<?php
/*
 * FooGallery Shortcodes
 */

if ( ! class_exists( 'FooGallery_Shortcodes' ) ) {

	class FooGallery_Shortcodes {

		function __construct() {
			add_action( 'foogallery_load_template', array( $this, 'handle_lightbox_field' ) );
			add_action( 'foogallery_loaded_template', array( $this, 'render_custom_css' ) );
			add_action( 'plugins_loaded', array( $this, 'init_shortcodes' ) );
		}

		function init_shortcodes() {
			add_shortcode( foogallery_gallery_shortcode_tag(), array( $this, 'render_foogallery_shortcode' ) );
			add_shortcode( 'foogallery-enqueue', array( $this, 'render_foogallery_enqueue' ) );
		}

		function render_foogallery_shortcode( $atts ) {

			$args = wp_parse_args( $atts, array(
				'id'      => 0,
				'gallery' => '',
			) );
			$args = $this->normalize_shortcode_args( $args );

			$args = apply_filters( 'foogallery_shortcode_atts', $args );

			//create new instance of template engine
			$engine = new FooGallery_Template_Loader();

			ob_start();

			$engine->render_template( $args );

			$output_string = ob_get_contents();
			ob_end_clean();
			return $output_string;
		}

		/**
		 * Normalize supported gallery shortcode aliases to their canonical arguments.
		 *
		 * @param array $args Parsed shortcode arguments.
		 *
		 * @return array
		 */
		function normalize_shortcode_args( $args ) {
			if ( ! is_array( $args ) ) {
				return $args;
			}

			if ( ! array_key_exists( 'template', $args ) && array_key_exists( 'layout', $args ) ) {
				$args['template'] = $args['layout'];
			}

			$args = $this->normalize_shortcode_thumbnail_size_arg( $args, 'thumbnail_size' );
			$args = $this->normalize_shortcode_thumbnail_size_arg( $args, 'thumbnail_dimensions' );

			if ( array_key_exists( 'thumbnail_size', $args ) && is_array( $args['thumbnail_size'] ) && ! array_key_exists( 'thumbnail_dimensions', $args ) ) {
				$args['thumbnail_dimensions'] = $args['thumbnail_size'];
			} else if ( array_key_exists( 'thumbnail_dimensions', $args ) && is_array( $args['thumbnail_dimensions'] ) && ! array_key_exists( 'thumbnail_size', $args ) ) {
				$args['thumbnail_size'] = $args['thumbnail_dimensions'];
			}

			if ( array_key_exists( 'thumbnail_size', $args ) && is_array( $args['thumbnail_size'] ) && ! array_key_exists( 'crop', $args['thumbnail_size'] ) ) {
				$args['thumbnail_size']['crop'] = '0';
			}

			if ( ! array_key_exists( 'thumbnail_width', $args ) && array_key_exists( 'thumbnail_size', $args ) && is_array( $args['thumbnail_size'] ) && array_key_exists( 'width', $args['thumbnail_size'] ) ) {
				$args['thumbnail_width'] = $args['thumbnail_size']['width'];
			}

			return $args;
		}

		/**
		 * Normalize a compound thumbnail size shortcode argument.
		 *
		 * @param array  $args Parsed shortcode arguments.
		 * @param string $key Argument key.
		 *
		 * @return array
		 */
		function normalize_shortcode_thumbnail_size_arg( $args, $key ) {
			if ( ! array_key_exists( $key, $args ) || is_array( $args[ $key ] ) ) {
				return $args;
			}

			$thumbnail_size = $this->parse_shortcode_thumbnail_size( $args[ $key ] );

			if ( false !== $thumbnail_size ) {
				$args[ $key ] = $thumbnail_size;
			}

			return $args;
		}

		/**
		 * Parse a compound thumbnail size shortcode attribute.
		 *
		 * @param string $thumbnail_size Thumbnail size in the form WIDTHxHEIGHT or WIDTHxHEIGHTxcrop.
		 *
		 * @return array|false
		 */
		function parse_shortcode_thumbnail_size( $thumbnail_size ) {
			if ( ! is_string( $thumbnail_size ) ) {
				return false;
			}

			if ( 1 !== preg_match( '/^(\d+)x(\d+)(?:x(crop))?$/i', trim( $thumbnail_size ), $matches ) ) {
				return false;
			}

			$args = array(
				'width'  => absint( $matches[1] ),
				'height' => absint( $matches[2] ),
			);

			if ( ! empty( $matches[3] ) ) {
				$args['crop'] = '1';
			}

			return $args;
		}

		function render_foogallery_enqueue() {
			foogallery_enqueue_core_gallery_template_script();
			foogallery_enqueue_core_gallery_template_style();
			wp_enqueue_script( 'masonry' );
		}

		/**
		 * Handle a gallery that has a lightbox. This allows us to include any scripts or CSS that is needed for the lightbox
		 *
		 * @param $gallery FooGallery
		 */
		function handle_lightbox_field( $gallery ) {
			if ( $gallery->gallery_template_has_field_of_type( 'lightbox' ) ) {
				$lightbox = foogallery_gallery_template_setting_lightbox();

				if ( !empty( $lightbox ) ) {
					do_action( "foogallery_template_lightbox-{$lightbox}", $gallery );
				}
			}
		}

		function render_custom_css( $foogallery ) {
			if ( !empty( $foogallery->custom_css ) ) {
				echo '<style type="text/css">';
				echo $foogallery->custom_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Custom CSS from gallery settings. The custom CSS is already sanitized
				echo '</style>';
			}
		}
	}
}
