<?php
/**
 * FooGallery Improve local state storage.
 *
 * @package FooGallery
 */

/** Local, non-autoloaded state for FooGallery Improve. */
class FooGallery_Usage_State {
	const OPTION = 'foogallery_usage_state';
	const OUTBOX = 'foogallery_usage_outbox';
	const SCAN   = 'foogallery_usage_scan';
	const LOCK   = 'foogallery_usage_lease';

	/**
	 * Return the normalized state defaults.
	 *
	 * @return mixed
	 */
	public static function defaults() {
		return array(
			'consent'                    => false,
			'consent_version'            => 0,
			'dismissed'                  => false,
			'epoch'                      => 1,
			'generation'                 => 1,
			'sequence'                   => 0,
			'site_id'                    => '',
			'token'                      => '',
			'phase'                      => 'idle',
			'next_collection'            => 0,
			'collection_token'           => '',
			'next_attempt'               => 0,
			'last_error'                 => '',
			'acknowledged'               => null,
			'outbox'                     => null,
			'delete_pending'             => false,
			'registered'                 => false,
			'action_observations'        => array(),
			'bulk_copy_observed_from'    => 0,
			'bulk_copy_last_success_day' => '',
		);
	}

	/**
	 * Read normalized Improve state.
	 *
	 * @return mixed
	 */
	public static function get() {
		$value = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $value ) ? $value : array(), self::defaults() );
	}

	/**

	 * CAS prevents two cron workers allocating the same sequence.
	 *
	 * @param callable $callback Mutation applied to the latest state during compare-and-swap.
	 */
	public static function mutate( $callback ) {
		global $wpdb;
		for ( $attempt = 0; $attempt < 4; $attempt++ ) {
			// Uncached reads are required for compare-and-swap correctness across workers.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION ) );
			if ( $wpdb->last_error ) {
				return new WP_Error( 'foogallery_usage_state', __( 'Unable to read reporting state.', 'foogallery' ) ); }
			$current = false === $raw || null === $raw ? self::defaults() : wp_parse_args( is_array( maybe_unserialize( $raw ) ) ? maybe_unserialize( $raw ) : array(), self::defaults() );
			$next    = call_user_func( $callback, $current );
			if ( ! is_array( $next ) ) {
				return new WP_Error( 'foogallery_usage_state', __( 'Unable to update reporting state.', 'foogallery' ) ); }
			$next = wp_parse_args( $next, self::defaults() );
			$old  = false === $raw || null === $raw ? false : $raw;
			$new  = maybe_serialize( $next );
			if ( $old === $new ) {
				return $next; }
			if ( false === $old ) {
				if ( add_option( self::OPTION, $next, '', 'no' ) ) {
					return $next;
				} wp_cache_delete( self::OPTION, 'options' );
				continue; }
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $new, self::OPTION, $old ) );
			if ( 1 === (int) $updated ) {
				wp_cache_delete( self::OPTION, 'options' );
				return $next; }
			wp_cache_delete( self::OPTION, 'options' );
		}
		return new WP_Error( 'foogallery_usage_busy', __( 'Reporting state is busy.', 'foogallery' ) );
	}

	/**
	 * Create the anonymous site identity when needed.
	 *
	 * @return mixed
	 */
	public static function ensure_identity() {
		return self::mutate(
			function ( $state ) {
				if ( empty( $state['site_id'] ) || empty( $state['token'] ) ) {
					$id    = self::random_bytes( 16 );
					$token = self::random_bytes( 32 );
					if ( is_wp_error( $id ) || is_wp_error( $token ) ) {
							return null;
					} if ( empty( $state['site_id'] ) ) {
								$state['site_id'] = bin2hex( $id );
					} if ( empty( $state['token'] ) ) {
						// Base64url encodes random token bytes; it does not hide executable content.
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
						$state['token'] = rtrim( strtr( base64_encode( $token ), '+/', '-_' ), '=' ); }
				}
				return $state;
			}
		);
	}

	/**
	 * Generate cryptographically secure identity bytes.
	 *
	 * @param int $length Number of cryptographically random bytes.
	 * @return mixed
	 */
	private static function random_bytes( $length ) {
		if ( function_exists( 'random_bytes' ) ) {
			try {
				return random_bytes( $length );
			} catch ( Exception $e ) {
				return new WP_Error( 'foogallery_usage_entropy', __( 'Secure identity generation is unavailable.', 'foogallery' ) ); }
		}
		return new WP_Error( 'foogallery_usage_entropy', __( 'Secure identity generation is unavailable.', 'foogallery' ) );
	}

	/**
	 * Acquire a named expiring worker lease.
	 *
	 * @param string $name Local lease purpose.
	 * @param int    $seconds Lease lifetime in seconds.
	 * @return mixed
	 */
	public static function acquire_lease( $name, $seconds = 120 ) {
		$now      = time();
		$token    = wp_generate_uuid4();
		$existing = get_option( self::LOCK . '_' . $name, array() );
		if ( is_array( $existing ) && ! empty( $existing['until'] ) && $existing['until'] > $now ) {
			return false; }
		if ( add_option(
			self::LOCK . '_' . $name,
			array(
				'token' => $token,
				'until' => $now + $seconds,
			),
			'',
			'no'
		) ) {
			return $token; }
		global $wpdb;
		$old = maybe_serialize( $existing );
		$new = maybe_serialize(
			array(
				'token' => $token,
				'until' => $now + $seconds,
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $new, self::LOCK . '_' . $name, $old ) ) ) {
			wp_cache_delete( self::LOCK . '_' . $name, 'options' );
			return $token; }
		return false;
	}
	/**
	 * Check lease ownership from the database, bypassing the option cache.
	 *
	 * @param string $name Local lease purpose.
	 *
	 * @param string $token Opaque credential or lease ownership token.
	 */
	public static function owns_lease( $name, $token ) {
		global $wpdb;
		// Uncached storage is the authority for lease ownership.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw   = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK . '_' . $name ) );
		$lease = maybe_unserialize( $raw );
		return is_array( $lease ) && isset( $lease['token'], $lease['until'] ) && is_string( $lease['token'] ) && hash_equals( $lease['token'], (string) $token ) && (int) $lease['until'] >= time();
	}
	/**
	 * Release a lease only when its token still owns it.
	 *
	 * @param string $name Local lease purpose.
	 * @param string $token Opaque credential or lease ownership token.
	 * @return void
	 */
	public static function release_lease( $name, $token ) {
		global $wpdb;
		$key   = self::LOCK . '_' . $name;
		$lease = get_option( $key, array() );
		if ( is_array( $lease ) && isset( $lease['token'] ) && hash_equals( $lease['token'], $token ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, maybe_serialize( $lease ) ) );
			wp_cache_delete( $key, 'options' ); } }
	/**
	 * Return the current exact outbound packet.
	 *
	 * @return mixed
	 */
	public static function outbox() {
		$state = self::get();
		return is_array( $state['outbox'] ) ? $state['outbox'] : null; }
	/**
	 * Cancel collection and delivery, while preserving an authenticated deletion retry.
	 *
	 * @param bool $preserve_next_collection Whether to retain the weekly deadline while cancelling jobs.
	 */
	public static function clear_reporting_jobs( $preserve_next_collection = false ) {
		FooGallery_Jobs::cancel( 'foogallery_usage_weekly_action' );
		FooGallery_Jobs::cancel( 'foogallery_usage_collect_action' );
		FooGallery_Jobs::cancel( 'foogallery_usage_send_action' );
		delete_option( self::SCAN );
		$audit = get_option( 'foogallery_media_audit_job' );
		if ( is_array( $audit ) && 'automatic' === $audit['trigger'] ) {
			FooGallery_Jobs::cancel( 'foogallery_media_audit_step', 'foogallery-media-audit' );
			delete_option( 'foogallery_media_audit_job' );
		}
		self::mutate(
			function ( $state ) use ( $preserve_next_collection ) {
				$state['outbox'] = null;
				$state['collection_token'] = '';
				if ( ! $preserve_next_collection ) {
					$state['next_collection'] = 0; }
				$state['next_attempt'] = 0;
				return $state;
			}
		);
	}

	/** Cancel every job. Intended for completed deletion or local uninstall only. */
	public static function clear_jobs() {
		self::clear_reporting_jobs();
		FooGallery_Jobs::cancel( 'foogallery_usage_delete_action' );
	}

	/** Remove every per-site reporting artifact. */
	public static function erase() {
		self::clear_jobs();
		delete_option( self::OPTION );
		delete_option( self::OUTBOX );
		delete_option( self::LOCK . '_scan' );
		delete_option( self::LOCK . '_send' );
		delete_option( 'foogallery_usage_bulk_copy_until' );
		self::clear_previews();
	}

	/** Remove every user-scoped local preview packet for the current site. */
	public static function clear_previews() {
		global $wpdb;
		$value = $wpdb->esc_like( '_transient_foogallery_usage_preview_' ) . '%';
		// Preview keys are enumerated only for complete privacy cleanup.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $value ) );
		foreach ( $names as $name ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) ); }
	}
}
