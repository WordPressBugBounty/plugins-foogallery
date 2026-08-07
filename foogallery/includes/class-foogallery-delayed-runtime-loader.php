<?php
/**
 * Delays the FooGallery frontend runtime when the current page is safe to do so.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FooGallery_Delayed_Runtime_Loader' ) ) {

	/**
	 * Class FooGallery_Delayed_Runtime_Loader
	 */
	class FooGallery_Delayed_Runtime_Loader {

		const CORE_HANDLE       = 'foogallery-core';
		const READY_HANDLE      = 'foogallery-ready';
		const DELAYED_LOADER_ID = 'foogallery-delayed-loader';

		/**
		 * Singleton instance.
		 *
		 * @var FooGallery_Delayed_Runtime_Loader
		 */
		private static $instance;

		/**
		 * Whether delayed loading is currently scheduled.
		 *
		 * @var bool
		 */
		private $scheduled = false;

		/**
		 * Whether the current page must use normal WordPress enqueueing.
		 *
		 * @var bool
		 */
		private $normal_required = false;

		/**
		 * The delayed runtime context for the page.
		 *
		 * @var array|null
		 */
		private $context = null;

		/**
		 * Get the shared instance.
		 *
		 * @return FooGallery_Delayed_Runtime_Loader
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Enqueue or schedule the FooGallery runtime.
		 *
		 * @param string[]|null $deps The script dependencies.
		 *
		 * @return void
		 */
		public function enqueue_core_gallery_template_script( $deps = null ) {
			$explicit_deps = isset( $deps );

			if ( $explicit_deps ) {
				$this->reset_core_handle();
			} else {
				$deps = apply_filters( 'foogallery_core_gallery_script_default_deps', $this->default_core_gallery_script_dependencies() );
			}

			$context = $this->build_core_gallery_script_context( $deps, $explicit_deps );

			if ( $this->normal_required ) {
				$context['fallback_reason'] = 'normal_enqueue_already_required';
				$this->enqueue_normal_core_gallery_template_script( $context );
				return;
			}

			if ( $this->should_delay_core_gallery_template_script( $context ) ) {
				$this->schedule_delayed_core_gallery_template_script( $context );
				return;
			}

			if ( $this->scheduled ) {
				$this->cancel_delayed_core_gallery_template_script();
			}

			$this->normal_required = true;
			$this->enqueue_normal_core_gallery_template_script( $context );
		}

		/**
		 * Get the default runtime dependencies.
		 *
		 * @return string[]
		 */
		private function default_core_gallery_script_dependencies() {
			return $this->is_frontend_request() ? array( 'jquery-core' ) : array( 'jquery' );
		}

		/**
		 * Check if the legacy runtime enqueue path should be forced.
		 *
		 * @return bool
		 */
		private function force_legacy_runtime_scripts() {
			if ( 'on' === foogallery_get_setting( 'force_legacy_runtime_scripts', false ) ) {
				return true;
			}

			if ( $this->setting_has_explicit_value( 'force_legacy_runtime_scripts' ) ) {
				return false;
			}

			if ( class_exists( 'FooGallery_Version_Check' ) ) {
				$stored_version = FooGallery_Version_Check::get_stored_version();

				if ( is_string( $stored_version ) && '' !== $stored_version && version_compare( $stored_version, '3.1.36', '<' ) ) {
					return true;
				}
			}

			return false;
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

		/**
		 * Build the runtime loading context.
		 *
		 * @param string[] $deps          The script dependencies.
		 * @param bool     $explicit_deps Whether dependencies were passed explicitly.
		 *
		 * @return array
		 */
		public function build_core_gallery_script_context( $deps, $explicit_deps = false ) {
			global $current_foogallery;
			global $current_foogallery_template;

			$filename_suffix = foogallery_is_debug() ? '' : '.min';
			$js              = apply_filters( 'foogallery_core_gallery_script', FOOGALLERY_DEFAULT_TEMPLATES_EXTENSION_SHARED_URL . 'js/foogallery' . $filename_suffix . '.js' );
			$deps            = apply_filters( 'foogallery_core_gallery_script_deps', $this->normalize_dependencies( $deps ) );
			$polyfills       = foogallery_get_setting( 'enqueue_polyfills', false );

			if ( $polyfills ) {
				foogallery_enqueue_polyfills();
				$deps[] = 'foogallery-polyfills';
			}

			$deps         = $this->normalize_dependencies( $deps );
			$js           = foogallery_resolve_asset_url( $js );
			$ready_src    = foogallery_resolve_asset_url( FOOGALLERY_DEFAULT_TEMPLATES_EXTENSION_SHARED_URL . 'js/foogallery.ready' . $filename_suffix . '.js' );
			$feature_deps = apply_filters( 'foogallery_feature_script_deps', array( self::CORE_HANDLE ) );
			$feature_deps = $this->normalize_dependencies( $feature_deps );
			$custom_js = foogallery_get_setting( 'custom_js', '' );
			$has_custom_js = is_string( $custom_js ) ? '' !== trim( $custom_js ) : ! empty( $custom_js );

			$context = array(
				'gallery'                      => $current_foogallery,
				'template'                     => $current_foogallery_template,
				'lightbox'                     => foogallery_gallery_template_setting_lightbox(),
				'js'                           => $js,
				'ready_src'                    => $ready_src,
				'deps'                         => $deps,
				'explicit_deps'                => $explicit_deps,
				'feature_deps'                 => $feature_deps,
				'has_custom_js'                => $has_custom_js,
				'force_legacy_runtime_scripts' => $this->force_legacy_runtime_scripts(),
				'polyfills_enabled'            => (bool) $polyfills,
				'legacy_lazyload'              => $this->gallery_uses_legacy_lazyload( $current_foogallery ),
				'dependency_assets'            => array(),
				'dependency_handles'           => array(),
				'missing_dependencies'         => array(),
				'fallback_reason'              => '',
			);

			$dependency_data                  = $this->resolve_dependency_assets( $deps );
			$context['dependency_assets']    = $dependency_data['assets'];
			$context['dependency_handles']   = $dependency_data['handles'];
			$context['missing_dependencies'] = $dependency_data['missing'];

			return $context;
		}

		/**
		 * Check if the current runtime context can be delayed.
		 *
		 * @param array $context Runtime context.
		 *
		 * @return bool
		 */
		public function should_delay_core_gallery_template_script( &$context ) {
			$should_delay = true;
			$reason       = '';

			if ( ! $this->is_frontend_request() ) {
				$should_delay = false;
				$reason       = 'not_frontend';
			} elseif ( empty( $context['gallery'] ) ) {
				$should_delay = false;
				$reason       = 'missing_gallery';
			} elseif ( 'foogallery' !== $context['lightbox'] ) {
				$should_delay = false;
				$reason       = 'unsupported_lightbox';
			} elseif ( $context['force_legacy_runtime_scripts'] ) {
				$should_delay = false;
				$reason       = 'force_legacy_runtime_scripts';
			} elseif ( $context['has_custom_js'] ) {
				$should_delay = false;
				$reason       = 'custom_js';
			} elseif ( $context['polyfills_enabled'] ) {
				$should_delay = false;
				$reason       = 'polyfills';
			} elseif ( $context['legacy_lazyload'] ) {
				$should_delay = false;
				$reason       = 'legacy_lazyload';
			} elseif ( ! empty( $context['missing_dependencies'] ) ) {
				$should_delay = false;
				$reason       = 'missing_dependencies';
			} elseif ( ! $this->dependencies_are_allowed( $context ) ) {
				$should_delay = false;
				$reason       = 'unsupported_dependencies';
			} elseif ( array( self::CORE_HANDLE ) !== array_values( $context['feature_deps'] ) ) {
				$should_delay = false;
				$reason       = 'feature_dependencies';
			} elseif ( $this->has_addon_dependencies( $context ) ) {
				$should_delay = false;
				$reason       = 'addon_dependencies';
			}

			$context['fallback_reason'] = $reason;
			$filtered_should_delay      = (bool) apply_filters( 'foogallery_delay_core_gallery_script_until_window_load', $should_delay, $context );

			if ( $should_delay && ! $filtered_should_delay && '' === $context['fallback_reason'] ) {
				$context['fallback_reason'] = 'filter_disabled';
			}

			return $should_delay && $filtered_should_delay;
		}

		/**
		 * Schedule the delayed runtime loader for the footer.
		 *
		 * @param array $context Runtime context.
		 *
		 * @return void
		 */
		public function schedule_delayed_core_gallery_template_script( $context ) {
			$this->register_runtime_handles( $context );
			do_action( 'foogallery_enqueue_script-core', $context['js'] );

			$this->context   = $context;
			$this->scheduled = true;

			if ( ! has_action( 'wp_footer', array( $this, 'print_delayed_core_gallery_template_script' ) ) ) {
				add_action( 'wp_footer', array( $this, 'print_delayed_core_gallery_template_script' ), 100 );
			}
		}

		/**
		 * Cancel a previously scheduled delayed runtime.
		 *
		 * @return void
		 */
		public function cancel_delayed_core_gallery_template_script() {
			remove_action( 'wp_footer', array( $this, 'print_delayed_core_gallery_template_script' ), 100 );

			if ( $this->scheduled ) {
				$this->reset_core_handle();
			}

			$this->scheduled = false;
			$this->context   = null;
		}

		/**
		 * Print the delayed runtime loader.
		 *
		 * @return void
		 */
		public function print_delayed_core_gallery_template_script() {
			if ( ! $this->scheduled || ! is_array( $this->context ) ) {
				return;
			}

			$items = $this->build_delayed_loader_items( $this->context );

			if ( empty( $items ) ) {
				return;
			}

			$payload = wp_json_encode( $items );
			if ( false === $payload ) {
				return;
			}

			ob_start();
			?>
			(function(items){
				var loader = document.currentScript;
				var nonce = loader ? (loader.nonce || loader.getAttribute("nonce") || "") : "";
				function applyNonce(script){
					if (nonce) {
						script.setAttribute("nonce", nonce);
					}
				}
				function runInline(code){
					var script = document.createElement("script");
					applyNonce(script);
					script.text = code;
					document.body.appendChild(script).parentNode.removeChild(script);
				}
				function reportFailure(item){
					var event;
					if (window.console && window.console.error) {
						window.console.error("FooGallery delayed runtime failed to load: " + item.src);
					}
					try {
						event = new CustomEvent("foogallery-runtime-error", { detail: item });
					} catch (error) {
						event = document.createEvent("CustomEvent");
						event.initCustomEvent("foogallery-runtime-error", false, false, item);
					}
					document.dispatchEvent(event);
				}
				function loadScript(item, done){
					if (item.handle === "jquery-core" && window.jQuery) {
						done();
						return;
					}
					var attempts = 0;
					function attempt(){
						var script = document.createElement("script");
						attempts += 1;
						applyNonce(script);
						script.src = item.src;
						script.async = false;
						script.onload = done;
						script.onerror = function(){
							if (script.parentNode) {
								script.parentNode.removeChild(script);
							}
							if (attempts < 2) {
								window.setTimeout(attempt, 250);
								return;
							}
							reportFailure(item);
						};
						document.body.appendChild(script);
					}
					attempt();
				}
				function next(index){
					var item;
					if (index >= items.length) {
						return;
					}
					item = items[index];
					if (item.type === "inline") {
						runInline(item.code);
						next(index + 1);
						return;
					}
					loadScript(item, function(){
						next(index + 1);
					});
				}
				function start(){
					next(0);
				}
				if (document.readyState === "complete") {
					start();
				} else {
					window.addEventListener("load", start, { once: true });
				}
			})(<?php echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>);
			<?php
			$loader_script = trim( ob_get_clean() );
			$attributes    = array( 'id' => self::DELAYED_LOADER_ID );

			if ( function_exists( 'wp_get_inline_script_tag' ) ) {
				// phpcs:ignore -- Compatibility is guarded by function_exists().
				echo wp_get_inline_script_tag( $loader_script, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core constructs and filters the complete script tag.
			} else {
				echo '<script id="' . esc_attr( self::DELAYED_LOADER_ID ) . '">' . $loader_script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The script is generated above from JSON-encoded registered assets.
			}
		}

		/**
		 * Check for add-on dependency signals.
		 *
		 * @param array $context Runtime context.
		 *
		 * @return bool
		 */
		public function has_addon_dependencies( $context ) {
			if ( array( self::CORE_HANDLE ) !== array_values( $context['feature_deps'] ) ) {
				return true;
			}

			if ( function_exists( 'foogallery_social_is_enabled_for_current_gallery' ) && foogallery_social_is_enabled_for_current_gallery() ) {
				return true;
			}

			if ( (bool) apply_filters( 'foogallery_is_social_addon_active', false ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Enqueue the runtime using the normal WordPress path.
		 *
		 * @param array $context Runtime context.
		 *
		 * @return void
		 */
		private function enqueue_normal_core_gallery_template_script( $context ) {
			wp_enqueue_script( self::CORE_HANDLE, $context['js'], $context['deps'], FOOGALLERY_VERSION );
			do_action( 'foogallery_enqueue_script-core', $context['js'] );

			$feature_deps = $context['feature_deps'];

			if ( $context['has_custom_js'] ) {
				$custom_assets = get_option( FOOGALLERY_OPTION_CUSTOM_ASSETS );
				if ( is_array( $custom_assets ) && array_key_exists( 'script', $custom_assets ) ) {
					wp_enqueue_script( 'foogallery-custom', $custom_assets['script'], $feature_deps, FOOGALLERY_VERSION );
					$feature_deps[] = 'foogallery-custom';
				}
			}

			wp_enqueue_script( self::READY_HANDLE, $context['ready_src'], $feature_deps, FOOGALLERY_VERSION );
		}

		/**
		 * Register handles needed for inline script collection.
		 *
		 * @param array $context Runtime context.
		 *
		 * @return void
		 */
		private function register_runtime_handles( $context ) {
			wp_register_script( self::CORE_HANDLE, $context['js'], $context['deps'], FOOGALLERY_VERSION );
			wp_register_script( self::READY_HANDLE, $context['ready_src'], array( self::CORE_HANDLE ), FOOGALLERY_VERSION );
		}

		/**
		 * Reset the core runtime handles and their inline state.
		 *
		 * @return void
		 */
		private function reset_core_handle() {
			wp_deregister_script( self::CORE_HANDLE );
			wp_deregister_script( self::READY_HANDLE );
			do_action( 'foogallery_dequeue_script-core' );
		}

		/**
		 * Build the delayed loader payload.
		 *
		 * @param array $context Runtime context.
		 *
		 * @return array
		 */
		private function build_delayed_loader_items( $context ) {
			$items = array();

			foreach ( $context['dependency_assets'] as $asset ) {
				if ( $this->script_handle_is_done( $asset['handle'] ) ) {
					continue;
				}
				$this->append_inline_items( $items, $asset['handle'], 'before' );
				$this->append_inline_items( $items, $asset['handle'], 'data' );
				$items[] = array(
					'type'   => 'script',
					'handle' => $asset['handle'],
					'src'    => esc_url_raw( $asset['src'] ),
				);
				$this->append_inline_items( $items, $asset['handle'], 'after' );
			}

			$this->append_inline_items( $items, self::CORE_HANDLE, 'before' );
			$this->append_inline_items( $items, self::CORE_HANDLE, 'data' );
			$items[] = array(
				'type'   => 'script',
				'handle' => self::CORE_HANDLE,
				'src'    => esc_url_raw( $context['js'] ),
			);
			$this->append_inline_items( $items, self::CORE_HANDLE, 'after' );
			$this->append_inline_items( $items, self::READY_HANDLE, 'before' );
			$this->append_inline_items( $items, self::READY_HANDLE, 'data' );
			$items[] = array(
				'type'   => 'script',
				'handle' => self::READY_HANDLE,
				'src'    => esc_url_raw( $context['ready_src'] ),
			);
			$this->append_inline_items( $items, self::READY_HANDLE, 'after' );

			return $items;
		}

		/**
		 * Append inline scripts for a handle.
		 *
		 * @param array  $items  Loader items.
		 * @param string $handle Script handle.
		 * @param string $key    Inline script key.
		 *
		 * @return void
		 */
		private function append_inline_items( &$items, $handle, $key ) {
			$wp_scripts = wp_scripts();
			$data       = $wp_scripts->get_data( $handle, $key );

			if ( empty( $data ) ) {
				return;
			}

			if ( ! is_array( $data ) ) {
				$data = array( $data );
			}

			foreach ( $data as $script ) {
				if ( '' === trim( $script ) ) {
					continue;
				}
				$items[] = array(
					'type'   => 'inline',
					'handle' => $handle,
					'code'   => $script,
				);
			}
		}

		/**
		 * Check if dependency handles are safe to delay.
		 *
		 * @param array $context Runtime context.
		 *
		 * @return bool
		 */
		private function dependencies_are_allowed( $context ) {
			$allowed = apply_filters(
				'foogallery_delayed_runtime_allowed_script_dependencies',
				array( 'jquery-core', 'jquery', 'jquery-migrate', 'masonry', 'imagesloaded' ),
				$context
			);

			if ( ! is_array( $allowed ) ) {
				$allowed = array();
			}

			$allowed = array_unique( array_map( 'strval', $allowed ) );
			$handles = array_unique( array_merge( $context['deps'], $context['dependency_handles'] ) );

			foreach ( $handles as $handle ) {
				if ( ! in_array( $handle, $allowed, true ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Resolve script dependency handles to loadable assets.
		 *
		 * @param string[] $deps Script dependencies.
		 *
		 * @return array
		 */
		private function resolve_dependency_assets( $deps ) {
			$wp_scripts = wp_scripts();
			$assets     = array();
			$handles    = array();
			$missing    = array();
			$seen       = array();

			foreach ( $deps as $handle ) {
				$this->resolve_dependency_asset( $handle, $wp_scripts, $assets, $handles, $missing, $seen );
			}

			return array(
				'assets'  => $assets,
				'handles' => array_values( array_unique( $handles ) ),
				'missing' => array_values( array_unique( $missing ) ),
			);
		}

		/**
		 * Resolve one script dependency handle.
		 *
		 * @param string     $handle     Script handle.
		 * @param WP_Scripts $wp_scripts Scripts registry.
		 * @param array      $assets     Resolved assets.
		 * @param array      $handles    Resolved handles.
		 * @param array      $missing    Missing handles.
		 * @param array      $seen       Handles already visited.
		 *
		 * @return void
		 */
		private function resolve_dependency_asset( $handle, $wp_scripts, &$assets, &$handles, &$missing, &$seen ) {
			if ( isset( $seen[ $handle ] ) ) {
				return;
			}

			$seen[ $handle ] = true;
			$handles[]       = $handle;

			if ( ! isset( $wp_scripts->registered[ $handle ] ) ) {
				$missing[] = $handle;
				return;
			}

			$registered = $wp_scripts->registered[ $handle ];

			if ( ! empty( $registered->deps ) ) {
				foreach ( $registered->deps as $dependency ) {
					$this->resolve_dependency_asset( $dependency, $wp_scripts, $assets, $handles, $missing, $seen );
				}
			}

			if ( empty( $registered->src ) ) {
				return;
			}

			$assets[] = array(
				'handle' => $handle,
				'src'    => $this->script_src( $registered, $wp_scripts ),
			);
		}

		/**
		 * Build a script URL from a registered script object.
		 *
		 * @param _WP_Dependency $registered Registered script.
		 * @param WP_Scripts     $wp_scripts Scripts registry.
		 *
		 * @return string
		 */
		private function script_src( $registered, $wp_scripts ) {
			$src = $registered->src;

			if ( ! preg_match( '|^(https?:)?//|', $src ) ) {
				if ( 0 === strpos( $src, '/' ) ) {
					$src = site_url( $src );
				} else {
					$src = $wp_scripts->base_url . $src;
				}
			}

			if ( null === $registered->ver ) {
				return $src;
			}

			$ver = $registered->ver ? $registered->ver : $wp_scripts->default_version;
			if ( $ver ) {
				$src = add_query_arg( 'ver', $ver, $src );
			}

			return $src;
		}

		/**
		 * Check if the current request can safely print a delayed frontend loader.
		 *
		 * @return bool
		 */
		private function is_frontend_request() {
			if ( is_admin() ) {
				return false;
			}

			if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
				return false;
			}

			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return false;
			}

			if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
				return false;
			}

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				return false;
			}

			if ( function_exists( 'is_feed' ) && did_action( 'wp' ) && is_feed() ) {
				return false;
			}

			return true;
		}

		/**
		 * Check if the current gallery is using legacy placeholder lazy loading.
		 *
		 * @param FooGallery|null $gallery The current gallery.
		 *
		 * @return bool
		 */
		public function gallery_uses_legacy_lazyload( $gallery ) {
			if ( ! is_object( $gallery ) ) {
				return false;
			}

			if ( ! isset( $gallery->lazyload_support ) || true !== $gallery->lazyload_support ) {
				return false;
			}

			if ( empty( $gallery->lazyload_enabled ) || ! empty( $gallery->lazyload_forced_disabled ) ) {
				return false;
			}

			$legacy_mode = class_exists( 'FooGallery_LazyLoad' ) ? FooGallery_LazyLoad::MODE_LEGACY : 'legacy';
			$mode        = isset( $gallery->lazyload_mode ) ? $gallery->lazyload_mode : foogallery_get_setting( 'lazy_loading_mode', '' );

			return $legacy_mode === $mode;
		}

		/**
		 * Normalize dependency handles.
		 *
		 * @param mixed $deps Dependency handles.
		 *
		 * @return array
		 */
		private function normalize_dependencies( $deps ) {
			if ( ! is_array( $deps ) ) {
				$deps = array();
			}

			$normalized = array();
			foreach ( $deps as $dep ) {
				if ( is_string( $dep ) && '' !== $dep ) {
					$normalized[] = $dep;
				}
			}

			return array_values( array_unique( $normalized ) );
		}

		/**
		 * Check if a script handle has already printed.
		 *
		 * @param string $handle Script handle.
		 *
		 * @return bool
		 */
		private function script_handle_is_done( $handle ) {
			$wp_scripts = wp_scripts();

			return is_array( $wp_scripts->done ) && in_array( $handle, $wp_scripts->done, true );
		}
	}
}
