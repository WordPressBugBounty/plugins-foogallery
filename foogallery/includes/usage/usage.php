<?php
/**
 * Load the opt-in reporting services without collecting during plugin loading.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-foogallery-usage-registry.php';
require_once __DIR__ . '/adapters/class-foogallery-usage-addon-adapter.php';
require_once __DIR__ . '/class-foogallery-usage-collector.php';
require_once __DIR__ . '/class-foogallery-usage-manifest.php';
require_once __DIR__ . '/class-foogallery-usage-state.php';
require_once __DIR__ . '/class-foogallery-usage-actions.php';
require_once __DIR__ . '/class-foogallery-usage-client.php';
require_once __DIR__ . '/class-foogallery-usage-scheduler.php';
require_once __DIR__ . '/class-foogallery-usage.php';
require_once __DIR__ . '/class-foogallery-usage-lifecycle.php';

add_action( 'action_scheduler_init', array( 'FooGallery_Usage', 'boot' ) );
add_action( 'activate_' . plugin_basename( FOOGALLERY_FILE ), array( 'FooGallery_Usage_Lifecycle', 'register_uninstall' ), 99 );
add_action( 'deactivate_' . plugin_basename( FOOGALLERY_FILE ), array( 'FooGallery_Usage_Lifecycle', 'deactivate' ), 99 );
add_action( 'foogallery_uninstall', array( 'FooGallery_Usage_Lifecycle', 'full_reset' ) );

if ( is_admin() ) {
	require_once FOOGALLERY_PATH . 'includes/admin/class-foogallery-admin-usage-settings.php';
	new FooGallery_Admin_Usage_Settings();
	require_once FOOGALLERY_PATH . 'includes/admin/class-foogallery-admin-usage-onboarding.php';
	new FooGallery_Admin_Usage_Onboarding();
	add_action( 'admin_init', 'foogallery_usage_privacy_policy_content' );
}

/** Offer site administrators accurate text for the optional reporting service. */
function foogallery_usage_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$text  = __( 'If a site administrator enables FooGallery Improve, this site sends FooPlugins a report of approved active modules and configured gallery features after initial collection and then weekly. No image IDs, URLs, filenames, alt text or captions are sent. Reports include a random site identifier, FooGallery version and edition, coarse gallery and album counts, first-party add-on configuration, whether successful use of Bulk Copy, Import, Export, and Migration was observed in the previous 30 days after consent, and whether a saved Media Audit completed successfully in the previous 30 days. No gallery content, visitor activity, client information or license details is included.', 'foogallery' );
	$text .= ' ' . __( 'WordPress also sends its version and the site address in its default User-Agent, which FooPlugins stores to help deduplicate reports. Reporting is site-identifiable, not anonymous. Cloudflare receives the connecting server IP. Administrators can inspect report bodies and request metadata, pause reporting, or request deletion in FooGallery Settings under Improve. Pausing preserves the reporting identity. An authenticated deletion request immediately revokes that identity and removes the site address, User-Agent, random site identifier and raw report from retained measurements. FooPlugins may retain compact anonymous feature measurements for aggregate reporting under the normal 90-day retention window while it reviews and closes the request.', 'foogallery' );
	wp_add_privacy_policy_content( 'FooGallery Improve', '<p>' . esc_html( $text ) . '</p><p><a href="https://fooplugins.com/privacy-policy/">' . esc_html__( 'FooPlugins privacy policy', 'foogallery' ) . '</a></p>' );
}
