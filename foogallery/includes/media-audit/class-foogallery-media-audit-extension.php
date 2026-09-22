<?php
/**
 * Register Media Audit as a bundled FooGallery feature.
 *
 * @package FooGallery
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-foogallery-media-audit.php';

/**
 * Registers the feature row and owns lifecycle cleanup that must remain available
 * even when the Media Audit runtime is disabled.
 */
class FooGallery_Media_Audit_Extension {
	const SLUG = 'foogallery-media-audit';

	/**
	 * Register the feature and its lifecycle hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'foogallery_available_extensions', array( $this, 'register_extension' ) );
	}

	/**
	 * Add Media Audit to the FooGallery Features screen.
	 *
	 * @param array $extensions Registered extensions.
	 * @return array
	 */
	public function register_extension( $extensions ) {
		$extensions[] = array(
			'slug'                 => self::SLUG,
			'class'                => 'FooGallery_Media_Audit',
			'categories'           => array( 'Free', 'Utilities' ),
			'title'                => foogallery__( 'Media Audit', 'foogallery' ),
			'description'          => foogallery__( 'Check for missing files, oversized images, alt-text issues, thumbnail upscaling, and gallery configuration problems - then see what to fix', 'foogallery' ),
			'dashicon'             => 'dashicons-search',
			'tags'                 => array( 'tools', 'media', 'accessibility', 'free' ),
			'source'               => 'bundled',
			'activated_by_default' => true,
			'feature'              => true,
		);

		return $extensions;
	}

	/**
	 * Stop scratch work when Media Audit is turned off without deleting its saved report.
	 *
	 * @return bool Whether the audit worker was cancelled under its publication lock.
	 */
	public static function deactivate_feature() {
		FooGallery_Media_Audit::load();
		foogallery_media_audit_engine();

		return FooGallery_Media_Audit_Scan::cancel();
	}

	/**
	 * Preserve the existing full cleanup when FooGallery itself is deactivated.
	 *
	 * @param bool $network_wide Whether FooGallery is being network deactivated.
	 * @return void
	 */
	public static function deactivate_plugin( $network_wide = false ) {
		FooGallery_Media_Audit::load();
		foogallery_media_audit_deactivate( $network_wide );
	}
}

add_action( 'foogallery_extension_deactivated-' . FooGallery_Media_Audit_Extension::SLUG, array( 'FooGallery_Media_Audit_Extension', 'deactivate_feature' ) );
register_deactivation_hook( FOOGALLERY_FILE, array( 'FooGallery_Media_Audit_Extension', 'deactivate_plugin' ) );
