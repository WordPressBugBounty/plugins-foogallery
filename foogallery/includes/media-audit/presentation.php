<?php
/** Report presentation adapted from the original audit design.
 *
 * @package FooGallery
 */
defined( 'ABSPATH' ) || exit;

/** Human-readable coverage without implying an unknown population was checked.
 *
 * @param array $check Canonical check record.
 * @return string
 */
function foogallery_media_audit_coverage_label( $check ) {
	if ( null === $check['eligible'] ) {
		/* translators: %s: assessed observations. */
		$label = sprintf( __( '%s assessed', 'foogallery' ), number_format_i18n( $check['assessed'] ) );
	} else {
		/* translators: 1: assessed observations, 2: eligible observations at scan time. */
		$label = sprintf( __( '%1$s of %2$s assessed', 'foogallery' ), number_format_i18n( $check['assessed'] ), number_format_i18n( $check['eligible'] ) );
	}
	$limitations = isset( $check['limitations'] ) ? foogallery_media_audit_limitation_labels( $check['limitations'] ) : array();
	if ( null === $check['eligible'] && ! $limitations ) {
		$limitations[] = __( 'The full total was not recorded for this check.', 'foogallery' );
	}
	return $label . ( $limitations ? ' · ' . implode( ' ', $limitations ) : '' );
}

/** Build the four consistently ordered labels shown below a finding title.
 *
 * @param array $check Canonical check record.
 * @return string[]
 */
function foogallery_media_audit_finding_stats( $check ) {
	return array(
		sprintf(
			/* translators: %s: finding count. */
			__( 'Findings: %s', 'foogallery' ),
			number_format_i18n( $check['findings'] )
		),
		sprintf(
			/* translators: %s: affected image count. */
			__( 'Images affected: %s', 'foogallery' ),
			number_format_i18n( $check['affected_images'] )
		),
		sprintf(
			/* translators: %s: affected gallery count. */
			__( 'Galleries affected: %s', 'foogallery' ),
			number_format_i18n( $check['affected_galleries'] )
		),
		foogallery_media_audit_coverage_label( $check ),
	);
}

/** Explain fixed limitation codes in the context of the saved run.
 *
 * @param array $codes Recorded limitation codes.
 * @return array
 */
function foogallery_media_audit_limitation_labels( $codes ) {
	$labels = array(
		'source_unavailable'   => __( 'Some gallery sources could not be checked.', 'foogallery' ),
		'dynamic_source'       => __( 'Some gallery sources could not be checked.', 'foogallery' ),
		'image_cap'            => __( 'The image limit was reached.', 'foogallery' ),
		'execution_budget'     => __( 'The scan time limit was reached.', 'foogallery' ),
		'elapsed_budget'       => __( 'The scan time limit was reached.', 'foogallery' ),
		'http_unavailable'     => __( 'Some image URLs could not be verified.', 'foogallery' ),
		'network_budget'       => __( 'The HTTP request budget was reached.', 'foogallery' ),
		'metadata_unavailable' => __( 'Some image metadata was unavailable.', 'foogallery' ),
		'header_unavailable'   => __( 'Some image headers could not be checked.', 'foogallery' ),
		'header_budget'        => __( 'The image header inspection budget was reached.', 'foogallery' ),
		'file_changed'         => __( 'Some image files changed while being checked.', 'foogallery' ),
		'audit_failed'         => __( 'The scan stopped before all checks finished.', 'foogallery' ),
		'storage_failed'       => __( 'The report could not be saved.', 'foogallery' ),
		'busy'                 => __( 'Another audit was already running.', 'foogallery' ),
	);
	return array_values( array_unique( array_intersect_key( $labels, array_fill_keys( $codes, true ) ) ) );
}

/** Render the familiar summary tiles and grouped findings from canonical records.
 *
 * @param array $report Valid detailed report.
 */
