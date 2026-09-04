<?php
/**
 * Speed Optimizer Compatibility Class
 *
 * @package foogallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FooGallery_Speed_Optimizer_Compatibility' ) ) {

	/**
	 * Class FooGallery_Speed_Optimizer_Compatibility
	 */
	class FooGallery_Speed_Optimizer_Compatibility {

		/**
		 * FooGallery_Speed_Optimizer_Compatibility constructor.
		 */
		public function __construct() {
			add_filter( 'sgo_lazy_load_exclude_classes', array( $this, 'exclude_skip_lazy_images' ) );
		}

		/**
		 * Ensure Speed Optimizer excludes images marked to skip external lazy loading.
		 *
		 * @param array $excluded_classes CSS classes excluded by Speed Optimizer.
		 *
		 * @return array
		 */
		public function exclude_skip_lazy_images( $excluded_classes ) {
			if ( ! in_array( 'skip-lazy', $excluded_classes, true ) ) {
				$excluded_classes[] = 'skip-lazy';
			}

			return $excluded_classes;
		}
	}
}
