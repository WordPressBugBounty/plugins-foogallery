<?php
/**
 * Consent-gated, coarse observations of successful administrative tools.
 *
 * @package FooGallery
 */

/** Only allowlisted tool states leave this site's local observation store. */
class FooGallery_Usage_Actions {
	/** Register no-content success signals and the migration persistence adapter. */
	public static function register() {
		foreach ( array( 'import', 'export', 'media_audit' ) as $tool ) {
			add_action(
				'foogallery_' . $tool . '_succeeded',
				function () use ( $tool ) {
					self::observe( $tool . '_30d' );
				}
			);
		}
		add_action( 'update_option_foogallery-migrate-data', array( __CLASS__, 'migration_saved' ), 10, 2 );
		add_action(
			'add_option_foogallery-migrate-data',
			function ( $option, $value ) {
				self::migration_saved( array(), $value );
			},
			10,
			2
		);
	}

	/**
	 * Start coverage for newly introduced tools without backfilling historical use.
	 *
	 * @param array $state Locked usage state.
	 * @return array
	 */
	public static function initialize( $state ) {
		foreach ( FooGallery_Usage_Registry::catalog()['actions'] as $id => $definition ) {
			if ( 'bulk_copy_30d' !== $id && empty( $state['action_observations'][ $id ] ) ) {
				$state['action_observations'][ $id ] = array(
					'observed_from'    => time(),
					'last_success_day' => '',
				);
			}
		}
		return $state;
	}

	/**
	 * Remember a UTC day only, after checking the current consent epoch.
	 *
	 * @param string $id Allowlisted action ID.
	 */
	public static function observe( $id ) {
		if ( ! FooGallery_Usage_Registry::has( 'actions', $id ) || ! FooGallery_Usage::instance()->consented() ) {
			return;
		}
		$epoch = (int) FooGallery_Usage_State::get()['epoch'];
		FooGallery_Usage_State::mutate(
			function ( $s ) use ( $epoch, $id ) {
				if ( ! $s['consent'] || 2 !== (int) $s['consent_version'] || (int) $s['epoch'] !== $epoch ) {
					return $s;
				}
				if ( 'bulk_copy_30d' === $id ) {
					if ( empty( $s['bulk_copy_observed_from'] ) ) {
						$s['bulk_copy_observed_from'] = time();
					}
					$s['bulk_copy_last_success_day'] = gmdate( 'Y-m-d' );
				} else {
					$s = self::initialize( $s );
					$s['action_observations'][ $id ]['last_success_day'] = gmdate( 'Y-m-d' );
				}
				return $s;
			}
		);
		// Overlay at preview/publication time; tool use does not invalidate Improve collection scans.
	}

