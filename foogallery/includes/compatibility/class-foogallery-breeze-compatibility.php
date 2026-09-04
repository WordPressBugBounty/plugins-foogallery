<?php
/**
 * Breeze Compatibility Class
 *
 * @package foogallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FooGallery_Breeze_Compatibility' ) ) {

	/**
	 * Class FooGallery_Breeze_Compatibility
	 */
	class FooGallery_Breeze_Compatibility {

		/**
		 * FooGallery_Breeze_Compatibility constructor.
		 */
		public function __construct() {
			add_filter( 'foogallery_attachment_html_image_attributes', array( $this, 'exclude_images_from_breeze_lazy_load' ), 100 );
		}

		/**
		 * Add Breeze's supported lazy-load opt-out attribute to FooGallery images.
		 *
		 * @param array $attributes FooGallery image attributes.
		 *
		 * @return array
		 */
		public function exclude_images_from_breeze_lazy_load( $attributes ) {
			$attributes['data-no-lazy'] = '1';

			return $attributes;
		}
	}
}
