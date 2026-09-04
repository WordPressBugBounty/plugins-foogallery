<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * FooGallery Admin Extension class
 */

if ( ! class_exists( 'FooGallery_Admin_Extensions' ) ) {

	class FooGallery_Admin_Extensions {

		function __construct() {
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'deactivated_plugin', array( $this, 'handle_extensions_deactivation' ), 10, 2 );
			add_action( 'activated_plugin', array( $this, 'handle_extensions_activation' ), 10, 2 );
		}

		function init() {
			add_action( 'admin_init', array( $this, 'handle_extension_action' ) );
		}

		function handle_extensions_deactivation( $plugin, $network_deactivating ) {
			//make sure that if we are dealing with a FooGallery extension, that we deactivate it too
			$api = new FooGallery_Extensions_API();
			$api->handle_wordpress_plugin_deactivation( $plugin );
		}

		function handle_extensions_activation( $plugin, $network_deactivating ) {
			//make sure that if we are dealing with a FooGallery extension, that we deactivate it too
			$api = new FooGallery_Extensions_API();
			$api->handle_wordpress_plugin_activation( $plugin );
		}

		/**
		 * Resolve and authorize an extension mutation for the current user.
		 *
		 * Menu visibility is intentionally separate from mutation authorization.
		 * Bundled feature toggles require the base FooGallery capability, while
		 * plugin-backed operations also follow WordPress plugin capabilities.
		 *
		 * @param string                    $action         Extension action.
		 * @param string                    $extension_slug Registered extension slug.
		 * @param FooGallery_Extensions_API $api            Optional API instance.
		 * @return array|false Authorized operation context, or false when denied.
		 */
		public static function authorize_extension_action( $action, $extension_slug, $api = null ) {
			if ( ! current_user_can( 'manage_options' ) || ! in_array( $action, array( 'download', 'activate', 'deactivate' ), true ) ) {
				return false;
			}

			if ( null === $api ) {
				$api = new FooGallery_Extensions_API();
			}

			$extension = $api->get_extension( $extension_slug );
			if ( ! $extension ) {
				return false;
			}

			$source = foo_safe_get( $extension, 'source', false );
			if ( 'bundled' === $source ) {
				if ( 'download' === $action ) {
					return false;
				}

				return array(
					'extension'    => $extension,
					'plugin_file'  => false,
					'network_wide' => false,
				);
			}

			if ( 'download' === $action ) {
				if ( ! current_user_can( 'install_plugins' ) ) {
					return false;
				}

				return array(
					'extension'    => $extension,
					'plugin_file'  => false,
					'network_wide' => false,
				);
			}

			$plugin = $api->get_wordpress_plugin( $extension );
			if ( ! $plugin ) {
				return false;
			}

			$plugin_file  = $plugin['file'];
			$network_wide = is_multisite() && is_network_admin();

			if ( is_multisite() && ! $network_wide ) {
				if ( ( 'activate' === $action && is_network_only_plugin( $plugin_file ) ) || is_plugin_active_for_network( $plugin_file ) ) {
					return false;
				}
			}

			if ( ! current_user_can( $action . '_plugin', $plugin_file ) ) {
				return false;
			}

			return array(
				'extension'    => $extension,
				'plugin_file'  => $plugin_file,
				'network_wide' => $network_wide,
			);
		}

		/**
		 * Check whether the current user may run an extension mutation.
		 *
		 * @param string                    $action         Extension action.
		 * @param string                    $extension_slug Registered extension slug.
		 * @param FooGallery_Extensions_API $api            Optional API instance.
		 * @return bool Whether the action is authorized.
		 */
		public static function can_manage_extension_action( $action, $extension_slug, $api = null ) {
			return false !== self::authorize_extension_action( $action, $extension_slug, $api );
		}

		function handle_extension_action() {

			$action         = sanitize_key( safe_get_from_request( 'action' ) );
			$extension_slug = sanitize_key( safe_get_from_request( 'extension' ) );
			$has_error      = safe_get_from_request( 'has_error' );
			$api            = null;
			$authorization  = false;

            if ( !empty( $extension_slug ) || $has_error ) {
                if ( !check_admin_referer( 'foogallery_extension_action' ) ) {
                    return;
                }

				$api = new FooGallery_Extensions_API();
				if ( $has_error && ! empty( $action ) ) {
					$authorization = false;
				} else {
					$authorization_action = $has_error ? 'deactivate' : $action;
					$authorization        = self::authorize_extension_action( $authorization_action, $extension_slug, $api );
				}

				if ( false === $authorization ) {
					wp_die(
						esc_html__( 'You do not have permission to manage FooGallery features.', 'foogallery' ),
						'',
						array( 'response' => 403 )
					);
				}
            }

			if ( ( 'download' === $action || 'activate' === $action || 'deactivate' === $action ) && $extension_slug ) {
				$fatal_error_redirect = remove_query_arg( 'action' );
				wp_redirect( add_query_arg( 'has_error', 'yes', $fatal_error_redirect ) ); // we'll override this later if the plugin can be included without fatal error
				ob_start();

				switch ( $action ) {
					case 'download':
						$result = $api->download( $extension_slug );
						break;
					case 'activate':
						$result = $api->activate( $extension_slug, true, $authorization['network_wide'] );
						break;
					case 'deactivate':
						$result = $api->deactivate( $extension_slug, true, false, $authorization['network_wide'] );
						break;
				}

				//if we get here then no fatal error - cool!
				if ( ob_get_length() > 0 ) {
					ob_end_clean();
				}

				//store the result in a short-lived transient
				if ( isset($result) ) {
					set_transient( FOOGALLERY_EXTENSIONS_MESSAGE_TRANSIENT_KEY, $result, 30 );
				}

				//first, remove unwanted query args
				$redirect_url = remove_query_arg( array( 'extension', 'action' ) );
				//then add a query arg for our message
				$redirect_url = add_query_arg( 'show_message', 'yes', $redirect_url );
				//finally, allow extensions to override their own redirect
				$redirect_url = apply_filters( 'foogallery_extensions_redirect_url-' . $extension_slug, $redirect_url, $action );

				//redirect to this page, so the plugin can be properly activated/deactivated etc
				if ( $redirect_url ) {
					wp_redirect( $redirect_url );
					die();
				}
			} else if ( $has_error ) {
				$api->deactivate( $extension_slug, true, false, $authorization['network_wide'] );

				$result = array(
					'message' => __( 'The extension could not be activated due to an error!', 'foogallery' ),
					'type'    => 'error',
				);

				set_transient( FOOGALLERY_EXTENSIONS_MESSAGE_TRANSIENT_KEY, $result, 30 );

				$api->add_to_error_extensions( $extension_slug, __( 'Activation Error!', 'foogallery' ) );

				//first, remove unwanted query args
				$redirect_url = remove_query_arg( array( 'extension', 'action', 'has_error' ) );
				//then add a query arg for our message
				$redirect_url = add_query_arg( 'show_message', 'yes', $redirect_url );

				wp_redirect( $redirect_url );
			}
		}
	}
}
