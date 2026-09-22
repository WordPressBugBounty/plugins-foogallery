<?php
/**
 * Media Audit runtime loader.
 *
 * @package FooGallery
 */

defined( 'ABSPATH' ) || exit;

/** Load the Media Audit runtime only while its FooGallery feature is active. */
class FooGallery_Media_Audit {
	/**
	 * Load the feature runtime.
	 *
	 * @return void
	 */
	public function __construct() {
		self::load();
	}

	/**
	 * Load the feature runtime once.
	 *
	 * @return void
	 */
	public static function load() {
		require_once __DIR__ . '/media-audit.php';
	}
}
