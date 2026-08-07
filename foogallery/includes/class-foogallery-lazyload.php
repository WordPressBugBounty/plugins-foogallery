<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class used to handle lazy loading for gallery templates
 * Date: 20/03/2017
 */
if ( ! class_exists( 'FooGallery_LazyLoad' ) ) {

	/**
	 * Class FooGallery_LazyLoad
	 */
	class FooGallery_LazyLoad {

		const MODE_SEO    = '';
		const MODE_LEGACY = 'legacy';

		/**
		 * FooGallery_LazyLoad constructor.
		 */
		public function __construct() {
			// determine lazy loading for the gallery once up front before the template is loaded.
			add_action( 'foogallery_located_template', array( $this, 'determine_lazyloading_for_gallery' ) );

			// force lazy loading for stack album.
			add_action( 'foogallery_located_album_template-stack', array( $this, 'force_lazyloading_for_galleries' ) );

			// change the image src attribute to data attributes if lazy loading is enabled.
			add_filter( 'foogallery_attachment_html_image_attributes', array( $this, 'change_src_attributes' ), 99, 3 );

			// set the native image loading attribute for SEO friendly lazy loading.
			add_filter( 'foogallery_attachment_html_image_loading_attribute', array( $this, 'image_loading_attribute' ), 10, 4 );

			// add the lazy load attributes to the gallery container.
			add_filter( 'foogallery_build_container_data_options', array( $this, 'add_lazyload_options' ), 10, 3 );

			// add common fields to the templates that support it.
			add_filter( 'foogallery_override_gallery_template_fields', array( $this, 'add_lazyload_field' ), 100, 2 );

			// add some settings to allow forcing of the lazy loading to be disabled.
			add_filter( 'foogallery_admin_settings_override', array( $this, 'add_settings' ) );

			add_filter( 'foogallery_attachment_html_item_classes', array( $this, 'add_item_classes_for_lazy_loading' ), 10, 3 );
		}

		/**
		 * Add the classes needed for lazy loading onto the items
		 *
		 * @param array                $classes The array of classes to add to the item.
		 * @param FooGalleryAttachment $foogallery_attachment The current attachment we are working with.
		 * @param array                $args Any extra args.
		 *
		 * @return array The array of classes to add to the item.
		 */
		public function add_item_classes_for_lazy_loading( $classes, $foogallery_attachment, $args ) {
			global $current_foogallery;
			if ( $this->gallery_seo_lazyload_enabled( $current_foogallery ) ) {
				$classes[] = 'fg-loading';
			} elseif ( $this->gallery_lazyload_enabled( $current_foogallery ) ) {
				$classes[] = 'fg-idle';
			} else {
				$classes[] = 'fg-loaded';
			}

			return $classes;
		}

		/**
		 * Force lazy loading for all galleries in the stack album
		 *
		 * @param FooGalleryAlbum $current_foogallery_album The current album that is being rendered.
		 */
		public function force_lazyloading_for_galleries( $current_foogallery_album ) {
			foreach ( $current_foogallery_album->galleries() as $gallery ) {
				$gallery->lazyload_support         = true;
				$gallery->lazyload_enabled         = true;
				$gallery->lazyload_forced_disabled = false;
			}
		}

		/**
		 * Determine all the lazy loading variables that can be set on a gallery
		 *
		 * @param $foogallery
		 */
		function determine_lazyloading_for_gallery( $foogallery ) {
			global $current_foogallery;
			global $current_foogallery_template;

			if ( $current_foogallery !== null ) {
				//make sure we only do this once for better performance
				if ( ! isset( $current_foogallery->lazyload_support ) ) {

					//load the gallery template
					$template_info = foogallery_get_gallery_template( $current_foogallery_template );

					//check if the template supports lazy loading
					$lazyloading_support = isset( $template_info['lazyload_support'] ) && true === $template_info['lazyload_support'];

					//set if lazy loading is supported for the gallery
					$current_foogallery->lazyload_support = apply_filters( 'foogallery_lazy_load', $lazyloading_support, $current_foogallery, $current_foogallery_template );

					//set if lazy loading is enabled for the gallery
					$lazyloading_default                  = '';
					$lazyloading_enabled                  = foogallery_gallery_template_setting( 'lazyload', $lazyloading_default ) === '';
					$current_foogallery->lazyload_enabled = $lazyloading_enabled;

					//set if lazy loading is forced to disabled for all galleries
					$lazyloading_forced_disabled                  = foogallery_get_setting( 'disable_lazy_loading' ) === 'on';
					$current_foogallery->lazyload_forced_disabled = $lazyloading_forced_disabled;

					//set the lazy loading mode for the gallery
					$current_foogallery->lazyload_mode = $this->lazyload_mode();

					//check if we are inside a feed
					if ( is_feed() ) {
						$current_foogallery->is_feed = true;
					}
				}
			}
		}

		/**
		 * Change the src and srcset attributes for lazy loading
		 *
		 * @param array                $attr
		 * @param array                $args
		 * @param FooGalleryAttachment $attachment
		 *
		 * @return mixed
		 */
		function change_src_attributes( $attr, $args, $attachment ) {
			if ( $this->legacy_lazyload_enabled_for_current_context() ) {
				if ( isset( $attr['src'] ) ) {
					// rename src => data-src-fg.
					$src = $attr['src'];
					unset( $attr['src'] );
					$attr['data-src-fg'] = $src;
				}

				if ( isset( $attr['srcset'] ) ) {
					// rename srcset => data-srcset-fg.
					$src = $attr['srcset'];
					unset( $attr['srcset'] );
					$attr['data-srcset-fg'] = $src;
				}

				// add a placeholder src.
				if ( isset( $attr['width'] ) && isset( $attr['height'] ) ) {
					// set the src to a transparent SVG that has the correct width and height.
					$attr['src'] = foogallery_get_svg_placeholder_image( $attr['width'], $attr['height'] );
				}
			} else if ( $this->seo_lazyload_enabled_for_current_context() ) {
				$attr['decoding'] = 'async';
			}

			return $attr;
		}

		/**
		 * Set the image loading attribute for SEO friendly lazy loading.
		 *
		 * @param string               $loading The loading attribute.
		 * @param array                $attr The image attributes.
		 * @param array                $args Any extra args.
		 * @param FooGalleryAttachment $attachment The current attachment we are working with.
		 *
		 * @return string The loading attribute.
		 */
		function image_loading_attribute( $loading, $attr, $args, $attachment ) {
			if ( $this->seo_lazyload_enabled_for_current_context() ) {
				return 'lazy';
			}

			return $loading;
		}

		/**
		 * Add the required lazy load options if needed
		 *
		 * @param $attributes array
		 * @param $gallery    FooGallery
		 *
		 * @return array
		 */
		function add_lazyload_options( $options, $gallery, $attributes ) {
			$lazyload_enabled = $this->gallery_lazyload_enabled( $gallery );
			$options['lazy']  = $lazyload_enabled;
			if ( ! $lazyload_enabled || $this->gallery_seo_lazyload_enabled( $gallery ) ) {
				$options['src']    = 'src';
				$options['srcset'] = 'srcset';
			}

			return $options;
		}

		/**
		 * @param $gallery FooGallery
		 */
		private function gallery_lazyload_enabled( $gallery ) {
			if ( isset( $gallery->lazyload_support ) && true === $gallery->lazyload_support ) {
				return $gallery->lazyload_enabled && ! $gallery->lazyload_forced_disabled;
			}

			return false;
		}

		/**
		 * Check if a gallery should use SEO friendly lazy loading.
		 *
		 * @param FooGallery $gallery The gallery to check.
		 *
		 * @return bool
		 */
		private function gallery_seo_lazyload_enabled( $gallery ) {
			return $this->gallery_lazyload_enabled( $gallery ) && self::MODE_SEO === $this->gallery_lazyload_mode( $gallery );
		}

		/**
		 * Check if a gallery should use legacy lazy loading.
		 *
		 * @param FooGallery $gallery The gallery to check.
		 *
		 * @return bool
		 */
		private function gallery_legacy_lazyload_enabled( $gallery ) {
			return $this->gallery_lazyload_enabled( $gallery ) && self::MODE_LEGACY === $this->gallery_lazyload_mode( $gallery );
		}

		/**
		 * Get the lazy loading mode for a gallery.
		 *
		 * @param FooGallery $gallery The gallery to check.
		 *
		 * @return string
		 */
		private function gallery_lazyload_mode( $gallery ) {
			if ( isset( $gallery->lazyload_mode ) ) {
				return $this->sanitize_lazyload_mode( $gallery->lazyload_mode );
			}

			return $this->lazyload_mode();
		}

		/**
		 * Get the global lazy loading mode.
		 *
		 * @return string
		 */
		private function lazyload_mode() {
			return $this->sanitize_lazyload_mode( foogallery_get_setting( 'lazy_loading_mode', self::MODE_SEO ) );
		}

		/**
		 * Sanitize the lazy loading mode.
		 *
		 * @param string $mode The mode value.
		 *
		 * @return string
		 */
		private function sanitize_lazyload_mode( $mode ) {
			if ( self::MODE_LEGACY === $mode ) {
				return self::MODE_LEGACY;
			}

			return self::MODE_SEO;
		}

		/**
		 * Check if the current rendering context should use SEO friendly lazy loading.
		 *
		 * @return bool
		 */
		private function seo_lazyload_enabled_for_current_context() {
			global $current_foogallery;
			global $current_foogallery_album;

			if ( null !== $current_foogallery ) {
				if ( isset( $current_foogallery->is_feed ) && true === $current_foogallery->is_feed ) {
					return false;
				}

				return $this->gallery_seo_lazyload_enabled( $current_foogallery );
			}

			if ( null !== $current_foogallery_album ) {
				return foogallery_get_setting( 'disable_lazy_loading' ) !== 'on' && self::MODE_SEO === $this->lazyload_mode();
			}

			return false;
		}

		/**
		 * Check if the current rendering context should use legacy lazy loading.
		 *
		 * @return bool
		 */
		private function legacy_lazyload_enabled_for_current_context() {
			global $current_foogallery;
			global $current_foogallery_album;

			if ( null !== $current_foogallery ) {
				if ( isset( $current_foogallery->is_feed ) && true === $current_foogallery->is_feed ) {
					return false;
				}

				return $this->gallery_legacy_lazyload_enabled( $current_foogallery );
			}

			if ( null !== $current_foogallery_album ) {
				return foogallery_get_setting( 'disable_lazy_loading' ) !== 'on' && self::MODE_LEGACY === $this->lazyload_mode();
			}

			return false;
		}

		/**
		 * Add lazyload field to the gallery template if supported
		 *
		 * @param $fields
		 * @param $template
		 *
		 * @return array
		 */
		function add_lazyload_field( $fields, $template ) {
			//check if the template supports lazy loading
			if ( $template && array_key_exists( 'lazyload_support', $template ) && true === $template['lazyload_support'] ) {

				$fields[] = array(
					'id'       => 'lazyload',
					'title'    => __( 'Lazy Loading', 'foogallery' ),
					'desc'     => __( 'If you choose to disable lazy loading, then all thumbnails will be loaded at once. This means you will lose the performance improvements that lazy loading gives you.', 'foogallery' ),
					'section'  => __( 'Advanced', 'foogallery' ),
					'type'     => 'radio',
					'default'  => '',
					'choices'  => array(
						'disabled' => __( 'Disabled', 'foogallery' ),
						''         => __( 'Enabled', 'foogallery' ),
					),
					'row_data' => array(
						'data-foogallery-change-selector' => 'input:radio',
						'data-foogallery-preview'         => 'shortcode',
					),
				);
			}

			return $fields;
		}

		/**
		 * Add some global settings
		 *
		 * @param $settings
		 *
		 * @return array
		 */
		function add_settings( $settings ) {

			$lazy_loading_mode_setting = array(
				'id'      => 'lazy_loading_mode',
				'title'   => __( 'Lazy Loading Mode', 'foogallery' ),
				'desc'    => __( 'SEO Friendly outputs real image URLs and uses native browser lazy loading. Legacy uses FooGallery\'s JavaScript placeholder lazy loading for compatibility with older setups.', 'foogallery' ),
				'type'    => 'radio',
				'default' => self::MODE_SEO,
				'choices' => array(
					self::MODE_SEO    => __( 'SEO Friendly', 'foogallery' ),
					self::MODE_LEGACY => __( 'Legacy', 'foogallery' ),
				),
				'tab'     => 'advanced',
			);

			$lazy_settings[] = array(
				'id'      => 'disable_lazy_loading',
				'title'   => __( 'Disable Lazy Loading', 'foogallery' ),
				'desc'    => __( 'This will disable lazy loading for ALL galleries. This is not recommended, but is sometimes needed when there are problems with the galleries displaying on some installs.', 'foogallery' ),
				'type'    => 'checkbox',
				'tab'     => 'general',
				'section' => __( 'Performance', 'foogallery' ),
			);

			$new_settings = array_merge( $lazy_settings, $settings['settings'] );

			$settings['settings'] = $new_settings;

			foreach ( $settings['settings'] as $index => $setting ) {
				if ( isset( $setting['tab'] ) && 'advanced' === $setting['tab'] ) {
					array_splice( $settings['settings'], $index, 0, array( $lazy_loading_mode_setting ) );

					return $settings;
				}
			}

			$settings['settings'][] = $lazy_loading_mode_setting;

			return $settings;
		}
	}
}
