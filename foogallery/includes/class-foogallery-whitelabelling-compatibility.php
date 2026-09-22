<?php
/**
 * Backwards-compatibility policy for the bundled white-label extension.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FooGallery_Whitelabelling_Compatibility' ) ) {
	/**
	 * Resolves and enforces access to the retained bundled implementation.
	 */
	class FooGallery_Whitelabelling_Compatibility {
		const LEGACY_CLASS = 'FooGallery_Pro_Whitelabelling_Extension';
		const OPTION       = 'foogallery_whitelabelling_grandfathered';
		const SLUG         = 'foogallery-whitelabelling';

		/**
		 * Register compatibility hooks.
		 */
		public static function register_hooks() {
			add_filter( 'pre_update_option_foogallery', array( __CLASS__, 'preserve_unregistered_settings' ), 20, 3 );
		}

		/**
		 * Persist the precedence-resolved legacy activation state once per site.
		 *
		 * @return string The explicit yes/no decision.
		 */
		public static function snapshot_legacy_state() {
			$snapshot = get_option( self::OPTION, false );
			if ( 'yes' === $snapshot || 'no' === $snapshot ) {
				return $snapshot;
			}

			$grandfathered = self::legacy_extension_is_active() ? 'yes' : 'no';
			if ( false === $snapshot ) {
				add_option( self::OPTION, $grandfathered, '', false );
			}

			return self::is_grandfathered() ? 'yes' : 'no';
		}

		/**
		 * Whether this site has the immutable legacy entitlement marker.
		 *
		 * @return bool
		 */
		public static function is_grandfathered() {
			return 'yes' === get_option( self::OPTION, 'no' );
		}

		/**
		 * Whether the retained bundled runtime is currently licensed for use.
		 *
		 * @return bool
		 */
		public static function can_use_bundled_feature() {
			if ( ! self::is_grandfathered() || ! function_exists( 'foogallery_fs' ) ) {
				return false;
			}

			$freemius = foogallery_fs();
			return $freemius->can_use_premium_code() && $freemius->is_plan_or_trial( 'commerce' );
		}

		/**
		 * Resolve the old extension state using the same override precedence.
		 *
		 * Malformed state fails closed. Retained settings never confer access.
		 *
		 * @return bool
		 */
		public static function legacy_extension_is_active() {
			$overrides = get_option( FOOGALLERY_EXTENSIONS_OVERRIDES_OPTIONS_KEY, array() );
			if ( ! is_array( $overrides ) ) {
				return false;
			}

			if ( array_key_exists( self::SLUG, $overrides ) ) {
				return 'active' === $overrides[ self::SLUG ];
			}

			$activated = get_option( FOOGALLERY_EXTENSIONS_ACTIVATED_OPTIONS_KEY, array() );
			return is_array( $activated ) &&
				array_key_exists( self::SLUG, $activated ) &&
				self::LEGACY_CLASS === $activated[ self::SLUG ];
		}

		/**
		 * Preserve dormant white-label settings during unrelated settings saves.
		 *
		 * Explicitly submitted values win. False and non-array values pass through so
		 * the settings reset and Options API deletion paths remain intact.
		 *
		 * @param mixed  $value     New option value.
		 * @param mixed  $old_value Existing option value.
		 * @param string $option    Option name.
		 * @return mixed
		 */
		public static function preserve_unregistered_settings( $value, $old_value, $option ) {
			if ( 'foogallery' !== $option || ! is_array( $value ) || ! is_array( $old_value ) || self::settings_are_registered() ) {
				return $value;
			}

			foreach ( $old_value as $key => $setting_value ) {
				if ( is_string( $key ) && 0 === strpos( $key, 'whitelabelling_' ) && ! array_key_exists( $key, $value ) ) {
					$value[ $key ] = $setting_value;
				}
			}

			return $value;
		}

		/**
		 * Determine whether either white-label runtime registered its settings.
		 *
		 * @return bool
		 */
		private static function settings_are_registered() {
			$settings = apply_filters( 'foogallery_admin_settings', false );
			if ( ! is_array( $settings ) || empty( $settings['settings'] ) || ! is_array( $settings['settings'] ) ) {
				return false;
			}

			foreach ( $settings['settings'] as $setting ) {
				if (
					is_array( $setting ) &&
					isset( $setting['id'] ) &&
					is_string( $setting['id'] ) &&
					0 === strpos( $setting['id'], 'whitelabelling_' )
				) {
					return true;
				}
			}

			return false;
		}
	}

	FooGallery_Whitelabelling_Compatibility::register_hooks();
}
