<?php

if ( ! class_exists( 'FooGallery_Extensions_Loader' ) ) {
	class FooGallery_Extensions_Loader {

		function __construct() {
			add_action( 'plugins_loaded', array( $this, 'load_active_extensions' ) );
		}

		/**
		 * Load all FooGallery extensions that have been activated.
		 * For each extension, create an instance of the extension class and add it to a global extensions array
		 */
		function load_active_extensions() {
			$action = foo_safe_get( $_POST, 'action');
			if ( 'deactivate' === $action ) { return; }

			if ( ! function_exists( 'get_current_screen' ) ) {
				require_once(ABSPATH . 'wp-admin/includes/screen.php');
			}

			$api               = new FooGallery_Extensions_API();
			$active_extensions = $api->get_loadable_extensions();
			foreach ( $active_extensions as $slug => $class ) {
				try {
					$this->load_extension( $slug, $class );
				}
				catch (Exception $e) {
					$error = $e;
					$something = $error;
				}
			}

			$this->load_requested_album_extension( $api );

			//What if no extensions were loaded?
		}

		/**
		 * Load albums when the current admin request is already for an album.
		 *
		 * This keeps existing album edit/save requests from reaching WordPress core
		 * with an unregistered post type when extension state options drift.
		 *
		 * @param FooGallery_Extensions_API $api The extensions API.
		 */
		private function load_requested_album_extension( $api ) {
			if ( ! is_admin() || ! $this->is_album_admin_request() ) {
				return;
			}

			$extension = $api->get_extension( 'albums' );
			$class     = foo_safe_get( $extension, 'class' );

			if ( ! empty( $class ) ) {
				$this->load_extension( 'albums', $class );
			}
		}

		/**
		 * Determine whether the current admin request is for a FooGallery album.
		 *
		 * @return bool
		 */
		private function is_album_admin_request() {
			$post_type = '';
			if ( isset( $_REQUEST['post_type'] ) && ! is_array( $_REQUEST['post_type'] ) ) {
				$post_type = sanitize_key( wp_unslash( $_REQUEST['post_type'] ) );
			}

			if ( FOOGALLERY_CPT_ALBUM === $post_type ) {
				return true;
			}

			$post_id = 0;
			if ( isset( $_REQUEST['post'] ) && ! is_array( $_REQUEST['post'] ) ) {
				$post_id = absint( wp_unslash( $_REQUEST['post'] ) );
			} elseif ( isset( $_REQUEST['post_ID'] ) && ! is_array( $_REQUEST['post_ID'] ) ) {
				$post_id = absint( wp_unslash( $_REQUEST['post_ID'] ) );
			}

			return $post_id > 0 && FOOGALLERY_CPT_ALBUM === get_post_type( $post_id );
		}

		function load_extension( $slug, $class ) {
			global $foogallery_extensions;
			global $foogallery_currently_loading;
			if ( is_null( $foogallery_extensions ) ) {
				$foogallery_extensions = array();
			}
			if ( class_exists( $class ) && !array_key_exists( $slug, $foogallery_extensions ) ) {
				$foogallery_currently_loading = $slug;
				$instance = new $class();
				$foogallery_extensions[ $slug ] = $instance;
			}
		}

		function handle_load_exceptions( $errno, $errstr, $errfile, $errline ) {
			global $foogallery_currently_loading;
			$api = new FooGallery_Extensions_API();
			$api->deactivate( $foogallery_currently_loading, false, true );

			//don't execute PHP internal error handler
			return true;
		}
	}
}
