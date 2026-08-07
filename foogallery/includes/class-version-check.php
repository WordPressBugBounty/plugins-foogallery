<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Created by brad.
 * Date: 15/11/2015
 */
if ( ! class_exists( 'FooGallery_Version_Check' ) ) {

	class FooGallery_Version_Check {

		/**
		 * Wire up the check so it is done in the admin after admin includes are loaded.
		 */
		public function wire_up_checker() {
			if ( is_admin() ) {
				// When in admin, check if a new version is running.
				add_action( 'admin_init', array( $this, 'perform_check' ) );
			}
		}

		/**
		 * Perform a check to see if the plugin has been updated
		 */
		public function perform_check() {
			$stored_version = self::get_stored_version();

			if ( $stored_version != FOOGALLERY_VERSION ) {
				//This code will run every time the plugin is updated

				//perform all our housekeeping
				$this->perform_housekeeping( $stored_version );

				//set the current version, so that this does not run again until the next update!
				update_site_option( FOOGALLERY_OPTION_VERSION, array(
					'version'    => FOOGALLERY_VERSION,
					'updated_at' => time(),
				) );
			}
		}

		/**
		 * Get the stored FooGallery version.
		 *
		 * @return string
		 */
		public static function get_stored_version() {
			$version_data = get_site_option( FOOGALLERY_OPTION_VERSION );

			if ( is_array( $version_data ) && isset( $version_data['version'] ) ) {
				return $version_data['version'];
			}

			return $version_data;
		}

		/**
		 * Get the last FooGallery update timestamp.
		 *
		 * @return int
		 */
		public static function get_last_update_time() {
			$version_data = get_site_option( FOOGALLERY_OPTION_VERSION );

			if ( is_array( $version_data ) && isset( $version_data['updated_at'] ) ) {
				return absint( $version_data['updated_at'] );
			}

			return 0;
		}

		/**
		 * Runs after FooGallery has been updated via the backend
		 *
		 * @param string|false $previous_version The previously stored FooGallery version.
		 */
		function perform_housekeeping( $previous_version = false ) {
			//allow extensions or other plugins to do stuff when foogallery is updated
			// this will catch both manual and auto updates!
			do_action( 'foogallery_admin_new_version_detected' );

			$this->maybe_force_legacy_runtime_scripts_for_existing_install( $previous_version );

			//we need to clear the foogallery css load optimizations when we update the plugin, to ensure the latest CSS files are loaded
			foogallery_clear_all_css_load_optimizations();
		}

		/**
		 * Existing installs should keep the legacy runtime enqueue behavior unless explicitly opted in.
		 *
		 * @param string|false $previous_version The previously stored FooGallery version.
		 *
		 * @return void
		 */
		private function maybe_force_legacy_runtime_scripts_for_existing_install( $previous_version ) {
			if ( ! is_string( $previous_version ) || '' === $previous_version || ! version_compare( $previous_version, '3.1.36', '<' ) ) {
				return;
			}

			if ( $this->setting_has_explicit_value( 'force_legacy_runtime_scripts' ) ) {
				return;
			}

			foogallery_set_setting( 'force_legacy_runtime_scripts', 'on' );
		}

		/**
		 * Check if a setting is explicitly saved and not only supplied by defaults.
		 *
		 * @param string $key The setting key.
		 *
		 * @return bool
		 */
		private function setting_has_explicit_value( $key ) {
			$options = get_option( FOOGALLERY_SLUG );

			if ( is_array( $options ) && array_key_exists( $key, $options ) ) {
				return true;
			}

			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				$site_options = get_site_option( FOOGALLERY_SLUG );

				if ( is_array( $site_options ) && array_key_exists( $key, $site_options ) ) {
					return true;
				}
			}

			return false;
		}
	}
}
