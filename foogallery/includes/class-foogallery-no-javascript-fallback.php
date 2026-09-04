<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the no-JavaScript thumbnail fallback for gallery containers.
 */
if ( ! class_exists( 'FooGallery_No_Javascript_Fallback' ) ) {

	/**
	 * Class FooGallery_No_Javascript_Fallback
	 */
	class FooGallery_No_Javascript_Fallback {

		/**
		 * FooGallery_No_Javascript_Fallback constructor.
		 */
		public function __construct() {
			add_filter( 'foogallery_build_class_attribute', array( $this, 'add_container_class' ) );
			add_filter( 'foogallery_admin_settings_override', array( $this, 'add_setting' ), 20 );
		}

		/**
		 * Add the no-JavaScript fallback class to the gallery container.
		 *
		 * @param array $classes The list of gallery container classes.
		 *
		 * @return array The gallery container classes.
		 */
		public function add_container_class( $classes ) {
			if ( 'on' !== foogallery_get_setting( 'disable_no_js_thumbnail_fallback' ) ) {
				$classes[] = 'fg-no-js';
			}

			return $classes;
		}

		/**
		 * Add the global no-JavaScript thumbnail fallback setting.
		 *
		 * @param array $settings The global FooGallery settings configuration.
		 *
		 * @return array The global FooGallery settings configuration.
		 */
		public function add_setting( $settings ) {
			$fallback_setting = array(
				'id'    => 'disable_no_js_thumbnail_fallback',
				'title' => __( 'Disable No-JavaScript Thumbnail Fallback', 'foogallery' ),
				'desc'  => __( 'Thumbnails normally remain visible if a gallery cannot start properly. Enable this setting to turn off that fallback. If a gallery fails to start, its thumbnails may remain hidden.', 'foogallery' ),
				'type'  => 'checkbox',
				'tab'   => 'advanced',
				'section' => __( 'Gallery Loading & Compatibility', 'foogallery' ),
			);

			if ( ! isset( $settings['settings'] ) || ! is_array( $settings['settings'] ) ) {
				$settings['settings'] = array();
			}

			foreach ( $settings['settings'] as $index => $setting ) {
				if ( isset( $setting['tab'] ) && 'advanced' === $setting['tab'] ) {
					array_splice( $settings['settings'], $index, 0, array( $fallback_setting ) );

					return $settings;
				}
			}

			$settings['settings'][] = $fallback_setting;

			return $settings;
		}
	}
}
