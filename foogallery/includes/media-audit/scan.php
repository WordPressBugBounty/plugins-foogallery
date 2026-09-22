<?php
/** Bounded saved-configuration audit. Loaded only for authorized application work.
 *
 * @package FooGallery
 */

defined( 'ABSPATH' ) || exit;

/** FooGallery feature service with private application-owned state. */
class FooGallery_Media_Audit_Scan {
	const JOB  = 'foogallery_media_audit_job';
	const HOOK = 'foogallery_media_audit_step';
	private static $deadline;

	/**
	 * Authorize and start a saved-configuration audit.
	 *
	 * @param string $scope Scope.
	 * @param int $gallery Gallery.
	 * @param string $trigger Trigger.
	 * @return mixed
	 */
	public static function start( $scope = 'site', $gallery = 0, $trigger = 'manual' ) {
		if ( ! in_array( $scope, array( 'site', 'gallery' ), true ) || ! in_array( $trigger, array( 'manual', 'automatic' ), true ) ) {
			return new WP_Error( 'audit_scope', 'Invalid audit scope.' ); }
		if ( 'gallery' === $scope && ( 'foogallery' !== get_post_type( $gallery ) || in_array( get_post_status( $gallery ), array( false, 'trash', 'auto-draft' ), true ) ) ) {
			return new WP_Error( 'audit_gallery', 'Save a valid gallery first.' ); }
		if ( 'automatic' === $trigger ? ( 'site' !== $scope || ! FooGallery_Usage::instance()->consented() ) : ! current_user_can( 'gallery' === $scope ? 'edit_post' : 'manage_options', $gallery ) ) {
			return new WP_Error( 'audit_forbidden', 'Audit permission required.' ); }
		if ( ! FooGallery_Media_Audit_Report::lock() ) {
			return new WP_Error( 'audit_busy', 'An audit is already running.' ); }
		$lease = FooGallery_Usage_State::acquire_lease( 'audit', 30 );
		if ( ! $lease ) {
			FooGallery_Media_Audit_Report::unlock();
			return new WP_Error( 'audit_busy', 'An audit is already running.' ); }
		try {
			$old = get_option( self::JOB );
			if ( is_array( $old ) && empty( $old['complete'] ) && time() - $old['created'] < DAY_IN_SECONDS ) {
				return new WP_Error( 'audit_busy', 'An audit is already running.' ); }
			$s                       = FooGallery_Usage_State::get();
			$r                       = FooGallery_Media_Audit_Report::empty_report( $scope, $trigger, 'incomplete' );
			$r['started']            = gmdate( 'Y-m-d\TH:i:s\Z' );
			$r['large_source_bytes'] = 0;
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Audit snapshot needs an exact live total.
			$r['totals']['galleries_eligible'] = 'gallery' === $scope ? 1 : (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='foogallery' AND post_status NOT IN ('trash','auto-draft')" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Audit snapshot needs an exact live total.
			$r['totals']['images_eligible']    = 'site' === $scope ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%' AND post_status<>'trash'" ) : null;
			if ( $wpdb->last_error ) { return new WP_Error( 'audit_database', __( 'Audit totals could not be read.', 'foogallery' ) ); }
			$job                               = array(
				'id'             => wp_generate_uuid4(),
				'created'        => time(),
				'spent'          => 0,
				'epoch'          => $s['epoch'],
				'scope'          => $scope,
				'gallery'        => absint( $gallery ),
				'trigger'        => $trigger,
				'phase'          => 'galleries',
				'cursor'         => 0,
				'member_offset'  => 0,
				'active_gallery' => 0,
				'members'        => array(),
				'images'         => array(),
				'compression_settings' => FooGallery_Media_Audit_Compression::settings(),
				'header_budget'  => array( 'bytes' => 0, 'seconds' => 0.0 ),
				'alt'            => array(),
				'report'         => $r,
				'network'        => 0,
				'requests'       => array(
					'gallery' => 0,
					'missing' => 0,
				),
				'complete'       => false,
			);
			if ( ! update_option( self::JOB, $job, false ) ) {
				return new WP_Error( 'audit_storage_failed', 'Could not save audit progress.' ); }
			self::queue( $job );
			return $job['id'];
		} finally {
			FooGallery_Usage_State::release_lease( 'audit', $lease );
			FooGallery_Media_Audit_Report::unlock(); }
	}
	/**
	 * Queue a continuation using opaque job tokens.
	 *
	 * @param array $job Job.
	 * @return mixed
	 */
	private static function queue( $job ) {
		FooGallery_Jobs::schedule( self::HOOK, array( $job['id'], wp_generate_uuid4() ), 'foogallery-media-audit', time() + 1 );
	}
	/**
	 * Advance one bounded audit batch owned by its job identifier.
	 *
	 * @param string $id Requested job identifier.
	 * @return mixed
	 */
	public static function step( $id ) {
		if ( ! FooGallery_Media_Audit_Report::lock() ) {
			return; }
		$lease = FooGallery_Usage_State::acquire_lease( 'audit', 30 );
		if ( ! $lease ) {
			FooGallery_Media_Audit_Report::unlock();
			return; }
		$started        = microtime( true );
		self::$deadline = $started + 5;
		try {
			$j = get_option( self::JOB );
			if ( ! is_array( $j ) || $j['id'] !== $id || $j['complete'] ) {
				return; }
			$s = FooGallery_Usage_State::get();
			if ( 'automatic' === $j['trigger'] && ( ! FooGallery_Usage::instance()->consented() || $j['epoch'] !== $s['epoch'] ) ) {
				self::cancel();
				return; }
			if ( $j['spent'] >= 300 || time() - $j['created'] >= DAY_IN_SECONDS ) {
				self::limit( $j, $j['spent'] >= 300 ? 'execution_budget' : 'elapsed_budget' );
				$j['complete'] = true; }
			for ( $n = 0; $n < 100 && microtime( true ) < self::$deadline && ! $j['complete']; ++$n ) {
				if ( 'galleries' === $j['phase'] ) {
					self::gallery_step( $j ); } else {
					self::image_step( $j ); }
			}
			$j['spent'] += microtime( true ) - $started;
			if ( $j['complete'] ) {
				self::finish( $j ); }
			// The named lock prevents a successor from publishing while this worker checks its lease.
			$current_job = get_option( self::JOB );
			if ( ! FooGallery_Usage_State::owns_lease( 'audit', $lease ) || ! is_array( $current_job ) || $current_job['id'] !== $id ) {
				return; }
			wp_cache_delete( FooGallery_Usage_State::OPTION, 'options' );
			$current_state = FooGallery_Usage_State::get();
			if ( 'automatic' === $j['trigger'] && ( ! FooGallery_Usage::instance()->consented() || $j['epoch'] !== $current_state['epoch'] ) ) {
				return; }
			$persisted = update_option( self::JOB, $j, false );
			if ( ! $persisted && get_option( self::JOB ) !== $j ) { return; }
			if ( $j['complete'] && 'site' === $j['scope'] && ! in_array( 'audit_failed', $j['report']['limitations'], true ) ) {
				$result = FooGallery_Media_Audit_Report::save( $j['report'] );
				if ( is_wp_error( $result ) ) {
					self::limit( $j, 'storage_failed' );
					$j['report']['state'] = 'incomplete';
					update_option( self::JOB, $j, false );
				}
			}
			if ( $j['complete'] && 'manual' === $j['trigger'] && ! array_intersect( array( 'audit_failed', 'storage_failed', 'execution_budget', 'elapsed_budget', 'image_cap' ), $j['report']['limitations'] ) ) {
				do_action( 'foogallery_media_audit_succeeded' );
			}
			if ( ! $j['complete'] ) {
				self::queue( $j ); }
		} catch ( Throwable $error ) {
			// Raw exception text may contain private paths or URLs; retain a fixed failure code only.
			$current_job = get_option( self::JOB );
			if ( isset( $j['id'], $current_job['id'] ) && $j['id'] === $id && $current_job['id'] === $id && FooGallery_Usage_State::owns_lease( 'audit', $lease ) ) {
				self::limit( $j, 'audit_failed' );
				$j['complete'] = true;
				$j['spent'] += microtime( true ) - $started;
				self::finish( $j );
				update_option( self::JOB, $j, false );
			}
		} finally {
			FooGallery_Usage_State::release_lease( 'audit', $lease );
			FooGallery_Media_Audit_Report::unlock(); }
	}
	/**
	 * Return only the completed report for the requested job.
	 *
	 * @param string $id Requested job identifier.
	 * @return mixed
	 */
	public static function report( $id ) {
		if ( ! FooGallery_Media_Audit_Report::lock() ) {
			return null;
		}
		try {
			$j = get_option( self::JOB );
			if ( ! is_array( $j ) || $j['id'] !== $id || ! $j['complete'] ) {
				return null;
			}
			if ( 'gallery' === $j['scope'] ) {
				delete_option( self::JOB );
			}
			return $j['report'];
		} finally {
			FooGallery_Media_Audit_Report::unlock();
		}
	}
	public static function cancel() {
		if ( ! FooGallery_Media_Audit_Report::lock( 30 ) ) {
			return false; }
		try {
			FooGallery_Jobs::cancel( self::HOOK, 'foogallery-media-audit' );
			delete_option( self::JOB );
			return true;
		} finally {
			FooGallery_Media_Audit_Report::unlock(); }
	}
	/**
	 * Record a fixed limitation code without raw error content.
	 *
	 * @param array $j Private resumable job state.
	 * @param string $code Code.
	 * @param array $rules Checks affected by this limitation.
	 * @return mixed
	 */
	private static function limit( &$j, $code, $rules = array() ) {
		if ( ! in_array( $code, $j['report']['limitations'], true ) ) {
			$j['report']['limitations'][] = $code; }
		if ( in_array( $code, array( 'image_cap', 'execution_budget', 'elapsed_budget' ), true ) ) {
			// Capture the affected phase now: a capped gallery may be skipped while
			// independent library work continues before the report is finalized.
			foreach ( FooGallery_Media_Audit_Report::rules() as $rule ) {
				if ( 'galleries' === $j['phase'] || 0 === strpos( $rule, 'FG-SRC-' ) || 0 === strpos( $rule, 'FG-A11Y-' ) ) {
					$rules[] = $rule;
				}
			}
		}
		foreach ( $rules as $rule ) {
			if ( ! isset( $j['report']['checks'][ $rule ]['limitations'] ) ) {
				$j['report']['checks'][ $rule ]['limitations'] = array();
			}
			if ( ! in_array( $code, $j['report']['checks'][ $rule ]['limitations'], true ) ) {
				$j['report']['checks'][ $rule ]['limitations'][] = $code;
			}
		}
	}
	/**
	 * Accumulate coverage for a canonical rule.
	 *
	 * @param array $j Private resumable job state.
	 * @param string $rule Rule.
	 * @param int $assessed Assessed.
	 * @param int $eligible Eligible.
	 * @return mixed
	 */
	private static function observe( &$j, $rule, $assessed, $eligible = 1 ) {
		$c              =& $j['report']['checks'][ $rule ];
		$c['assessed'] += $assessed;
		$c['eligible']  = (int) $c['eligible'] + $eligible;
	}
	/**
	 * Count each distinct image against the shared image cap.
	 *
	 * @param array $j Private resumable job state.
	 * @param int $id Local subject identifier.
	 * @return mixed
	 */
	private static function claim_image( &$j, $id ) {
		if ( isset( $j['seen_images'][ $id ] ) ) { return true; }
		if ( isset( $j['seen_images'] ) && count( $j['seen_images'] ) >= 20000 ) { self::limit( $j, 'image_cap' ); return false; }
		$j['seen_images'][ $id ] = true;
		++$j['report']['totals']['images_assessed'];
		return true;
	}
	/**
	 * Add a report-local finding and distinct affected counts.
	 *
	 * @param array $j Private resumable job state.
	 * @param string $rule Rule.
	 * @param int $image Image.
	 * @param int $gallery Gallery.
	 * @param string $severity Severity.
	 * @param string $url Url.
	 * @param array $evidence Minimal evidence captured when the finding was observed.
	 * @return mixed
	 */
	private static function finding( &$j, $rule, $image, $gallery, $severity = 'warning', $url = '', $evidence = array() ) {
		$id = strtolower( $rule ) . '-' . $image . '-' . $gallery;
		if ( isset( $j['report']['findings'][ $id ] ) ) {
			return; }
		++$j['report']['checks'][ $rule ]['findings'];
		if ( $image && ! isset( $j['affected'][ $rule ]['images'][ $image ] ) ) {
			$j['affected'][ $rule ]['images'][ $image ] = true;
			++$j['report']['checks'][ $rule ]['affected_images']; }
		$galleries = array();
		if ( $gallery ) {
			$galleries[] = $gallery;
		} elseif ( $image && ! empty( $j['members'][ $image ] ) ) {
			$galleries = array_keys( $j['members'][ $image ] );
		}
		foreach ( $galleries as $affected_gallery ) {
			$affected_gallery = (int) $affected_gallery;
			if ( $affected_gallery && ! isset( $j['affected'][ $rule ]['galleries'][ $affected_gallery ] ) ) {
				$j['affected'][ $rule ]['galleries'][ $affected_gallery ] = true;
				++$j['report']['checks'][ $rule ]['affected_galleries']; }
		}
		$subject = array(
			'image'       => $image,
			'gallery'     => $gallery,
			'sampled_url' => $url,
		);
		$allowed_evidence = array(
			'alt'              => 'string',
			'alt_length'       => 'integer',
			'bytes'            => 'integer',
			'compression_version' => 'string',
			'compression_min_bytes' => 'integer',
			'compression_density' => 'double',
			'http_status'      => 'integer',
			'member_count'     => 'integer',
			'reason'           => 'string',
			'source_format'    => 'string',
			'requested_height' => 'integer',
			'requested_width'  => 'integer',
			'source_height'    => 'integer',
			'source_width'     => 'integer',
		);
		foreach ( $allowed_evidence as $key => $type ) {
			if ( isset( $evidence[ $key ] ) && gettype( $evidence[ $key ] ) === $type ) {
				$subject[ $key ] = $evidence[ $key ];
			}
		}
		$j['report']['findings'][ $id ] = array(
			'id'       => $id,
			'rule'     => $rule,
			'severity' => $severity,
			'subjects' => array( $subject ),
		);
	}
	/**
	 * Resolve one saved gallery or referenced attachment.
	 *
	 * @param array $j Private resumable job state.
	 * @return mixed
	 */
	private static function gallery_step( &$j ) {
		global $wpdb;
		if ( ! $j['active_gallery'] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyset scan requires an uncached one-row query.
			$id = 'gallery' === $j['scope'] ? ( $j['cursor'] ? 0 : $j['gallery'] ) : (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='foogallery' AND post_status NOT IN ('trash','auto-draft') AND ID>%d ORDER BY ID LIMIT 1", $j['cursor'] ) );
			if ( $wpdb->last_error ) { self::limit( $j, 'audit_failed' ); $j['complete'] = true; return; }
			if ( ! $id ) {
				$j['phase']  = 'images';
				$j['cursor'] = 0;
				return; }
			$j['cursor']         = $id;
			$j['active_gallery'] = $id;
			$j['member_offset']  = 0;
			$j['member_count']   = 0;
			++$j['report']['totals']['galleries_assessed'];
			$source      = get_post_meta( $id, FOOGALLERY_META_DATASOURCE, true );
			$j['source'] = $source ? $source : 'media_library';
			return; // Resolving a gallery counts as one subject.
		}
		$id   = $j['active_gallery'];
		$page = 'media_library' === $j['source'] ? self::attachment_page( $id, $j['member_offset'], 1 ) : self::source_page( $id, $j['source'], $j['member_offset'] );
		if ( is_wp_error( $page ) || $page['unknown'] ) {
			self::limit( $j, 'media_library' === $j['source'] ? 'source_unavailable' : 'dynamic_source' );
			$j['active_gallery'] = 0;
			return; }
		$j['member_offset'] = $page['offset'];
		if ( ! empty( $page['external'] ) ) {
			$remaining = 20000 - $j['member_count'];
			if ( $page['external'] > $remaining ) {
				self::limit( $j, 'image_cap' );
				$j['active_gallery'] = 0;
				return;
			}
			$j['member_count'] += $page['external'];
		}
		foreach ( $page['ids'] as $image ) {
			++$j['member_count'];
			if ( count( $j['members'] ) >= 20000 && ! isset( $j['members'][ $image ] ) ) {
				self::limit( $j, 'image_cap' );
				$j['active_gallery'] = 0;
				break; }
			if ( $image > 0 ) { $j['members'][ $image ][ $id ] = true; }
			$p                             = get_post( $image );
			self::observe( $j, 'FG-INT-01', 1 );
			if ( ! $image || ! $p || 'attachment' !== $p->post_type || 'trash' === $p->post_status ) {
				$reason = ! $image ? 'empty_attachment_id' : ( ! $p ? 'attachment_not_found' : ( 'trash' === $p->post_status ? 'attachment_trashed' : 'not_attachment' ) );
				self::finding( $j, 'FG-INT-01', $image, $id, 'error', '', array( 'reason' => $reason ) );
			} elseif ( 0 === strpos( (string) $p->post_mime_type, 'image/' ) ) {
				if ( ! self::claim_image( $j, $image ) ) { $j['active_gallery'] = 0; return; }
				self::accessibility( $j, $image, $id, isset( $page['alt'][ $image ] ) ? $page['alt'][ $image ] : null );
				$template   = get_post_meta( $id, FOOGALLERY_META_TEMPLATE, true );
				$settings   = get_post_meta( $id, FOOGALLERY_META_SETTINGS, true );
				$dimensions = foogallery_media_audit_thumb_dimensions( $id, $template, is_array( $settings ) ? $settings : array() );
				$meta       = wp_get_attachment_metadata( $image );
				if ( $dimensions['applicable'] ) {
					$known = isset( $meta['width'], $meta['height'] );
					self::observe( $j, 'FG-DEL-02', $known ? 1 : 0 );
					if ( ! $known ) { self::limit( $j, 'metadata_unavailable', array( 'FG-DEL-02' ) ); }
					if ( $known && ( $dimensions['width'] > $meta['width'] || $dimensions['height'] > $meta['height'] ) ) {
						self::finding(
							$j,
							'FG-DEL-02',
							$image,
							$id,
							'info',
							'',
							array(
								'source_width'     => (int) $meta['width'],
								'source_height'    => (int) $meta['height'],
								'requested_width'  => (int) $dimensions['width'],
								'requested_height' => (int) $dimensions['height'],
							)
						); }
				}
				self::observe( $j, 'FG-INT-02', 0 );
				if ( ! isset( $j['sampled'][ $id ] ) ) {
					$url                 = wp_get_attachment_url( $image );
					$code                = self::probe( $j, $url, 'gallery' );
					$j['sampled'][ $id ] = true;
					if ( null !== $code ) {
						++$j['report']['checks']['FG-INT-02']['assessed']; }
					if ( null !== $code && $code >= 400 ) {
						self::finding( $j, 'FG-INT-02', $image, $id, 'warning', $url, array( 'http_status' => (int) $code ) ); }
				}
			}
		}
		if ( $page['complete'] ) {
			$template   = get_post_meta( $id, FOOGALLERY_META_TEMPLATE, true );
			$settings   = get_post_meta( $id, FOOGALLERY_META_SETTINGS, true );
			$settings   = is_array( $settings ) ? $settings : array();
			$definition = foogallery_get_gallery_template( $template );
			if ( is_array( $definition ) && ! empty( $definition['paging_support'] ) ) {
				$paging = isset( $settings[ $template . '_paging_type' ] ) ? $settings[ $template . '_paging_type' ] : '';
				self::observe( $j, 'FG-CFG-02', 1 );
				if ( '' === $paging && $j['member_count'] > 100 ) {
					self::finding( $j, 'FG-CFG-02', 0, $id, 'info', '', array( 'member_count' => (int) $j['member_count'] ) ); }
			}
			$lazy = foogallery_media_audit_lazy_state( $template, $settings );
			if ( ! empty( $definition['lazyload_support'] ) ) {
				self::observe( $j, 'FG-CFG-01', 1 );
				if ( ! empty( $lazy['off'] ) && $j['member_count'] > 20 ) {
					self::finding( $j, 'FG-CFG-01', 0, $id, 'info', '', array( 'member_count' => (int) $j['member_count'] ) ); }
			}
			$j['active_gallery'] = 0;
		}
	}
	/**
	 * Inspect one attachment’s source metadata.
	 *
	 * @param array $j Private resumable job state.
	 * @return mixed
	 */
	private static function image_step( &$j ) {
		global $wpdb;
		if ( 'site' === $j['scope'] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyset scan requires an uncached one-row query.
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE %s AND post_status<>'trash' AND ID>%d ORDER BY ID LIMIT 1", 'image/%', $j['cursor'] ) );
			if ( $wpdb->last_error ) { self::limit( $j, 'audit_failed' ); $j['complete'] = true; return; }
		} else {
			$ids = array_keys( $j['members'] );
			$id  = isset( $ids[ $j['cursor'] ] ) ? $ids[ $j['cursor'] ] : 0;
		}
		if ( ! $id ) {
			$j['complete'] = true;
			return; }
		if ( count( $j['images'] ) >= 20000 ) {
			self::limit( $j, 'image_cap' );
			$j['complete'] = true;
			return; }
		$j['cursor'] = 'site' === $j['scope'] ? $id : $j['cursor'] + 1;
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type || 0 !== strpos( (string) $post->post_mime_type, 'image/' ) || 'trash' === $post->post_status ) {
			return; }
		if ( ! self::claim_image( $j, $id ) ) { $j['complete'] = true; return; }
		$j['images'][ $id ] = true;
		$meta   = wp_get_attachment_metadata( $id );
		$file   = get_attached_file( $id );
		$url    = wp_get_attachment_url( $id );
		$exists = $file && is_file( $file );
		$bytes  = $exists ? wp_filesize( $file ) : ( isset( $meta['filesize'] ) ? (int) $meta['filesize'] : null );
		$format = self::source_format( $post, $file, $meta, $url );
		self::compression( $j, $id, $format, $file, $meta );
		self::observe( $j, 'FG-SRC-04', $format ? 1 : 0 );
		if ( ! $format ) {
			self::limit( $j, 'metadata_unavailable', array( 'FG-SRC-04' ) );
		}
		if ( in_array( $format, array( 'JPEG', 'PNG' ), true ) ) {
			$format_evidence = array(
				'source_format' => $format,
			);
			if ( is_array( $meta ) ) {
				$format_evidence['source_width']  = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
				$format_evidence['source_height'] = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
			}
			if ( null !== $bytes ) {
				$format_evidence['bytes'] = (int) $bytes;
			}
			self::finding( $j, 'FG-SRC-04', $id, 0, 'info', '', $format_evidence );
		}
		self::observe( $j, 'FG-SRC-01', is_array( $meta ) ? 1 : 0 );
		if ( ! is_array( $meta ) ) { self::limit( $j, 'metadata_unavailable', array( 'FG-SRC-01' ) ); }
		if ( is_array( $meta ) && ( max( isset( $meta['width'] ) ? $meta['width'] : 0, isset( $meta['height'] ) ? $meta['height'] : 0 ) > 2500 || $bytes > 1048576 ) ) {
			$evidence = array(
				'source_width'  => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
				'source_height' => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
			);
			if ( null !== $bytes ) {
				$evidence['bytes'] = (int) $bytes;
			}
			self::finding( $j, 'FG-SRC-01', $id, 0, 'info', '', $evidence );
			$j['report']['large_source_bytes'] = null === $bytes || null === $j['report']['large_source_bytes'] ? null : $j['report']['large_source_bytes'] + (int) $bytes;
		}
		$code = null;
		if ( ! $exists ) {
			$code = self::probe( $j, $url, 'missing' ); }
		$verified = $exists || ( null !== $code && $code >= 200 && $code < 300 ) || in_array( $code, array( 404, 410 ), true );
		self::observe( $j, 'FG-SRC-05', $verified ? 1 : 0 );
		if ( in_array( $code, array( 404, 410 ), true ) ) {
			self::finding( $j, 'FG-SRC-05', $id, 0, 'error', $url, array( 'http_status' => (int) $code ) ); }
		if ( ! isset( $j['members'][ $id ] ) ) {
			self::accessibility( $j, $id, 0 ); }
	}

	/** Inspect one distinct source for the versioned compression heuristic. */
	private static function compression( &$j, $id, $format, $file, $meta ) {
		$rule = 'FG-SRC-07';
		if ( $format && ! isset( $j['compression_settings']['density'][ $format ] ) ) { return; }
		if ( ! $format ) {
			self::observe( $j, $rule, 0 );
			self::limit( $j, 'metadata_unavailable', array( $rule ) );
			return;
		}
		$result = FooGallery_Media_Audit_Compression::inspect( $file, $format, $j['header_budget'], self::$deadline );
		if ( 'excluded' === $result['state'] ) { return; }
		self::observe( $j, $rule, 0 );
		if ( 'unavailable' === $result['state'] ) {
			self::limit( $j, $result['limitation'], array( $rule ) );
			return;
		}
		$width = isset( $meta['width'] ) ? $meta['width'] : null;
		$height = isset( $meta['height'] ) ? $meta['height'] : null;
		if ( ! FooGallery_Media_Audit_Compression::dimension( $width ) || ! FooGallery_Media_Audit_Compression::dimension( $height ) ) {
			self::limit( $j, 'metadata_unavailable', array( $rule ) );
			return;
		}
		++$j['report']['checks'][ $rule ]['assessed'];
		$settings = $j['compression_settings'];
		if ( FooGallery_Media_Audit_Compression::qualifies( $result['bytes'], $width, $height, $format, $settings ) ) {
			self::finding( $j, $rule, $id, 0, 'info', '', array(
				'source_format' => $format,
				'source_width' => $width,
				'source_height' => $height,
				'bytes' => $result['bytes'],
				'compression_version' => $settings['version'],
				'compression_min_bytes' => $settings['min_bytes'],
				'compression_density' => $settings['density'][ $format ],
			) );
		}
	}

	/**
	 * Normalize an attachment source format from its registered MIME type, with
	 * the saved filename or URL extension as a fallback.
	 *
	 * @param WP_Post $post Attachment post.
	 * @param string  $file Attached file path.
	 * @param mixed   $meta Attachment metadata.
	 * @param string  $url Attachment URL.
	 * @return string Normalized source format, or an empty string.
	 */
	private static function source_format( $post, $file, $meta, $url ) {
		$mime = strtolower( trim( (string) $post->post_mime_type ) );
		$mime_formats = array(
			'image/jpeg'    => 'JPEG',
			'image/jpg'     => 'JPEG',
			'image/pjpeg'   => 'JPEG',
			'image/png'     => 'PNG',
			'image/apng'    => 'PNG',
			'image/webp'    => 'WebP',
			'image/avif'    => 'AVIF',
			'image/avif-sequence' => 'AVIF',
			'image/gif'     => 'GIF',
			'image/svg+xml' => 'SVG',
			'image/bmp'     => 'BMP',
			'image/tiff'    => 'TIFF',
			'image/heic'    => 'HEIC',
			'image/heif'    => 'HEIF',
		);
		if ( isset( $mime_formats[ $mime ] ) ) {
			return $mime_formats[ $mime ];
		}

		$source = $file;
		if ( ! $source && is_array( $meta ) && ! empty( $meta['file'] ) ) {
			$source = $meta['file'];
		}
		if ( ! $source && $url ) {
			$source = wp_parse_url( $url, PHP_URL_PATH );
		}
		$extension = strtolower( pathinfo( (string) $source, PATHINFO_EXTENSION ) );
		$formats = array(
			'jpg'  => 'JPEG',
			'jpeg' => 'JPEG',
			'jpe'  => 'JPEG',
			'png'  => 'PNG',
			'webp' => 'WebP',
			'avif' => 'AVIF',
		);
		return isset( $formats[ $extension ] ) ? $formats[ $extension ] : '';
	}

	/**
	 * Evaluate the rendered alt observation for one gallery and image.
	 *
	 * @param array $j Private resumable job state.
	 * @param int $image Image.
	 * @param int $gallery Gallery.
	 * @param string|null $rendered_alt Rendered datasource alt text, or null for media-library rendering.
	 * @return mixed
	 */
	private static function accessibility( &$j, $image, $gallery, $rendered_alt = null ) {
		if ( isset( $j['observed'][ $gallery ][ $image ] ) ) {
			return; }
		$j['observed'][ $gallery ][ $image ] = true;
		$attachment                          = FooGalleryAttachment::get_by_id( $image );
		$alt                                 = null === $rendered_alt ? trim( (string) $attachment->alt ) : trim( (string) $rendered_alt );
		foreach ( array( 'FG-A11Y-01', 'FG-A11Y-02', 'FG-A11Y-03', 'FG-A11Y-04', 'FG-A11Y-05' ) as $rule ) {
			self::observe( $j, $rule, 1 ); }
		if ( '' === $alt ) {
			self::finding( $j, 'FG-A11Y-01', $image, $gallery, 'info', '', array( 'alt' => '' ) ); }
		if ( preg_match( '/^(IMG|DSC|DSCN|Screenshot)[ _-]?\d/i', $alt ) ) {
			self::finding( $j, 'FG-A11Y-02', $image, $gallery, 'info', '', array( 'alt' => $alt ) ); }
		if ( preg_match( '/^(image|photo|picture|placeholder|test)$/iD', $alt ) ) {
			self::finding( $j, 'FG-A11Y-03', $image, $gallery, 'info', '', array( 'alt' => $alt ) ); }
		$alt_length = foogallery_media_audit_text_length( $alt );
		$config     = foogallery_media_audit_config();
		if ( $alt_length > (int) $config['a11y05_alt_max_len'] ) {
			self::finding( $j, 'FG-A11Y-05', $image, $gallery, 'info', '', array( 'alt' => $alt, 'alt_length' => $alt_length ) ); }
		if ( $alt ) {
			$key           = hash( 'sha256', strtolower( $alt ) );
			$ids           =& $j['alt'][ $gallery ][ $key ];
			$ids[ $image ] = true;
			if ( count( $ids ) === 3 ) {
				foreach ( array_keys( $ids ) as $id ) {
					self::finding( $j, 'FG-A11Y-04', $id, $gallery, 'info', '', array( 'alt' => $alt ) ); }
			} elseif ( count( $ids ) > 3 ) {
				self::finding( $j, 'FG-A11Y-04', $image, $gallery, 'info', '', array( 'alt' => $alt ) ); }
		}
	}

	/**
	 * Perform bounded TLS-verified HTTP observation.
	 *
	 * @param array $j Private resumable job state.
	 * @param string $url Url.
	 * @param string $bucket Bucket.
	 * @return mixed
	 */
	private static function probe( &$j, $url, $bucket ) {
		$rules = array( 'missing' === $bucket ? 'FG-SRC-05' : 'FG-INT-02' );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			self::limit( $j, 'http_unavailable', $rules );
			return null; }
		foreach ( array( 'HEAD', 'GET' ) as $method ) {
			$remaining = min( 20 - $j['network'], self::$deadline - microtime( true ) );
			if ( $remaining < 0.25 || $j['requests'][ $bucket ] >= ( 'missing' === $bucket ? 20 : 200 ) ) {
				self::limit( $j, 'network_budget', $rules );
				return null; }
			++$j['requests'][ $bucket ];
			$start         = microtime( true );
			$response      = wp_safe_remote_request(
				$url,
				array(
					'method'              => $method,
					'timeout'             => min( 5, $remaining ),
					'redirection'         => 0,
					'sslverify'           => true,
					'limit_response_size' => 1024,
				)
			);
			$j['network'] += microtime( true ) - $start;
			if ( is_wp_error( $response ) ) {
				self::limit( $j, 'http_unavailable', $rules );
				return null; }
			$code = wp_remote_retrieve_response_code( $response );
			if ( 'HEAD' === $method && in_array( $code, array( 405, 501 ), true ) ) {
				continue; }
			if ( $code >= 300 && $code < 400 ) {
				self::limit( $j, 'http_unavailable', $rules );
				return null; }
			if ( $code >= 200 && $code < 300 ) {
				$j['report']['diagnostics'][] = array(
					'sampled_url'  => $url,
					'status'       => $code,
					'content_type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
					'scope'        => 'sampled_source_only',
				);
			}
			return $code >= 200 ? $code : null;
		}
		return null;
	}
	/**
	 * Finalize coverage and available observations.
	 *
	 * @param array $j Private resumable job state.
	 * @return mixed
	 */
	private static function finish( &$j ) {
		$r             =& $j['report'];
		$r['finished'] = gmdate( 'Y-m-d\TH:i:s\Z' );
		// HTTP and metadata failures do not erase a fully enumerated gallery population.
		if ( 'gallery' === $j['scope'] && 'images' === $j['phase'] && ! array_intersect( $r['limitations'], array( 'source_unavailable', 'dynamic_source', 'image_cap', 'audit_failed' ) ) ) {
			$r['totals']['images_eligible'] = $r['totals']['images_assessed']; }
		foreach ( $r['checks'] as $rule => &$c ) {
			$c['limitations'] = isset( $c['limitations'] ) ? $c['limitations'] : array();
			$source_rule = in_array( $rule, array( 'FG-SRC-01', 'FG-SRC-04', 'FG-SRC-05', 'FG-SRC-07' ), true );
			// An unresolved gallery cannot invalidate the independent site media-library pass.
			$source_gaps = ( ! $source_rule || 'gallery' === $j['scope'] )
				? array_intersect( $r['limitations'], array( 'source_unavailable', 'dynamic_source' ) ) : array();
			$stopped     = array_intersect( $c['limitations'], array( 'image_cap', 'execution_budget', 'elapsed_budget' ) );
			$failed      = array_intersect( $r['limitations'], array( 'audit_failed' ) );
			$c['limitations'] = array_values( array_unique( array_merge( $c['limitations'], $source_gaps, $stopped, $failed ) ) );
			// Keep the observed denominator in the snapshot even when the full population
			// could not be enumerated. Never reconstruct it from current site content.
			$c['observed_eligible'] = isset( $c['observed_eligible'] ) ? $c['observed_eligible'] : (int) $c['eligible'];
			$c['eligible'] = $c['observed_eligible'];
			if ( $source_gaps || $stopped || $failed ) { $c['eligible'] = null; }
			if ( $source_rule && 'FG-SRC-07' !== $rule && 'site' === $j['scope'] && ! $failed ) {
				$c['eligible'] = max( $c['observed_eligible'], $r['totals']['images_eligible'] );
			}
			$c['coverage'] = $c['eligible'] ? round( $c['assessed'] / $c['eligible'], 4 ) : null;
			$complete = null !== $c['eligible'] && $c['assessed'] === $c['eligible'];
			$c['state'] = $c['findings'] ? 'findings' : ( $c['assessed'] ? ( $complete ? 'passed' : 'incomplete' ) : ( 0 === $c['eligible'] ? 'not_applicable' : 'unavailable' ) );
		}
		unset( $c );
		$r['state'] = $r['limitations'] ? 'incomplete' : 'complete';
		foreach ( $r['checks'] as $c ) {
			if ( in_array( $c['state'], array( 'incomplete', 'unavailable' ), true ) || ( $c['findings'] && ( null === $c['coverage'] || $c['coverage'] < 1 ) ) ) {
				$r['state'] = 'incomplete'; }
		}
		$r['findings']           = array_values( $r['findings'] );
		$r['gallery_membership'] = $j['members'];
	}
	/**
	 * Resolve one rendered observation through a bounded provider.
	 *
	 * @param int $id Local subject identifier.
	 * @param string $source Source.
	 * @param int $offset Offset.
	 * @return mixed
	 */
	private static function source_page( $id, $source, $offset ) {
		$page     = apply_filters( 'foogallery_media_audit_source_page', null, $id, $source, $offset, 1, self::$deadline );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		$external = isset( $page['external'] ) ? $page['external'] : 0;
		if ( ! is_array( $page ) || ! isset( $page['ids'], $page['offset'], $page['complete'] ) || ! is_array( $page['ids'] ) || ! is_int( $external ) || $external < 0 || count( $page['ids'] ) + $external > 1 || ! is_int( $page['offset'] ) || ( ! $page['complete'] && $page['offset'] <= $offset ) ) {
			return array( 'unknown' => true );
		}
		foreach ( $page['ids'] as $image ) {
			if ( ! is_int( $image ) || $image < 1 || ! isset( $page['alt'][ $image ] ) || ! is_string( $page['alt'][ $image ] ) ) {
				return array( 'unknown' => true ); }
		}
		$page['external'] = $external;
		$page['unknown']  = false;
		return $page;
	}
	/**
	 * Parse a bounded slice of the serialized attachment list.
	 *
	 * @param int $id Local subject identifier.
	 * @param int $offset Offset.
	 * @param int $limit Limit.
	 * @return mixed
	 */
	private static function attachment_page( $id, $offset, $limit ) {
		global $wpdb;
		$result = array(
			'ids'      => array(),
			'offset'   => $offset,
			'complete' => false,
			'unknown'  => false,
		);
		if ( $limit < 1 ) {
			return $result; }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded substring avoids loading and caching the full serialized gallery.
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT SUBSTRING(meta_value,%d,8192) FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s LIMIT 1", $offset + 1, $id, FOOGALLERY_META_ATTACHMENTS ) );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'collection_database', __( 'Attachment collection could not read the database.', 'foogallery' ) ); }
		if ( null === $raw || '' === $raw ) {
			$result['complete'] = true;
			return $result; }
		$position = 0;
		if ( 0 === $offset ) {
			if ( ! preg_match( '/\Aa:\d+:\{/', $raw, $header ) ) {
				$result['unknown']  = true;
				$result['complete'] = true;
				return $result; }
			$position = strlen( $header[0] );
		}
		$raw_length = strlen( $raw );
		$parsed     = 0;
		while ( $parsed < $limit && $position < $raw_length ) {
			if ( '}' === $raw[ $position ] ) {
				$result['complete'] = true;
				++$position;
				break; }
			if ( ! preg_match( '/\Gi:\d+;(?:i:(\d+);|s:(\d+):"(\d*)";)/', $raw, $match, 0, $position ) ) {
				// A split token is retried in the next bounded chunk. Non-list data is unmeasured.
				if ( strlen( $raw ) < 8192 || 0 === $position ) {
					$result['unknown']  = true;
					$result['complete'] = true; }
				break;
			}
			if ( isset( $match[2] ) && (int) $match[2] !== strlen( $match[3] ) ) {
				$result['unknown']  = true;
				$result['complete'] = true;
				break;
			}
			$value = isset( $match[2] ) ? $match[3] : $match[1];
			if ( '' !== $value ) {
				$result['ids'][] = absint( $value );
			}
			++$parsed;
			$position += strlen( $match[0] );
		}
		$result['offset'] = $offset + $position;
		return $result;
	}
}
