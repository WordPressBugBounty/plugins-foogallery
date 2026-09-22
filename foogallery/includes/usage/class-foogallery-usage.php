<?php
/**
 * FooGallery Improve lifecycle facade.
 *
 * @package FooGallery
 */

/** FooGallery Improve lifecycle and safe UI facade. */
class FooGallery_Usage {
	/**
	 * Shared facade instance.
	 *
	 * @var FooGallery_Usage|null
	 */
	private static $instance;
	/**
	 * Scheduler service.
	 *
	 * @var FooGallery_Usage_Scheduler
	 */
	private $scheduler;
	/**
	 * Return the shared Improve facade instance.
	 *
	 * @return mixed
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		} return self::$instance; }
	/**
	 * Register the Improve subsystem.
	 *
	 * @return void
	 */
	public static function boot() {
		self::instance()->register(); }
	/**
	 * Handle   construct.
	 *
	 * @return mixed
	 */
	public function __construct() {
		$this->scheduler = new FooGallery_Usage_Scheduler(); }
	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public function register() {
		static $registered = false;
		if ( $registered ) {
			return;
		} $registered = true;
		$this->scheduler->register();
		$this->scheduler->ensure_weekly();
		if ( ! $this->consented() && ( FooGallery_Jobs::pending( 'foogallery_usage_weekly_action' ) || FooGallery_Jobs::pending( 'foogallery_usage_collect_action' ) || FooGallery_Jobs::pending( 'foogallery_usage_send_action' ) ) ) {
			FooGallery_Usage_State::mutate(
				function ( $s ) {
					if ( ! $s['delete_pending'] ) {
						++$s['epoch'];
					} return $s;
				}
			);
			FooGallery_Usage_State::clear_reporting_jobs();
		}
		add_action( 'foogallery_settings_reset', array( $this, 'dirty' ) );
		add_action( 'add_option_foogallery', array( $this, 'dirty' ) );
		add_action( 'update_option_foogallery', array( $this, 'dirty' ) );
		add_action( 'foogallery_extension_activated', array( $this, 'dirty' ) );
		add_action( 'foogallery_extension_deactivated', array( $this, 'dirty' ) );
		FooGallery_Usage_Actions::register();
		add_action( 'foogallery_bulk_copy_succeeded', array( $this, 'observe_bulk_copy' ) );
		if ( $this->consented() ) {
			$state = FooGallery_Usage_State::mutate(
				function ( $s ) {
					if ( empty( $s['bulk_copy_observed_from'] ) ) {
						$s['bulk_copy_observed_from']    = time();
						$s['bulk_copy_last_success_day'] = '';
					} return FooGallery_Usage_Actions::initialize( $s );
				}
			);
			if ( ! is_wp_error( $state ) ) {
				$this->scheduler->repair(); }
		}
	}
	/**
	 * Check whether reporting is allowed.
	 *
	 * @return mixed
	 */
	public function available() {
		if ( defined( 'FOOGALLERY_USAGE_DISABLED' ) && FOOGALLERY_USAGE_DISABLED ) {
			return false; }
		return '' === $this->availability_gate();
	}
	/**
	 * Return the bounded WordPress environment label for reporting and disclosure.
	 *
	 * @return string
	 */
	public static function environment_type() {
		$environment          = 'production';
		$environment_callback = 'wp_get_environment_type';
		if ( is_callable( $environment_callback ) ) {
			$environment = call_user_func( $environment_callback );
		} elseif ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			$environment = WP_ENVIRONMENT_TYPE;
		} elseif ( defined( 'WP_ENV' ) ) {
			$environment = WP_ENV;
		}
		$environment = strtolower( (string) $environment );
		return in_array( $environment, array( 'production', 'staging', 'development', 'local' ), true ) ? $environment : 'production';
	}
	/** Return a stable reason when reporting must remain off. */
	private function availability_gate() {
		if ( defined( 'FOOGALLERY_USAGE_DISABLED' ) && FOOGALLERY_USAGE_DISABLED ) {
			return 'disabled'; }
		if ( ! (bool) apply_filters( 'foogallery_usage_environment_allowed', true ) ) {
			return 'environment'; }
		if ( ! (bool) apply_filters( 'foogallery_usage_available', true ) ) {
			return 'policy'; }
		return '';
	}
	/**
	 * Check whether stored identity belongs to another home URL.
	 *
	 * @param array|null $state Current local state, or null to read it.
	 * @return mixed
	 */
	private function clone_detected( $state = null ) {
		$state = is_array( $state ) ? $state : FooGallery_Usage_State::get();
		return ! empty( $state['home_url'] ) && ! hash_equals( untrailingslashit( (string) $state['home_url'] ), untrailingslashit( (string) home_url() ) );
	}
	/**
	 * Check current versioned consent and policy gates.
	 *
	 * @return mixed
	 */
	public function consented() {
		$s = FooGallery_Usage_State::get();
		return $this->available() && ! $this->clone_detected( $s ) && ! empty( $s['consent'] ) && 2 === (int) $s['consent_version']; }
	/**
	 * Increment the collection generation.
	 *
	 * @return void
	 */
	public function dirty() {
		FooGallery_Usage_State::mutate(
			function ( $s ) {
				$s['generation'] = (int) $s['generation'] + 1;
				return $s;
			}
		); }
	/**
	 * Record a privacy-safe Bulk Copy success day.
	 *
	 * @return void
	 */
	public function observe_bulk_copy() {
		FooGallery_Usage_Actions::observe( 'bulk_copy_30d' );
	}

	/**
	 * Execute an authorized Improve UI operation.
	 *
	 * @param string $operation Reviewed administrator operation.
	 * @return mixed
	 */
	public function handle( $operation ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to manage Improve settings.', 'foogallery' ) ); }
		if ( ! is_string( $operation ) ) {
			return new WP_Error( 'invalid_operation', __( 'Unsupported Improve operation.', 'foogallery' ) ); }
		$operation = sanitize_key( $operation );
		if ( 'status' === $operation ) {
			return $this->status(); }
		if ( 'last_report' === $operation ) {
			$state = FooGallery_Usage_State::get();
			if ( empty( $state['acknowledged']['body'] ) || ! is_string( $state['acknowledged']['body'] ) ) {
				return new WP_Error( 'missing_report', __( 'No successfully delivered report is available.', 'foogallery' ) );
			}
			$receipt = isset( $state['acknowledged']['receipt'] ) && is_array( $state['acknowledged']['receipt'] ) ? $state['acknowledged']['receipt'] : array();
			return array(
				'last_report_body'    => $state['acknowledged']['body'],
				'last_report_receipt' => array_intersect_key( $receipt, array_flip( array( 'site_id', 'sequence', 'body_sha256', 'manifest_hash', 'received_at', 'accepted_user_agent' ) ) ),
			);
		}
		if ( 'dismiss' === $operation ) {
			FooGallery_Usage_State::mutate(
				function ( $s ) {
						$s['dismissed'] = true;
						return $s;
				}
			);
			return $this->status(); }
		if ( 'enable' === $operation ) {
			$before = FooGallery_Usage_State::get();
			if ( ! empty( $before['delete_pending'] ) ) {
				return new WP_Error( 'deletion_pending', __( 'Deletion must finish before sharing can be enabled again.', 'foogallery' ) );
			} if ( ! $this->available() ) {
				return new WP_Error( 'unavailable', __( 'Improve is unavailable in this environment.', 'foogallery' ) );
			} if ( $this->clone_detected() ) {
				return new WP_Error( 'clone_choice_required', __( 'Choose whether this is a migrated site or an independent copy.', 'foogallery' ) );
			} if ( ! empty( $before['consent'] ) && 2 === (int) $before['consent_version'] ) {
				$this->scheduler->repair();
				return $this->status();
			} $updated = FooGallery_Usage_State::mutate(
				function ( $s ) {
						$s['consent']                    = true;
						$s['consent_version']            = 2;
						$s['dismissed']                  = true;
						$s['home_url']                   = untrailingslashit( home_url() );
						$s['epoch']                      = (int) $s['epoch'] + 1;
						$s['phase']                      = 'collecting';
						$s['bulk_copy_observed_from']    = time();
						$s['bulk_copy_last_success_day'] = '';
						$s['action_observations'] = array();
						$s = FooGallery_Usage_Actions::initialize( $s );
						return $s;
				}
			);
			if ( is_wp_error( $updated ) ) {
				return $updated;
			} $this->scheduler->schedule_collection( time() );
			return $this->status(); }
		if ( 'pause' === $operation ) {
			$updated = FooGallery_Usage_State::mutate(
				function ( $s ) {
						$s['consent']                    = false;
						$s['dismissed']                  = true;
						$s['acknowledged']               = null;
						$s['action_observations'] = array();
						$s['bulk_copy_observed_from']    = 0;
						$s['bulk_copy_last_success_day'] = '';
					if ( empty( $s['delete_pending'] ) ) {
						$s['epoch'] = (int) $s['epoch'] + 1;
						$s['phase'] = 'paused';
					} return $s;
				}
			);
			FooGallery_Usage_State::clear_reporting_jobs();
			FooGallery_Usage_State::clear_previews();
			return is_wp_error( $updated ) ? $updated : $this->status(); }
		if ( 'send' === $operation ) {
			$send_state = FooGallery_Usage_State::get();
			if ( get_option( FooGallery_Usage_State::SCAN ) || FooGallery_Jobs::pending( 'foogallery_usage_collect_action' ) || ! empty( $send_state['outbox'] ) ) {
				return $this->status(); }
			if ( 'blocked' === $send_state['phase'] ) {
				return new WP_Error( 'reporting_blocked', __( 'Resolve the reporting error before sending again.', 'foogallery' ) );
			} if ( ! $this->consented() ) {
				return new WP_Error( 'consent_required', __( 'Consent is required.', 'foogallery' ) );
			}
			$updated = FooGallery_Usage_State::mutate(
				function ( $state ) use ( $send_state ) {
					if ( ! $state['consent'] || (int) $state['epoch'] !== (int) $send_state['epoch'] ) {
						return null;
					}
					$state['epoch']      = (int) $state['epoch'] + 1;
					$state['phase']      = 'collecting';
					$state['last_error'] = '';
					$state['outbox']     = null;
					return $state;
				}
			);
			if ( is_wp_error( $updated ) ) {
				return $updated; }
			FooGallery_Usage_State::clear_reporting_jobs();
			$this->scheduler->schedule_collection( time() );
			return $this->status(); }
		if ( 'delete' === $operation ) {
			return $this->delete_remote(); }
		if ( 'preview_start' === $operation || 'preview_step' === $operation ) {
			return $this->preview( 'preview_start' === $operation ); }
		if ( 'reveal' === $operation ) {
			$s = FooGallery_Usage_State::get();
			if ( empty( $s['token'] ) ) {
				return new WP_Error( 'no_identity', __( 'No reporting identity exists.', 'foogallery' ) );
			} return array(
				'authorization' => 'Bearer ' . $s['token'],
				'site_id'       => $s['site_id'],
			); }
		if ( 'migrate' === $operation ) {
			if ( ! $this->clone_detected() ) {
				return $this->status();
			} $updated = FooGallery_Usage_State::mutate(
				function ( $s ) {
						$s['home_url'] = untrailingslashit( home_url() );
						$s['epoch']    = (int) $s['epoch'] + 1;
						$s['phase']    = ! empty( $s['delete_pending'] ) ? 'deletion_pending' : ( ! empty( $s['consent'] ) ? 'collecting' : 'paused' );
						return $s;
				}
			);
			if ( is_wp_error( $updated ) ) {
				return $updated;
			} if ( ! empty( $updated['delete_pending'] ) ) {
				FooGallery_Jobs::cancel( 'foogallery_usage_delete_action' );
				FooGallery_Jobs::schedule( 'foogallery_usage_delete_action', array( (int) FooGallery_Usage_State::get()['epoch'], wp_generate_uuid4() ), 'foogallery-improve', time() );
			} elseif ( ! empty( $updated['consent'] ) ) {
				$this->scheduler->schedule_collection( time() );
			} return $this->status(); }
		if ( 'independent' === $operation ) {
			if ( ! $this->clone_detected() ) {
				return $this->status();
			} FooGallery_Usage_State::clear_reporting_jobs();
			delete_option( 'foogallery_usage_bulk_copy_until' );
			$updated = FooGallery_Usage_State::mutate(
				function ( $s ) {
						$dismissed      = ! empty( $s['dismissed'] );
						$epoch          = (int) $s['epoch'] + 1;
						$s              = FooGallery_Usage_State::defaults();
						$s['dismissed'] = $dismissed;
						$s['epoch']     = $epoch;
						$s['phase']     = 'paused';
						return $s;
				}
			);
			return is_wp_error( $updated ) ? $updated : $this->status(); }
		return new WP_Error( 'invalid_operation', __( 'Unsupported Improve operation.', 'foogallery' ) );
	}
	/**
	 * Advance a user-scoped local preview.
	 *
	 * @param bool $start Whether to start a fresh local preview.
	 * @return mixed
	 */
	private function preview( $start ) {
		$key  = 'foogallery_usage_preview_' . get_current_user_id();
		$scan = $start ? FooGallery_Usage_Collector::start() : get_transient( $key );
		if ( ! is_array( $scan ) ) {
			return new WP_Error( 'preview_missing', __( 'Start a preview first.', 'foogallery' ) );
		} $generation = (int) FooGallery_Usage_State::get()['generation'];
		if ( $start ) {
			$scan['usage_generation']     = $generation;
			$scan['usage_invalidations']  = 0;
		} elseif ( ! isset( $scan['usage_generation'] ) || (int) $scan['usage_generation'] !== $generation ) {
			$invalidations               = isset( $scan['usage_invalidations'] ) ? (int) $scan['usage_invalidations'] + 1 : 1;
			$scan                        = FooGallery_Usage_Collector::start();
			$scan['usage_generation']    = $generation;
			$scan['usage_invalidations'] = $invalidations;
			if ( $invalidations >= 3 ) {
				set_transient( $key, $scan, 30 * MINUTE_IN_SECONDS );
				return new WP_Error( 'preview_changed', __( 'Gallery settings changed repeatedly. Start the preview again when changes are complete.', 'foogallery' ) ); }
		}
		$scan = FooGallery_Usage_Collector::step( $scan );
		if ( is_wp_error( $scan ) ) {
			return $scan;
		} $after_generation = (int) FooGallery_Usage_State::get()['generation'];
		if ( $after_generation !== $generation ) {
			$invalidations               = isset( $scan['usage_invalidations'] ) ? (int) $scan['usage_invalidations'] + 1 : 1;
			$scan                        = FooGallery_Usage_Collector::start();
			$scan['usage_generation']    = $after_generation;
			$scan['usage_invalidations'] = $invalidations;
			set_transient( $key, $scan, 30 * MINUTE_IN_SECONDS );
			if ( $invalidations >= 3 ) {
				return new WP_Error( 'preview_changed', __( 'Gallery settings changed repeatedly. Start the preview again when changes are complete.', 'foogallery' ) );
			}
		} else {
			set_transient( $key, $scan, 30 * MINUTE_IN_SECONDS );
		} $result                      = $this->status();
		$result['preview_in_progress'] = empty( $scan['complete'] );
		$result['preview_complete']    = ! empty( $scan['complete'] );
		if ( ! empty( $scan['complete'] ) ) {
			FooGallery_Usage_Actions::overlay( $scan['data'], FooGallery_Usage_State::get() );
			$body                   = FooGallery_Usage_Manifest::preview( $scan['data'] );
			$result['preview_body'] = is_wp_error( $body ) ? '' : $body;
		} return $result;
	}
	/**
	 * Delete the authenticated remote registration.
	 *
	 * @return mixed
	 */
	private function delete_remote() {
		$s = FooGallery_Usage_State::get();
		if ( $this->clone_detected( $s ) ) {
			return new WP_Error( 'clone_choice_required', __( 'Choose whether this is a migrated site or an independent copy.', 'foogallery' ) );
		} $deleting = FooGallery_Usage_State::mutate(
			function ( $state ) {
				$state['consent']        = false;
				$state['dismissed']      = true;
				$state['epoch']          = (int) $state['epoch'] + 1;
				$state['phase']          = 'deleting';
				$state['delete_pending'] = true;
				$state['acknowledged']   = null;
				$state['last_error']     = '';
				return $state;
			}
		);
		FooGallery_Usage_State::clear_reporting_jobs();
		FooGallery_Usage_State::clear_previews();
		if ( is_wp_error( $deleting ) ) {
			return $deleting;
		} $delete_epoch = (int) $deleting['epoch'];
		if ( empty( $s['site_id'] ) || empty( $s['token'] ) ) {
			FooGallery_Usage_State::erase();
			FooGallery_Usage_State::mutate(
				function ( $state ) {
						$state['dismissed'] = true;
						$state['phase']     = 'deleted';
						return $state;
				}
			);
			return $this->status(); }
		$response = FooGallery_Usage_Client::response( FooGallery_Usage_Client::delete( $s['site_id'], $s['token'] ) );
		if ( ! is_wp_error( $response ) && in_array( $response['code'], array( 200, 204, 410 ), true ) ) {
			self::finish_delete( $delete_epoch );
			return $this->status(); }
		$failed = FooGallery_Usage_State::mutate(
			function ( $state ) use ( $delete_epoch ) {
				if ( ! empty( $state['delete_pending'] ) && (int) $state['epoch'] === $delete_epoch ) {
					$state['last_error'] = 'deletion_failed';
					$state['phase']      = 'deletion_pending';
				} return $state;
			}
		);
		if ( ! is_wp_error( $failed ) && ! empty( $failed['delete_pending'] ) && (int) $failed['epoch'] === $delete_epoch ) {
			FooGallery_Jobs::cancel( 'foogallery_usage_delete_action' );
			FooGallery_Jobs::schedule( 'foogallery_usage_delete_action', array( (int) FooGallery_Usage_State::get()['epoch'], wp_generate_uuid4() ), 'foogallery-improve', time() + HOUR_IN_SECONDS );
		} return $this->status();
	}
	/** A deletion retry is the sole network operation permitted after opt-out. */
	public static function retry_delete( $expected_epoch = null ) {
		if ( null !== $expected_epoch && (int) $expected_epoch !== (int) FooGallery_Usage_State::get()['epoch'] ) {
			return; }
		$s = FooGallery_Usage_State::get();
		if ( empty( $s['delete_pending'] ) || empty( $s['site_id'] ) || empty( $s['token'] ) ) {
			return;
		} if ( self::instance()->clone_detected( $s ) ) {
			FooGallery_Jobs::cancel( 'foogallery_usage_delete_action' );
			return;
		} $epoch  = (int) $s['epoch'];
		$response = FooGallery_Usage_Client::response( FooGallery_Usage_Client::delete( $s['site_id'], $s['token'] ) );
		if ( ! is_wp_error( $response ) && in_array( $response['code'], array( 200, 204, 410 ), true ) ) {
			self::finish_delete( $epoch );
			return;
		} $current = FooGallery_Usage_State::get();
		if ( ! empty( $current['delete_pending'] ) && (int) $current['epoch'] === $epoch ) {
			FooGallery_Jobs::cancel( 'foogallery_usage_delete_action' );
			FooGallery_Jobs::schedule( 'foogallery_usage_delete_action', array( (int) FooGallery_Usage_State::get()['epoch'], wp_generate_uuid4() ), 'foogallery-improve', time() + DAY_IN_SECONDS ); } }
	/**
	 * Commit a successful deletion locally.
	 *
	 * @param int $epoch Consent epoch that owns this work.
	 * @return void
	 */
	private static function finish_delete( $epoch ) {
		$updated = FooGallery_Usage_State::mutate(
			function ( $state ) use ( $epoch ) {
				if ( empty( $state['delete_pending'] ) || (int) $state['epoch'] !== (int) $epoch ) {
					return $state;
				} $state            = FooGallery_Usage_State::defaults();
				$state['dismissed'] = true;
				$state['epoch']     = (int) $epoch + 1;
				$state['phase']     = 'deleted';
				return $state;
			}
		);
		if ( ! is_wp_error( $updated ) && 'deleted' === $updated['phase'] ) {
			FooGallery_Jobs::cancel( 'foogallery_usage_delete_action' );
			delete_option( 'foogallery_usage_bulk_copy_until' ); } }
	/**
	 * Return the capability-safe Improve status payload.
	 *
	 * @param bool $include_acknowledged_body Whether to include the stored acknowledged packet for a download.
	 * @return mixed
	 */
	public function status( $include_acknowledged_body = false ) {
		$s           = FooGallery_Usage_State::get();
		$outbox      = FooGallery_Usage_State::outbox();
		$safe_outbox = is_array( $outbox ) ? array(
			'body'        => isset( $outbox['body'] ) ? $outbox['body'] : '',
			'body_sha256' => isset( $outbox['body_sha256'] ) ? $outbox['body_sha256'] : '',
			'sequence'    => isset( $outbox['sequence'] ) ? (int) $outbox['sequence'] : 0,
		) : null;
		$ack         = null;
		if ( ! empty( $s['acknowledged'] ) && is_array( $s['acknowledged'] ) ) {
			if ( $include_acknowledged_body ) {
				$ack = array_intersect_key( $s['acknowledged'], array_flip( array( 'body', 'body_sha256', 'sequence', 'receipt' ) ) );
			} else {
				$receipt = isset( $s['acknowledged']['receipt'] ) && is_array( $s['acknowledged']['receipt'] ) ? $s['acknowledged']['receipt'] : array();
				$ack     = array( 'receipt' => array_intersect_key( $receipt, array_flip( array( 'received_at' ) ) ) );
			}
		}
		$preview_body        = '';
		$preview_complete    = false;
		$preview_in_progress = false;
		$preview             = get_transient( 'foogallery_usage_preview_' . get_current_user_id() );
		if ( is_array( $preview ) ) {
			$preview_complete    = ! empty( $preview['complete'] );
			$preview_in_progress = ! $preview_complete;
			if ( $preview_complete && isset( $preview['data'] ) ) {
				FooGallery_Usage_Actions::overlay( $preview['data'], $s );
				$built_preview = FooGallery_Usage_Manifest::preview( $preview['data'] );
				if ( is_string( $built_preview ) ) {
					$preview_body = $built_preview; }
			}
		}
			$gate = $this->availability_gate();
		if ( '' === $gate && ! empty( $s['consent'] ) && 2 !== (int) $s['consent_version'] ) {
			$gate = '';
		} if ( '' === $gate && $this->clone_detected( $s ) ) {
			$gate = 'clone'; }
			$endpoint = FooGallery_Usage_Client::endpoint();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Apply WordPress's own HTTP filter to preview its actual default header.
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(), $endpoint );
			return array(
				'consent'             => (bool) $s['consent'] && 2 === (int) $s['consent_version'],
				'dismissed'           => (bool) $s['dismissed'],
				'available'           => '' === $gate,
				'gate'                => $gate,
				'environment_type'    => self::environment_type(),
				'site_id'             => (string) $s['site_id'],
				'phase'               => (string) $s['phase'],
				'next_collection'     => (int) $s['next_collection'],
				'next_attempt'        => (int) $s['next_attempt'],
				'last_error'          => (string) $s['last_error'],
				'outbox'              => $safe_outbox,
				'acknowledged'        => $ack,
				'preview_body'        => $preview_body,
				'preview_complete'    => $preview_complete,
				'preview_in_progress' => $preview_in_progress,
				'endpoint'            => $endpoint,
				'expected_user_agent' => $user_agent,
				'authorization'       => 'Bearer [redacted]',
				'catalog'             => class_exists( 'FooGallery_Usage_Registry' ) ? FooGallery_Usage_Registry::catalog() : array(),
			);
	}
	/**
	 * Cancel reporting jobs while retaining consent and identity.
	 *
	 * @param bool $network_wide Whether WordPress is deactivating the plugin network-wide.
	 * @return void
	 */
	public static function deactivate( $network_wide = false ) {
		if ( $network_wide && is_multisite() ) {
			$offset = 0;
			do {
				$sites = get_sites(
					array(
						'number' => 100,
						'offset' => $offset,
						'fields' => 'ids',
					)
				);
				foreach ( $sites as $site_id ) {
							switch_to_blog( $site_id );
							self::deactivate_site();
							restore_current_blog();
				} $site_count = count( $sites );
				$offset      += $site_count;
			} while ( 100 === $site_count );
			return;
		} self::deactivate_site(); }
	/**
	 * Deactivate Improve for the current site.
	 *
	 * @return void
	 */
	private static function deactivate_site() {
		FooGallery_Usage_State::mutate(
			function ( $s ) {
				if ( empty( $s['delete_pending'] ) ) { $s['epoch'] = (int) $s['epoch'] + 1; }
				$s['action_observations'] = array();
				$s['bulk_copy_observed_from']    = 0;
				$s['bulk_copy_last_success_day'] = '';
				return $s;
			}
		);
		FooGallery_Usage_State::clear_reporting_jobs( true ); }
	/**
	 * Called from uninstall only after the active edition guard has been evaluated by bootstrap.
	 *
	 * @param bool $force Whether the caller already verified the active-edition guard.
	 */
	public static function uninstall( $force = false ) {
		if ( $force || ! self::other_edition_active() ) {
			FooGallery_Usage_State::erase(); } }
	/**
	 * Check whether another FooGallery edition is active.
	 *
	 * @return mixed
	 */
	public static function other_edition_active() {
		$self    = defined( 'FOOGALLERY_FILE' ) ? plugin_basename( FOOGALLERY_FILE ) : '';
		$plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		} foreach ( $plugins as $plugin ) {
			if ( $plugin !== $self && preg_match( '#(^|/)[^/]+/foogallery\.php$#i', $plugin ) ) {
				return true;
			}
		} return false; }
}