	/**
	 * Apply the same recent-use states to local previews and outgoing reports.
	 *
	 * @param array $data Allowlisted collection data, by reference.
	 * @param array $state Current local usage state.
	 */
	public static function overlay( &$data, $state ) {
		$cutoff = time() - 30 * DAY_IN_SECONDS;
		foreach ( $data['actions'] as $id => $value ) {
			if ( 'unavailable' === $value ) {
				continue;
			}
			$observation = 'bulk_copy_30d' === $id
				? array(
					'observed_from'    => $state['bulk_copy_observed_from'],
					'last_success_day' => $state['bulk_copy_last_success_day'],
				)
				: ( isset( $state['action_observations'][ $id ] ) ? $state['action_observations'][ $id ] : array() );
			$value       = 'unknown';
			if ( ! empty( $state['consent'] ) && 2 === (int) $state['consent_version'] ) {
				$day = isset( $observation['last_success_day'] ) ? $observation['last_success_day'] : '';
				if ( 'media_audit_30d' === $id ) {
					$audit_day = self::media_audit_success_day();
					if ( $audit_day > $day ) {
						$day = $audit_day;
					}
				}
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) && $day >= gmdate( 'Y-m-d', $cutoff ) && $day <= gmdate( 'Y-m-d' ) ) {
					$value = 'enabled';
				} elseif ( ! empty( $observation['observed_from'] ) && $observation['observed_from'] <= $cutoff ) {
					$value = 'disabled';
				}
			}
			$data['actions'][ $id ] = $value;
		}
	}

	/**
	 * Return the UTC day of the latest saved successful site audit.
	 *
	 * Audit completion is durable local evidence and is not tied to an Improve
	 * consent epoch. Only the coarse day is projected into a usage report.
	 *
	 * @return string
	 */
	private static function media_audit_success_day() {
		$report = get_option( 'foogallery_media_audit_report', array() );
		if ( ! is_array( $report ) || 'site' !== ( isset( $report['scope'] ) ? $report['scope'] : '' ) || ! isset( $report['finished'], $report['state'], $report['limitations'] ) || ! is_string( $report['finished'] ) || ! is_array( $report['limitations'] ) || ! in_array( $report['state'], array( 'complete', 'incomplete' ), true ) ) {
			return '';
		}
		if ( array_intersect( array( 'audit_failed', 'storage_failed', 'execution_budget', 'elapsed_budget', 'image_cap' ), $report['limitations'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $report['finished'] ) ) {
			return '';
		}
		$finished = strtotime( $report['finished'] );
		return false === $finished ? '' : gmdate( 'Y-m-d', $finished );
	}

	/**
	 * Observe a newly completed gallery/album saved by an actual migration request.
	 * Status edits, discovery, refreshes and historical records are not tool use.
	 *
	 * @param mixed $old Previous migration settings.
	 * @param mixed $value Persisted migration settings.
	 */
	public static function migration_saved( $old, $value ) {
		if ( ! FooGallery_Usage::instance()->consented() ) {
			return;
		}
		$running = false;
		foreach ( array( 'wp_ajax_foogallery_migrate', 'wp_ajax_foogallery_migrate_continue', 'wp_ajax_foogallery_migrate_retry_gallery', 'wp_ajax_foogallery_album_migrate', 'wp_ajax_foogallery_album_migrate_continue', 'wp_ajax_foogallery_content_replace', 'admin_post_foogallery_migrate_content' ) as $hook ) {
			$running = $running || doing_action( $hook );
		}
		if ( ! $running || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$before = self::migration_records( $old );
		foreach ( self::migration_records( $value ) as $key => $record ) {
			if ( self::migration_completed( $key, $record ) && ( ! isset( $before[ $key ] ) || ! self::migration_completed( $key, $before[ $key ] ) ) ) {
				self::observe( 'migration_30d' );
				return;
			}
		}
	}

	/**
	 * Read only known persisted formats; never hydrate migration objects.
	 *
	 * @param mixed $settings Local migration settings supplied by WordPress.
	 * @return array
	 */
	private static function migration_records( $settings ) {
		$records = isset( $settings['migrated'] ) && is_array( $settings['migrated'] ) ? $settings['migrated'] : array();
		if ( isset( $records['_foogallery_migrate_compact'] ) ) {
			return 1 === $records['_foogallery_migrate_compact'] && isset( $records['items'] ) && is_array( $records['items'] ) ? $records['items'] : array();
		}
		return $records;
	}

	/**
	 * Inspect only completion fields, excluding image-only and failed migrations.
	 *
	 * @param string $key Local migration record key (never retained).
	 * @param mixed  $record Persisted record.
	 * @return bool
	 */
	private static function migration_completed( $key, $record ) {
		if ( ! is_array( $record ) && ! is_object( $record ) ) {
			return false;
		}
		$record = (array) $record;
		return preg_match( '/^(gallery|album)_/', $key ) && isset( $record['migration_status'] ) && 'completed' === $record['migration_status'] && ! empty( $record['migrated_id'] ) && empty( $record['error'] );
	}
}
