<?php
/**
 * FooGallery first-party add-on usage adapter.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/** Reviewed adapters for supported first-party add-on versions. */
class FooGallery_Usage_Addon_Adapter {
	/**
	 * Detect running add-ons that have only promotional entries in the Features list.
	 *
	 * @param array $states Reviewed module states to update.
	 * @return void
	 */
	public static function modules( &$states ) {
		foreach ( array(
			'uploads'  => 'foogallery-user-uploads',
			'social'   => 'foogallery-social',
			'proofing' => 'foogallery-proofing',
		) as $kind => $id ) {
			if ( isset( $states[ $id ] ) && self::runtime_active( $kind ) ) {
				$states[ $id ] = 'enabled';
			}
		}
	}

	/**
	 * Check callbacks registered by the add-on's working runtime, in admin and cron.
	 *
	 * @param string $kind Approved add-on adapter name.
	 * @return bool
	 */
	private static function runtime_active( $kind ) {
		$callbacks = array(
			'uploads'  => array( 'FooPlugins\\FooGallery\\UserUploads\\Init', 'custom_show_after_gallery_option' ),
			'social'   => array( 'FooPlugins\\FooGallery\\Social\\Init', 'add_container_data_options' ),
			'proofing' => array( 'FooGallery_Proofing_Public', 'maybe_render_proof' ),
		);
		return isset( $callbacks[ $kind ] ) && FooGallery_Usage_Collector::runtime_provider( $callbacks[ $kind ][0], $callbacks[ $kind ][1] );
	}

	/**
	 * Accumulate configuration evidence for one published gallery.
	 *
	 * @param array $record Effective local gallery configuration.
	 *
	 * @param array $states Measurement states to update.
	 */
	public static function gallery( $record, &$states ) {
		$settings = isset( $record['settings'] ) && is_array( $record['settings'] ) ? $record['settings'] : array();
		$template = isset( $record['template'] ) && is_string( $record['template'] ) ? $record['template'] : '';
		$source   = isset( $record['datasource'] ) ? sanitize_key( $record['datasource'] ) : '';
		if ( self::supported( 'uploads' ) ) {
			$form_mode = self::find( $settings, $template, 'show_upload_form', 'feu-no' );
			$form      = in_array( $form_mode, array( 'feu-after-gallery', 'feu-shortcode' ), true );
			self::enable( $states, 'addon.user_uploads_form', $form );
			$video = $form && 'yes' === self::find( $settings, $template, 'allow_video_uploads', 'no' ) && function_exists( 'foogallery_user_uploads_gallery_allows_video_uploads' );
			self::enable( $states, 'addon.user_uploads_video', $video );
		}
		if ( self::supported( 'social' ) && 'folders' !== $source ) {
			$social = 'yes' === self::find( $settings, $template, 'social_enabled', 'no' );
			self::enable( $states, 'addon.social.enabled', $social );
			self::enable( $states, 'addon.social.likes', $social && 'yes' === self::find( $settings, $template, 'likes_enabled', 'yes' ) );
			self::enable( $states, 'addon.social.comments', $social && 'yes' === self::find( $settings, $template, 'comments_enabled', 'yes' ) && 'foogallery' === self::find( $settings, $template, 'lightbox', 'foogallery' ) );
			self::enable( $states, 'addon.social.sharing', $social && 'yes' === self::find( $settings, $template, 'share_enabled', 'yes' ) && 'foogallery' === self::find( $settings, $template, 'lightbox', 'foogallery' ) );
		}
	}

	/**

	 * Finalize supported add-on states and detect valid proofing sessions.
	 *
	 * @param array $modules Reviewed module states.
	 *
	 * @param array $states Measurement states to update.
	 */
	public static function site( &$modules, &$states ) {
		foreach ( array(
			'uploads' => 'user_uploads',
			'social'  => 'social',
		) as $kind => $prefix ) {
			if ( self::supported( $kind ) ) {
				self::unknown_to_disabled( $states, 'addon.' . $prefix ); } elseif ( self::module_enabled( $modules, $kind ) ) {
				self::prefix_state( $states, 'addon.' . $prefix, 'unknown' ); }
		}
		if ( ! self::supported( 'proofing' ) ) {
			if ( self::module_enabled( $modules, 'proofing' ) ) {
				self::prefix_state( $states, 'addon.proofing', 'unknown' ); }
			return;
		}
		$proofing                          = self::has_valid_proofing_session();
		$states['addon.proofing_sessions'] = null === $proofing ? 'unknown' : ( $proofing ? 'enabled' : 'disabled' );
	}

