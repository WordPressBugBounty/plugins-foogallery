<?php
/** Live administration progress for private media audits.
 *
 * @package FooGallery
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', 'foogallery_media_audit_progress_assets' );
add_action( 'wp_ajax_foogallery_media_audit_progress', 'foogallery_media_audit_progress_ajax' );

/** Load the live UI only on the authorized audit screen. */
function foogallery_media_audit_progress_assets() {
	$screen = get_current_screen();
	if ( ! $screen || 'foogallery_page_foogallery-media-audit' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_enqueue_style( 'wp-components' );
	wp_enqueue_style( 'foogallery-media-audit', FOOGALLERY_URL . 'css/foogallery-media-audit.css', array(), FOOGALLERY_MEDIA_AUDIT_VERSION . '-ui5' );
	wp_enqueue_script( 'foogallery-media-audit', FOOGALLERY_URL . 'js/foogallery-media-audit.js', array( 'jquery', 'wp-element', 'wp-components', 'wp-i18n' ), FOOGALLERY_MEDIA_AUDIT_VERSION . '-ui5', true );
	wp_enqueue_script( 'foogallery-media-audit-modal', FOOGALLERY_URL . 'js/foogallery-media-audit-modal.js', array( 'foogallery-media-audit' ), FOOGALLERY_MEDIA_AUDIT_VERSION . '-ui5', true );
	wp_localize_script(
		'foogallery-media-audit',
		'FooGalleryMediaAudit',
		array(
			'url'     => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'foogallery_media_audit_progress' ),
			'initial' => foogallery_media_audit_progress(),
		)
	);
}

/** Return counts and lifecycle status, never the private job itself.
 *
 * @return array
 */
function foogallery_media_audit_progress() {
	$job      = get_option( 'foogallery_media_audit_job' );
	$report   = FooGallery_Media_Audit_Report::load();
	$site_job = is_array( $job ) && isset( $job['scope'] ) && 'site' === $job['scope'];
	$running  = is_array( $job ) && empty( $job['complete'] );
	$totals   = $site_job && isset( $job['report']['totals'] ) ? $job['report']['totals'] : array();
	$failed   = $site_job && ! $running && ! empty( array_intersect( array( 'audit_failed', 'storage_failed' ), $job['report']['limitations'] ) );
	return array(
		'running'       => $running,
		'state'         => $running ? 'running' : ( $failed ? 'failed' : ( $site_job ? 'finished' : 'idle' ) ),
		'phase'         => $site_job && isset( $job['phase'] ) && 'images' === $job['phase'] ? 'images' : 'galleries',
		'gallery_scope' => $running && ! $site_job,
		'galleries'     => isset( $totals['galleries_assessed'] ) ? (int) $totals['galleries_assessed'] : 0,
		'images'        => isset( $totals['images_assessed'] ) ? (int) $totals['images_assessed'] : 0,
		'has_report'    => null !== $report,
		'partial'       => $report && 'complete' !== $report['state'],
	);
}

/** Capability- and nonce-protected start/status operations. */
function foogallery_media_audit_progress_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'foogallery' ) ), 403 );
	}
	if ( ! check_ajax_referer( 'foogallery_media_audit_progress', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => __( 'Your session has expired. Sign in again to resume live updates.', 'foogallery' ) ), 403 );
	}
	$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
	if ( ! in_array( $operation, array( 'start', 'status', 'affected' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid operation.', 'foogallery' ) ), 400 );
	}
	if ( 'affected' === $operation ) {
		$rule = isset( $_POST['rule'] ) && is_string( $_POST['rule'] ) ? sanitize_text_field( wp_unslash( $_POST['rule'] ) ) : '';
		$page = isset( $_POST['page'] ) && is_scalar( $_POST['page'] ) ? filter_var( wp_unslash( $_POST['page'] ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
		$revision = isset( $_POST['revision'] ) && is_string( $_POST['revision'] ) ? sanitize_text_field( wp_unslash( $_POST['revision'] ) ) : '';
		if ( ! in_array( $rule, FooGallery_Media_Audit_Report::rules(), true ) || false === $page || ! preg_match( '/^[a-f0-9]{64}$/D', $revision ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid affected-image request.', 'foogallery' ) ), 400 );
		}
		$report = FooGallery_Media_Audit_Report::load();
		if ( ! $report ) {
			wp_send_json_error( array( 'message' => __( 'This report is no longer available. Close this dialog and run an audit.', 'foogallery' ) ), 404 );
		}
		if ( ! hash_equals( foogallery_media_audit_revision( $report ), $revision ) ) {
			wp_send_json_error( array( 'message' => __( 'A newer audit report is available. Load its findings to continue.', 'foogallery' ), 'revision' => foogallery_media_audit_revision( $report ) ), 409 );
		}
		require_once __DIR__ . '/functions.php';
		nocache_headers();
		wp_send_json_success( foogallery_media_audit_affected_page( $report, $rule, $page ) );
	}
	if ( 'start' === $operation ) {
		foogallery_media_audit_engine();
		$result = FooGallery_Media_Audit_Scan::start();
		if ( is_wp_error( $result ) && 'audit_busy' !== $result->get_error_code() ) {
			wp_send_json_error( array( 'message' => __( 'The audit could not start. Check whether another audit is active and try again.', 'foogallery' ) ), 400 );
		}
	}
	$status = foogallery_media_audit_progress();
	if ( ! $status['running'] ) {
		require_once __DIR__ . '/functions.php';
		ob_start();
		foogallery_media_audit_render_saved_report();
		$status['html'] = ob_get_clean();
	}
	nocache_headers();
	wp_send_json_success( $status );
}
