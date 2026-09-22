<?php

/*
Plugin Name: FooGallery
Description: FooGallery is the most intuitive and extensible gallery management tool ever created for WordPress
Version:     3.3.7
Author:      FooPlugins
Plugin URI:  https://fooplugins.com/foogallery-wordpress-gallery-plugin/
Author URI:  https://fooplugins.com
Text Domain: foogallery
License:     GPL-2.0+
Domain Path: /languages
Requires at least: 6.8
Requires PHP: 7.2
*/
// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
    die;
}
// Stop before loading dependencies on unsupported runtimes.
if ( version_compare( PHP_VERSION, '7.2', '<' ) || version_compare( $GLOBALS['wp_version'], '6.8', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'FooGallery requires WordPress 6.8 and PHP 7.2 or newer.', 'foogallery' ) . '</p></div>';
    } );
    return;
}
if ( function_exists( 'foogallery_fs' ) ) {
    foogallery_fs()->set_basename( false, __FILE__ );
} else {
    if ( !class_exists( 'FooGallery_Plugin' ) ) {
        define( 'FOOGALLERY_SLUG', 'foogallery' );
        define( 'FOOGALLERY_PATH', plugin_dir_path( __FILE__ ) );
        define( 'FOOGALLERY_URL', plugin_dir_url( __FILE__ ) );
        define( 'FOOGALLERY_FILE', __FILE__ );
        define( 'FOOGALLERY_VERSION', '3.3.7' );
        define( 'FOOGALLERY_SETTINGS_VERSION', '2' );
        if ( file_exists( FOOGALLERY_PATH . 'vendor-scoped/scoper-autoload.php' ) ) {
            require_once FOOGALLERY_PATH . 'vendor-scoped/scoper-autoload.php';
        } elseif ( file_exists( FOOGALLERY_PATH . 'vendor-scoped/autoload.php' ) ) {
            require_once FOOGALLERY_PATH . 'vendor-scoped/autoload.php';
        }
        // Register the bundled version before plugins_loaded priority zero.
        require_once FOOGALLERY_PATH . 'lib/action-scheduler/action-scheduler.php';
        require_once FOOGALLERY_PATH . 'includes/background-jobs/class-foogallery-jobs.php';
        require_once FOOGALLERY_PATH . 'includes/constants.php';
        require_once FOOGALLERY_PATH . 'includes/functions.php';
        // Create a helper function for easy SDK access.
        function foogallery_fs() {
            global $foogallery_fs;
            if ( !isset( $foogallery_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/freemius/start.php';
                $foogallery_fs = fs_dynamic_init( array(
                    'id'               => '843',
                    'slug'             => 'foogallery',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_d87616455a835af1d0658699d0192',
                    'anonymous_mode'   => foogallery_freemius_is_anonymous(),
                    'is_premium'       => false,
                    'has_paid_plans'   => true,
                    'has_addons'       => true,
                    'has_affiliation'  => 'selected',
                    'trial'            => array(
                        'days'               => 7,
                        'is_require_payment' => false,
                    ),
                    'menu'             => array(
                        'slug'       => 'edit.php?post_type=' . FOOGALLERY_CPT_GALLERY,
                        'first-path' => 'edit.php?post_type=' . FOOGALLERY_CPT_GALLERY . '&page=' . FOOGALLERY_ADMIN_MENU_HELP_SLUG,
                        'account'    => true,
                        'contact'    => false,
                        'support'    => false,
                    ),
                    'is_live'          => true,
                    'is_org_compliant' => true,
                ) );
            }
            return $foogallery_fs;
        }

        // Disable extension inventory sharing before SDK initialization, including cron requests.
        add_filter( 'fs_is_extensions_tracking_allowed_foogallery', '__return_false' );
        add_filter( 'fs_permission_list_foogallery', function ( $permissions ) {
            foreach ( $permissions as $key => $permission ) {
                if ( 'extensions' === $permission['id'] ) {
                    unset($permissions[$key]);
                }
            }
            return $permissions;
        } );
        // Init Freemius.
        foogallery_fs();
        // Signal that SDK was initiated.
        do_action( 'foogallery_fs_loaded' );
        require_once FOOGALLERY_PATH . 'includes/foopluginbase/bootstrapper.php';
        /**
         * FooGallery_Plugin class
         *
         * @package   FooGallery
         * @author    Brad Vincent <brad@fooplugins.com>
         * @license   GPL-2.0+
         * @link      https://github.com/fooplugins/foogallery
         * @copyright 2013 FooPlugins LLC
         */
        class FooGallery_Plugin extends Foo_Plugin_Base_v2_4 {
            private static $instance;

            public static function get_instance() {
                if ( !isset( self::$instance ) && !self::$instance instanceof FooGallery_Plugin ) {
                    self::$instance = new FooGallery_Plugin();
                }
                return self::$instance;
            }

            /**
             * Initialize the plugin by setting localization, filters, and administration functions.
             */
            private function __construct() {
                // include everything we need!
                require_once FOOGALLERY_PATH . 'includes/includes.php';
                // Resolve the per-site legacy entitlement before any extensions can load.
                FooGallery_Whitelabelling_Compatibility::snapshot_legacy_state();
                register_activation_hook( __FILE__, array('FooGallery_Plugin', 'activate') );
                FooGallery_License_Constant_Handler::init();
                // init FooPluginBase.
                $this->init(
                    FOOGALLERY_FILE,
                    FOOGALLERY_SLUG,
                    FOOGALLERY_VERSION,
                    'FooGallery'
                );
                add_filter( 'foogallery_default_options', 'foogallery_get_default_options' );
                // load text domain.
                add_action( 'init', array($this, 'load_plugin_textdomain') );
                // setup gallery post type.
                new FooGallery_PostTypes();
                // load any extensions.
                new FooGallery_Extensions_Loader();
                // Load any bundled extension initializers.
                new FooGallery_Import_Export_Extension();
                new FooGallery_Media_Audit_Extension();
                if ( is_admin() ) {
                    new FooGallery_Admin();
                    add_action( 'wpmu_new_blog', array($this, 'set_default_extensions_for_multisite_network_activated') );
                    foogallery_fs()->add_filter(
                        'plugin_icon',
                        array($this, 'freemius_plugin_icon'),
                        10,
                        1
                    );
                    foogallery_fs()->add_filter( 'pricing/show_annual_in_monthly', '__return_false' );
                    foogallery_fs()->add_filter( 'show_affiliate_program_notice', '__return_false' );
                    add_action( 'foogallery_admin_menu_before', array($this, 'add_freemius_activation_menu') );
                } else {
                    new FooGallery_Public();
                }
                // handles previews. Needed on both frontend and backend.
                new FooGallery_Previews();
                // initialize the thumbnail manager.
                new FooGallery_Thumb_Manager();
                new FooGallery_Shortcodes();
                new FooGallery_Thumbnails();
                new FooGallery_Attachment_Filters();
                new FooGallery_Retina();
                new FooGallery_Animated_Gif_Support();
                new FooGallery_Cache();
                new FooGallery_Lightbox();
                new FooGallery_Common_Fields();
                new FooGallery_No_Javascript_Fallback();
                new FooGallery_LazyLoad();
                new FooGallery_Paging();
                new FooGallery_Thumbnail_Dimensions();
                new FooGallery_Attachment_Filename();
                new FooGallery_Attachment_Custom_Class();
                new FooGallery_Compatibility();
                new FooGallery_Extensions_Compatibility();
                new FooGallery_Crop_Position();
                new FooGallery_ForceHttps();
                new FooGallery_Debug();
                new FooGallery_Password_Protect();
                $checker = new FooGallery_Version_Check();
                $checker->wire_up_checker();
                new FooGallery_Widget_Init();
                // include the default templates no matter what!
                new FooGallery_Default_Templates();
                // init the default media library datasource.
                new FooGallery_Datasource_MediaLibrary();
                // Initialize the FooGallery abilities bridge for WordPress core.
                new FooGallery_Abilities();
                new FooGallery_Attachment_Type();
                // Add upgrade rows for premium features that the current plan did not register.
                add_filter( 'foogallery_extensions_for_view', array($this, 'add_foogallery_pro_features') );
                add_filter( 'foogallery_extensions_for_view', array($this, 'add_foogallery_addon_features'), 20 );
                // Add consistent add-on and documentation links after all feature rows have been registered.
                add_filter( 'foogallery_extensions_for_view', array($this, 'add_foogallery_feature_links'), 30 );
                // init Gutenberg!
                new FooGallery_Gutenberg();
                // init advanced settings.
                new FooGallery_Advanced_Gallery_Settings();
                // init localization for FooGallery.
                new FooGallery_il8n();
            }

            function add_foogallery_pro_features( $extensions ) {
                $pro_features = foogallery_pro_features();
                $upgrade_features = array();
                $upgrade_features[] = array(
                    'slug'               => 'foogallery-bulk-copy',
                    'categories'         => array('Premium'),
                    'title'              => foogallery__( 'Bulk Copy', 'foogallery' ),
                    'description'        => $pro_features['bulk_copy']['desc'],
                    'external_link_text' => foogallery__( 'Read documentation', 'foogallery' ),
                    'external_link_url'  => $pro_features['bulk_copy']['link'],
                    'dashicon'           => 'dashicons-admin-page',
                    'tags'               => array('Premium'),
                    'source'             => 'upgrade',
                );
                $upgrade_features[] = array(
                    'slug'               => 'foogallery-exif',
                    'categories'         => array('Premium'),
                    'title'              => foogallery__( 'EXIF', 'foogallery' ),
                    'description'        => $pro_features['exif']['desc'],
                    'external_link_text' => foogallery__( 'Read documentation', 'foogallery' ),
                    'external_link_url'  => $pro_features['exif']['link'],
                    'dashicon'           => 'dashicons-camera',
                    'tags'               => array('Premium'),
                    'source'             => 'upgrade',
                );
                $upgrade_features[] = array(
                    'slug'               => 'foogallery-filtering',
                    'categories'         => array('Premium'),
                    'title'              => foogallery__( 'Filtering', 'foogallery' ),
                    'description'        => $pro_features['filtering']['desc'],
                    'external_link_text' => foogallery__( 'Read documentation', 'foogallery' ),
                    'external_link_url'  => $pro_features['filtering']['link'],
                    'dashicon'           => 'dashicons-filter',
                    'tags'               => array('Premium'),
                    'source'             => 'upgrade',
                );
                $upgrade_features[] = array(
                    'slug'               => 'foogallery-gallery-blueprints',
                    'categories'         => array('Premium'),
                    'title'              => foogallery__( 'Gallery Blueprints', 'foogallery' ),
                    'description'        => $pro_features['gallery_blueprints']['desc'],
                    'external_link_text' => foogallery__( 'Read documentation', 'foogallery' ),
                    'external_link_url'  => $pro_features['gallery_blueprints']['link'],
                    'dashicon'           => 'dashicons-networking',
                    'tags'               => array('Premium'),
                    'source'             => 'upgrade',
                );
                $upgrade_features[] = array(
                    'slug'               => 'foogallery-paging',
                    'categories'         => array('Premium'),
                    'title'              => foogallery__( 'Pagination', 'foogallery' ),
                    'description'        => $pro_features['pagination']['desc'],
                    'external_link_text' => foogallery__( 'Read documentation', 'foogallery' ),
                    'external_link_url'  => $pro_features['pagination']['link'],
                    'dashicon'           => 'dashicons-arrow-right-alt',
                    'tags'               => array('Premium'),
                    'source'             => 'upgrade',
                );
                $upgrade_features[] = array(
                    'slug'               => 'foogallery-protection',
                    'categories'         => array('Premium'),
                    'title'              => foogallery__( 'Watermarking & Protection', 'foogallery' ),
                    'description'        => $pro_features['protection']['desc'],
                    'external_link_text' => foogallery__( 'Read documentation', 'foogallery' ),
                    'external_link_url'  => $pro_features['protection']['link'],
                    'dashicon'           => 'dashicons-lock',
                    'tags'               => array('Premium'),
                    'source'             => 'upgrade',
                );
                $upgrade_features[] = array(
                    'slug'               => 'foogallery-video',
                    'categories'         => array('Premium'),
                    'title'              => foogallery__( 'Video', 'foogallery' ),
                    'description'        => $pro_features['video']['desc'],
                    'external_link_text' => foogallery__( 'Read documentation', 'foogallery' ),
                    'external_link_url'  => $pro_features['video']['link'],
                    'dashicon'           => 'dashicons-video-alt3',
                    'tags'               => array('Premium'),
                    'source'             => 'upgrade',
                );
                $upgrade_features[] = array(
                    'slug'               => 'foogallery-woocommerce',
                    'categories'         => array('Premium'),
                    'title'              => foogallery__( 'Ecommerce', 'foogallery' ),
                    'description'        => $pro_features['ecommerce']['desc'],
                    'external_link_text' => foogallery__( 'Read documentation', 'foogallery' ),
                    'external_link_url'  => $pro_features['ecommerce']['link'],
                    'dashicon'           => 'dashicons-cart',
                    'tags'               => array('Premium'),
                    'source'             => 'upgrade',
                );
                foreach ( $upgrade_features as $upgrade_feature ) {
                    if ( !$this->feature_exists_for_view( $extensions, $upgrade_feature['slug'] ) ) {
                        $extensions[] = $upgrade_feature;
                    }
                }
                return $extensions;
            }

            function add_foogallery_addon_features( $extensions ) {
                $addon_features = array(array(
                    'slug'               => 'foogallery-user-uploads',
                    'categories'         => array('Add-ons', 'Premium'),
                    'title'              => foogallery__( 'User Uploads', 'foogallery' ),
                    'description'        => foogallery__( 'Allow visitors to upload images and videos into galleries. Includes image moderation, form field customization, email notifications and more.', 'foogallery' ),
                    'external_link_text' => foogallery__( 'Learn More', 'foogallery' ),
                    'external_link_url'  => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/user-uploads/',
                    'addon_link_url'     => foogallery_fs()->addon_url( 'foogallery-user-uploads' ),
                    'dashicon'           => 'dashicons-upload',
                    'tags'               => array('Add-on', 'Premium'),
                    'source'             => 'addon',
                ), array(
                    'slug'               => 'foogallery-social',
                    'categories'         => array('Add-ons', 'Premium'),
                    'title'              => foogallery__( 'Social', 'foogallery' ),
                    'description'        => foogallery__( 'Allow your visitors to like, comment and share images to social networks.', 'foogallery' ),
                    'external_link_text' => foogallery__( 'Learn More', 'foogallery' ),
                    'external_link_url'  => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/social/',
                    'addon_link_url'     => foogallery_fs()->addon_url( 'foogallery-social' ),
                    'dashicon'           => 'dashicons-share',
                    'tags'               => array('Add-on', 'Premium'),
                    'source'             => 'addon',
                ), array(
                    'slug'              => 'foogallery-proofing',
                    'categories'        => array('Add-ons', 'Premium'),
                    'title'             => foogallery__( 'Client Proofing', 'foogallery' ),
                    'description'       => foogallery__( 'Create private proofing sessions so clients can select, reject, comment on, and submit gallery images for review.', 'foogallery' ),
                    'addon_link_url'    => foogallery_fs()->addon_url( 'foogallery-proofing' ),
                    'external_link_url' => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/client-proofing/',
                    'dashicon'          => 'dashicons-visibility',
                    'tags'              => array('Add-on', 'Premium'),
                    'source'            => 'addon',
                ));
                foreach ( $addon_features as $addon_feature ) {
                    if ( !$this->feature_exists_for_view( $extensions, $addon_feature['slug'] ) ) {
                        $extensions[] = $addon_feature;
                    }
                }
                return $extensions;
            }

            /**
             * Add add-on and documentation links to rows on the Features page.
             *
             * Documentation links are kept separate from the existing optional external
             * link so a feature can offer both without overloading the link label.
             *
             * @param array $extensions Feature rows prepared for the admin view.
             * @return array
             */
            public function add_foogallery_feature_links( $extensions ) {
                $documentation_links = array(
                    'albums'                          => 'https://fooplugins.com/documentation/foogallery/getting-started-foogallery/adding-albums/',
                    'foogallery-migrate'              => 'https://fooplugins.com/documentation/foogallery/getting-started-foogallery/migrate-to-foogallery/',
                    'foogallery-import-export'        => 'https://fooplugins.com/documentation/foogallery/getting-started-foogallery/import-export/',
                    'foogallery-media-audit'          => 'https://fooplugins.com/documentation/foogallery/getting-started-foogallery/foogallery-media-audit/',
                    'foogallery-custom-css'           => 'https://fooplugins.com/documentation/foogallery/developers/customize-gallery-custom-css/',
                    'foogallery-bulk-copy'            => 'https://fooplugins.com/bulk-copy-foogallery-pro/',
                    'foogallery-exif'                 => 'https://fooplugins.com/documentation/foogallery/pro-expert/adding-exif-data/',
                    'foogallery-filtering'            => 'https://fooplugins.com/documentation/foogallery/pro-expert/filtering-settings/',
                    'foogallery-gallery-blueprints'   => 'https://fooplugins.com/documentation/foogallery/pro-commerce/use-master-gallery/',
                    'foogallery-paging'               => 'https://fooplugins.com/documentation/foogallery/appearance-foogallery/paging-settings/',
                    'foogallery-protection'           => 'https://fooplugins.com/documentation/foogallery/pro-commerce/watermark-gallery-images/',
                    'foogallery-video'                => 'https://fooplugins.com/documentation/foogallery/pro-expert/foogallery-pro-video/',
                    'foogallery-woocommerce'          => 'https://fooplugins.com/documentation/foogallery/pro-commerce/getting-started-pro-commerce/',
                    'foogallery-colors'               => 'https://fooplugins.com/documentation/foogallery/pro-starter/dominant-color-sorting/',
                    'foogallery-whitelabelling'       => 'https://fooplugins.com/documentation/foogallery/addons/white-labeling/',
                    'foogallery-whitelabelling-addon' => 'https://fooplugins.com/documentation/foogallery/addons/white-labeling/',
                    'foogallery-user-uploads'         => 'https://fooplugins.com/documentation/foogallery/addons/user-uploads/',
                    'foogallery-social'               => 'https://fooplugins.com/documentation/foogallery/addons/social-getting-started/',
                    'foogallery-proofing'             => 'https://fooplugins.com/documentation/foogallery/client-proofing/client-proofing-workflow/',
                );
                $addon_features = array(
                    'albums',
                    'foogallery-exif',
                    'foogallery-filtering',
                    'foogallery-paging',
                    'foogallery-protection',
                    'foogallery-video',
                    'foogallery-woocommerce',
                    'foogallery-colors',
                    'foogallery-user-uploads',
                    'foogallery-social',
                    'foogallery-proofing'
                );
                $addon_links = array(
                    'albums'                          => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/wordpress-album-gallery/',
                    'foogallery-whitelabelling'       => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/whitelabel/',
                    'foogallery-whitelabelling-addon' => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/whitelabel/',
                );
                foreach ( $extensions as &$extension ) {
                    if ( !isset( $extension['slug'], $documentation_links[$extension['slug']] ) ) {
                        continue;
                    }
                    $documentation_url = $documentation_links[$extension['slug']];
                    $extension['documentation_link_text'] = foogallery__( 'Read documentation', 'foogallery' );
                    $extension['documentation_link_url'] = $documentation_url;
                    if ( isset( $addon_links[$extension['slug']] ) ) {
                        $extension['external_link_text'] = foogallery__( 'Visit add-on', 'foogallery' );
                        $extension['external_link_url'] = $addon_links[$extension['slug']];
                    } elseif ( isset( $extension['external_link_url'] ) && $documentation_url === $extension['external_link_url'] ) {
                        unset($extension['external_link_text'], $extension['external_link_url']);
                    } elseif ( 'foogallery-migrate' === $extension['slug'] && !empty( $extension['external_link_url'] ) ) {
                        $extension['external_link_text'] = foogallery__( 'View details', 'foogallery' );
                    } elseif ( in_array( $extension['slug'], $addon_features, true ) && !empty( $extension['external_link_url'] ) ) {
                        $extension['external_link_text'] = foogallery__( 'Visit add-on', 'foogallery' );
                    }
                }
                unset($extension);
                return $extensions;
            }

            private function feature_exists_for_view( $extensions, $slug ) {
                foreach ( $extensions as $extension ) {
                    if ( isset( $extension['slug'] ) && $slug === $extension['slug'] ) {
                        return true;
                    }
                }
                return false;
            }

            function add_freemius_activation_menu() {
                if ( foogallery_freemius_is_anonymous() ) {
                    return;
                }
                global $foogallery_fs;
                $parent_slug = foogallery_admin_menu_parent_slug();
                if ( !$foogallery_fs->is_registered() ) {
                    add_submenu_page(
                        $parent_slug,
                        __( 'FooGallery Opt-In', 'foogallery' ),
                        __( 'Activation', 'foogallery' ),
                        'manage_options',
                        'foogallery-optin',
                        array($foogallery_fs, '_connect_page_render')
                    );
                }
            }

            /**
             * Set Freemius plugin icon.
             *
             * @return string
             */
            public function freemius_plugin_icon( $icon ) {
                return FOOGALLERY_PATH . 'assets/foogallery.jpg';
            }

            /**
             * Set default extensions when a new site is created in multisite and FooGallery is network activated
             *
             * @since 1.2.5
             *
             * @param int $blog_id The ID of the newly created site
             */
            public function set_default_extensions_for_multisite_network_activated( $blog_id ) {
                switch_to_blog( $blog_id );
                if ( false === get_option( FOOGALLERY_EXTENSIONS_AUTO_ACTIVATED_OPTIONS_KEY, false ) ) {
                    $api = new FooGallery_Extensions_API();
                    $api->auto_activate_extensions();
                    update_option( FOOGALLERY_EXTENSIONS_AUTO_ACTIVATED_OPTIONS_KEY, true );
                }
                restore_current_blog();
            }

            /**
             * Fired when the plugin is activated.
             *
             * @since    1.0.0
             *
             * @param    boolean $network_wide       True if WPMU superadmin uses
             *                                       "Network Activate" action, false if
             *                                       WPMU is disabled or plugin is
             *                                       activated on an individual blog.
             */
            public static function activate( $network_wide ) {
                FooGallery_License_Constant_Handler::flag_activation();
                if ( function_exists( 'is_multisite' ) && is_multisite() ) {
                    if ( $network_wide ) {
                        // Get all blog ids
                        $blog_ids = self::get_blog_ids();
                        if ( is_array( $blog_ids ) ) {
                            foreach ( $blog_ids as $blog_id ) {
                                switch_to_blog( $blog_id );
                                self::single_activate();
                            }
                            restore_current_blog();
                        }
                    } else {
                        self::single_activate();
                    }
                } else {
                    self::single_activate( false );
                }
            }

            /**
             * Fired for each blog when the plugin is activated.
             *
             * @since    1.0.0
             */
            private static function single_activate( $multisite = true ) {
                if ( false === get_option( FOOGALLERY_EXTENSIONS_AUTO_ACTIVATED_OPTIONS_KEY, false ) ) {
                    $api = new FooGallery_Extensions_API();
                    $api->auto_activate_extensions();
                    update_option( FOOGALLERY_EXTENSIONS_AUTO_ACTIVATED_OPTIONS_KEY, true );
                }
                if ( false === $multisite ) {
                    // Make sure we redirect to the welcome page
                    set_transient( FOOGALLERY_ACTIVATION_REDIRECT_TRANSIENT_KEY, true, 30 );
                }
                // Set the 'advanced_attachment_modal' setting to 'on'
                if ( !foogallery_get_setting( 'advanced_attachment_modal' ) ) {
                    foogallery_set_setting( 'advanced_attachment_modal', 'on' );
                }
                // Force a version check on activation to make sure housekeeping is performed
                foogallery_perform_version_check();
            }

            /**
             * Get all blog ids of blogs in the current network that are:
             * - not archived
             * - not spam
             * - not deleted
             *
             * @since    1.0.0
             *
             * @return array The blog IDs.
             */
            private static function get_blog_ids() {
                $sites = get_sites();
                $blog_ids = array();
                foreach ( $sites as $site ) {
                    $blog_ids[] = $site->blog_id;
                }
                return $blog_ids;
            }

        }

    }
    FooGallery_Plugin::get_instance();
}