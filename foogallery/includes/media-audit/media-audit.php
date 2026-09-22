<?php
/** Lazy audit bootstrap and lifecycle. @package FooGallery */
defined( 'ABSPATH' ) || exit;
define( 'FOOGALLERY_MEDIA_AUDIT_VERSION', '0.13.0' );
define( 'FOOGALLERY_MEDIA_AUDIT_RULESET', 'r4.2' );
define( 'FOOGALLERY_MEDIA_AUDIT_FINDINGS_REFERENCE_URL', 'https://fooplugins.com/documentation/foogallery/getting-started-foogallery/foogallery-media-audit-findings-reference/' );
require_once __DIR__ . '/class-foogallery-media-audit-report.php';

/** Load only at the authorized operation boundary. */
function foogallery_media_audit_engine() {
	require_once __DIR__ . '/functions.php';
	require_once __DIR__ . '/class-foogallery-media-audit-compression.php';
	require_once __DIR__ . '/scan.php';
}
add_action(
	'foogallery_media_audit_step',
	function ( $id ) {
		foogallery_media_audit_engine();
		FooGallery_Media_Audit_Scan::step( $id );
	}
);
function foogallery_media_audit_deactivate( $network_wide = false ) {
	require_once __DIR__ . '/functions.php';
	if ( $network_wide && is_multisite() ) {
		$offset = 0;
		do {
			$ids = get_sites(
				array(
					'fields'  => 'ids',
					'number'  => 100,
					'offset'  => $offset,
					'orderby' => 'id',
					'order'   => 'ASC',
				)
			);
			foreach ( $ids as $id ) {
				switch_to_blog( $id );
				try {
					foogallery_media_audit_cleanup();
				} finally {
					restore_current_blog(); }
			}
			$offset += 100;
		} while ( count( $ids ) === 100 );
	} else {
		foogallery_media_audit_cleanup(); }
}
if ( is_admin() ) {
	require_once __DIR__ . '/admin.php'; }
