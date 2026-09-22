<?php
/**
 * FooGallery Improve scheduler.
 *
 * @package FooGallery
 */

/** Bounded collection, exact outbox publication and retry scheduling. */
class FooGallery_Usage_Scheduler {
	const MAX_INVALIDATIONS = 3;
	const OUTBOX_MAX_AGE    = 172800;

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'foogallery_usage_collect_action', array( $this, 'collect' ), 10, 2 );
		add_action( 'foogallery_usage_weekly_action', array( $this, 'weekly' ) );
		add_action( 'foogallery_usage_send_action', array( $this, 'send_scheduled' ), 10, 2 );
		add_action( 'foogallery_usage_delete_action', array( 'FooGallery_Usage', 'retry_delete' ) );
	}

	/** Queue tokens identify a packet, not a held application lease. */
	public function send_scheduled( $epoch, $sequence ) {
		$s = FooGallery_Usage_State::get();
		if ( (int) $s['epoch'] === (int) $epoch && is_array( $s['outbox'] ) && (int) $s['outbox']['sequence'] === (int) $sequence ) {
			$this->send(); }
	}

	/** Repair a single weekly trigger only while current consent permits it. */
	public function ensure_weekly() {
		if ( $this->permitted() ) {
			$state = FooGallery_Usage_State::get();
			$due   = 'idle' === $state['phase'] && $state['next_collection'] > time() ? (int) $state['next_collection'] : time() + WEEK_IN_SECONDS;
			FooGallery_Jobs::schedule( 'foogallery_usage_weekly_action', array(), 'foogallery-improve', $due, WEEK_IN_SECONDS );
		}
	}

	/** Recover lost continuation/retry actions without launching an idle collection. */
	public function repair() {
		if ( ! $this->permitted() ) {
			return; }
		$this->ensure_weekly();
		$s = FooGallery_Usage_State::get();
		if ( is_array( $s['outbox'] ) ) {
			if ( ! FooGallery_Jobs::pending( 'foogallery_usage_send_action' ) ) {
				FooGallery_Jobs::schedule( 'foogallery_usage_send_action', array( (int) $s['epoch'], (int) $s['outbox']['sequence'], wp_generate_uuid4() ), 'foogallery-improve', max( time(), (int) $s['next_attempt'] ) );
			}
		} elseif ( ( get_option( FooGallery_Usage_State::SCAN ) || 'collecting' === $s['phase'] ) && ! FooGallery_Jobs::pending( 'foogallery_usage_collect_action' ) ) {
			$this->schedule_collection();
		}
	}

	/** A weekly tick must not replace an active collection or exact outbox. */
	public function weekly() {
		$state = FooGallery_Usage_State::get();
		if ( $this->permitted() && ! get_option( FooGallery_Usage_State::SCAN ) && empty( $state['outbox'] ) && ! FooGallery_Jobs::pending( 'foogallery_usage_collect_action' ) ) {
			$this->schedule_collection();
		}
	}

	/**
	 * Check every collection and delivery gate.
	 *
	 * @return mixed
	 */
	private function permitted() {
		$state = FooGallery_Usage_State::get();
		return 'blocked' !== $state['phase'] && empty( $state['delete_pending'] ) && FooGallery_Usage::instance()->consented();
	}

	/**
	 * Schedule one collection and persist its due time.
	 *
	 * @param int $when Unix timestamp for the next collection.
	 * @return mixed
	 */
	public function schedule_collection( $when = 0 ) {
		if ( ! $this->permitted() ) {
			return false; }
		$epoch = (int) FooGallery_Usage_State::get()['epoch'];
		$when  = $when ? (int) $when : time();
		$token = wp_generate_uuid4();
		FooGallery_Jobs::cancel( 'foogallery_usage_collect_action' );
		$updated = FooGallery_Usage_State::mutate(
			function ( $state ) use ( $epoch, $when, $token ) {
				if ( ! $state['consent'] || (int) $state['epoch'] !== $epoch || ! empty( $state['outbox'] ) ) {
					return null; }
				$state['next_collection'] = $when;
				$state['collection_token'] = $token;
				return $state;
			}
		);
		if ( is_wp_error( $updated ) ) {
			return false; }
		$this->ensure_weekly();
		return (bool) FooGallery_Jobs::schedule( 'foogallery_usage_collect_action', array( $epoch, $token ), 'foogallery-improve', $when );
	}

	/**
	 * Advance one bounded collection step.
	 *
	 * @return mixed
	 */
	public function collect( $expected_epoch = null, $expected_token = null ) {
		if ( null !== $expected_epoch && (int) $expected_epoch !== (int) FooGallery_Usage_State::get()['epoch'] ) {
			return; }
		$entry_state = FooGallery_Usage_State::get();
		if ( ! empty( $entry_state['outbox'] ) || ( null !== $expected_token && ! hash_equals( (string) $entry_state['collection_token'], (string) $expected_token ) ) ) { return; }
		if ( ! $this->permitted() ) {
			return; }
		$lease = FooGallery_Usage_State::acquire_lease( 'scan', 120 );
		if ( ! $lease ) {
			return; }
		try {
			$state         = FooGallery_Usage_State::get();
			$epoch         = (int) $state['epoch'];
			$generation    = (int) $state['generation'];
			$scan          = get_option( FooGallery_Usage_State::SCAN, null );
			$invalidations = is_array( $scan ) && isset( $scan['usage_invalidations'] ) ? (int) $scan['usage_invalidations'] : 0;
			if ( ! is_array( $scan ) || ! isset( $scan['usage_epoch'], $scan['usage_generation'] ) || (int) $scan['usage_epoch'] !== $epoch || (int) $scan['usage_generation'] !== $generation ) {
				if ( is_array( $scan ) ) {
					++$invalidations; }
				$scan     = FooGallery_Usage_Collector::start();
				if ( is_wp_error( $scan ) ) {
					$this->fail( 'collection_failed', $epoch );
					return; }
				$scan['usage_epoch']         = $epoch;
				$scan['usage_generation']    = $generation;
				$scan['usage_invalidations'] = $invalidations;
			}
			if ( $invalidations >= self::MAX_INVALIDATIONS ) {
				$scan     = FooGallery_Usage_Collector::start();
				if ( is_wp_error( $scan ) ) {
					$this->fail( 'collection_failed', $epoch );
					return; }
				$scan['usage_epoch']         = $epoch;
				$scan['usage_generation']    = $generation;
				$scan['usage_invalidations'] = 0;
				update_option( FooGallery_Usage_State::SCAN, $scan, false );
				$this->set_phase( 'collection_delayed', $epoch );
				$this->schedule_collection( time() + 300 );
				return;
			}
			$scan = FooGallery_Usage_Collector::step( $scan );
			if ( is_wp_error( $scan ) ) {
				$this->fail( 'collection_failed', $epoch );
				return; }
			$current = FooGallery_Usage_State::get();
			if ( ! FooGallery_Usage_State::owns_lease( 'scan', $lease ) || ! $this->permitted() || (int) $current['epoch'] !== $epoch ) {
				return; }
			if ( (int) $current['generation'] !== $generation ) {
				$scan['usage_invalidations'] = $invalidations + 1;
				update_option( FooGallery_Usage_State::SCAN, $scan, false );
				$this->schedule_collection( time() + ( $scan['usage_invalidations'] >= self::MAX_INVALIDATIONS ? 300 : 1 ) );
				return;
			}
			if ( empty( $scan['complete'] ) ) {
				update_option( FooGallery_Usage_State::SCAN, $scan, false );
				$this->schedule_collection( time() + 5 );
				return;
			}
			if ( ! $this->publish( $scan['data'], $epoch, $generation ) ) {
				update_option( FooGallery_Usage_State::SCAN, $scan, false );
				$this->schedule_collection( time() + 60 );
				return;
			}
			delete_option( FooGallery_Usage_State::SCAN );
		} finally {
			FooGallery_Usage_State::release_lease( 'scan', $lease ); }
	}

	/**
	 * Publish completed scan data to an exact outbox.
	 *
	 * @param array $data Allowlisted request or measurement data.
	 * @param int   $epoch Consent epoch that owns this work.
	 * @param int   $generation Configuration generation that owns this scan.
	 * @return mixed
	 */
	private function publish( $data, $epoch, $generation ) {
		$state = FooGallery_Usage_State::get();
		if ( ! $this->permitted() || (int) $state['epoch'] !== (int) $epoch || (int) $state['generation'] !== (int) $generation ) {
			return false; }
		FooGallery_Usage_Actions::overlay( $data, $state );
		$identity = FooGallery_Usage_State::ensure_identity();
		if ( is_wp_error( $identity ) || ! $this->permitted() || (int) $identity['epoch'] !== (int) $epoch ) {
			return false; }
		$lease = FooGallery_Usage_State::acquire_lease( 'send', 120 );
		if ( ! $lease ) {
			return false; }
		try {
			$before = FooGallery_Usage_State::get();
			if ( (int) $before['sequence'] >= 9007199254740991 ) {
				$this->fail( 'sequence_exhausted', $epoch );
				return false; }
			$sequence = (int) $before['sequence'] + 1;
			$body     = FooGallery_Usage_Manifest::build( $data, $identity['site_id'], $sequence );
			if ( is_wp_error( $body ) ) {
				$this->fail( 'invalid_manifest', $epoch );
				return false; }
			$outbox    = array(
				'body'        => $body,
				'body_sha256' => hash( 'sha256', $body ),
				'sequence'    => $sequence,
				'epoch'       => (int) $epoch,
				'attempts'    => 0,
				'created'     => time(),
			);
			$published = FooGallery_Usage_State::mutate(
				function ( $current ) use ( $before, $identity, $outbox, $generation, $sequence ) {
					if ( ! $current['consent'] || (int) $current['epoch'] !== (int) $outbox['epoch'] || (int) $current['generation'] !== (int) $generation || (int) $current['sequence'] !== (int) $before['sequence'] || ! hash_equals( (string) $current['site_id'], (string) $identity['site_id'] ) ) {
						return null; }
					$current['sequence'] = $sequence;
					$current['outbox']   = $outbox;
					$current['phase']    = 'sending';
					$current['collection_token'] = '';
					return $current;
				}
			);
			if ( is_wp_error( $published ) ) {
				return false; }
			$this->ensure_weekly();
			if ( FooGallery_Usage_State::owns_lease( 'send', $lease ) ) { $this->send( $lease ); }
			else { $this->repair(); }
			return true;
		} finally {
			FooGallery_Usage_State::release_lease( 'send', $lease ); }
	}

	/**
	 * Send an exact manifest body.
	 *
	 * @param string $held_lease Existing send lease, or empty to acquire one.
	 * @return mixed
	 */
	public function send( $held_lease = '' ) {
		if ( ! $this->permitted() ) {
			return; }
		$lease = $held_lease ? $held_lease : FooGallery_Usage_State::acquire_lease( 'send', 120 );
		if ( ! $lease ) {
			return; }
		try {
			$state  = FooGallery_Usage_State::get();
			$outbox = is_array( $state['outbox'] ) ? $state['outbox'] : null;
			if ( ! $this->permitted() || ! $this->valid_outbox( $state, $outbox ) || ! FooGallery_Usage_State::owns_lease( 'send', $lease ) ) {
				return; }
			$payload = json_decode( $outbox['body'], true );
			if ( is_array( $payload ) && ( array_key_exists( 'media_audit', $payload ) || ! isset( $payload['catalog_version'] ) || FooGallery_Usage_Registry::CATALOG_VERSION !== $payload['catalog_version'] ) ) {
				// Discard obsolete packets intact; never rewrite bytes under an existing sequence.
				$this->expire_outbox( $outbox );
				return;
			}
			if ( time() - (int) $outbox['created'] > self::OUTBOX_MAX_AGE ) {
				$this->expire_outbox( $outbox );
				return; }
			if ( empty( $state['registered'] ) ) {
				$response = FooGallery_Usage_Client::response( FooGallery_Usage_Client::enroll( $state['site_id'], $state['token'] ) );
				if ( is_wp_error( $response ) ) {
					$this->retry( $outbox, array() );
					return; }
				if ( in_array( $response['code'], array( 401, 403, 410, 422 ), true ) ) {
					$this->fail( 'service_rejected', $outbox['epoch'] );
					return; }
				if ( ! in_array( $response['code'], array( 200, 201 ), true ) || ! isset( $response['data']['site_id'] ) || ! is_string( $response['data']['site_id'] ) || ! hash_equals( $state['site_id'], $response['data']['site_id'] ) ) {
					$this->retry( $outbox, $response );
					return; }
				FooGallery_Usage_State::mutate(
					function ( $current ) use ( $outbox, $state ) {
						if ( $this->valid_outbox( $current, $outbox ) && hash_equals( (string) $current['site_id'], (string) $state['site_id'] ) ) {
							$current['registered'] = true;
						} return $current;
					}
				);
				$state = FooGallery_Usage_State::get();
			}
			if ( ! $this->permitted() || ! $this->valid_outbox( $state, $outbox ) || ! FooGallery_Usage_State::owns_lease( 'send', $lease ) ) {
				return; }
			$response = FooGallery_Usage_Client::response( FooGallery_Usage_Client::send( $outbox['body'], $state['token'] ) );
			if ( is_wp_error( $response ) ) {
				$this->retry( $outbox, array() );
				return; }
			if ( $this->valid_receipt( $response, $state, $outbox ) ) {
				$this->acknowledge( $response['data'], $outbox );
				return; }
			if ( 409 === $response['code'] ) {
				$this->conflict( $state, $outbox );
				return; }
			if ( in_array( $response['code'], array( 401, 403, 410, 422 ), true ) ) {
				$this->fail( 'service_rejected', $outbox['epoch'] );
				return; }
			$this->retry( $outbox, $response );
		} finally {
			if ( ! $held_lease ) {
				FooGallery_Usage_State::release_lease( 'send', $lease ); }
		}
	}

	/**
	 * Verify packet identity, epoch, sequence, and hash.
	 *
	 * @param array|null $state Current local state, or null to read it.
	 * @param array|null $outbox Exact pending report and its delivery metadata.
	 * @return mixed
	 */
	private function valid_outbox( $state, $outbox ) {
		return $state['consent'] && is_array( $outbox ) && (int) $state['epoch'] === (int) $outbox['epoch'] && is_array( $state['outbox'] ) && (int) $state['outbox']['sequence'] === (int) $outbox['sequence'] && isset( $state['outbox']['body_sha256'], $outbox['body_sha256'] ) && is_string( $state['outbox']['body_sha256'] ) && is_string( $outbox['body_sha256'] ) && hash_equals( $state['outbox']['body_sha256'], $outbox['body_sha256'] );
	}

	/**
	 * Validate a structured authenticated receipt.
	 *
	 * @param array|WP_Error $response WordPress HTTP response or normalized service response.
	 * @param array|null     $state Current local state, or null to read it.
	 * @param array|null     $outbox Exact pending report and its delivery metadata.
	 * @return mixed
	 */
	private function valid_receipt( $response, $state, $outbox ) {
		$data = $response['data'];
		return 200 === $response['code'] && isset( $data['site_id'], $data['sequence'], $data['body_sha256'], $data['manifest_hash'], $data['received_at'], $data['accepted_user_agent'] ) && is_string( $data['site_id'] ) && is_int( $data['sequence'] ) && is_string( $data['body_sha256'] ) && is_string( $data['manifest_hash'] ) && is_string( $data['received_at'] ) && is_string( $data['accepted_user_agent'] ) && hash_equals( (string) $state['site_id'], $data['site_id'] ) && $data['sequence'] === (int) $outbox['sequence'] && preg_match( '/^[a-f0-9]{64}$/', $data['body_sha256'] ) && hash_equals( $outbox['body_sha256'], $data['body_sha256'] ) && preg_match( '/^[a-f0-9]{64}$/', $data['manifest_hash'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/', $data['received_at'] ) && false !== strtotime( $data['received_at'] ) && strlen( $data['accepted_user_agent'] ) <= 1024 && ! preg_match( '/[\x00-\x1F\x7F]/', $data['accepted_user_agent'] );
	}

	/**
	 * Commit an accepted exact packet and receipt.
	 *
	 * @param array      $data Allowlisted request or measurement data.
	 * @param array|null $outbox Exact pending report and its delivery metadata.
	 * @return mixed
	 */
	private function acknowledge( $data, $outbox ) {
		$ack            = $outbox;
		$ack['receipt'] = array_intersect_key( $data, array_flip( array( 'site_id', 'sequence', 'body_sha256', 'manifest_hash', 'received_at', 'accepted_user_agent' ) ) );
		$next           = as_next_scheduled_action( 'foogallery_usage_weekly_action', array(), 'foogallery-improve' );
		$next           = is_int( $next ) && $next > time() ? $next : time() + WEEK_IN_SECONDS;
		FooGallery_Usage_State::mutate(
			function ( $state ) use ( $ack, $outbox, $next ) {
				if ( ! $this->valid_outbox( $state, $outbox ) ) {
					return $state;
				} $state['outbox']        = null;
				$state['acknowledged']    = $ack;
				$state['last_error']      = '';
				$state['next_attempt']    = 0;
				$state['next_collection'] = $next;
				$state['phase']           = 'idle';
				return $state;
			}
		);
	}

	/**
	 * Discard a stale packet and schedule recollection.
	 *
	 * @param array|null $outbox Exact pending report and its delivery metadata.
	 * @return mixed
	 */
	private function expire_outbox( $outbox ) {
		FooGallery_Usage_State::mutate(
			function ( $state ) use ( $outbox ) {
				if ( $this->valid_outbox( $state, $outbox ) ) {
					$state['outbox'] = null;
				} return $state;
			}
		);
		$this->schedule_collection( time() );
	}

	/**
	 * Persist retry state and bounded jittered backoff.
	 *
	 * @param array|null     $outbox Exact pending report and its delivery metadata.
	 * @param array|WP_Error $response WordPress HTTP response or normalized service response.
	 * @return mixed
	 */
	private function retry( $outbox, $response ) {
		$attempts = isset( $outbox['attempts'] ) ? (int) $outbox['attempts'] + 1 : 1;
		$delays   = array( 900, 3600, 21600, DAY_IN_SECONDS );
		$delay    = isset( $delays[ $attempts - 1 ] ) ? $delays[ $attempts - 1 ] : DAY_IN_SECONDS;
		if ( ! empty( $response['retry_after'] ) ) {
			$delay = min( DAY_IN_SECONDS, max( $delay, (int) $response['retry_after'] ) ); }
		$delay   = min( DAY_IN_SECONDS, $delay + wp_rand( 0, max( 1, (int) ( $delay / 10 ) ) ) );
		$when    = time() + $delay;
		$updated = FooGallery_Usage_State::mutate(
			function ( $state ) use ( $attempts, $outbox, $when ) {
				if ( ! $this->valid_outbox( $state, $outbox ) ) {
					return $state;
				} $state['outbox']['attempts'] = $attempts;
				$state['next_attempt']         = $when;
				$state['last_error']           = 'retrying';
				return $state;
			}
		);
		if ( ! is_wp_error( $updated ) && $this->valid_outbox( $updated, $outbox ) ) {
			FooGallery_Jobs::cancel( 'foogallery_usage_send_action' );
			FooGallery_Jobs::schedule( 'foogallery_usage_send_action', array( (int) $outbox['epoch'], (int) $outbox['sequence'], wp_generate_uuid4() ), 'foogallery-improve', $when ); }
	}

	/**
	 * Reconcile an authenticated remote sequence conflict.
	 *
	 * @param array|null $state Current local state, or null to read it.
	 * @param array|null $outbox Exact pending report and its delivery metadata.
	 * @return mixed
	 */
	private function conflict( $state, $outbox ) {
		$status = FooGallery_Usage_Client::response( FooGallery_Usage_Client::status( $state['site_id'], $state['token'] ) );
		if ( is_wp_error( $status ) || 200 !== $status['code'] || ! isset( $status['data']['site_id'], $status['data']['sequence'], $status['data']['body_sha256'] ) || ! is_string( $status['data']['site_id'] ) || ! hash_equals( (string) $state['site_id'], $status['data']['site_id'] ) || ! is_int( $status['data']['sequence'] ) || $status['data']['sequence'] < (int) $outbox['sequence'] || ! is_string( $status['data']['body_sha256'] ) || ! preg_match( '/^[a-f0-9]{64}$/', $status['data']['body_sha256'] ) ) {
			$this->fail( 'sequence_conflict', $outbox['epoch'] );
			return; }
		$remote = $status['data']['sequence'];
		FooGallery_Usage_State::mutate(
			function ( $current ) use ( $outbox, $remote ) {
				if ( $this->valid_outbox( $current, $outbox ) ) {
					$current['outbox']   = null;
					$current['sequence'] = max( (int) $current['sequence'], $remote );
				} return $current;
			}
		);
		delete_option( FooGallery_Usage_State::SCAN );
		$this->schedule_collection( time() );
	}

	/**
	 * Update phase only for the expected consent epoch.
	 *
	 * @param string $phase Reporting lifecycle phase.
	 * @param int    $epoch Consent epoch that owns this work.
	 * @return void
	 */
	private function set_phase( $phase, $epoch ) {
		FooGallery_Usage_State::mutate(
			function ( $state ) use ( $epoch, $phase ) {
				if ( $state['consent'] && (int) $state['epoch'] === (int) $epoch ) {
					$state['phase'] = $phase;
				} return $state;
			}
		); }
	/**
	 * Block automated delivery with a stable error code.
	 *
	 * @param string $code Stable local failure code.
	 * @param int    $epoch Consent epoch that owns this work.
	 * @return void
	 */
	private function fail( $code, $epoch ) {
		FooGallery_Jobs::cancel( 'foogallery_usage_send_action' );
		FooGallery_Usage_State::mutate(
			function ( $state ) use ( $code, $epoch ) {
				if ( (int) $state['epoch'] === (int) $epoch ) {
					$state['last_error'] = $code;
					$state['phase']      = 'blocked';
				} return $state;
			}
		); }
}
