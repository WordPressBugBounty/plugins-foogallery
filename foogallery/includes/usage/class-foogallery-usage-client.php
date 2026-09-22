<?php
/**
 * FooGallery Improve HTTP client.
 *
 * @package FooGallery
 */

/** Strict HTTP boundary for the Improve service. */
class FooGallery_Usage_Client {
	const HOST = 'https://usage.fooplugins.com';
	/**
	 * Return the fixed manifest endpoint.
	 *
	 * @return mixed
	 */
	public static function endpoint() {
		return self::HOST . '/v1/manifest'; }
	/**
	 * Send one bounded authenticated service request.
	 *
	 * @param string $route Fixed service route suffix.
	 * @param string $body Exact encoded request bytes.
	 * @param string $token Opaque credential or lease ownership token.
	 * @return mixed
	 */
	private static function request( $route, $body, $token ) {
		$args = array(
			'timeout'             => 10,
			'redirection'         => 0,
			'sslverify'           => true,
			'reject_unsafe_urls'  => true,
			'blocking'            => true,
			'cookies'             => array(),
			'headers'             => array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			),
			'body'                => $body,
			'data_format'         => 'body',
			'limit_response_size' => 4096,
		);
		return wp_remote_post( self::HOST . '/v1/' . $route, $args );
	}
	/**
	 * Encode a small service request body.
	 *
	 * @param array $data Allowlisted request or measurement data.
	 * @return mixed
	 */
	private static function body( $data ) {
		return wp_json_encode( $data, JSON_UNESCAPED_SLASHES ); }
	/**
	 * Enroll the anonymous reporting identity.
	 *
	 * @param string $site_id Random 128-bit reporting identifier.
	 * @param string $token Opaque credential or lease ownership token.
	 * @return mixed
	 */
	public static function enroll( $site_id, $token ) {
		return self::request(
			'enroll',
			self::body(
				array(
					'site_id'         => $site_id,
					'schema_version'  => 2,
					'consent_version' => 2,
				)
			),
			$token
		); }
	/**
	 * Return the capability-safe Improve status payload.
	 *
	 * @param string $site_id Random 128-bit reporting identifier.
	 * @param string $token Opaque credential or lease ownership token.
	 * @return mixed
	 */
	public static function status( $site_id, $token ) {
		return self::request( 'status', self::body( array( 'site_id' => $site_id ) ), $token ); }
	/**
	 * Request authenticated remote deletion.
	 *
	 * @param string $site_id Random 128-bit reporting identifier.
	 * @param string $token Opaque credential or lease ownership token.
	 * @return mixed
	 */
	public static function delete( $site_id, $token ) {
		return self::request( 'delete', self::body( array( 'site_id' => $site_id ) ), $token ); }
	/**
	 * Send an exact manifest body.
	 *
	 * @param string $body Exact encoded request bytes.
	 * @param string $token Opaque credential or lease ownership token.
	 * @return mixed
	 */
	public static function send( $body, $token ) {
		return self::request( 'manifest', $body, $token ); }
	/**
	 * Normalize and bound an HTTP response.
	 *
	 * @param array|WP_Error $response WordPress HTTP response or normalized service response.
	 * @return mixed
	 */
	public static function response( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'network', __( 'The reporting service is unavailable.', 'foogallery' ) ); }
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		if ( ! is_string( $raw ) || strlen( $raw ) > 4096 ) {
			return new WP_Error( 'invalid_response', __( 'The reporting service returned an invalid response.', 'foogallery' ) ); }
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array(); }
		$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
		if ( is_array( $retry_after ) ) {
			$retry_after = 0; }
		return array(
			'code'        => $code,
			'data'        => $data,
			'retry_after' => min( 86400, max( 0, (int) $retry_after ) ),
		);
	}
}
