<?php
/*
 * FooGallery Admin Notices class
 */

if ( ! class_exists( 'FooGallery_Admin_Notices' ) ) {

	class FooGallery_Admin_Notices {

		public function __construct() {
			add_action( 'admin_notices', array( $this, 'display_thumb_test_notice' ) );
			add_action( 'admin_notices', array( $this, 'display_rating_notice' ) );

			add_action( 'foogallery_thumbnail_generation_test', array( $this, 'save_test_results' ) );
			add_action( 'wp_ajax_foogallery_admin_rating_notice_dismiss', array( $this, 'admin_rating_notice_dismiss' ) );

			add_action( 'admin_notices', array( $this, 'display_fooconvert_notice' ) );
			add_action( 'wp_ajax_foogallery_admin_fooconvert_notice_dismiss', array( $this, 'admin_fooconvert_notice_dismiss' ) );
		}

		function get_thumb_test_option() {
			$option = get_option( FOOGALLERY_OPTION_THUMB_TEST );

			if ( ! is_array( $option ) ) {
				return false;
			}

			return $option;
		}

		function should_run_tests() {
			$option       = $this->get_thumb_test_option();
			$option_value = $this->generate_option_value();

			if ( $option === false ) {
				//we have never run tests before, or the saved option is malformed.
				return true;
			}

			if ( ! array_key_exists( 'key', $option ) || $option_value !== $option['key'] ) {
				//either the PHP version or Host has changed. In either case, we should run tests again!
				return true;
			}

			if (
				! array_key_exists( 'results', $option ) ||
				! is_array( $option['results'] ) ||
				! array_key_exists( 'success', $option['results'] )
			) {
				return true;
			}

			return false;
		}

		function should_show_alert() {
			$option = $this->get_thumb_test_option();

			if (
				false === $option ||
				! array_key_exists( 'results', $option ) ||
				! is_array( $option['results'] ) ||
				! array_key_exists( 'success', $option['results'] )
			) {
				return false;
			}

			$results = $option['results'];

			//should show the alert if the tests were not a success
			return ! (bool) $results['success'];
		}

		function generate_option_value() {
			$php_version = phpversion();
			$host        = home_url();

			return "php$($php_version}-{$host}";
		}

		function save_test_results( $results ) {
			update_option( FOOGALLERY_OPTION_THUMB_TEST, array(
				'key'     => $this->generate_option_value(),
				'results' => $results,
			) );
		}

		/**
		 * Dismiss the admin rating notice forever
		 */
		function admin_rating_notice_dismiss() {
			if ( ! check_ajax_referer( 'foogallery_admin_rating_notice_dismiss', false, false ) ) {
				wp_send_json_error( array(
					'message' => __( 'Invalid security token.', 'foogallery' ),
				), 403 );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array(
					'message' => __( 'Insufficient permissions.', 'foogallery' ),
				), 403 );
			}

			update_option( 'foogallery_admin_rating_notice_dismiss', 'hide' );
			wp_send_json_success();
		}

		function should_show_rating_message() {
			//first try to get the saved option
			$show_message = get_option( 'foogallery_admin_rating_notice_dismiss', 0 );

			if ( 'hide' === $show_message ) {
				return false; //never show - user has dismissed
			}

			if ( 'show' === $show_message ) {
				return true; //always show - user has created 5 or more galleries
			}

			//do not show the notice if on activation page
			if ( foogallery_is_activation_page() ) {
				return false;
			}

			//we must show the message - get out early
			if ( 0 === $show_message ) {
				$galleries = get_posts( array(
					'post_type'     => FOOGALLERY_CPT_GALLERY,
					'post_status'   => array( 'publish', 'draft' ),
					'cache_results' => false,
					'nopaging'      => true,
				) );

				$gallery_count = $this->count_excluding_demos( $galleries );

				if ( $gallery_count >= 5 ) {
					update_option( 'foogallery_admin_rating_notice_dismiss', 'show' );
					return true;
				} else {
					return false;
				}
			}

			return true;
		}

		/**
		 * Get a count of galleries that are not auto-generated demos
		 *
		 * @param $galleries
		 *
		 * @return int
		 */
		function count_excluding_demos( $galleries ) {
			if ( ! is_array( $galleries ) ) {
				return 0;
			}

			$count = 0;
			foreach ( $galleries as $gallery ) {
				if ( strpos( $gallery->post_title, 'Demo : ' ) === false ) {
					$count++;
				}
			}

			return $count;
		}

		function display_rating_notice() {
			if ( $this->should_show_rating_message() ) {

				$url = 'https://fooplugins.link/please-rate-foogallery';
				?>
				<script type="text/javascript">
					(function( $ ) {
						$( document ).ready( function() {
							$( '.foogallery-rating-notice.is-dismissible' )
								.on( 'click', '.notice-dismiss', function( e ) {
									e.preventDefault();
									$.post( ajaxurl, {
										action: 'foogallery_admin_rating_notice_dismiss',
										url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
										_wpnonce: "<?php echo esc_attr( wp_create_nonce( 'foogallery_admin_rating_notice_dismiss' )); ?>"
									} );
								} );
						} );
					})( jQuery );
				</script>
				<style>
                    .foogallery-rating-notice {
                        border-left-color: #ff69b4;
                    }

                    .foogallery-rating-notice .dashicons-heart {
                        color: #ff69b4;
                    }
				</style>
				<div class="foogallery-rating-notice notice notice-success is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Thanks for using FooGallery', 'foogallery' ) ?> <span class="dashicons dashicons-heart"></span></strong>
						<br/>
						<?php esc_html_e( 'We noticed you have created 5 galleries in FooGallery. If you love FooGallery, please consider giving it a 5 star rating. Your positive ratings help spread the word and help us grow.', 'foogallery' ); ?>
						<br/>
						<br/>
						<a class="button button-primary button-large" target="_blank" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Rate FooGallery', 'foogallery' ); ?></a>
					</p>
				</div>
				<?php
			}
		}

		function display_thumb_test_notice() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! is_object( $screen ) ) {
				return;
			}

			// Only run on the galleries list page, not other FooGallery admin screens.
			if ( 'edit-foogallery' === $screen->id ) {

				if ( $this->should_run_tests() ) {
					$thumbs = new FooGallery_Thumbnails();
					$thumbs->run_thumbnail_generation_tests();
				}

				if ( $this->should_show_alert() ) {
					?>
					<div class="notice error">
						<p>
							<strong><?php esc_html_e( 'Thumbnail Generation Alert!', 'foogallery' ); ?></strong><br/>
							<?php esc_html_e( 'There is a problem generating thumbnails for your galleries. There could be a number of reasons which could cause this problem.', 'foogallery' ); ?>
							<br/>
							<?php esc_html_e( 'If thumbnails cannot be generated, then full-sized, uncropped images will be used instead. This will result in slow page load times, and thumbnails that do not look correct.', 'foogallery' ); ?>
							<br/>
							<a target="_blank"
							   href="https://fooplugins.com/documentation/foogallery/troubleshooting-foogallery/thumbnail-generation-alert-help/"><?php esc_html_e( 'View Troubleshooting Documentation', 'foogallery' ); ?></a>
							<br/>
						</p>
					</div>
					<?php
				}
			}
		}

		function display_fooconvert_notice() {
			if ( $this->should_display_fooconvert_notice() ) {

				$install_fooconvert = wp_nonce_url( add_query_arg( array(
					'action' => 'install-plugin',
					'plugin' => 'fooconvert',
				), admin_url( 'update.php' ) ), 'install-plugin_fooconvert' );
				?>
				<script type="text/javascript">
					(function( $ ) {
						$( document ).ready( function() {
							$( '.foogallery-fooconvert-notice.is-dismissible' )
								.on( 'click', '.notice-dismiss', function( e ) {
									e.preventDefault();
									$.post( ajaxurl, {
										action: 'foogallery_admin_fooconvert_notice_dismiss',
										url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
										_wpnonce: "<?php echo esc_attr( wp_create_nonce( 'foogallery_admin_fooconvert_notice_dismiss' )); ?>"
									} );
								} );
						} );
					})( jQuery );
				</script>
				<style>
                    .foogallery-fooconvert-notice {
                        border-left-color: #7c3aed;
                    }

                    .foogallery-fooconvert-notice .dashicons-format-chat {
                        color: #7c3aed;
                    }
				</style>
				<div class="foogallery-fooconvert-notice notice is-dismissible">
					<p>
						<strong><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'Are you looking for a Popup Builder for the block editor?', 'foogallery' ); ?></strong>
						<?php esc_html_e( 'FooConvert can help!', 'foogallery' ); ?>
						<br/>
						<?php esc_html_e( 'FooConvert is a free plugin for building bars, flyouts, and overlays in the WordPress block editor. Create conversion-focused campaigns with triggers, display rules, lead capture, and analytics.', 'foogallery' ); ?>
						<br/>
						<br/>
						<a class="button button-primary button-large" target="_blank"
						   href="<?php echo esc_url( $install_fooconvert ); ?>"><?php esc_html_e( 'Install FooConvert', 'foogallery' ); ?></a>
						<a class="button" target="_blank"
						   href="https://wordpress.org/plugins/fooconvert/"><?php esc_html_e( 'View Details', 'foogallery' ); ?></a>
					</p>
				</div>
				<?php
			}
		}

		function should_display_fooconvert_notice() {
			//do not show the notice to people who have FooConvert installed and activated
			if ( class_exists( 'FooPlugins\FooConvert\Init' ) ) {
				return false;
			}

			//do not show the notice to pro users
			if ( foogallery_is_pro() ) {
				return false;
			}

			//do not show the notice if on activation page
			if ( foogallery_is_activation_page() ) {
				return false;
			}

			//only show on foogallery pages
			if ( function_exists( 'get_current_screen' ) ) {
				$screen = get_current_screen();
				if ( isset( $screen ) ) {
					if ( $screen->post_type === FOOGALLERY_CPT_GALLERY || $screen->post_type === FOOGALLERY_CPT_ALBUM || $screen->id === FOOGALLERY_ADMIN_MENU_SETTINGS_SLUG ) {

						//first try to get the saved option
						$show_message = get_option( 'foogallery_admin_fooconvert_notice_dismiss', 0 );

						if ( 'hide' === $show_message ) {
							return false; //never show - user has dismissed
						}

						if ( ! $this->has_fooconvert_notice_update_grace_period_elapsed() ) {
							return false;
						}

						if ( 'show' === $show_message ) {
							return true; //always show - user has created 5 or more galleries
						}

						if ( 0 === $show_message ) {
							$oldest_gallery = get_posts( array(
								'post_type'   => FOOGALLERY_CPT_GALLERY,
								'post_status' => array( 'publish', 'draft' ),
								'order_by'    => 'publish_date',
								'order'       => 'ASC',
								'numberposts' => 1,
							) );

							if ( is_array( $oldest_gallery ) && count( $oldest_gallery ) > 0 ) {
								$oldest_gallery = $oldest_gallery[0];

								if ( strtotime( $oldest_gallery->post_date ) < strtotime( '-7 days' ) ) {
									//The oldest gallery is older than 7 days - so show the admin notice
									update_option( 'foogallery_admin_fooconvert_notice_dismiss', 'show' );

									return true;
								}
							}
						}
					}
				}
			}

			return false;
		}

		/**
		 * Check whether enough time has passed since the latest FooGallery update.
		 *
		 * @return bool
		 */
		function has_fooconvert_notice_update_grace_period_elapsed() {
			$updated_at = class_exists( 'FooGallery_Version_Check' ) ? FooGallery_Version_Check::get_last_update_time() : 0;

			if ( 0 === $updated_at ) {
				return false;
			}

			return ( time() - $updated_at ) >= ( 3 * DAY_IN_SECONDS );
		}

		/**
		 * Dismiss the admin FooConvert notice forever
		 */
		function admin_fooconvert_notice_dismiss() {
			if ( ! check_ajax_referer( 'foogallery_admin_fooconvert_notice_dismiss', false, false ) ) {
				wp_send_json_error( array(
					'message' => __( 'Invalid security token.', 'foogallery' ),
				), 403 );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array(
					'message' => __( 'Insufficient permissions.', 'foogallery' ),
				), 403 );
			}

			update_option( 'foogallery_admin_fooconvert_notice_dismiss', 'hide', false );
			wp_send_json_success();
		}
	}
}
