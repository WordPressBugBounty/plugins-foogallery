<?php
/**
 * Strict serializer for the reviewed reporting contract.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Strict allowlisted serializer. Never serializes scan progress or source settings. */
class FooGallery_Usage_Manifest {
	/** Maximum encoded request size in bytes. */
	const MAX_BODY = 16384;
	/**
	 * Encode a complete snapshot, dropping every non-catalogue source field.
	 *
	 * @param array  $data Completed measurement.
	 * @param string $site_id Random site identity.
	 * @param int    $sequence Monotonic delivery sequence.
	 * @return string|WP_Error
	 */
	public static function build( $data, $site_id, $sequence ) {
		if ( ! is_array( $data ) || ! isset( $data['collection_day'], $data['plugin'], $data['inventory'] ) || ! is_string( $data['collection_day'] ) || ! is_array( $data['plugin'] ) || ! is_array( $data['inventory'] ) || ! isset( $data['plugin']['version'], $data['plugin']['edition'], $data['plugin']['trial'] ) || ! is_string( $data['plugin']['version'] ) || ! is_string( $data['plugin']['edition'] ) || ! is_string( $data['plugin']['trial'] ) || ! is_string( $site_id ) || ! preg_match( '/^[a-f0-9]{32}$/', $site_id ) || ! is_int( $sequence ) || $sequence < 1 || $sequence > 9007199254740991 ) {
			return new WP_Error( 'foogallery_usage_invalid_manifest', __( 'Invalid usage manifest.', 'foogallery' ) );
		}
		$catalog = FooGallery_Usage_Registry::catalog();
		foreach ( array( 'modules', 'features', 'actions' ) as $group ) {
			if ( ! isset( $data[ $group ] ) || ! is_array( $data[ $group ] ) || array_diff_key( $data[ $group ], $catalog[ $group ] ) || array_diff_key( $catalog[ $group ], $data[ $group ] ) ) {
				return new WP_Error( 'foogallery_usage_invalid_manifest', __( 'Usage manifest does not match the reviewed catalogue.', 'foogallery' ) );
			}
			foreach ( $data[ $group ] as $state ) {
				if ( ! is_string( $state ) || ! in_array( $state, FooGallery_Usage_Registry::states(), true ) ) {
					return new WP_Error( 'foogallery_usage_invalid_manifest', __( 'Invalid usage state.', 'foogallery' ) );
				}
			}
			ksort( $data[ $group ] );
		}
		$buckets   = array( '0', '1', '2-5', '6-20', '21-100', '101+' );
		$day       = isset( $data['collection_day'] ) ? $data['collection_day'] : '';
		$valid_day = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) && gmdate( 'Y-m-d', strtotime( $day . ' 00:00:00 UTC' ) ) === $day;
		if ( count( $catalog['modules'] ) + count( $catalog['features'] ) + count( $catalog['actions'] ) > 256 || ! $valid_day || empty( $data['plugin']['version'] ) || strlen( $data['plugin']['version'] ) > 32 || ! preg_match( '/^[A-Za-z0-9._-]+$/', $data['plugin']['version'] ) || ! in_array( $data['plugin']['edition'], array( 'free', 'starter', 'expert', 'commerce', 'unknown' ), true ) || ! in_array( $data['plugin']['trial'], array( 'active', 'used', 'none' ), true ) || ! isset( $data['inventory']['galleries'], $data['inventory']['albums'] ) || ! in_array( $data['inventory']['galleries'], $buckets, true ) || ! in_array( $data['inventory']['albums'], $buckets, true ) ) {
			return new WP_Error( 'foogallery_usage_invalid_manifest', __( 'Invalid usage manifest.', 'foogallery' ) );
		}
		$body = array(
			'schema_version'  => FooGallery_Usage_Registry::SCHEMA_VERSION,
			'catalog_version' => FooGallery_Usage_Registry::CATALOG_VERSION,
			'consent_version' => 2,
			'site_id'         => $site_id,
			'sequence'        => $sequence,
			'collection_day'  => $data['collection_day'],
			'environment_type' => isset( $data['environment_type'] ) ? $data['environment_type'] : FooGallery_Usage::environment_type(),
			'plugin'          => array(
				'version' => $data['plugin']['version'],
				'edition' => $data['plugin']['edition'],
				'trial'   => $data['plugin']['trial'],
			),
			'measurement'     => 'feature_usage',
			'inventory'       => array(
				'galleries' => $data['inventory']['galleries'],
				'albums'    => $data['inventory']['albums'],
			),
			'modules'         => $data['modules'],
			'actions'         => $data['actions'],
			'features'        => $data['features'],
		);
		if ( ! in_array( $body['environment_type'], array( 'production', 'staging', 'development', 'local' ), true ) ) {
			return new WP_Error( 'foogallery_usage_invalid_manifest', __( 'Invalid usage environment.', 'foogallery' ) );
		}
		$json = wp_json_encode( $body );
		if ( ! is_string( $json ) || strlen( $json ) > self::MAX_BODY ) {
			return new WP_Error( 'foogallery_usage_manifest_too_large', __( 'Usage manifest exceeds its fixed limit.', 'foogallery' ) );
		}
		return $json;
	}
	/**
	 * Encode a local preview without allocating a reporting identity.
	 *
	 * @param array $data Completed measurement.
	 * @return string|WP_Error
	 */
	public static function preview( $data ) {
		return self::build( $data, '00000000000000000000000000000000', 1 );
	}
}
