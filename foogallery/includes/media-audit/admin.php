<?php
/** Native administration and reusable findings presentation. @package FooGallery */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/progress.php';
require_once __DIR__ . '/presentation.php';
require_once __DIR__ . '/affected.php';
add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=foogallery', __( 'Media Audit', 'foogallery' ), __( 'Media Audit', 'foogallery' ), 'manage_options', 'foogallery-media-audit', 'foogallery_media_audit_render_page' );
	},
	20
);
function foogallery_media_audit_page_url() {
	return admin_url( 'edit.php?post_type=foogallery&page=foogallery-media-audit' ); }
foreach ( array( 'run', 'download', 'clear' ) as $foogallery_audit_operation ) {
	add_action(
		'admin_post_foogallery_media_audit_' . $foogallery_audit_operation,
		function () use ( $foogallery_audit_operation ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Permission denied.', 'foogallery' ), 403 ); }
			check_admin_referer( 'foogallery_media_audit_' . $foogallery_audit_operation );
			require_once __DIR__ . '/functions.php';
			if ( 'download' === $foogallery_audit_operation ) {
				$report = FooGallery_Media_Audit_Report::load();
				if ( ! $report ) {
					wp_die( esc_html__( 'Run an audit first.', 'foogallery' ) ); }
				nocache_headers();
				header( 'Cache-Control: private, no-store, max-age=0' );
				header( 'Content-Type: application/json; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="foogallery-media-audit.json"' );
				echo wp_json_encode( $report ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
				exit;
			}
			if ( 'clear' === $foogallery_audit_operation ) {
				$result = foogallery_media_audit_cleanup();
			} else {
				foogallery_media_audit_engine();
				$result = FooGallery_Media_Audit_Scan::start();
			}
				wp_safe_redirect( add_query_arg( 'audit_status', is_wp_error( $result ) || ! $result ? 'failed' : 'updated', foogallery_media_audit_page_url() ) );
				exit;
		}
	);
}
function foogallery_media_audit_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return; }
	require_once __DIR__ . '/functions.php';
	$status = foogallery_media_audit_progress();
	echo '<div class="wrap foogallery-media-audit"><h1>' . esc_html__( 'FooGallery Media Audit', 'foogallery' ) . ' <span class="fga-version">' . esc_html( 'v' . FOOGALLERY_MEDIA_AUDIT_VERSION . ' · ' . FOOGALLERY_MEDIA_AUDIT_RULESET ) . '</span></h1>';
	echo '<p class="description fga-intro">' . esc_html__( 'Check your galleries and media library for image, accessibility and configuration issues. Review the findings and choose what to improve.', 'foogallery' ) . '</p>';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag; no state change.
	if ( isset( $_GET['audit_status'] ) && 'failed' === $_GET['audit_status'] ) {
		echo '<p role="alert">' . esc_html__( 'The audit operation could not complete. Check for an active audit, then try again.', 'foogallery' ) . '</p>';
	}
	echo '<div class="foogallery-media-audit-actions">';
	foreach ( array(
		'run'      => __( 'Start audit', 'foogallery' ),
		'download' => __( 'Download report', 'foogallery' ),
		'clear'    => __( 'Delete report', 'foogallery' ),
	) as $action => $label ) {
		echo '<form data-audit-action="' . esc_attr( $action ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="foogallery_media_audit_' . esc_attr( $action ) . '">';
		wp_nonce_field( 'foogallery_media_audit_' . $action );
		submit_button( $label, 'run' === $action ? 'primary' : 'secondary', 'submit', false, ( 'run' === $action ? $status['running'] : ! $status['has_report'] ) ? array( 'disabled' => 'disabled' ) : array() );
		echo '</form>';
	}
	echo '</div>';
	echo '<div id="foogallery-media-audit-progress">';
	if ( $status['running'] ) {
		echo '<p role="status">' . esc_html__( 'Audit in progress. Results will appear automatically when it finishes.', 'foogallery' ) . '</p>';
	}
	echo '</div><div id="foogallery-media-audit-results"' . ( $status['running'] ? ' hidden' : '' ) . '>';
	foogallery_media_audit_render_saved_report();
	echo '</div></div>';
}

/** Render the current saved report, including its empty and stale states. */
function foogallery_media_audit_render_saved_report() {
	$report = FooGallery_Media_Audit_Report::load();
	if ( $report ) {
		foogallery_media_audit_render_report( $report ); } else {
		echo '<p class="fga-note">' . esc_html__( 'No private report is available. Start an audit to inspect saved gallery configuration and media.', 'foogallery' ) . '</p>'; }
}
/** Render the requested page of affected subjects with current permission checks. */
function foogallery_media_audit_render_affected( $report ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result filter on a capability-guarded screen.
	$rule     = isset( $_GET['audit_rule'] ) ? sanitize_text_field( wp_unslash( $_GET['audit_rule'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination on a capability-guarded screen.
	$page     = isset( $_GET['audit_page'] ) ? max( 1, absint( $_GET['audit_page'] ) ) : 1;
	$subjects = array();
	foreach ( $report['findings'] as $finding ) {
		if ( $finding['rule'] === $rule ) {
			foreach ( $finding['subjects'] as $subject ) {
				if ( ! $subject['gallery'] && ! empty( $report['gallery_membership'][ $subject['image'] ] ) ) {
					foreach ( array_keys( $report['gallery_membership'][ $subject['image'] ] ) as $gallery ) {
						$context                                        = $subject;
						$context['gallery']                             = $gallery;
						$subjects[ $subject['image'] . '-' . $gallery ] = $context;
					}
				} else {
					$subjects[ $subject['image'] . '-' . $subject['gallery'] ] = $subject; }
			}
		}
	}
	if ( ! $subjects ) {
		return; }
	echo '<section id="foogallery-audit-affected" class="fga-group fga-affected"><h3>' . esc_html__( 'Affected subjects', 'foogallery' ) . '</h3><div class="fga-group-body"><ul>';
	foreach ( array_slice( $subjects, ( $page - 1 ) * 25, 25 ) as $subject ) {
		echo '<li>';
		foreach ( array( 'image', 'gallery' ) as $kind ) {
			$id = $subject[ $kind ];
			if ( ! $id ) {
				continue; }
			$post  = get_post( $id );
			$label = $kind . ' #' . $id . ( $post ? ': ' . get_the_title( $post ) : ' (' . __( 'missing', 'foogallery' ) . ')' );
			$link  = $post && current_user_can( 'edit_post', $id ) ? get_edit_post_link( $id, 'raw' ) : null;
			echo $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $label ) . '</a> ' : esc_html( $label . ' ' );
		}
		if ( $subject['sampled_url'] ) {
			echo '<a href="' . esc_url( $subject['sampled_url'] ) . '" rel="noreferrer noopener">' . esc_html__( 'Sampled URL', 'foogallery' ) . '</a>'; }
		echo '</li>';
	}
	echo '</ul>';
	echo '<nav class="fga-pagination" aria-label="' . esc_attr__( 'Affected subjects pages', 'foogallery' ) . '">';
	$pages = (int) ceil( count( $subjects ) / 25 );
	for ( $n = 1; $n <= $pages; ++$n ) {
		echo '<a href="' . esc_url(
			add_query_arg(
				array(
					'audit_rule' => $rule,
					'audit_page' => $n,
				),
				foogallery_media_audit_page_url()
			)
		) . '">' . esc_html( (string) $n ) . '</a> '; }
	echo '</nav></div></section>';
}