	/**
	 * Check for a proofing record referencing a published gallery.
	 *
	 * @return mixed
	 */
	private static function has_valid_proofing_session() {
		global $wpdb;
		$post_type    = defined( 'FOOGALLERY_PROOFING_POST_TYPE' ) ? FOOGALLERY_PROOFING_POST_TYPE : 'foogallery_proof';
		$gallery_meta = defined( 'FOOGALLERY_PROOFING_META_GALLERY_ID' ) ? FOOGALLERY_PROOFING_META_GALLERY_ID : '_fgproof_gallery_id';
		$gallery_type = defined( 'FOOGALLERY_CPT_GALLERY' ) ? FOOGALLERY_CPT_GALLERY : 'foogallery';
		// Direct, uncached existence lookup is intentional because proof records change independently.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT proof.ID FROM {$wpdb->posts} proof INNER JOIN {$wpdb->postmeta} reference ON reference.post_id=proof.ID AND reference.meta_key=%s INNER JOIN {$wpdb->posts} gallery ON gallery.ID=CAST(reference.meta_value AS UNSIGNED) AND gallery.post_type=%s AND gallery.post_status='publish' WHERE proof.post_type=%s AND proof.post_status NOT IN ('trash','auto-draft') ORDER BY proof.ID ASC LIMIT 1", $gallery_meta, $gallery_type, $post_type ) );
		return $wpdb->last_error ? null : (bool) $result;
	}

	/**
	 * Check runtime presence and reviewed add-on version support.
	 *
	 * @param string $kind Approved add-on adapter name.
	 * @return mixed
	 */
	private static function supported( $kind ) {
		$versions = array(
			'uploads'  => defined( 'FGFUU_VERSION' ) ? FGFUU_VERSION : '',
			'social'   => defined( 'FG_SOCIAL_VERSION' ) ? FG_SOCIAL_VERSION : '',
			'proofing' => defined( 'FG_PROOFING_VERSION' ) ? FG_PROOFING_VERSION : '',
		);
		$runtime  = array(
			'uploads'  => function_exists( 'foogallery_user_uploads_gallery_allows_video_uploads' ),
			'social'   => function_exists( 'foogallery_social_is_enabled_for_current_gallery' ),
			'proofing' => class_exists( 'FooGallery_Proofing_Storage' ),
		);
		return isset( $versions[ $kind ], $runtime[ $kind ] ) && $runtime[ $kind ] && self::runtime_active( $kind ) && self::supports_version( $kind, $versions[ $kind ] );
	}

	/**

	 * Return whether a reviewed adapter covers an add-on release.
	 *
	 * @param string $kind Approved add-on adapter name.
	 *
	 * @param string $version Installed add-on version.
	 */
	public static function supports_version( $kind, $version ) {
		$minimums = array(
			'uploads'  => '1.2.0',
			'social'   => '1.1.7',
			'proofing' => '1.0.6',
		);
		$maximums = array(
			'uploads'  => '1.2.0',
			'social'   => '1.1.7',
			'proofing' => '1.0.6',
		);
		return isset( $minimums[ $kind ], $maximums[ $kind ] ) && is_string( $version ) && '' !== $version && version_compare( $version, $minimums[ $kind ], '>=' ) && version_compare( $version, $maximums[ $kind ], '<=' );
	}

	/**
	 * Check whether the matching add-on module is enabled.
	 *
	 * @param array  $modules Reviewed module states.
	 * @param string $kind Approved add-on adapter name.
	 * @return mixed
	 */
	private static function module_enabled( $modules, $kind ) {
		$ids = array(
			'uploads'  => 'foogallery-user-uploads',
			'social'   => 'foogallery-social',
			'proofing' => 'foogallery-proofing',
		);
		return isset( $ids[ $kind ], $modules[ $ids[ $kind ] ] ) && 'enabled' === $modules[ $ids[ $kind ] ];
	}

	/**
	 * Read one setting for the active gallery template.
	 *
	 * @param array  $settings Local saved gallery settings.
	 * @param string $template Active gallery template slug.
	 * @param string $key Reviewed setting key.
	 * @param string $fallback Default used when the reviewed field is absent.
	 * @return mixed
	 */
	private static function find( $settings, $template, $key, $fallback = '' ) {
		$stored = '' !== $template ? $template . '_' . $key : '';
		if ( '' !== $stored && isset( $settings[ $stored ] ) && is_scalar( $settings[ $stored ] ) ) {
			return (string) $settings[ $stored ]; }
		return $fallback;
	}

	/**
	 * Mark a feature enabled when supported evidence exists.
	 *
	 * @param array  $states Measurement states to update.
	 * @param string $id Reviewed feature identifier.
	 * @param bool   $condition Whether positive configuration evidence exists.
	 * @return mixed
	 */
	private static function enable( &$states, $id, $condition ) {
		if ( $condition && isset( $states[ $id ] ) && 'unavailable' !== $states[ $id ] ) {
			$states[ $id ] = 'enabled'; }
	}

	/**
	 * Finalize unknown states for a fully supported add-on.
	 *
	 * @param array  $states Measurement states to update.
	 * @param string $prefix Approved feature family prefix.
	 * @return mixed
	 */
	private static function unknown_to_disabled( &$states, $prefix ) {
		foreach ( $states as $id => $state ) {
			if ( 0 === strpos( $id, $prefix ) && 'unknown' === $state ) {
				$states[ $id ] = 'disabled'; }
		}
	}

	/**
	 * Set every catalog state under a feature prefix.
	 *
	 * @param array  $states Measurement states to update.
	 * @param string $prefix Approved feature family prefix.
	 * @param string $value Approved measurement state.
	 * @return mixed
	 */
	private static function prefix_state( &$states, $prefix, $value ) {
		foreach ( $states as $id => $state ) {
			if ( 0 === strpos( $id, $prefix ) ) {
				$states[ $id ] = $value; }
		}
	}
}