function foogallery_media_audit_render_report( $report ) {
	$severity      = array(
		'error'   => 0,
		'warning' => 0,
		'info'    => 0,
	);
	$rule_severity = array();
	$weights       = array(
		'info'    => 1,
		'warning' => 2,
		'error'   => 3,
	);
	foreach ( $report['findings'] as $finding ) {
		++$severity[ $finding['severity'] ];
		$rule = $finding['rule'];
		if ( ! isset( $rule_severity[ $rule ] ) || $weights[ $finding['severity'] ] > $weights[ $rule_severity[ $rule ] ] ) {
			$rule_severity[ $rule ] = $finding['severity'];
		}
	}
	$passed  = array();
	$partial = array();
	foreach ( $report['checks'] as $rule => $check ) {
		if ( 'passed' === $check['state'] ) {
			$passed[ $rule ] = $check; }
		if ( in_array( $check['state'], array( 'incomplete', 'unavailable' ), true ) || ( 'findings' === $check['state'] && ( null === $check['eligible'] || $check['assessed'] < $check['eligible'] ) ) ) {
			$partial[ $rule ] = $check;
		}
	}
	$tiles = array(
		array( $severity['error'], __( 'to fix', 'foogallery' ), $severity['error'] ? 'error' : 'neutral' ),
		array( $severity['warning'], __( 'worth improving', 'foogallery' ), $severity['warning'] ? 'warning' : 'neutral' ),
		array( $severity['info'], __( 'review suggestions', 'foogallery' ), 'info' ),
		array( count( $passed ) . '/' . count( $report['checks'] ), __( 'checks passed', 'foogallery' ), $passed ? 'passed' : 'neutral' ),
		array( $report['totals']['galleries_assessed'], __( 'galleries checked', 'foogallery' ), 'info' ),
		array( $report['totals']['images_assessed'], __( 'images checked', 'foogallery' ), 'info' ),
	);
	echo '<div class="fga-tiles" aria-label="' . esc_attr__( 'Audit summary', 'foogallery' ) . '">';
	foreach ( $tiles as $tile ) {
		echo '<div class="fga-tile fga-' . esc_attr( $tile[2] ) . '"><b>' . esc_html( is_int( $tile[0] ) ? number_format_i18n( $tile[0] ) : $tile[0] ) . '</b><span>' . esc_html( $tile[1] ) . '</span></div>';
	}
	echo '</div><p class="fga-meta">' . esc_html__( 'These results describe what was checked during the saved audit run. The same image can appear in more than one check.', 'foogallery' ) . '</p>';
	echo '<h2>' . esc_html__( 'Findings and coverage', 'foogallery' ) . '</h2>';
	$revision = foogallery_media_audit_revision( $report );
	$actions  = foogallery_media_audit_help_actions();
	foreach ( array( 'error', 'warning', 'info' ) as $severity_key ) {
		foreach ( $report['checks'] as $rule => $check ) {
			if ( ! $check['findings'] || ( isset( $rule_severity[ $rule ] ) ? $rule_severity[ $rule ] : 'info' ) !== $severity_key ) {
				continue; }
			$meta          = foogallery_media_audit_rule_meta( $rule );
			$reference_url = isset( $actions[ $rule ] ) && is_string( $actions[ $rule ] ) && 'https' === wp_parse_url( $actions[ $rule ], PHP_URL_SCHEME ) ? $actions[ $rule ] : '';
			echo '<section class="fga-group fga-' . esc_attr( $severity_key ) . '"><h3 class="fga-group-heading"><span class="fga-heading-label"><span class="fga-dot" aria-hidden="true"></span>' . esc_html( $meta['label'] ) . '</span>';
			if ( $reference_url ) {
				echo '<a class="fga-rule-reference" href="' . esc_url( $reference_url ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( sprintf(
					/* translators: %s: Media Audit finding ID. */
					__( 'Read the documentation for %s', 'foogallery' ),
					$rule
				) ) . '">' . esc_html( $rule ) . '</a>';
			}
			echo '</h3><div class="fga-group-body">';
			echo '<p class="fga-where fga-stats" role="list">';
			foreach ( foogallery_media_audit_finding_stats( $check ) as $stat_index => $stat_label ) {
				echo '<span class="fga-stat' . ( 3 === $stat_index ? ' fga-card-coverage' : '' ) . '" role="listitem">';
				if ( $stat_index > 0 ) {
					echo '<span class="fga-stat-divider" aria-hidden="true">·</span>';
				}
				echo '<span class="fga-stat-label">' . esc_html( $stat_label ) . '</span></span>';
			}
			echo '</p>';
			echo '<p class="fga-fix"><strong>' . esc_html__( 'How to fix:', 'foogallery' ) . '</strong> ' . esc_html( $meta['fix'] ) . '</p><p class="fga-links"><a class="button button-secondary fga-view-affected" data-audit-rule="' . esc_attr( $rule ) . '" data-audit-revision="' . esc_attr( $revision ) . '" aria-haspopup="dialog" href="' . esc_url(
				add_query_arg(
					array(
						'audit_rule' => $rule,
						'audit_page' => 1,
					),
					foogallery_media_audit_page_url()
				) . '#foogallery-audit-affected'
			) . '">' . esc_html__( 'View affected images', 'foogallery' ) . ' <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span></a>';
			echo '</p></div></section>';
		}
	}
	if ( ! $report['findings'] ) {
		echo '<div class="fga-note"><p>' . esc_html__( 'No findings were recorded in the observations made. Review the coverage below to see which checks could be completed.', 'foogallery' ) . '</p></div>';
	}
	foogallery_media_audit_render_affected( $report );
	foreach ( array(
		'partial' => $partial,
		'passed'  => $passed,
	) as $kind => $checks ) {
		if ( ! $checks ) {
			continue; }
		echo '<section class="fga-group fga-' . esc_attr( 'partial' === $kind ? 'warning' : 'passed' ) . '"><h3>' . esc_html( 'partial' === $kind ? __( 'Checked only in part or unavailable', 'foogallery' ) : __( 'Checked and fine', 'foogallery' ) ) . '</h3><ul class="fga-check-list fga-check-' . esc_attr( $kind ) . '">';
		foreach ( $checks as $rule => $check ) {
			$meta = foogallery_media_audit_rule_meta( $rule );
			echo '<li><span aria-hidden="true">' . ( 'passed' === $kind ? '✓' : '~' ) . '</span><div>' . esc_html( $meta['label'] ) . '<small class="fga-meta">' . esc_html( foogallery_media_audit_coverage_label( $check ) ) . '</small></div></li>';
		}
		echo '</ul></section>';
	}
	echo '<details class="fga-group fga-all-checks"><summary>' . esc_html__( 'All checks and coverage', 'foogallery' ) . '</summary><div class="fga-table-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Check', 'foogallery' ) . '</th><th>' . esc_html__( 'Outcome', 'foogallery' ) . '</th><th>' . esc_html__( 'Coverage', 'foogallery' ) . '</th></tr></thead><tbody>';
	$states = array(
		'passed'         => __( 'Passed', 'foogallery' ),
		'findings'       => __( 'Findings', 'foogallery' ),
		'incomplete'     => __( 'Incomplete', 'foogallery' ),
		'unavailable'    => __( 'Unavailable', 'foogallery' ),
		'not_applicable' => __( 'Not applicable', 'foogallery' ),
	);
	foreach ( $report['checks'] as $rule => $check ) {
		$meta = foogallery_media_audit_rule_meta( $rule );
		echo '<tr><td>' . esc_html( $meta['label'] ) . '<small class="fga-meta">' . esc_html( $rule ) . '</small></td><td>' . esc_html( $states[ $check['state'] ] ) . '</td><td>' . esc_html( foogallery_media_audit_coverage_label( $check ) ) . '</td></tr>';
	}
	echo '</tbody></table></div></details><h2>' . esc_html__( 'About this audit', 'foogallery' ) . '</h2><table class="widefat striped fga-about"><tbody>';
	$about = array(
		__( 'Scan completed', 'foogallery' )   => $report['finished'],
		__( 'Started by', 'foogallery' )       => 'automatic' === $report['trigger'] ? __( 'Automatic scheduled audit', 'foogallery' ) : __( 'Manual audit', 'foogallery' ),
		__( 'Gallery coverage', 'foogallery' ) => foogallery_media_audit_coverage_label(
			array(
				'assessed' => $report['totals']['galleries_assessed'],
				'eligible' => $report['totals']['galleries_eligible'],
			)
		),
		__( 'Image coverage', 'foogallery' )   => foogallery_media_audit_coverage_label(
			array(
				'assessed' => $report['totals']['images_assessed'],
				'eligible' => $report['totals']['images_eligible'],
			)
		),
	);
	foreach ( $about as $label => $value ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>'; }
	if ( $report['limitations'] ) {
		echo '<tr><th scope="row">' . esc_html__( 'Coverage limitations', 'foogallery' ) . '</th><td>' . esc_html( implode( ' ', foogallery_media_audit_limitation_labels( $report['limitations'] ) ) ) . '</td></tr>';
	}
	echo '</tbody></table><div class="fga-note"><p>' . esc_html__( 'Accessibility observations are review suggestions: decorative images may correctly have empty alt text. Source sizes describe storage, not visitor download size. HTTP checks are samples.', 'foogallery' ) . '</p></div>';
}
