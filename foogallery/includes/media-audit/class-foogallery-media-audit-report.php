<?php
/** Private audit storage and a fixed, content-free aggregate projection.
 *
 * @package FooGallery
 */

defined( 'ABSPATH' ) || exit;
/** FooGallery feature service with private application-owned state. */
class FooGallery_Media_Audit_Report {
	const OPTION  = 'foogallery_media_audit_report';
	const BUILD   = '0.13.0';
	const RULESET = 'r4.2';
	/**
	 * Acquire the per-blog publication lock.
	 *
	 * @param int $wait Wait.
	 * @return mixed
	 */
	public static function lock( $wait = 0 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory lock state must be read directly.
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,%d)', 'fg_audit_' . md5( $wpdb->options ), $wait ) );
	}
	public static function unlock() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory lock release must execute directly.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'fg_audit_' . md5( $wpdb->options ) ) );
	}

	/** Return the registered rule catalogue for new scans. */
	public static function rules() {
		return array( 'FG-DEL-02', 'FG-SRC-01', 'FG-SRC-04', 'FG-SRC-05', 'FG-SRC-07', 'FG-INT-01', 'FG-INT-02', 'FG-A11Y-01', 'FG-A11Y-02', 'FG-A11Y-03', 'FG-A11Y-04', 'FG-A11Y-05', 'FG-CFG-01', 'FG-CFG-02' );
	}

	/**
	 * Create an explicit unmeasured report skeleton.
	 *
	 * @param string $scope Site or gallery scope.
	 * @param string $trigger Manual or automatic origin.
	 * @param string $state Overall completion state.
	 * @return array
	 */
	public static function empty_report( $scope = 'site', $trigger = 'manual', $state = 'not_run' ) {
		$checks = array();
		foreach ( self::rules() as $rule ) {
			$checks[ $rule ] = array( 'state' => 'unavailable', 'assessed' => 0, 'eligible' => null, 'coverage' => null, 'findings' => 0, 'affected_images' => 0, 'affected_galleries' => 0, 'limitations' => array() );
		}
		return array(
			'schema' => 1,
			'build' => self::BUILD,
			'ruleset' => self::RULESET,
			'scope' => $scope,
			'trigger' => $trigger,
			'started' => null,
			'finished' => null,
			'state' => $state,
			'limitations' => array(),
			'totals' => array( 'galleries_assessed' => 0, 'galleries_eligible' => null, 'images_assessed' => 0, 'images_eligible' => null ),
			'checks' => $checks,
			'findings' => array(),
			'diagnostics' => array(),
		);
	}

	/**
	 * Validate the local report structure before use.
	 *
	 * @param array|null $r Local report, or null when unmeasured.
	 * @return mixed
	 */
	public static function valid( $r ) {
		if ( ! is_array( $r ) || ! isset( $r['checks'], $r['totals'], $r['limitations'] ) || ! is_array( $r['checks'] ) || ! is_array( $r['totals'] ) || ! is_array( $r['limitations'] ) ) {
			return false; }
		if ( ! is_array( $r ) || ! isset( $r['schema'], $r['scope'], $r['checks'], $r['findings'], $r['totals'], $r['state'], $r['build'], $r['ruleset'], $r['trigger'], $r['limitations'] ) || 1 !== $r['schema'] || ! in_array( $r['scope'], array( 'site', 'gallery' ), true ) || ! is_array( $r['findings'] ) || array_keys( $r['checks'] ) !== self::rules() ) {
			return false; }
		if ( self::BUILD !== $r['build'] || self::RULESET !== $r['ruleset'] || ! in_array( $r['state'], array( 'complete', 'incomplete', 'unavailable', 'not_run' ), true ) ) { return false; }
		foreach ( $r['checks'] as $check ) {
			if ( ! is_array( $check ) ) {
				return false; }
			if ( ! isset( $check['state'], $check['assessed'], $check['findings'] ) || ! array_key_exists( 'eligible', $check ) || ! in_array( $check['state'], array( 'passed', 'findings', 'incomplete', 'unavailable', 'not_applicable' ), true ) || ! is_int( $check['assessed'] ) || $check['assessed'] < 0 || ( null !== $check['eligible'] && ( ! is_int( $check['eligible'] ) || $check['eligible'] < $check['assessed'] ) ) ) {
				return false; }
			if ( isset( $check['limitations'] ) && ( ! is_array( $check['limitations'] ) || array_filter( $check['limitations'], function ( $code ) { return ! is_string( $code ); } ) ) ) { return false; }
			if ( isset( $check['observed_eligible'] ) && ( ! is_int( $check['observed_eligible'] ) || $check['observed_eligible'] < $check['assessed'] ) ) { return false; }
			foreach ( array( 'findings', 'affected_images', 'affected_galleries' ) as $key ) { if ( ! isset( $check[ $key ] ) || ! is_int( $check[ $key ] ) || $check[ $key ] < 0 ) { return false; } }
		}
		foreach ( $r['findings'] as $finding ) {
			if ( ! is_array( $finding ) || ! isset( $finding['id'], $finding['rule'], $finding['severity'], $finding['subjects'] ) || ! is_string( $finding['id'] ) || ! in_array( $finding['rule'], self::rules(), true ) || ! in_array( $finding['severity'], array( 'error', 'warning', 'info' ), true ) || ! is_array( $finding['subjects'] ) ) { return false; }
			foreach ( $finding['subjects'] as $subject ) {
				if ( ! is_array( $subject ) || ! isset( $subject['image'], $subject['gallery'], $subject['sampled_url'] ) || ! is_int( $subject['image'] ) || ! is_int( $subject['gallery'] ) || ! is_string( $subject['sampled_url'] ) ) { return false; }
				foreach ( array( 'alt', 'reason', 'source_format' ) as $key ) {
					if ( isset( $subject[ $key ] ) && ! is_string( $subject[ $key ] ) ) { return false; }
				}
				foreach ( array( 'alt_length', 'bytes', 'http_status', 'member_count', 'requested_height', 'requested_width', 'source_height', 'source_width' ) as $key ) {
					if ( isset( $subject[ $key ] ) && ( ! is_int( $subject[ $key ] ) || $subject[ $key ] < 0 ) ) { return false; }
				}
				if ( isset( $subject['compression_version'] ) && 'density-1' !== $subject['compression_version'] ) { return false; }
				if ( isset( $subject['compression_min_bytes'] ) && ( ! is_int( $subject['compression_min_bytes'] ) || $subject['compression_min_bytes'] <= 0 ) ) { return false; }
				if ( isset( $subject['compression_density'] ) && ( ! is_numeric( $subject['compression_density'] ) || is_string( $subject['compression_density'] ) || ! is_finite( (float) $subject['compression_density'] ) || $subject['compression_density'] <= 0 ) ) { return false; }
			}
		}
		return false !== wp_json_encode( $r );
	}
	public static function load() {
		$r = get_option( self::OPTION, null );
		return self::valid( $r ) && 'site' === $r['scope'] ? self::reconcile_affected_galleries( $r ) : null;
	}
	/**
	 * Derive gallery totals from the same saved contexts shown in the affected-items modal.
	 *
	 * @param array $report Valid detailed report.
	 * @return array
	 */
	private static function reconcile_affected_galleries( $report ) {
		$affected = array();
		foreach ( self::rules() as $rule ) {
			$affected[ $rule ] = array();
		}
		foreach ( $report['findings'] as $finding ) {
			foreach ( $finding['subjects'] as $subject ) {
				$galleries = array();
				if ( $subject['gallery'] ) {
					$galleries[] = $subject['gallery'];
				} elseif ( $subject['image'] && ! empty( $report['gallery_membership'][ $subject['image'] ] ) && is_array( $report['gallery_membership'][ $subject['image'] ] ) ) {
					$galleries = array_keys( $report['gallery_membership'][ $subject['image'] ] );
				}
				foreach ( $galleries as $gallery ) {
					$gallery = (int) $gallery;
					if ( $gallery ) {
						$affected[ $finding['rule'] ][ $gallery ] = true;
					}
				}
			}
		}
		foreach ( $affected as $rule => $galleries ) {
			$report['checks'][ $rule ]['affected_galleries'] = count( $galleries );
		}
		return $report;
	}
	/**
	 * Replace the private site report after validation.
	 *
	 * @param array $report Report.
	 * @return mixed
	 */
	public static function save( $report ) {
		if ( ! self::valid( $report ) || 'site' !== $report['scope'] ) {
			return new WP_Error( 'audit_invalid_report', __( 'The audit report could not be validated.', 'foogallery' ) ); }
		$ok = update_option( self::OPTION, $report, false );
		wp_set_option_autoload( self::OPTION, false );
		if ( ! $ok && get_option( self::OPTION ) !== $report ) {
			return new WP_Error( 'audit_storage_failed', __( 'The audit report could not be saved.', 'foogallery' ) ); }
		return true;
	}
	/**
	 * Project a site report into the allowlisted reporting contract.
	 *
	 * @param array|null $r Local report, or null when unmeasured.
	 * @return mixed
	 */
	public static function aggregate( $r = null ) {
		if ( ! self::valid( $r ) || 'site' !== $r['scope'] ) {
			$r = self::empty_report(); }
		$r = self::reconcile_affected_galleries( $r );
		$out = array(
			'schema'      => 1,
			'build'       => self::BUILD,
			'ruleset'     => self::RULESET,
			'scope'       => 'site',
			'trigger'     => in_array( $r['trigger'], array( 'manual', 'automatic' ), true ) ? $r['trigger'] : 'manual',
			'state'       => in_array( $r['state'], array( 'complete', 'incomplete', 'unavailable', 'not_run' ), true ) ? $r['state'] : 'unavailable',
			'started'     => null,
			'finished'    => null,
			'limitations' => array(),
			'totals'      => array(),
			'severity'    => array(
				'error'   => 0,
				'warning' => 0,
				'info'    => 0,
			),
			'rules'       => array(),
			'magnitudes'  => array(
				'large_source_bytes' => array(
					'value'       => null,
					'unit'        => 'bytes',
					'estimated'   => false,
					'aggregation' => 'sum_distinct_sources',
				),
			),
		);
		if ( isset( $r['large_source_bytes'] ) && is_int( $r['large_source_bytes'] ) && $r['large_source_bytes'] >= 0 && $r['large_source_bytes'] <= 9007199254740991 ) {
			$out['magnitudes']['large_source_bytes']['value'] = $r['large_source_bytes'];
		}
		foreach ( array( 'started', 'finished' ) as $key ) {
			if ( isset( $r[ $key ] ) && is_string( $r[ $key ] ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $r[ $key ] ) ) {
				$out[ $key ] = $r[ $key ]; }
		}
		$codes = array( 'image_cap', 'execution_budget', 'elapsed_budget', 'network_budget', 'dynamic_source', 'http_unavailable', 'metadata_unavailable', 'audit_failed', 'storage_failed', 'busy', 'source_unavailable', 'header_unavailable', 'header_budget', 'file_changed' );
		foreach ( $codes as $code ) {
			if ( in_array( $code, $r['limitations'], true ) ) {
				$out['limitations'][] = $code; }
		}
		foreach ( array_keys( self::empty_report()['totals'] ) as $key ) {
			$out['totals'][ $key ] = self::number( isset( $r['totals'][ $key ] ) ? $r['totals'][ $key ] : null ); }
		foreach ( self::rules() as $rule ) {
			$c                     = $r['checks'][ $rule ];
			$out['rules'][ $rule ] = array( 'state' => $c['state'] );
			foreach ( array( 'assessed', 'eligible', 'findings', 'affected_images', 'affected_galleries' ) as $key ) {
				$out['rules'][ $rule ][ $key ] = self::number( isset( $c[ $key ] ) ? $c[ $key ] : null ); }
			$out['rules'][ $rule ]['coverage'] = ! empty( $c['eligible'] ) ? round( $c['assessed'] / $c['eligible'], 4 ) : null;
		}
		foreach ( $r['findings'] as $finding ) {
			if ( isset( $finding['severity'], $out['severity'][ $finding['severity'] ] ) ) {
				++$out['severity'][ $finding['severity'] ]; }
		}
		return $out;
	}
	/**
	 * Normalize a nullable aggregate count.
	 *
	 * @param mixed $n N.
	 * @return mixed
	 */
	private static function number( $n ) {
		return is_int( $n ) && $n >= 0 ? min( $n, 2147483647 ) : null; }
}
