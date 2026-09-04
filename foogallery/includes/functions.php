<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * FooGallery global functions
 *
 * @package   FooGallery
 * @author    Brad Vincent <brad@fooplugins.com>
 * @license   GPL-2.0+
 * @link      https://github.com/fooplugins/foogallery
 * @copyright 2014 FooPlugins LLC
 */
/**
 * Returns the name of the plugin. (Allows the name to be overridden from extensions or functions.php)
 * @return string
 */
function foogallery_plugin_name() {
    return apply_filters( 'foogallery_plugin_name', 'FooGallery' );
}

/**
 * Checks whether a plugin is active for the current site or network.
 *
 * WordPress caches option reads, so activation changes made during the current
 * request remain visible without causing repeated database queries.
 *
 * @param string $plugin_file Plugin file relative to the plugins directory.
 *
 * @return bool
 */
function foogallery_is_plugin_active(  $plugin_file  ) {
    $active_plugins = (array) get_option( 'active_plugins', array() );
    if ( in_array( $plugin_file, $active_plugins, true ) ) {
        return true;
    }
    if ( !is_multisite() ) {
        return false;
    }
    $network_active_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
    return isset( $network_active_plugins[$plugin_file] );
}

/**
 * Return all the gallery templates used within FooGallery
 *
 * @return array
 */
function foogallery_gallery_templates() {
    return apply_filters( 'foogallery_gallery_templates', array() );
}

/**
 * Return a specific gallery template based on the slug
 * @param $slug
 *
 * @return bool|array
 */
function foogallery_get_gallery_template(  $slug  ) {
    foreach ( foogallery_gallery_templates() as $template ) {
        if ( $slug == $template['slug'] ) {
            return $template;
        }
    }
    return false;
}

/**
 * Return the FooGallery extension API class
 *
 * @return FooGallery_Extensions_API
 */
function foogallery_extensions_api() {
    return new FooGallery_Extensions_API();
}

/**
 * Returns the default gallery template
 *
 * @return string
 */
function foogallery_default_gallery_template() {
    return foogallery_get_setting( 'gallery_template' );
}

/**
 * Returns the current WordPress major version as an integer.
 *
 * Examples:
 * - 6.8.1 => 6
 * - 7.0-beta2-61784 => 7
 *
 * @return int
 */
function foogallery_wp_version_major() {
    global $wp_version;
    preg_match( '/^(\\d+)/', (string) $wp_version, $wp_version_matches );
    return (int) ($wp_version_matches[1] ?? 0);
}

/**
 * Returns if gallery permalinks are enabled
 *
 * @return bool
 */
function foogallery_permalinks_enabled() {
    return foogallery_get_setting( 'gallery_permalinks_enabled' );
}

/**
 * Returns the gallery permalink
 *
 * @return string
 */
function foogallery_permalink() {
    return foogallery_get_setting( 'gallery_permalink' );
}

/**
 * Return the FooGallery saved setting, or a default value
 *
 * @param string $key The key for the setting
 *
 * @param bool $default The default if no value is saved or found
 *
 * @return mixed
 */
function foogallery_get_setting(  $key, $default = false  ) {
    $foogallery = FooGallery_Plugin::get_instance();
    $value = $foogallery->options()->get( $key, foogallery_get_default( $key, $default ) );
    return apply_filters( 'foogallery_get_setting-' . $key, $value, $default );
}

/**
 * Sets a specific option based on a key
 *
 * @param $key
 * @param $value
 *
 * @return mixed
 */
function foogallery_set_setting(  $key, $value  ) {
    $foogallery = FooGallery_Plugin::get_instance();
    return $foogallery->options()->save( $key, $value );
}

/**
 * Builds up a FooGallery gallery shortcode
 *
 * @param $gallery_id
 *
 * @return string
 */
function foogallery_build_gallery_shortcode(  $gallery_id  ) {
    return '[' . foogallery_gallery_shortcode_tag() . ' id="' . $gallery_id . '"]';
}

/**
 * Returns the gallery shortcode tag
 *
 * @return string
 */
function foogallery_gallery_shortcode_tag() {
    return apply_filters( 'foogallery_gallery_shortcode_tag', FOOGALLERY_CPT_GALLERY );
}

/**
 * Helper method for getting default settings
 *
 * @param string $key The default config key to retrieve.
 *
 * @param bool $default The default if no default is set or found
 *
 * @return string Key value on success, false on failure.
 */
function foogallery_get_default(  $key, $default = false  ) {
    $defaults = foogallery_get_default_options();
    // Return the key specified.
    return ( isset( $defaults[$key] ) ? $defaults[$key] : $default );
}

function foogallery_get_default_options() {
    $defaults = array(
        'gallery_template'                 => 'default',
        'gallery_permalinks_enabled'       => false,
        'gallery_permalink'                => 'gallery',
        'lightbox'                         => 'foogallery',
        'thumb_jpeg_quality'               => '90',
        'gallery_sorting'                  => '',
        'datasource'                       => 'media_library',
        'advanced_attachment_modal'        => 'on',
        'hide_editor_button'               => 'on',
        'thumb_resize_upscale_small'       => 'on',
        'thumb_resize_upscale_small_color' => 'auto',
        'force_legacy_runtime_scripts'     => false,
    );
    // A handy filter to override the defaults.
    $defaults = apply_filters( 'foogallery_defaults', $defaults );
    return $defaults;
}

/**
 * Returns the FooGallery Galleries Url within the admin
 *
 * @return string The Url to the FooGallery Gallery listing page in admin
 */
function foogallery_admin_gallery_listing_url() {
    return admin_url( 'edit.php?post_type=' . FOOGALLERY_CPT_GALLERY );
}

/**
 * Returns the FooGallery Add Gallery Url within the admin
 *
 * @return string The Url to the FooGallery Add Gallery page in admin
 */
function foogallery_admin_add_gallery_url() {
    return admin_url( 'post-new.php?post_type=' . FOOGALLERY_CPT_GALLERY );
}

/**
 * Returns the FooGallery help page Url within the admin
 *
 * @return string The Url to the FooGallery help page in admin
 */
function foogallery_admin_help_url() {
    return foogallery_admin_url_for_page( FOOGALLERY_ADMIN_MENU_HELP_SLUG );
}

/**
 * Returns the FooGallery settings page Url within the admin
 *
 * @return string The Url to the FooGallery settings page in admin
 */
function foogallery_admin_settings_url() {
    return foogallery_admin_url_for_page( FOOGALLERY_ADMIN_MENU_SETTINGS_SLUG );
}

/**
 * DEPRECATED!
 *
 * @return string The Url to the FooGallery extensions page in admin
 */
function foogallery_admin_extensions_url() {
    return '';
}

/**
 * Returns the FooGallery features page Url within the admin
 *
 * @return string The Url to the FooGallery extensions page in admin
 */
function foogallery_admin_features_url() {
    return foogallery_admin_url_for_page( FOOGALLERY_ADMIN_MENU_FEATURES_SLUG );
}

/**
 * Returns the FooGallery system info page Url within the admin
 *
 * @return string The Url to the FooGallery system info page in admin
 */
function foogallery_admin_systeminfo_url() {
    return foogallery_admin_url_for_page( FOOGALLERY_ADMIN_MENU_SYSTEMINFO_SLUG );
}

/**
 * Returns the FooGallery pricing page Url within the admin
 *
 * @return string The Url to the FooGallery pricing page in admin
 */
function foogallery_admin_pricing_url() {
    return foogallery_admin_url_for_page( FOOGALLERY_ADMIN_MENU_PRICING_SLUG );
}

/**
 * Returns the FooGallery addon page Url within the admin
 *
 * @return string The Url to the FooGallery addon page in admin
 */
function foogallery_admin_addon_url() {
    return foogallery_admin_url_for_page( FOOGALLERY_ADMIN_MENU_ADDON_SLUG );
}

/**
 * Returns the FooGallery free trial pricing page Url within the admin
 *
 * @return string The Url to the FooGallery free trial page in admin
 */
function foogallery_admin_freetrial_url() {
    return add_query_arg( 'trial', 'true', foogallery_admin_pricing_url() );
}

/**
 * Returns the FooGallery Url within the admin for a specific page
 *
 * @param string $admin_page The page to get the Url for
 *
 * @return string The Url to the FooGallery system info page in admin
 */
function foogallery_admin_url_for_page(  $admin_page  ) {
    return admin_url( add_query_arg( array(
        'page' => $admin_page,
    ), foogallery_admin_menu_parent_slug() ) );
}

/**
 * Get a foogallery template setting for the current foogallery that is being output to the frontend
 * @param string	$key
 * @param string	$default
 *
 * @return mixed
 */
function foogallery_gallery_template_setting(  $key, $default = ''  ) {
    if ( foogallery_gallery_template_mobile_key_is_declared( $key ) ) {
        return foogallery_gallery_template_mobile_setting( $key, $default );
    }
    return foogallery_gallery_template_setting_raw( $key, $default );
}

/**
 * Read a gallery template setting without applying the mobile entitlement
 * boundary. Callers must first establish that a responsive variant is Free.
 *
 * @param string $key            Setting key.
 * @param mixed  $default_value  Default value.
 * @param string $declared_alias Optional declared responsive alias.
 * @return mixed
 */
function foogallery_gallery_template_setting_raw(  $key, $default_value = '', $declared_alias = ''  ) {
    global $current_foogallery;
    global $current_foogallery_arguments;
    global $current_foogallery_template;
    $settings_key = "{$current_foogallery_template}_{$key}";
    $arguments_key = apply_filters( 'foogallery_gallery_template_argument_alias', $key, $current_foogallery_template );
    if ( $current_foogallery_arguments && $arguments_key !== $key && array_key_exists( $arguments_key, $current_foogallery_arguments ) ) {
        // Try to get the value from the arguments using the alias.
        $value = $current_foogallery_arguments[$arguments_key];
    } elseif ( $current_foogallery_arguments && '' !== $declared_alias && array_key_exists( $declared_alias, $current_foogallery_arguments ) ) {
        // Try the alias declared by an explicitly Free responsive field.
        $value = $current_foogallery_arguments[$declared_alias];
    } elseif ( $current_foogallery_arguments && array_key_exists( $key, $current_foogallery_arguments ) ) {
        // Try to get the value from the arguments using the original key.
        $value = $current_foogallery_arguments[$key];
    } elseif ( !empty( $current_foogallery ) && $current_foogallery->settings && array_key_exists( $settings_key, $current_foogallery->settings ) ) {
        // Then get the value out of the saved gallery settings.
        $value = $current_foogallery->settings[$settings_key];
    } else {
        // Otherwise set it to the default.
        $value = $default_value;
    }
    $value = apply_filters( 'foogallery_gallery_template_setting-' . $key, $value );
    // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Existing public hook.
    return $value;
}

/**
 * Returns the gallery template setting for lightbox
 *
 * @return string
 */
function foogallery_gallery_template_setting_lightbox() {
    return foogallery_gallery_template_setting( 'lightbox', 'foogallery' );
}

/**
 * Get the admin menu parent slug
 * @return string
 */
function foogallery_admin_menu_parent_slug() {
    return apply_filters( 'foogallery_admin_menu_parent_slug', FOOGALLERY_ADMIN_MENU_PARENT_SLUG );
}

/**
 * Helper function to build up the admin menu Url
 * @param array $extra_args
 *
 * @return string|void
 */
function foogallery_build_admin_menu_url(  $extra_args = array()  ) {
    $url = admin_url( foogallery_admin_menu_parent_slug() );
    if ( !empty( $extra_args ) ) {
        $url = add_query_arg( $extra_args, $url );
    }
    return $url;
}

/**
 * Helper function for adding a foogallery sub menu
 *
 * @param string   $menu_title Menu title.
 * @param string   $capability Required capability.
 * @param string   $menu_slug  Menu slug.
 * @param callable $function   Page-render callback.
 * @return string|false Resulting page hook suffix, or false when the user lacks the required capability.
 */
function foogallery_add_submenu_page(
    $menu_title,
    $capability,
    $menu_slug,
    $function
) {
    return add_submenu_page(
        foogallery_admin_menu_parent_slug(),
        $menu_title,
        $menu_title,
        apply_filters( 'foogallery_admin_menu_capability', $capability ),
        $menu_slug,
        $function
    );
}

/**
 * Returns all FooGallery galleries
 *
 * @return FooGallery[] array of FooGallery galleries
 */
function foogallery_get_all_galleries(  $excludes = false, $extra_args = false  ) {
    $args = array(
        'post_type'     => FOOGALLERY_CPT_GALLERY,
        'post_status'   => array('publish', 'draft'),
        'cache_results' => false,
        'nopaging'      => true,
    );
    if ( is_array( $excludes ) ) {
        $args['post__not_in'] = $excludes;
    }
    if ( is_array( $extra_args ) ) {
        $args = array_merge( $args, $extra_args );
    }
    $gallery_posts = get_posts( $args );
    if ( empty( $gallery_posts ) ) {
        return array();
    }
    update_meta_cache( 'post', wp_list_pluck( $gallery_posts, 'ID' ) );
    $galleries = array();
    foreach ( $gallery_posts as $post ) {
        $galleries[] = FooGallery::get( $post );
    }
    return $galleries;
}

/**
 * Parse some content and return an array of all gallery shortcodes that are used inside it
 *
 * @param $content The content to search for gallery shortcodes
 *
 * @return array An array of all the foogallery shortcodes found in the content
 */
function foogallery_extract_gallery_shortcodes(  $content  ) {
    $shortcodes = array();
    $regex_pattern = foogallery_gallery_shortcode_regex();
    if ( preg_match_all( '/' . $regex_pattern . '/s', $content, $matches ) ) {
        for ($i = 0; $i < count( $matches[0] ); ++$i) {
            $shortcode = $matches[0][$i];
            $args = $matches[3][$i];
            $attribure_string = str_replace( ' ', '&', trim( $args ) );
            $attribure_string = str_replace( '"', '', $attribure_string );
            $attributes = wp_parse_args( $attribure_string );
            if ( array_key_exists( 'id', $attributes ) ) {
                $id = intval( $attributes['id'] );
                $shortcodes[$id] = $shortcode;
            }
        }
    }
    return $shortcodes;
}

/**
 * Build up the FooGallery shortcode regex
 *
 * @return string
 */
function foogallery_gallery_shortcode_regex() {
    $tag = foogallery_gallery_shortcode_tag();
    return '\\[' . '(\\[?)' . "({$tag})" . '(?![\\w-])' . '(' . '[^\\]\\/]*' . '(?:' . '\\/(?!\\])' . '[^\\]\\/]*' . ')*?' . ')' . '(?:' . '(\\/)' . '\\]' . '|' . '\\]' . '(?:' . '(' . '[^\\[]*+' . '(?:' . '\\[(?!\\/\\2\\])' . '[^\\[]*+' . ')*+' . ')' . '\\[\\/\\2\\]' . ')?' . ')' . '(\\]?)';
    // 6: Optional second closing bracket for escaping shortcodes: [[tag]]
}

/**
 * Builds up a class attribute that can be used in a gallery template
 * @param $gallery FooGallery
 *
 * @return string the classname based on the gallery and any extra attributes
 */
function foogallery_build_class_attribute(  $gallery  ) {
    $classes[] = 'foogallery';
    $classes[] = 'foogallery-container';
    $classes[] = "foogallery-{$gallery->gallery_template}";
    $num_args = func_num_args();
    if ( $num_args > 1 ) {
        $arg_list = func_get_args();
        for ($i = 1; $i < $num_args; $i++) {
            $classes[] = $arg_list[$i];
        }
    }
    $classes = apply_filters( 'foogallery_build_class_attribute', $classes, $gallery );
    //extract any classes from the gallery arguments
    global $current_foogallery_arguments;
    if ( isset( $current_foogallery_arguments ) && is_array( $current_foogallery_arguments ) ) {
        if ( array_key_exists( 'classname', $current_foogallery_arguments ) ) {
            $classes[] = $current_foogallery_arguments['classname'];
        }
        if ( array_key_exists( 'classes', $current_foogallery_arguments ) ) {
            $classes[] = $current_foogallery_arguments['classes'];
        }
    }
    $classes = array_filter( $classes, 'strlen' );
    return implode( ' ', $classes );
}

/**
 * Builds up a SAFE class attribute that can be used in a gallery template
 * @param $gallery FooGallery
 *
 * @return string the classname based on the gallery and any extra attributes
 */
function foogallery_build_class_attribute_safe(  $gallery  ) {
    $args = func_get_args();
    $result = call_user_func_array( "foogallery_build_class_attribute", $args );
    return esc_attr( $result );
}

/**
 * Renders an escaped class attribute that can be used directly by gallery templates
 *
 * @param $gallery FooGallery
 */
function foogallery_build_class_attribute_render_safe(  $gallery  ) {
    $args = func_get_args();
    $result = call_user_func_array( "foogallery_build_class_attribute_safe", $args );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $result is already escaped via esc_attr() in foogallery_build_class_attribute_safe()
    echo $result;
}

/**
 * Removes privileged custom overrides from render arguments.
 *
 * Developer-only custom attributes and settings must come from saved gallery settings.
 *
 * @param mixed $args Render arguments.
 * @return mixed
 */
function foogallery_strip_custom_attribute_render_args(  $args  ) {
    if ( !is_array( $args ) ) {
        return $args;
    }
    unset($args['custom_attribute_key'], $args['custom_attribute_value'], $args['custom_settings']);
    return $args;
}

/**
 * Returns true when a value is a syntactically safe HTML attribute name.
 *
 * @param mixed $key Attribute key.
 * @return bool
 */
function foogallery_is_safe_html_attribute_key(  $key  ) {
    if ( !is_string( $key ) ) {
        return false;
    }
    $key = trim( $key );
    if ( '' === $key ) {
        return false;
    }
    if ( preg_match( '/[\\x00-\\x1F\\x7F\\s"\'=<>`]/', $key ) ) {
        return false;
    }
    if ( preg_match( '/^on/i', $key ) ) {
        return false;
    }
    return 1 === preg_match( '/^[A-Za-z_:][A-Za-z0-9_:\\.-]*$/', $key );
}

/**
 * Sanitizes a custom gallery container attribute key.
 *
 * By default, user-configured custom attributes are limited to data-* attributes.
 * Trusted code can allow additional safe attribute names through the
 * foogallery_custom_attribute_key_allowed filter.
 *
 * @param mixed $key     Attribute key.
 * @param mixed $gallery Gallery context.
 * @return string
 */
function foogallery_sanitize_custom_attribute_key(  $key, $gallery = null  ) {
    if ( !is_scalar( $key ) ) {
        return '';
    }
    $key = strtolower( trim( (string) wp_unslash( $key ) ) );
    if ( !foogallery_is_safe_html_attribute_key( $key ) ) {
        return '';
    }
    $allowed = 1 === preg_match( '/^data-[a-z0-9_-]+$/', $key );
    $allowed = apply_filters(
        'foogallery_custom_attribute_key_allowed',
        $allowed,
        $key,
        $gallery
    );
    return ( $allowed ? $key : '' );
}

/**
 * Sanitizes a custom gallery container attribute value.
 *
 * @param mixed $value Attribute value.
 * @return string
 */
function foogallery_sanitize_custom_attribute_value(  $value  ) {
    if ( !is_scalar( $value ) ) {
        return '';
    }
    return foogallery_sanitize_javascript( sanitize_text_field( wp_unslash( (string) $value ) ) );
}

/**
 * Builds up the attributes that are appended to a gallery template container
 *
 * @param $gallery    FooGallery
 * @param $attributes array
 *
 * @return string
 */
function foogallery_build_container_attributes_safe(  $gallery, $attributes  ) {
    //add the default gallery id
    $attributes['id'] = $gallery->container_id();
    //add the standard data-foogallery attribute so that the JS initializes correctly
    $attributes['data-foogallery'] = foogallery_build_container_data_options( $gallery, $attributes );
    // Add optional mobile overrides. The client merges these over the standard options on mobile.
    $mobile_data_options = foogallery_build_container_mobile_data_options( $gallery, $attributes );
    if ( '' !== $mobile_data_options ) {
        $attributes['data-foogallery-mobile'] = $mobile_data_options;
    }
    //allow others to add their own attributes globally
    $attributes = apply_filters( 'foogallery_build_container_attributes', $attributes, $gallery );
    //allow others to add their own attributes for a specific gallery template
    $attributes = apply_filters( 'foogallery_build_container_attributes-' . $gallery->gallery_template, $attributes, $gallery );
    //clean up the attributes to make them safe for output
    $html = '';
    foreach ( $attributes as $key => $value ) {
        $key = ( is_string( $key ) ? trim( $key ) : '' );
        if ( !foogallery_is_safe_html_attribute_key( $key ) ) {
            continue;
        }
        $safe_value = foogallery_esc_attr( $value );
        $html .= esc_attr( $key ) . '="' . $safe_value . '" ';
    }
    return apply_filters(
        'foogallery_build_container_attributes_html',
        $html,
        $attributes,
        $gallery
    );
}

/**
 * Builds up the data-foogallery attribute options that is used by the core javascript
 *
 * @param $gallery
 * @param $attributes
 *
 * @return string
 */
function foogallery_build_container_data_options(  $gallery, $attributes  ) {
    $options = apply_filters(
        'foogallery_build_container_data_options',
        array(),
        $gallery,
        $attributes
    );
    $options = apply_filters(
        'foogallery_build_container_data_options-' . $gallery->gallery_template,
        $options,
        $gallery,
        $attributes
    );
    return foogallery_json_encode( $options );
}

/**
 * Builds optional mobile overrides for the core JavaScript gallery options.
 *
 * The client recursively merges these options over data-foogallery when its
 * mobile mode is active. An empty result omits the data attribute entirely.
 *
 * @param FooGallery $gallery    Gallery instance.
 * @param array      $attributes Gallery container attributes.
 *
 * @return string JSON-encoded mobile options, or an empty string.
 */
function foogallery_build_container_mobile_data_options(  $gallery, $attributes  ) {
    $options = foogallery_build_mobile_field_data_options( array(), $gallery );
    $options = apply_filters(
        'foogallery_build_container_mobile_data_options',
        $options,
        $gallery,
        $attributes
    );
    $options = apply_filters(
        'foogallery_build_container_mobile_data_options-' . $gallery->gallery_template,
        $options,
        $gallery,
        $attributes
    );
    if ( !foogallery_mobile_settings_is_entitled() && foogallery_mobile_settings_is_core_template( $gallery->gallery_template ) && foogallery_gallery_template_has_paid_mobile_fields( $gallery->gallery_template ) ) {
        // Rebuild from declarations that are explicitly Free. This prevents a
        // mobile-options filter from smuggling paid values into a gated template.
        // Templates without paid declarations retain their existing filter API.
        $options = foogallery_build_mobile_field_data_options( array(), $gallery );
    }
    return ( empty( $options ) ? '' : foogallery_json_encode( $options ) );
}

/**
 * Add declaratively mapped mobile field values to the client options.
 *
 * A mobile field can declare a nested client option path using
 * `mobile.data_option`, for example array( 'template', 'rowHeight' ).
 *
 * @param array      $options Mobile client options collected so far.
 * @param FooGallery $gallery Gallery instance.
 *
 * @return array
 */
function foogallery_build_mobile_field_data_options(  $options, $gallery  ) {
    if ( !is_array( $options ) || !$gallery instanceof FooGallery ) {
        return ( is_array( $options ) ? $options : array() );
    }
    $template = foogallery_get_gallery_template( $gallery->gallery_template );
    if ( !is_array( $template ) || !isset( $template['fields'] ) || !is_array( $template['fields'] ) ) {
        return $options;
    }
    $fields = foogallery_get_fields_for_template( $template );
    foreach ( $fields as $field ) {
        if ( !isset( $field['mobile'] ) || !is_array( $field['mobile'] ) || empty( $field['mobile']['data_option'] ) || !is_array( $field['mobile']['data_option'] ) ) {
            continue;
        }
        $path = $field['mobile']['data_option'];
        $valid_path_keys = array_filter( $path, function ( $key ) {
            return is_string( $key ) && 1 === preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $key );
        } );
        if ( empty( $path ) || count( $valid_path_keys ) !== count( $path ) ) {
            continue;
        }
        $mobile_field = foogallery_get_mobile_field_for_template_field( $field, null, $gallery->gallery_template );
        if ( false === $mobile_field ) {
            continue;
        }
        $value = foogallery_gallery_template_mobile_setting( $mobile_field['id'], null );
        if ( !is_scalar( $value ) || '' === trim( (string) $value ) ) {
            continue;
        }
        $type = ( isset( $mobile_field['type'] ) ? $mobile_field['type'] : '' );
        $option_type = ( isset( $field['mobile']['data_option_type'] ) ? $field['mobile']['data_option_type'] : '' );
        if ( isset( $mobile_field['choices'] ) && is_array( $mobile_field['choices'] ) && !array_key_exists( (string) $value, $mobile_field['choices'] ) ) {
            continue;
        }
        if ( 'boolean' === $option_type ) {
            if ( !in_array( (string) $value, array(
                'true',
                'false',
                '1',
                '0'
            ), true ) ) {
                continue;
            }
            $value = in_array( (string) $value, array('true', '1'), true );
        } elseif ( 'number' === $type || 'slider' === $type ) {
            if ( !is_numeric( $value ) ) {
                continue;
            }
            $value = ( false === strpos( (string) $value, '.' ) ? intval( $value ) : floatval( $value ) );
            if ( isset( $mobile_field['min'] ) && is_numeric( $mobile_field['min'] ) ) {
                $value = max( $value, $mobile_field['min'] + 0 );
            }
            if ( isset( $mobile_field['max'] ) && is_numeric( $mobile_field['max'] ) ) {
                $value = min( $value, $mobile_field['max'] + 0 );
            }
        }
        $target =& $options;
        foreach ( $path as $key ) {
            if ( !isset( $target[$key] ) || !is_array( $target[$key] ) ) {
                $target[$key] = array();
            }
            $target =& $target[$key];
        }
        $target = $value;
        unset($target);
    }
    return $options;
}

/**
 * Return the shared mobile breakpoint used by gallery CSS and JavaScript.
 *
 * @return int Mobile breakpoint in pixels.
 */
function foogallery_get_mobile_size() {
    $mobile_size = absint( apply_filters( 'foogallery_mobile_size', 600 ) );
    return ( 0 === $mobile_size ? 600 : $mobile_size );
}

/**
 * Get the entitled per-setting mobile override provider.
 *
 * This intentionally has no filter. The provider is loaded from PRO and is
 * reached only after the existing Freemius checks succeed.
 *
 * @return FooGallery_Pro_Mobile_Settings|null
 */
function foogallery_mobile_settings_provider() {
    if ( !function_exists( 'foogallery_is_pro' ) || !foogallery_is_pro() || !function_exists( 'foogallery_fs' ) ) {
        return null;
    }
    $fs = foogallery_fs();
    if ( !is_object( $fs ) || !method_exists( $fs, 'is_plan_or_trial' ) || !$fs->is_plan_or_trial( FOOGALLERY_PRO_PLAN_STARTER ) ) {
        return null;
    }
    if ( !class_exists( 'FooGallery_Pro_Mobile_Settings', false ) ) {
        return null;
    }
    return FooGallery_Pro_Mobile_Settings::instance();
}

/**
 * Determine whether per-setting mobile overrides are entitled.
 *
 * @return bool
 */
function foogallery_mobile_settings_is_entitled() {
    return null !== foogallery_mobile_settings_provider();
}

/**
 * Return the built-in template slugs whose mobile settings are product-gated.
 *
 * This list intentionally has no filter. Third-party templates remain free to
 * declare their own responsive controls without weakening built-in gating.
 *
 * @return array
 */
function foogallery_mobile_settings_core_template_slugs() {
    return array(
        'default',
        'masonry',
        'justified',
        'simple_portfolio',
        'thumbnail',
        'carousel',
        'image-viewer',
        'foogridpro',
        'polaroid_new',
        'product',
        'slider',
        'spotlight'
    );
}

/**
 * Determine whether a template uses the built-in mobile product boundary.
 *
 * @param string $template_slug Template slug.
 * @return bool
 */
function foogallery_mobile_settings_is_core_template(  $template_slug  ) {
    return in_array( sanitize_key( $template_slug ), foogallery_mobile_settings_core_template_slugs(), true );
}

/**
 * Expand a mobile declaration without applying entitlement checks.
 *
 * @param array  $field         Desktop field definition.
 * @param mixed  $default_value Default value.
 * @param string $template_slug Template slug.
 * @return array|false
 */
function foogallery_expand_mobile_field_unchecked(  $field, $default_value = null, $template_slug = ''  ) {
    if ( !is_array( $field ) || !isset( $field['id'] ) || !isset( $field['mobile'] ) || true !== $field['mobile'] && !is_array( $field['mobile'] ) ) {
        return false;
    }
    $mobile = ( is_array( $field['mobile'] ) ? $field['mobile'] : array() );
    $mobile_field = array_replace( $field, $mobile );
    $mobile_field['id'] = ( isset( $mobile['id'] ) ? $mobile['id'] : 'mobile_' . $field['id'] );
    if ( !array_key_exists( 'alias', $mobile ) ) {
        $base_alias = ( !empty( $field['alias'] ) ? $field['alias'] : $field['id'] );
        $mobile_field['alias'] = 'mobile_' . $base_alias;
    }
    $mobile_field['default'] = ( array_key_exists( 'default', $mobile ) ? $mobile['default'] : $default_value );
    $mobile_field['_foogallery_mobile_variant'] = true;
    $mobile_field['_foogallery_mobile_free'] = foogallery_mobile_field_is_free( $field, $template_slug );
    if ( !array_key_exists( 'for', $mobile ) ) {
        unset($mobile_field['for']);
    }
    unset($mobile_field['mobile']);
    unset($mobile_field['free']);
    return $mobile_field;
}

/**
 * Determine whether a mobile declaration is explicitly allowed in Free.
 *
 * Built-in fields use a fixed exception for the legacy Free Columns setting,
 * so a public field-alteration filter cannot opt other built-in fields into
 * Free. A third-party template may still declare its own Free mobile fields.
 *
 * @param array  $field         Field definition.
 * @param string $template_slug Template slug.
 * @return bool
 */
function foogallery_mobile_field_is_free(  $field, $template_slug = ''  ) {
    if ( !is_array( $field ) || !isset( $field['mobile'] ) || !is_array( $field['mobile'] ) || true !== ($field['mobile']['free'] ?? false) ) {
        return false;
    }
    $template_slug = sanitize_key( $template_slug );
    if ( '' !== $template_slug && !foogallery_mobile_settings_is_core_template( $template_slug ) ) {
        return true;
    }
    $mobile_id = ( isset( $field['mobile']['id'] ) ? sanitize_key( $field['mobile']['id'] ) : '' );
    $mobile_alias = ( isset( $field['mobile']['alias'] ) ? sanitize_key( $field['mobile']['alias'] ) : '' );
    return ('' === $template_slug || 'default' === $template_slug) && 'layout' === sanitize_key( $field['id'] ?? '' ) && 'mobile_columns' === $mobile_id && 'mobile_columns' === $mobile_alias;
}

/**
 * Determine whether a setting key is a declared responsive field variant.
 *
 * This declaration-aware check protects existing third-party runtime calls to
 * foogallery_gallery_template_setting() without broadly gating unrelated keys
 * that merely contain the word "mobile".
 *
 * @param string $key Setting ID or alias.
 * @return bool
 */
function foogallery_gallery_template_mobile_key_is_declared(  $key  ) {
    global $current_foogallery;
    global $current_foogallery_template;
    static $checking_fields = false;
    if ( $checking_fields || empty( $current_foogallery_template ) || !is_string( $key ) || '' === $key ) {
        return false;
    }
    $template = foogallery_get_gallery_template( $current_foogallery_template );
    if ( !is_array( $template ) || !isset( $template['fields'] ) ) {
        return false;
    }
    $cache_key = 'mobile_setting_declarations_' . sanitize_key( $current_foogallery_template );
    if ( !empty( $current_foogallery ) && isset( $current_foogallery->cached_values[$cache_key] ) && is_array( $current_foogallery->cached_values[$cache_key] ) ) {
        return isset( $current_foogallery->cached_values[$cache_key][$key] );
    }
    $declarations = array();
    $checking_fields = true;
    try {
        foreach ( foogallery_get_fields_for_template( $template ) as $field ) {
            $mobile_field = foogallery_expand_mobile_field_unchecked( $field, null, $current_foogallery_template );
            if ( !is_array( $mobile_field ) || empty( $mobile_field['id'] ) ) {
                continue;
            }
            $declarations[$mobile_field['id']] = true;
            if ( !empty( $mobile_field['alias'] ) ) {
                $declarations[$mobile_field['alias']] = true;
            }
        }
    } finally {
        $checking_fields = false;
    }
    if ( !empty( $current_foogallery ) ) {
        if ( !isset( $current_foogallery->cached_values ) || !is_array( $current_foogallery->cached_values ) ) {
            $current_foogallery->cached_values = array();
        }
        $current_foogallery->cached_values[$cache_key] = $declarations;
    }
    return isset( $declarations[$key] );
}

/**
 * Read a declared mobile setting through the entitlement boundary.
 *
 * Paid values return a neutral null in Free rather than activating a caller's
 * mobile-specific default. The normal setting accessor remains unchanged for
 * unrelated keys such as lightbox_mobile_layout.
 *
 * @param string $key           Declared mobile field ID or alias.
 * @param mixed  $default_value Value when no mobile override is available.
 * @return mixed
 */
function foogallery_gallery_template_mobile_setting(  $key, $default_value = null  ) {
    global $current_foogallery_template;
    if ( empty( $current_foogallery_template ) ) {
        return $default_value;
    }
    $template = foogallery_get_gallery_template( $current_foogallery_template );
    if ( !is_array( $template ) || !isset( $template['fields'] ) ) {
        return $default_value;
    }
    foreach ( foogallery_get_fields_for_template( $template ) as $field ) {
        $mobile_field = foogallery_expand_mobile_field_unchecked( $field, $default_value, $current_foogallery_template );
        if ( !is_array( $mobile_field ) || !in_array( $key, array($mobile_field['id'], $mobile_field['alias'] ?? ''), true ) ) {
            continue;
        }
        if ( foogallery_mobile_field_is_free( $field, $current_foogallery_template ) ) {
            $mobile_default = ( array_key_exists( 'default', $mobile_field ) ? $mobile_field['default'] : $default_value );
            return foogallery_gallery_template_setting_raw( $mobile_field['id'], $mobile_default, $mobile_field['alias'] ?? '' );
        }
        $provider = foogallery_mobile_settings_provider();
        if ( null === $provider ) {
            return null;
        }
        return $provider->resolve_value( $mobile_field, $default_value );
    }
    return $default_value;
}

/**
 * Determine whether a template contains any paid mobile declarations.
 *
 * @param string $template Template slug.
 * @return bool
 */
function foogallery_gallery_template_has_paid_mobile_fields(  $template  ) {
    $template_slug = sanitize_key( $template );
    $template = foogallery_get_gallery_template( $template );
    if ( !is_array( $template ) || !isset( $template['fields'] ) ) {
        return false;
    }
    foreach ( foogallery_get_fields_for_template( $template ) as $field ) {
        if ( isset( $field['mobile'] ) && (true === $field['mobile'] || is_array( $field['mobile'] )) && !foogallery_mobile_field_is_free( $field, $template_slug ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Build the registered paid mobile storage-key map.
 *
 * Aliases map back to the canonical field ID so incoming alias keys can be
 * rejected while an existing canonical value is preserved.
 *
 * @param string $selected_template Optional selected template slug.
 * @param array  $candidate_settings Optional stored/submitted settings to scan.
 * @return array<string,string> Incoming storage key => canonical storage key.
 */
function foogallery_get_paid_mobile_setting_storage_keys(  $selected_template = '', $candidate_settings = array()  ) {
    $template_definitions = foogallery_gallery_templates();
    $selected_definition = foogallery_get_gallery_template( $selected_template );
    if ( is_array( $selected_definition ) ) {
        $template_definitions[] = $selected_definition;
    }
    $storage_keys = array();
    $free_storage_keys = array();
    $processed_templates = array();
    // Keep historical built-in slugs in Free so a downgrade can still freeze
    // paired values declared by a PRO-only field that is no longer registered.
    $template_slugs = foogallery_mobile_settings_core_template_slugs();
    if ( '' !== sanitize_key( $selected_template ) ) {
        $template_slugs[] = sanitize_key( $selected_template );
    }
    foreach ( $template_definitions as $template_definition ) {
        if ( !is_array( $template_definition ) || empty( $template_definition['slug'] ) ) {
            continue;
        }
        $template_slug = sanitize_key( $template_definition['slug'] );
        if ( isset( $processed_templates[$template_slug] ) ) {
            continue;
        }
        $processed_templates[$template_slug] = true;
        $template_slugs[] = $template_slug;
        foreach ( foogallery_get_fields_for_template( $template_definition ) as $field ) {
            $mobile_field = foogallery_expand_mobile_field_unchecked( $field, null, $template_slug );
            if ( !is_array( $mobile_field ) || empty( $mobile_field['id'] ) ) {
                continue;
            }
            $canonical_key = $template_slug . '_' . $mobile_field['id'];
            $alias_key = ( !empty( $mobile_field['alias'] ) ? $template_slug . '_' . $mobile_field['alias'] : '' );
            if ( foogallery_mobile_field_is_free( $field, $template_slug ) ) {
                $free_storage_keys[$canonical_key] = true;
                if ( '' !== $alias_key ) {
                    $free_storage_keys[$alias_key] = true;
                }
                continue;
            }
            $storage_keys[$canonical_key] = $canonical_key;
            if ( '' !== $alias_key && $canonical_key !== $alias_key ) {
                $storage_keys[$alias_key] = $canonical_key;
            }
        }
    }
    $template_slugs = array_values( array_unique( $template_slugs ) );
    foreach ( array_keys( ( is_array( $candidate_settings ) ? $candidate_settings : array() ) ) as $candidate_key ) {
        if ( !is_string( $candidate_key ) || isset( $free_storage_keys[$candidate_key] ) ) {
            continue;
        }
        foreach ( $template_slugs as $template_slug ) {
            if ( 0 === strpos( $candidate_key, $template_slug . '_mobile_' ) ) {
                if ( !isset( $storage_keys[$candidate_key] ) ) {
                    $storage_keys[$candidate_key] = $candidate_key;
                }
                break;
            }
        }
    }
    return $storage_keys;
}

/**
 * Render a foogallery
 *
 * @param       $gallery_id int The id of the foogallery you want to render
 * @param array $args
 */
function foogallery_render_gallery(  $gallery_id, $args = array()  ) {
    //create new instance of template engine
    $engine = new FooGallery_Template_Loader();
    $shortcode_args = wp_parse_args( $args, array(
        'id' => $gallery_id,
    ) );
    $engine->render_template( $shortcode_args );
}

/**
 * Returns the available sorting options that can be chosen for galleries and albums
 *
 * @param string $context Sorting context.
 */
function foogallery_sorting_options(  $context = 'gallery'  ) {
    return apply_filters( 'foogallery_sorting_options', array(
        ''              => __( 'Default', 'foogallery' ),
        'date_desc'     => __( 'Date created - newest first', 'foogallery' ),
        'date_asc'      => __( 'Date created - oldest first', 'foogallery' ),
        'modified_desc' => __( 'Date modified - most recent first', 'foogallery' ),
        'modified_asc'  => __( 'Date modified - most recent last', 'foogallery' ),
        'title_asc'     => __( 'Title - alphabetically', 'foogallery' ),
        'title_desc'    => __( 'Title - reverse', 'foogallery' ),
        'rand'          => __( 'Random', 'foogallery' ),
    ), $context );
}

function foogallery_sorting_get_posts_orderby_arg(  $sorting_option  ) {
    $orderby_arg = 'post__in';
    switch ( $sorting_option ) {
        case 'date_desc':
        case 'date_asc':
            $orderby_arg = 'date';
            break;
        case 'modified_desc':
        case 'modified_asc':
            $orderby_arg = 'modified';
            break;
        case 'title_asc':
        case 'title_desc':
            $orderby_arg = 'title';
            break;
        case 'rand':
            $orderby_arg = 'rand';
            break;
    }
    return apply_filters( 'foogallery_sorting_get_posts_orderby_arg', $orderby_arg, $sorting_option );
}

function foogallery_sorting_get_posts_order_arg(  $sorting_option  ) {
    $order_arg = 'DESC';
    switch ( $sorting_option ) {
        case 'date_asc':
        case 'modified_asc':
        case 'title_asc':
            $order_arg = 'ASC';
            break;
    }
    return apply_filters( 'foogallery_sorting_get_posts_order_arg', $order_arg, $sorting_option );
}

/**
 * Returns the effective gallery sort, including shortcode overrides.
 *
 * @param FooGallery $gallery Gallery instance.
 *
 * @return string
 */
function foogallery_sorting_get_effective_sort(  $gallery  ) {
    $sort = ( isset( $gallery->sorting ) ? $gallery->sorting : '' );
    global $current_foogallery_arguments;
    if ( isset( $current_foogallery_arguments ) && is_array( $current_foogallery_arguments ) && isset( $current_foogallery_arguments['sort'] ) ) {
        $shortcode_sort = sanitize_text_field( $current_foogallery_arguments['sort'] );
        if ( array_key_exists( $shortcode_sort, foogallery_sorting_options() ) ) {
            $sort = $shortcode_sort;
        }
    }
    return apply_filters( 'foogallery_sorting_effective_sort', $sort, $gallery );
}

/**
 * Returns true when query paging args must be applied after PHP sorting.
 *
 * @param string $sorting_option Selected sorting option.
 *
 * @return bool
 */
function foogallery_sorting_should_defer_query_args(  $sorting_option  ) {
    return apply_filters( 'foogallery_sorting_should_defer_query_args', false, $sorting_option );
}

/**
 * Stores and removes query paging args that must be applied after PHP sorting.
 *
 * @param array  $query_args     Attachment query args.
 * @param string $sorting_option Selected sorting option.
 *
 * @return array
 */
function foogallery_sorting_defer_query_args(  $query_args, $sorting_option  ) {
    global $foogallery_deferred_attachment_query_args;
    $foogallery_deferred_attachment_query_args = null;
    if ( !foogallery_sorting_should_defer_query_args( $sorting_option ) || !is_array( $query_args ) ) {
        return $query_args;
    }
    $foogallery_deferred_attachment_query_args = array(
        'posts_per_page' => ( isset( $query_args['posts_per_page'] ) ? intval( $query_args['posts_per_page'] ) : -1 ),
        'offset'         => ( isset( $query_args['offset'] ) ? intval( $query_args['offset'] ) : 0 ),
        'page'           => ( isset( $query_args['page'] ) ? intval( $query_args['page'] ) : 0 ),
        'paged'          => ( isset( $query_args['paged'] ) ? intval( $query_args['paged'] ) : 0 ),
    );
    $query_args['posts_per_page'] = -1;
    unset($query_args['offset'], $query_args['page'], $query_args['paged']);
    return $query_args;
}

/**
 * Applies deferred query paging args after PHP sorting.
 *
 * @param FooGalleryAttachment[] $attachments    Array of attachment objects.
 * @param string                 $sorting_option Selected sorting option.
 *
 * @return FooGalleryAttachment[]
 */
function foogallery_sorting_apply_deferred_query_args(  $attachments, $sorting_option  ) {
    if ( !foogallery_sorting_should_defer_query_args( $sorting_option ) || empty( $attachments ) || !is_array( $attachments ) ) {
        return $attachments;
    }
    global $foogallery_deferred_attachment_query_args;
    if ( empty( $foogallery_deferred_attachment_query_args ) || !is_array( $foogallery_deferred_attachment_query_args ) ) {
        return $attachments;
    }
    $posts_per_page = max( -1, intval( $foogallery_deferred_attachment_query_args['posts_per_page'] ) );
    $offset = max( 0, intval( $foogallery_deferred_attachment_query_args['offset'] ) );
    $page = max( intval( $foogallery_deferred_attachment_query_args['page'] ), intval( $foogallery_deferred_attachment_query_args['paged'] ) );
    if ( $posts_per_page > 0 && $page > 1 ) {
        $offset += ($page - 1) * $posts_per_page;
    }
    if ( $posts_per_page > 0 ) {
        return array_slice( $attachments, $offset, $posts_per_page );
    }
    if ( $offset > 0 ) {
        return array_slice( $attachments, $offset );
    }
    return $attachments;
}

/**
 * @deprecated 1.4.7 Default templates loaded by default and no longer activated via extension
 *
 * Activate the default templates extension when there are no gallery templates loaded
 */
function foogallery_activate_default_templates_extension() {
    //no longer needed but left in case any 3rd party extensions call this function
    _deprecated_function( __FUNCTION__, '1.4.7' );
}

/**
 * Allow FooGallery to enqueue stylesheet and allow them to be enqueued in the head on the next page load
 *
 * @param $handle string
 * @param $src string
 * @param array $deps
 * @param bool $ver
 * @param string $media
 */
function foogallery_enqueue_style(
    $handle,
    $src,
    $deps = array(),
    $ver = false,
    $media = 'all'
) {
    $src = apply_filters( 'foogallery_enqueue_style_src', $src, $handle );
    //resolve the asset URL to a fingerprinted version if available.
    $src = foogallery_resolve_asset_url( $src );
    wp_enqueue_style(
        $handle,
        $src,
        $deps,
        $ver,
        $media
    );
    do_action(
        'foogallery_enqueue_style',
        $handle,
        $src,
        $deps,
        $ver,
        $media
    );
}

/**
 * Returns all foogallery post objects that are attached to the post
 *
 * @param $post_id int The ID of the post
 *
 * @return array List of foogallery posts.
 */
function foogallery_get_galleries_attached_to_post(  $post_id  ) {
    $gallery_ids = get_post_meta( $post_id, FOOGALLERY_META_POST_USAGE, false );
    if ( !empty( $gallery_ids ) ) {
        return get_posts( array(
            'post_type'      => array(FOOGALLERY_CPT_GALLERY),
            'post_status'    => array('draft', 'publish'),
            'posts_per_page' => -1,
            'include'        => $gallery_ids,
        ) );
    }
    return array();
}

/**
 * Clears all css load optimization post meta
 */
function foogallery_clear_all_css_load_optimizations() {
    delete_post_meta_by_key( FOOGALLERY_META_POST_USAGE_CSS );
}

/**
 * Performs a check to see if the plugin has been updated, and perform any housekeeping if necessary
 */
function foogallery_perform_version_check() {
    $checker = new FooGallery_Version_Check();
    $checker->perform_check();
}

/**
 * Returns the JPEG quality used when generating thumbnails
 *
 * @return int The quality value stored in settings
 */
function foogallery_thumbnail_jpeg_quality() {
    $quality = intval( foogallery_get_setting( 'thumb_jpeg_quality' ) );
    //check if we get an invalid value for whatever reason and if so return a default of 80
    if ( $quality <= 0 ) {
        $quality = 80;
    }
    return $quality;
}

/**
 * Returns the caption title source setting
 *
 * @return string
 */
function foogallery_caption_title_source() {
    $source = foogallery_get_setting( 'caption_title_source', 'caption' );
    if ( empty( $source ) ) {
        $source = 'caption';
    }
    return $source;
}

/**
 * Returns the attachment caption title based on the caption_title_source setting
 *
 * @param WP_Post $attachment_post
 * @param bool $source
 *
 * @return string
 */
function foogallery_get_caption_title_for_attachment(  $attachment_post, $source = false  ) {
    if ( false === $source ) {
        $source = foogallery_gallery_template_setting( 'caption_title_source', false );
        if ( empty( $source ) || "none" === $source ) {
            $source = foogallery_caption_title_source();
        }
    }
    switch ( $source ) {
        case 'title':
            $caption = trim( $attachment_post->post_title );
            break;
        case 'desc':
            $caption = trim( $attachment_post->post_content );
            break;
        case 'alt':
            $caption = trim( get_post_meta( $attachment_post->ID, '_wp_attachment_image_alt', true ) );
            break;
        case 'filename':
            $caption = ( class_exists( 'FooGallery_Attachment_Filename' ) ? FooGallery_Attachment_Filename::get_filename( $attachment_post ) : '' );
            break;
        default:
            $caption = trim( $attachment_post->post_excerpt );
    }
    return apply_filters( 'foogallery_get_caption_title_for_attachment', $caption, $attachment_post );
}

/**
 * Returns the attachment caption title based on the caption_title_source setting
 *
 * @param FooGalleryAttachment $attachment
 * @param string $source
 * @param string $caption_type The type of caption (title or desc)
 *
 * @return string
 */
function foogallery_get_caption_by_source(  $attachment, $source, $caption_type  ) {
    if ( false === $source ) {
        $source = foogallery_gallery_template_setting( 'caption_' . $caption_type . '_source', false );
        if ( empty( $source ) || "none" === $source ) {
            if ( 'title' === $caption_type ) {
                $source = 'caption';
                //bad legacy naming!
            } else {
                $source = $caption_type;
            }
        }
    }
    switch ( $source ) {
        case 'title':
            $caption = trim( $attachment->title );
            break;
        case 'desc':
            $caption = trim( $attachment->description );
            break;
        case 'alt':
            $caption = trim( $attachment->alt );
            break;
        case 'filename':
            $caption = ( class_exists( 'FooGallery_Attachment_Filename' ) ? FooGallery_Attachment_Filename::get_filename( $attachment ) : '' );
            break;
        case 'caption':
        default:
            $caption = trim( $attachment->caption );
    }
    return apply_filters(
        'foogallery_get_caption_by_source',
        $caption,
        $attachment,
        $source,
        $caption_type
    );
}

/**
 * Returns the caption description source setting
 *
 * @return string
 */
function foogallery_caption_desc_source() {
    $source = foogallery_get_setting( 'caption_desc_source', 'desc' );
    if ( empty( $source ) ) {
        $source = 'desc';
    }
    return $source;
}

/**
 * Returns the attachment caption description based on the caption_desc_source setting
 *
 * @param WP_Post $attachment_post
 * @param bool $source
 *
 * @return string
 */
function foogallery_get_caption_desc_for_attachment(  $attachment_post, $source = false  ) {
    if ( false === $source ) {
        $source = foogallery_gallery_template_setting( 'caption_desc_source', false );
        if ( empty( $source ) || "none" === $source ) {
            $source = foogallery_caption_desc_source();
        }
    }
    if ( is_int( $attachment_post ) ) {
        $attachment_post = get_post( $attachment_post );
    }
    switch ( $source ) {
        case 'title':
            $caption = trim( $attachment_post->post_title );
            break;
        case 'caption':
            $caption = trim( $attachment_post->post_excerpt );
            break;
        case 'alt':
            $caption = trim( get_post_meta( $attachment_post->ID, '_wp_attachment_image_alt', true ) );
            break;
        case 'filename':
            $caption = ( class_exists( 'FooGallery_Attachment_Filename' ) ? FooGallery_Attachment_Filename::get_filename( $attachment_post ) : '' );
            break;
        default:
            $caption = trim( $attachment_post->post_content );
    }
    return apply_filters( 'foogallery_get_caption_desc_for_attachment', $caption, $attachment_post );
}

/**
 * Runs thumbnail tests and outputs results in a table format
 */
function foogallery_output_thumbnail_generation_results() {
    $thumbs = new FooGallery_Thumbnails();
    try {
        $results = $thumbs->run_thumbnail_generation_tests();
        if ( $results['success'] ) {
            echo '<span style="color:#0c0">' . esc_html__( 'Thumbnail generation test ran successfully.', 'foogallery' ) . '</span>';
        } else {
            echo '<span style="color:#c00">' . esc_html__( 'Thumbnail generation test failed!', 'foogallery' ) . '</span>';
            var_dump( $results['error'] );
            var_dump( $results['file_info'] );
        }
    } catch ( Exception $e ) {
        echo 'Exception: ' . esc_html( $e->getMessage() );
    }
}

/**
 * Returns the URL to the test image
 *
 * @return string
 */
function foogallery_test_thumb_url() {
    return apply_filters( 'foogallery_test_thumb_url', FOOGALLERY_URL . 'assets/logo.png' );
}

/**
 * Return all the gallery datasources used within FooGallery
 *
 * @return array
 */
function foogallery_gallery_datasources() {
    $default_datasource = foogallery_default_datasource();
    $datasources[$default_datasource] = array(
        'id'     => $default_datasource,
        'name'   => __( 'Media Library', 'foogallery' ),
        'label'  => __( 'From Media Library', 'foogallery' ),
        'public' => false,
    );
    return apply_filters( 'foogallery_gallery_datasources', $datasources );
}

/**
 * Returns the default gallery datasource
 *
 * @return string
 */
function foogallery_default_datasource() {
    return foogallery_get_default( 'datasource', 'media_library' );
}

/**
 * Returns the src to the built-in image placeholder
 * @return string
 */
function foogallery_image_placeholder_src() {
    return apply_filters( 'foogallery_image_placeholder_src', FOOGALLERY_URL . 'assets/image-placeholder.png' );
}

/**
 * Returns the image html for the built-in image placeholder
 *
 * @param array $args
 *
 * @return string
 */
function foogallery_image_placeholder_html(  $args  ) {
    if ( !isset( $args ) ) {
        $args = array(
            'width'  => 150,
            'height' => 150,
        );
    }
    $args['src'] = foogallery_image_placeholder_src();
    $args = array_map( 'esc_attr', $args );
    $html = '<img ';
    foreach ( $args as $name => $value ) {
        $html .= " {$name}=" . '"' . $value . '"';
    }
    $html .= ' />';
    return apply_filters( 'foogallery_image_placeholder_html', $html, $args );
}

/**
 * Returns the thumbnail html for the featured attachment for a gallery.
 * If no featured attachment can be found, then a placeholder image src is returned instead
 *
 * @param FooGallery $gallery
 * @param array $args
 *
 * @return string
 */
function foogallery_find_featured_attachment_thumbnail_html(  $gallery, $args = null  ) {
    if ( !isset( $gallery ) || false === $gallery ) {
        return '';
    }
    if ( !isset( $args ) ) {
        $args = array(
            'width'  => 150,
            'height' => 150,
        );
    }
    $featuredAttachment = $gallery->featured_attachment();
    if ( $featuredAttachment ) {
        return foogallery_attachment_html_image( $featuredAttachment, $args );
    } else {
        //if we have no featured attachment, then use the built-in image placeholder
        return foogallery_image_placeholder_html( $args );
    }
}

/**
 * Returns the thumbnail src for the featured attachment for a gallery.
 * If no featured attachment can be found, then a placeholder image src is returned instead
 *
 * @param FooGallery $gallery
 * @param array $args
 *
 * @return string
 */
function foogallery_find_featured_attachment_thumbnail_src(  $gallery, $args = null  ) {
    if ( !isset( $gallery ) || false === $gallery ) {
        return '';
    }
    if ( !isset( $args ) ) {
        $args = array(
            'width'  => 150,
            'height' => 150,
        );
    }
    $featuredAttachment = $gallery->featured_attachment();
    if ( $featuredAttachment ) {
        return $featuredAttachment->html_img_src( $args );
    } else {
        //if we have no featured attachment, then use the built-in image placeholder
        return foogallery_image_placeholder_src();
    }
}

/**
 * Returns the available retina options that can be chosen
 */
function foogallery_retina_options() {
    return apply_filters( 'foogallery_retina_options', array(
        '2x' => __( '2x', 'foogallery' ),
        '3x' => __( '3x', 'foogallery' ),
        '4x' => __( '4x', 'foogallery' ),
    ) );
}

/**
 * Does a full uninstall of the plugin including all data and settings!
 */
function foogallery_uninstall() {
    if ( !current_user_can( 'install_plugins' ) ) {
        exit;
    }
    //delete all gallery posts first
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall intentionally retrieves every matching ID immediately before deleting the posts.
    $gallery_post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} AS p WHERE p.post_type = %s", FOOGALLERY_CPT_GALLERY ) );
    if ( !empty( $gallery_post_ids ) ) {
        $deleted = 0;
        foreach ( $gallery_post_ids as $post_id ) {
            $del = wp_delete_post( $post_id );
            if ( false !== $del ) {
                ++$deleted;
            }
        }
    }
    //delete all options
    if ( is_network_admin() ) {
        delete_site_option( FOOGALLERY_SLUG );
    } else {
        delete_option( FOOGALLERY_SLUG );
    }
    delete_option( FOOGALLERY_OPTION_VERSION );
    delete_option( FOOGALLERY_OPTION_THUMB_TEST );
    delete_option( FOOGALLERY_EXTENSIONS_ACTIVATED_OPTIONS_KEY );
    delete_option( FOOGALLERY_EXTENSIONS_ERRORS_OPTIONS_KEY );
    //let any extensions clean up after themselves
    do_action( 'foogallery_uninstall' );
}

/**
 * Returns an attachment field friendly name, based on a field name that is passed in
 *
 * @param $field
 *
 * @return string
 */
function foogallery_get_attachment_field_friendly_name(  $field  ) {
    switch ( $field ) {
        case 'title':
            return __( 'Attachment Title', 'foogallery' );
        case 'caption':
            return __( 'Attachment Caption', 'foogallery' );
        case 'desc':
            return __( 'Attachment Description', 'foogallery' );
        case 'alt':
            return __( 'Attachment Alt', 'foogallery' );
    }
}

/**
 * Return the registered gallery-setting sections.
 *
 * Section and subsection IDs are stable, untranslated identifiers. Labels are
 * centralized here so field definitions can reference a section without
 * repeating translated strings or using translations as array keys.
 *
 * @return array
 */
function foogallery_get_gallery_setting_sections() {
    $labels = array(
        'advanced'       => __( 'Advanced', 'foogallery' ),
        'appearance'     => __( 'Appearance', 'foogallery' ),
        'buttons'        => __( 'Buttons', 'foogallery' ),
        'captions'       => __( 'Captions', 'foogallery' ),
        'colors'         => __( 'Colors', 'foogallery' ),
        'controls'       => __( 'Controls', 'foogallery' ),
        'ecommerce'      => __( 'Ecommerce', 'foogallery' ),
        'exif'           => __( 'EXIF', 'foogallery' ),
        'filtering'      => __( 'Filtering', 'foogallery' ),
        'general'        => __( 'General', 'foogallery' ),
        'hover-effects'  => __( 'Hover Effects', 'foogallery' ),
        'lightbox'       => __( 'Lightbox', 'foogallery' ),
        'master-product' => __( 'Master Product', 'foogallery' ),
        'paging'         => __( 'Paging', 'foogallery' ),
        'panel'          => __( 'Panel', 'foogallery' ),
        'protection'     => __( 'Protection', 'foogallery' ),
        'ribbons'        => __( 'Ribbons', 'foogallery' ),
        'search'         => __( 'Search', 'foogallery' ),
        'seo'            => __( 'SEO', 'foogallery' ),
        'slider'         => __( 'Slider', 'foogallery' ),
        'social'         => __( 'Social', 'foogallery' ),
        'thumbnails'     => __( 'Thumbnails', 'foogallery' ),
        'video'          => __( 'Video', 'foogallery' ),
    );
    $lightbox_subsections = array(
        'lightbox-general'    => array(
            'label' => $labels['general'],
            'order' => 0,
        ),
        'lightbox-controls'   => array(
            'label' => $labels['controls'],
            'order' => 10,
        ),
        'lightbox-thumbnails' => array(
            'label' => $labels['thumbnails'],
            'order' => 20,
        ),
        'lightbox-captions'   => array(
            'label' => $labels['captions'],
            'order' => 30,
        ),
    );
    $sections = array(
        'general'       => array(
            'label' => $labels['general'],
            'order' => 0,
        ),
        'lightbox'      => array(
            'label'       => $labels['lightbox'],
            'order'       => 1,
            'subsections' => $lightbox_subsections,
        ),
        'panel'         => array(
            'label'       => $labels['panel'],
            'order'       => 1,
            'subsections' => $lightbox_subsections,
        ),
        'slider'        => array(
            'label'       => $labels['slider'],
            'order'       => 1,
            'subsections' => $lightbox_subsections,
        ),
        'appearance'    => array(
            'label' => $labels['appearance'],
            'order' => 2,
        ),
        'hover-effects' => array(
            'label' => $labels['hover-effects'],
            'order' => 3,
        ),
        'captions'      => array(
            'label' => $labels['captions'],
            'order' => 4,
        ),
        'paging'        => array(
            'label' => $labels['paging'],
            'order' => 5,
        ),
        'colors'        => array(
            'label' => $labels['colors'],
            'order' => 5,
        ),
        'filtering'     => array(
            'label'       => $labels['filtering'],
            'order'       => 20,
            'subsections' => array(
                'filtering-general'  => array(
                    'label' => $labels['general'],
                    'order' => 0,
                ),
                'filtering-search'   => array(
                    'label' => $labels['search'],
                    'order' => 10,
                ),
                'filtering-advanced' => array(
                    'label' => $labels['advanced'],
                    'order' => 20,
                ),
            ),
        ),
        'exif'          => array(
            'label' => $labels['exif'],
            'order' => 25,
        ),
        'video'         => array(
            'label' => $labels['video'],
            'order' => 30,
        ),
        'ecommerce'     => array(
            'label'       => $labels['ecommerce'],
            'order'       => 40,
            'subsections' => array(
                'ecommerce-buttons'        => array(
                    'label' => $labels['buttons'],
                    'order' => 0,
                ),
                'ecommerce-ribbons'        => array(
                    'label' => $labels['ribbons'],
                    'order' => 10,
                ),
                'ecommerce-lightbox'       => array(
                    'label' => $labels['lightbox'],
                    'order' => 20,
                ),
                'ecommerce-master-product' => array(
                    'label' => $labels['master-product'],
                    'order' => 30,
                ),
            ),
        ),
        'protection'    => array(
            'label' => $labels['protection'],
            'order' => 50,
        ),
        'social'        => array(
            'label' => $labels['social'],
            'order' => 60,
        ),
        'seo'           => array(
            'label' => $labels['seo'],
            'order' => 98,
        ),
        'advanced'      => array(
            'label' => $labels['advanced'],
            'order' => 9999,
        ),
    );
    /**
     * Filter the gallery-setting section registry.
     *
     * Extensions should use stable section IDs and provide a translated label,
     * order, and optional subsection definitions.
     *
     * @param array $sections Registered gallery-setting sections.
     */
    $sections = apply_filters( 'foogallery_gallery_setting_sections', $sections );
    return ( is_array( $sections ) ? $sections : array() );
}

/**
 * Resolve the icon class for a gallery-setting section.
 *
 * Canonical section IDs are offered to the icon filter first. When no callback
 * handles the ID, the filter is retried with the legacy section slug so add-ons
 * written before section IDs continue to provide their tab icons.
 *
 * @param string $section_id    Canonical section ID.
 * @param string $section_label Translated section label.
 * @return string
 */
function foogallery_get_gallery_setting_section_icon(  $section_id, $section_label = ''  ) {
    $section_id = sanitize_key( $section_id );
    $icon_class = apply_filters( 'foogallery_gallery_settings_metabox_section_icon', $section_id );
    if ( $icon_class !== $section_id || '' === $section_label ) {
        return $icon_class;
    }
    $legacy_section_slug = apply_filters( 'foogallery_gallery_settings_metabox_section_slug', $section_label );
    if ( !is_scalar( $legacy_section_slug ) ) {
        return $icon_class;
    }
    $legacy_section_slug = (string) $legacy_section_slug;
    if ( '' === $legacy_section_slug || $section_id === $legacy_section_slug ) {
        return $icon_class;
    }
    $legacy_icon_class = apply_filters( 'foogallery_gallery_settings_metabox_section_icon', $legacy_section_slug );
    return ( $legacy_icon_class !== $legacy_section_slug ? $legacy_icon_class : $icon_class );
}

/**
 * Normalize a gallery field's section and subsection identifiers.
 *
 * Legacy third-party fields using translated `section` and `subsection`
 * values remain supported. FooGallery fields should declare `section_id` and
 * `subsection_id` directly.
 *
 * @param array      $field    Gallery field definition.
 * @param array|null $sections Optional preloaded section registry.
 * @return array
 */
function foogallery_normalize_gallery_setting_field_sections(  $field, $sections = null  ) {
    if ( !is_array( $field ) ) {
        return $field;
    }
    $sections = ( is_array( $sections ) ? $sections : foogallery_get_gallery_setting_sections() );
    $section_id = ( isset( $field['section_id'] ) ? sanitize_key( $field['section_id'] ) : '' );
    if ( empty( $section_id ) && isset( $field['section'] ) && is_scalar( $field['section'] ) ) {
        $legacy_label = (string) $field['section'];
        foreach ( $sections as $registered_id => $section ) {
            if ( isset( $section['label'] ) && $legacy_label === (string) $section['label'] ) {
                $section_id = sanitize_key( $registered_id );
                break;
            }
        }
        if ( empty( $section_id ) ) {
            $legacy_slug = apply_filters( 'foogallery_gallery_settings_metabox_section_slug', $legacy_label );
            $section_id = sanitize_title( $legacy_slug );
        }
    }
    if ( empty( $section_id ) ) {
        $section_id = 'general';
    }
    $field['section_id'] = $section_id;
    if ( empty( $field['subsection_id'] ) && isset( $field['subsection'] ) ) {
        if ( is_array( $field['subsection'] ) ) {
            $subsection_id = key( $field['subsection'] );
        } else {
            $subsection_id = ( is_scalar( $field['subsection'] ) ? $field['subsection'] : '' );
        }
        if ( is_string( $subsection_id ) ) {
            $field['subsection_id'] = sanitize_key( $subsection_id );
        }
    } elseif ( isset( $field['subsection_id'] ) ) {
        $field['subsection_id'] = sanitize_key( $field['subsection_id'] );
    }
    return $field;
}

/**
 * Determine whether a gallery setting field can be copied between galleries.
 *
 * @param array $field Gallery field definition.
 * @return bool
 */
function foogallery_gallery_setting_field_is_bulk_copyable(  $field  ) {
    if ( !is_array( $field ) || empty( $field['id'] ) ) {
        return false;
    }
    if ( !empty( $field['_foogallery_mobile_variant'] ) && empty( $field['_foogallery_mobile_free'] ) && !foogallery_mobile_settings_is_entitled() ) {
        return false;
    }
    if ( array_key_exists( 'bulk_copy', $field ) ) {
        $copyable = (bool) $field['bulk_copy'];
    } else {
        $field_type = ( isset( $field['type'] ) ? sanitize_key( $field['type'] ) : '' );
        $copyable = !in_array( $field_type, array(
            'help',
            'html',
            'promo',
            'warning',
            'color_status'
        ), true );
    }
    return (bool) apply_filters( 'foogallery_gallery_setting_field_is_bulk_copyable', $copyable, $field );
}

/**
 * Returns the fields for a specific gallery template
 *
 * @param $template mixed
 * @return mixed
 */
function foogallery_get_fields_for_template(  $template  ) {
    if ( is_string( $template ) ) {
        $template = foogallery_get_gallery_template( $template );
    }
    $fields = $template['fields'];
    // Allow for extensions to override fields for every gallery template.
    // Also passes the $template along so you can inspect and conditionally alter fields based on the template properties
    $fields = apply_filters( 'foogallery_override_gallery_template_fields', $fields, $template );
    // Allow for extensions to override fields for a specific gallery template.
    // Also passes the $template along so you can inspect and conditionally alter fields based on the template properties
    $fields = apply_filters( "foogallery_override_gallery_template_fields-{$template['slug']}", $fields, $template );
    // Easily remove fields.
    $fields_to_remove = apply_filters( 'foogallery_override_gallery_template_fields_remove', array(), $template );
    $fields_to_remove = apply_filters( "foogallery_override_gallery_template_fields_remove-{$template['slug']}", $fields_to_remove );
    // Easily set defaults for fields.
    $override_fields_defaults = apply_filters( 'foogallery_override_gallery_template_fields_defaults', array(), $template );
    $override_fields_defaults = apply_filters( "foogallery_override_gallery_template_fields_defaults-{$template['slug']}", $override_fields_defaults );
    // Easily hide certain fields.
    $fields_to_hide = apply_filters( 'foogallery_override_gallery_template_fields_hidden', array(), $template );
    $fields_to_hide = apply_filters( "foogallery_override_gallery_template_fields_hidden-{$template['slug']}", $fields_to_hide );
    $indexes_to_remove = array();
    $gallery_setting_sections = foogallery_get_gallery_setting_sections();
    foreach ( $fields as $key => &$field ) {
        // Allow for the field to be altered by extensions. Also used by the build-in fields, e.g. lightbox.
        $field = apply_filters( 'foogallery_alter_gallery_template_field', $field, $template['slug'] );
        $field = foogallery_normalize_gallery_setting_field_sections( $field, $gallery_setting_sections );
        if ( in_array( $field['id'], $fields_to_remove ) ) {
            $indexes_to_remove[] = $key;
        } else {
            // Last time to set field defaults.
            if ( array_key_exists( $field['id'], $override_fields_defaults ) ) {
                $field['default'] = $override_fields_defaults[$field['id']];
            }
            // Make fields invisible.
            if ( in_array( $field['id'], $fields_to_hide ) ) {
                // Make sure the field is not visible.
                $field['row_data']['data-foogallery-invisible'] = true;
                // Force the field to not be hidden, which means it's values can be used in previews.
                if ( isset( $field['row_data']['data-foogallery-hidden'] ) ) {
                    unset($field['row_data']['data-foogallery-hidden']);
                }
                // Remove the conditionals to FORCE the field to never be shown.
                if ( isset( $field['row_data']['data-foogallery-show-when-field'] ) ) {
                    unset($field['row_data']['data-foogallery-show-when-field']);
                }
                if ( isset( $field['row_data']['data-foogallery-show-when-field-value'] ) ) {
                    unset($field['row_data']['data-foogallery-show-when-field-value']);
                }
            }
        }
    }
    // remove the fields that were marked for removal.
    foreach ( $indexes_to_remove as $index ) {
        unset($fields[$index]);
    }
    // Finally, sort the fields.
    uasort( $fields, 'foogallery_sort_template_fields' );
    return $fields;
}

/**
 * Used to sort gallery template fields
 *
 * @param mixed $a
 * @param mixed $b
 *
 * @return int
 */
function foogallery_sort_template_fields(  $a, $b  ) {
    if ( isset( $a['order'] ) && isset( $b['order'] ) ) {
        if ( $a['order'] === $b['order'] ) {
            return 0;
        }
        return ( $a['order'] < $b['order'] ? -1 : 1 );
    }
    return 0;
}

/**
 * Build the normalized mobile variant for an opted-in template field.
 *
 * Mobile fields keep their values in the existing flat settings array while
 * inheriting the desktop field's control configuration by default.
 *
 * @param array  $field         Desktop field definition.
 * @param mixed  $default_value Default to use when the mobile definition omits one.
 * @param string $template_slug Template slug.
 *
 * @return array|false Mobile field definition, or false when not opted in.
 */
function foogallery_get_mobile_field_for_template_field(  $field, $default_value = null, $template_slug = ''  ) {
    if ( !is_array( $field ) || !isset( $field['id'] ) || !isset( $field['mobile'] ) || true !== $field['mobile'] && !is_array( $field['mobile'] ) ) {
        return false;
    }
    if ( foogallery_mobile_field_is_free( $field, $template_slug ) ) {
        return foogallery_expand_mobile_field_unchecked( $field, $default_value, $template_slug );
    }
    $provider = foogallery_mobile_settings_provider();
    if ( null !== $provider ) {
        return $provider->expand_field( $field, $default_value );
    }
    return false;
}

/**
 * Builds default settings for the supplied gallery template.
 *
 * @param string $template_name Gallery template slug.
 * @return array
 */
function foogallery_build_default_settings_for_gallery_template(  $template_name  ) {
    $fields = foogallery_get_fields_for_template( $template_name );
    $settings = array();
    // Loop through the fields and build up an array of keys and default values.
    foreach ( $fields as $field ) {
        $field_variants = array($field);
        $mobile_field = foogallery_get_mobile_field_for_template_field( $field, null, $template_name );
        if ( false !== $mobile_field ) {
            $field_variants[] = $mobile_field;
        }
        foreach ( $field_variants as $field_variant ) {
            if ( array_key_exists( 'default', $field_variant ) && null !== $field_variant['default'] ) {
                $settings["{$template_name}_{$field_variant['id']}"] = $field_variant['default'];
            }
        }
    }
    return $settings;
}

/**
 * Compare responsive setting values after normalizing scalar form values.
 *
 * HTML form controls submit numbers and booleans as strings, while template
 * defaults may use their native PHP types. Treat those representations as the
 * same value, but retain recursive array shape and keys.
 *
 * @param mixed $left  First value.
 * @param mixed $right Second value.
 * @return bool
 */
function foogallery_mobile_setting_values_match(  $left, $right  ) {
    if ( is_array( $left ) || is_array( $right ) ) {
        if ( !is_array( $left ) || !is_array( $right ) || array_keys( $left ) !== array_keys( $right ) ) {
            return false;
        }
        foreach ( $left as $key => $value ) {
            if ( !foogallery_mobile_setting_values_match( $value, $right[$key] ) ) {
                return false;
            }
        }
        return true;
    }
    if ( (is_scalar( $left ) || null === $left) && (is_scalar( $right ) || null === $right) ) {
        return (string) $left === (string) $right;
    }
    return $left === $right;
}

/**
 * Remove submitted mobile values that are identical to their fallback.
 *
 * The editor always submits its visible mobile control. Keeping only values
 * that differ from the declared mobile default (or desktop value for inherited
 * fields) makes the stored setting itself the source of truth for an override.
 *
 * @param array  $settings Submitted settings.
 * @param string $template Template slug.
 * @return array
 */
function foogallery_normalize_mobile_settings_for_save(  $settings, $template  ) {
    if ( !is_array( $settings ) || !is_string( $template ) || '' === $template ) {
        return $settings;
    }
    foreach ( foogallery_get_fields_for_template( $template ) as $field ) {
        if ( !is_array( $field ) || empty( $field['id'] ) ) {
            continue;
        }
        $desktop_key = $template . '_' . $field['id'];
        $desktop_default = ( array_key_exists( 'default', $field ) ? $field['default'] : null );
        $desktop_value = ( array_key_exists( $desktop_key, $settings ) ? $settings[$desktop_key] : $desktop_default );
        $mobile_field = foogallery_expand_mobile_field_unchecked( $field, $desktop_value, $template );
        if ( false === $mobile_field ) {
            continue;
        }
        $mobile_key = $template . '_' . $mobile_field['id'];
        if ( array_key_exists( $mobile_key, $settings ) && foogallery_mobile_setting_values_match( $settings[$mobile_key], $mobile_field['default'] ) ) {
            unset($settings[$mobile_key]);
        }
    }
    return $settings;
}

/**
 * Preserve paid mobile values while Free settings are saved.
 *
 * The settings form intentionally does not contain paid mobile inputs in
 * Free. Existing values therefore need to survive a full settings replacement
 * and any incoming paid-mobile keys must be ignored.
 *
 * @param array  $settings Submitted settings.
 * @param string $template Template slug.
 * @param int    $post_id  Gallery post ID.
 * @return array
 */
function foogallery_preserve_paid_mobile_settings(  $settings, $template, $post_id  ) {
    if ( !is_array( $settings ) || foogallery_mobile_settings_is_entitled() ) {
        return $settings;
    }
    $existing = get_post_meta( absint( $post_id ), FOOGALLERY_META_SETTINGS, true );
    $existing = ( is_array( $existing ) ? $existing : array() );
    $storage_keys = foogallery_get_paid_mobile_setting_storage_keys( $template, array_merge( $existing, $settings ) );
    foreach ( $storage_keys as $incoming_key => $canonical_key ) {
        unset($settings[$incoming_key]);
        if ( $incoming_key === $canonical_key && array_key_exists( $canonical_key, $existing ) ) {
            $settings[$canonical_key] = $existing[$canonical_key];
        }
    }
    return $settings;
}

/**
 * Returns the choices used for the thumb link field type
 * @return array
 */
function foogallery_gallery_template_field_thumb_link_choices() {
    return apply_filters( 'foogallery_gallery_template_field_thumb_links', array(
        'image'       => __( 'Full Size Image', 'foogallery' ),
        'page'        => __( 'Image Attachment Page', 'foogallery' ),
        'parent_post' => __( 'Parent / Uploaded Post', 'foogallery' ),
        'custom'      => __( 'Custom URL', 'foogallery' ),
        'none'        => __( 'Not linked', 'foogallery' ),
    ) );
}

/**
 * Returns the choices used for the lightbox field type
 * @return array
 */
function foogallery_gallery_template_field_lightbox_choices() {
    $lightboxes = apply_filters( 'foogallery_gallery_template_field_lightboxes', array() );
    $lightboxes['none'] = __( 'None', 'foogallery' );
    return $lightboxes;
}

if ( !function_exists( 'wp_get_raw_referer' ) ) {
    /**
     * Retrieves unvalidated referer from '_wp_http_referer' or HTTP referer.
     *
     * Do not use for redirects, use {@see wp_get_referer()} instead.
     *
     * @since 1.4.9
     * @return string|false Referer URL on success, false on failure.
     */
    function wp_get_raw_referer() {
        if ( !empty( $_REQUEST['_wp_http_referer'] ) ) {
            return wp_unslash( $_REQUEST['_wp_http_referer'] );
        } else {
            if ( !empty( $_SERVER['HTTP_REFERER'] ) ) {
                return wp_unslash( $_SERVER['HTTP_REFERER'] );
            }
        }
        return false;
    }

}
/**
 * Return the attachments for the currently displayed gallery
 *
 * @return array
 */
function foogallery_current_gallery_attachments_for_rendering() {
    global $current_foogallery;
    $attachments = apply_filters( 'foogallery_gallery_attachments_override_for_rendering', false, $current_foogallery );
    if ( $attachments !== false ) {
        return $attachments;
    }
    // by default, return all attachments.
    return $current_foogallery->attachments();
}

/**
 * Return attachment ID from a URL
 *
 * @param $url String URL to the image we are checking
 *
 * @return null or attachment ID
 */
function foogallery_get_attachment_id_by_url(  $url  ) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This exact GUID lookup must reflect the current attachment record.
    $attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE guid = %s LIMIT 1", $url ) );
    return ( null === $attachment_id ? null : $attachment_id );
}

/**
 * Safer escaping for HTML attributes.
 *
 * @since 1.4.31
 *
 * @param string $text
 * @return string
 */
function foogallery_esc_attr(  $text, $overrides = false  ) {
    $safe_text = wp_check_invalid_utf8( $text );
    $quote_style = ENT_QUOTES;
    $charset = false;
    $double_encode = true;
    if ( false !== $overrides ) {
        if ( isset( $overrides['quote_style'] ) ) {
            $quote_style = $overrides['quote_style'];
        }
        if ( isset( $overrides['charset'] ) ) {
            $charset = $overrides['charset'];
        }
        if ( isset( $overrides['double_encode'] ) ) {
            $double_encode = $overrides['double_encode'];
        }
    }
    $safe_text = _wp_specialchars(
        $safe_text,
        $quote_style,
        $charset,
        $double_encode
    );
    return $safe_text;
}

/**
 * Create a FooGallery and return the ID
 *
 * @param $template
 * @param $attachment_ids
 *
 * @return int
 */
function foogallery_create_gallery(  $template, $attachment_ids  ) {
    if ( empty( $template ) ) {
        $template = foogallery_default_gallery_template();
    }
    //create an empty foogallery
    $foogallery_args = array(
        'post_title'  => 'Demo Gallery',
        'post_type'   => FOOGALLERY_CPT_GALLERY,
        'post_status' => 'publish',
    );
    $gallery_id = wp_insert_post( $foogallery_args );
    //set a gallery template
    add_post_meta(
        $gallery_id,
        FOOGALLERY_META_TEMPLATE,
        $template,
        true
    );
    $settings = array();
    //set default settings if there are any, and also if the template is the same as the default
    if ( foogallery_default_gallery_template() === $template ) {
        $default_gallery_id = foogallery_get_setting( 'default_gallery_settings' );
        if ( $default_gallery_id ) {
            $settings = get_post_meta( $default_gallery_id, FOOGALLERY_META_SETTINGS, true );
            add_post_meta(
                $gallery_id,
                FOOGALLERY_META_SETTINGS,
                $settings,
                true
            );
        }
    }
    if ( empty( $settings ) ) {
        switch ( $template ) {
            case 'masonry':
                $settings = array(
                    'foogallery_items_view'                   => 'preview',
                    'masonry_alignment'                       => 'fg-center',
                    'masonry_border_size'                     => 'fg-border-thin',
                    'masonry_caption_desc_source'             => '',
                    'masonry_caption_title_source'            => '',
                    'masonry_captions_limit_length'           => '',
                    'masonry_custom_settings'                 => '',
                    'masonry_drop_shadow'                     => 'fg-shadow-outline',
                    'masonry_filtering_type'                  => '',
                    'masonry_gutter_width'                    => '10',
                    'masonry_hover_effect_caption_visibility' => 'fg-captions-bottom',
                    'masonry_hover_effect_color'              => '',
                    'masonry_hover_effect_icon'               => 'fg-hover-zoom',
                    'masonry_hover_effect_preset'             => 'fg-custom',
                    'masonry_hover_effect_scale'              => '',
                    'masonry_hover_effect_transition'         => 'fg-hover-fade',
                    'masonry_inner_shadow'                    => '',
                    'masonry_layout'                          => 'fixed',
                    'masonry_lazyload'                        => '',
                    'masonry_lightbox'                        => 'foobox',
                    'masonry_loaded_effect'                   => 'fg-loaded-fade-in',
                    'masonry_loading_icon'                    => 'fg-loading-default',
                    'masonry_paging_type'                     => '',
                    'masonry_rounded_corners'                 => '',
                    'masonry_state'                           => 'no',
                    'masonry_theme'                           => 'fg-dark',
                    'masonry_thumbnail_link'                  => 'image',
                    'masonry_thumbnail_width'                 => '250',
                    'masonry_video_autoplay'                  => 'yes',
                    'masonry_video_hover_icon'                => 'fg-video-default',
                    'masonry_video_size'                      => '640x360',
                    'masonry_video_sticky_icon'               => '',
                );
        }
    }
    add_post_meta(
        $gallery_id,
        FOOGALLERY_META_SETTINGS,
        $settings,
        true
    );
    $attachments = explode( ',', $attachment_ids );
    update_post_meta( $gallery_id, FOOGALLERY_META_ATTACHMENTS, $attachments );
    return $gallery_id;
}

/**
 * Returns an array of marketing demos
 * @return array
 */
function foogallery_marketing_demos() {
    $demos = array();
    $demos[] = array(
        'demo'    => __( 'Responsive Image Gallery', 'foogallery' ),
        'section' => __( 'Standard Gallery Demos', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/responsive-image-gallery/',
    );
    $demos[] = array(
        'demo'    => __( 'Masonry Image Gallery', 'foogallery' ),
        'section' => __( 'Standard Gallery Demos', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/masonry-gallery/',
    );
    $demos[] = array(
        'demo'    => __( 'Justified Gallery', 'foogallery' ),
        'section' => __( 'Standard Gallery Demos', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/justified-gallery/',
    );
    $demos[] = array(
        'demo'    => __( 'Image Viewer Gallery', 'foogallery' ),
        'section' => __( 'Standard Gallery Demos', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/image-viewer-gallery/',
    );
    $demos[] = array(
        'demo'    => __( 'Portfolio Gallery', 'foogallery' ),
        'section' => __( 'Standard Gallery Demos', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/wordpress-portfolio-gallery/',
    );
    $demos[] = array(
        'demo'    => __( 'Single Thumbnail Gallery', 'foogallery' ),
        'section' => __( 'Standard Gallery Demos', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/single-thumbnail-gallery/',
    );
    $demos[] = array(
        'demo'    => __( 'Grid PRO Gallery', 'foogallery' ),
        'section' => __( 'PRO Gallery Demos', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/grid-gallery/',
    );
    $demos[] = array(
        'demo'    => __( 'Polaroid PRO Gallery', 'foogallery' ),
        'section' => __( 'PRO Gallery Demos', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/polaroid-gallery/',
    );
    $demos[] = array(
        'demo'    => __( 'Slider PRO Gallery', 'foogallery' ),
        'section' => __( 'PRO Gallery Demos', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/slider-gallery/',
    );
    $demos[] = array(
        'demo'    => __( 'Hover Presets Demo', 'foogallery' ),
        'section' => __( 'PRO Features', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/hover-presets/',
    );
    $demos[] = array(
        'demo'    => __( 'Filtering Demos', 'foogallery' ),
        'section' => __( 'PRO Features', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/filtering/',
    );
    $demos[] = array(
        'demo'    => __( 'Pagination Types Demo', 'foogallery' ),
        'section' => __( 'PRO Features', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/pagination/',
    );
    $demos[] = array(
        'demo'    => __( 'Video Gallery Demos', 'foogallery' ),
        'section' => __( 'PRO Features', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/video-gallery/',
    );
    $demos[] = array(
        'demo'    => __( 'Bulk Copy (admin)', 'foogallery' ),
        'section' => __( 'PRO Features', 'foogallery' ),
        'href'    => 'https://fooplugins.com/bulk-copy-foogallery-pro/',
    );
    $demos[] = array(
        'demo'    => __( 'Albums', 'foogallery' ),
        'section' => __( 'Album Demos', 'foogallery' ),
        'href'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/wordpress-album-gallery/',
    );
    return $demos;
}

/**
 * Returns an array of the PRO features
 * @return array
 */
function foogallery_marketing_pro_features() {
    $features[] = array(
        'feature' => __( 'Video Galleries', 'foogallery' ),
        'desc'    => __( 'Create beautiful video galleries from YouTube, Vimeo, Facebook, Wistia and more!', 'foogallery' ),
        'demo'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/video-gallery/',
    );
    $features[] = array(
        'feature' => __( 'Media Tags + Filtering', 'foogallery' ),
        'desc'    => __( 'Assign tags to your media, which allows visitors to filter the galleries by tag.', 'foogallery' ),
        'demo'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/filtering/',
    );
    $features[] = array(
        'feature' => __( 'More Gallery Layouts', 'foogallery' ),
        'desc'    => __( '3 more awesome gallery layouts, including Slider, Grid and Polaroid.', 'foogallery' ),
        'demo'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/slider-gallery/',
    );
    $features[] = array(
        'feature' => __( 'Preset Hover Effects', 'foogallery' ),
        'desc'    => __( 'Choose from 11 beautifully designed preset hover effects.', 'foogallery' ),
        'demo'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/hover-presets/',
    );
    $features[] = array(
        'feature' => __( 'Advanced Pagination + Infinite Scroll', 'foogallery' ),
        'desc'    => __( 'Choose from more paging types like numbered, load more or infinite scroll.', 'foogallery' ),
        'demo'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/pagination/',
    );
    $features[] = array(
        'feature' => __( 'Animated Loading Effects', 'foogallery' ),
        'desc'    => __( 'Choose from 9 awesome animation effects to display as your galleries load.', 'foogallery' ),
        'demo'    => 'https://fooplugins.com/foogallery-wordpress-gallery-plugin/animated-loaded-effects/',
    );
    $features[] = array(
        'feature' => __( 'Bulk Copy Settings', 'foogallery' ),
        'desc'    => __( 'Bulk copy your gallery settings to other galleries in a flash.', 'foogallery' ),
        'demo'    => 'https://fooplugins.com/bulk-copy-foogallery-pro/',
    );
    return $features;
}

/**
 * Returns the allowed post types that galleries can be attached to
 * @return array
 */
function foogallery_allowed_post_types_for_usage() {
    $allowed_post_types = apply_filters( 'foogallery_allowed_post_types_for_attachment', array('post', 'page') );
    // Use foogallery_get_setting to retrieve the selected custom post types.
    $selected_custom_post_types = foogallery_get_setting( 'allowed_custom_post_types', array() );
    if ( !is_array( $selected_custom_post_types ) ) {
        $selected_custom_post_types = array();
    }
    // Merge the selected custom post types with the default allowed post types.
    $allowed_post_types = array_merge( $allowed_post_types, $selected_custom_post_types );
    return $allowed_post_types;
}

/**
 * Returns true if FooGallery is in debug mode
 * @return bool
 */
function foogallery_is_debug() {
    return foogallery_get_setting( 'enable_debugging', false );
}

/**
 * Get the current gallery in the admin
 * @param $post_gallery
 *
 * @return FooGallery|null
 */
function foogallery_admin_get_current_gallery(  $post_gallery  ) {
    global $post;
    global $current_foogallery_admin;
    if ( is_admin() && isset( $post ) ) {
        if ( !isset( $current_foogallery_admin ) || $post_gallery->ID !== $post->ID ) {
            $current_foogallery_admin = FooGallery::get( $post_gallery );
        }
        return $current_foogallery_admin;
    }
    return null;
}

/**
 * Takes an RGB string and returns an array of the colors
 * @param string $rgba RBG color string in the format rgb(0,0,0)
 *
 * @return array|int[]
 */
function foogallery_rgb_to_color_array(  $rgba  ) {
    if ( empty( $rgba ) ) {
        return array(0, 0, 0);
    }
    preg_match( '/^rgba?[\\s+]?\\([\\s+]?(\\d+)[\\s+]?,[\\s+]?(\\d+)[\\s+]?,[\\s+]?(\\d+)[\\s+]?/i', $rgba, $by_color );
    if ( count( $by_color ) >= 3 ) {
        return array($by_color[1], $by_color[2], $by_color[3]);
    }
    //return black if there was a problem getting the color
    return array(0, 0, 0);
}

/**
 * Do full sanitization of a string
 *
 * @param string $text
 *
 * @return string
 */
function foogallery_sanitize_full(  $text  ) {
    return foogallery_sanitize_html( foogallery_sanitize_javascript( $text ) );
}

/**
 * Sanitize attachment custom URLs before persisting or rendering.
 *
 * @since 1.0.0
 *
 * @param string $url
 * @return string
 */
function foogallery_sanitize_attachment_custom_url(  $url  ) {
    if ( !is_string( $url ) ) {
        return '';
    }
    $url = trim( $url );
    if ( '' === $url ) {
        return '';
    }
    return esc_url_raw( $url );
}

/**
 * Sanitize attachment custom target values against known options.
 *
 * @since 1.0.0
 *
 * @param string $target
 * @return string
 */
function foogallery_sanitize_attachment_custom_target(  $target  ) {
    if ( !is_string( $target ) ) {
        return '';
    }
    $target = sanitize_key( $target );
    if ( '' === $target ) {
        return '';
    }
    $target_options = foogallery_get_target_options();
    if ( array_key_exists( $target, $target_options ) ) {
        return $target;
    }
    return 'default';
}

/**
 * Sanitize attachment custom rel values against allowed tokens.
 *
 * @since 1.0.0
 *
 * @param string $rel
 * @return string
 */
function foogallery_sanitize_attachment_custom_rel(  $rel  ) {
    if ( !is_string( $rel ) ) {
        return '';
    }
    $rel = strtolower( trim( $rel ) );
    if ( '' === $rel ) {
        return '';
    }
    $allowed = wp_kses_allowed_html();
    $allowed_tokens = array(
        'alternate',
        'author',
        'bookmark',
        'external',
        'help',
        'license',
        'me',
        'next',
        'nofollow',
        'dofollow',
        'noopener',
        'noreferrer',
        'prev',
        'search',
        'sponsored',
        'tag',
        'ugc'
    );
    /**
     * Filter the list of allowed rel tokens for attachment custom rel values.
     *
     * @since 1.0.0
     *
     * @param array  $allowed_tokens Allowed rel tokens.
     * @param string $rel            Raw rel value before tokenization.
     */
    $allowed_tokens = apply_filters( 'foogallery_custom_rel_allowed_tokens', $allowed_tokens, $rel );
    if ( !is_array( $allowed_tokens ) ) {
        $allowed_tokens = array();
    }
    $rel_tokens = preg_split( '/\\s+/', $rel );
    if ( !is_array( $rel_tokens ) ) {
        return '';
    }
    $sanitized_tokens = array();
    foreach ( $rel_tokens as $token ) {
        $token = sanitize_key( $token );
        if ( in_array( $token, $allowed_tokens, true ) ) {
            $sanitized_tokens[] = $token;
        }
    }
    if ( empty( $sanitized_tokens ) ) {
        return '';
    }
    $sanitized_tokens = array_values( array_unique( $sanitized_tokens ) );
    return implode( ' ', $sanitized_tokens );
}

/**
 * Sanitize HTML to make it safe to output. Used to sanitize potentially harmful HTML used for captions
 *
 * @since 1.9.23
 *
 * @param string $text
 * @return string
 */
function foogallery_sanitize_html(  $text  ) {
    $safe_text = wp_kses_post( $text );
    return $safe_text;
}

/**
 * Filter out executable JavaScript patterns and inline scripts from an input string.
 *
 * @param string $input
 * @return string
 */
function foogallery_sanitize_javascript(  $input  ) {
    if ( !is_string( $input ) ) {
        return '';
    }
    $javascript_patterns = array(
        '/<\\/?script\\b[^>]*>/i',
        '/\\bnew\\s+Function\\s*\\(/i',
        '/\\bdocument\\s*\\.\\s*write\\s*\\(/i',
        '/\\beval\\s*(?:\\?\\.\\s*)?\\(/i',
        '/\\beval\\s*\\)\\s*\\(/i',
        '/\\beval\\s*\\.\\s*(?:call|apply|bind)\\s*\\(/i',
        '/\\[\\s*[\'"]eval[\'"]\\s*\\]\\s*(?:\\?\\.\\s*)?\\(/i',
        '/\\b(?:Function|setTimeout|setInterval|encodeURIComponent|decodeURIComponent|JSON\\s*\\.\\s*parse|XMLHttpRequest|createElement|appendChild|RegExp|String\\s*\\.\\s*fromCharCode|encodeURI|decodeURI)\\s*\\(/i',
        '/\\b(?:innerHTML|outerHTML)\\s*=/i',
        '/\\bon(?:mouseover|mouseout|pointerenter|click|load|change|error)\\b\\s*=?/i',
        '/javascript\\s*:/i'
    );
    $sanitized = preg_replace( $javascript_patterns, '', $input );
    return ( is_string( $sanitized ) ? $sanitized : '' );
}

/**
 * Returns true if PRO is in use
 * @return bool
 */
function foogallery_is_pro() {
    $pro = false;
    return $pro;
}

/**
 * Safe function for encoding objects to json
 *
 * @param $value
 *
 * @return false|string
 */
function foogallery_json_encode(  $value  ) {
    $flags = JSON_UNESCAPED_SLASHES;
    if ( defined( 'JSON_UNESCAPED_UNICODE' ) ) {
        $flags = JSON_UNESCAPED_UNICODE | $flags;
    }
    $flags = apply_filters( 'foogallery_json_encode_flags', $flags );
    return json_encode( $value, $flags );
}

/**
 * Get a language array entry which gets a value from settings
 * @param $setting_key
 * @param $default
 *
 * @return string|false
 */
function foogallery_get_language_array_value(  $setting_key, $default  ) {
    $setting_value = foogallery_get_setting( $setting_key, $default );
    if ( empty( $setting_value ) ) {
        $setting_value = $default;
    }
    if ( $default !== $setting_value ) {
        return $setting_value;
    }
    return false;
}

/**
 * Safely returns the WP Filesystem instance for use in FooGallery
 *
 * @return WP_Filesystem_Base
 */
function foogallery_wp_filesystem() {
    global $wp_filesystem;
    if ( !function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( !WP_Filesystem( true ) ) {
        return false;
    }
    return $wp_filesystem;
}

/**
 * Returns a formatted date
 *
 * @param        $timestamp
 * @param string $format
 *
 * @return string
 */
function foogallery_format_date(  $timestamp, $format = null  ) {
    if ( !$format ) {
        $format = get_option( 'date_format' );
    }
    if ( function_exists( 'wp_date' ) ) {
        return wp_date( $format, $timestamp );
    } else {
        $datetime = date_create( '@' . $timestamp );
        $timezone = wp_timezone();
        $datetime->setTimezone( $timezone );
        return $datetime->format( $format );
    }
}

/**
 * Shortcut method to safely check if the current gallery template supports a specific feature
 *
 * e.g. panel_support, preview_support, common_fields_support, lazyload_support, paging_support, filtering_support
 *
 * @param      $feature_to_check
 * @param bool $value_to_check
 *
 * @return bool
 */
function foogallery_current_gallery_check_template_has_supported_feature(  $feature_to_check, $value_to_check = true  ) {
    global $current_foogallery;
    //get out early if there is no current gallery
    if ( !isset( $current_foogallery ) ) {
        return false;
    }
    //check if we have previously checked before recently
    if ( isset( $current_foogallery->supports ) && is_array( $current_foogallery->supports ) && array_key_exists( $feature_to_check, $current_foogallery->supports ) ) {
        return $current_foogallery->supports[$feature_to_check] === $value_to_check;
    } else {
        //check if we need to init the array
        if ( !isset( $current_foogallery->supports ) || !is_array( $current_foogallery->supports ) ) {
            $current_foogallery->supports = array();
        }
        if ( !array_key_exists( $feature_to_check, $current_foogallery->supports ) ) {
            $template_object = foogallery_get_gallery_template( $current_foogallery->gallery_template );
            if ( $template_object && is_array( $template_object ) && array_key_exists( $feature_to_check, $template_object ) ) {
                $current_foogallery->supports[$feature_to_check] = $template_object[$feature_to_check];
            } else {
                //this is not stored against the template config, so assume it does not have the feature support
                $current_foogallery->supports[$feature_to_check] = false;
            }
        }
        return $current_foogallery->supports[$feature_to_check] === $value_to_check;
    }
}

/**
 * Checks to see if we have a cached value stored against the current gallery
 * Certain values are cached against the gallery if they have to be done multiple times, for example for each item in the gallery
 *
 * @param $cache_key
 *
 * @return bool
 */
function foogallery_current_gallery_has_cached_value(  $cache_key  ) {
    global $current_foogallery;
    //get out early if there is no current gallery
    if ( !isset( $current_foogallery ) ) {
        return true;
        //this is to ensure we short-circuit having to calculate the cached value later
    }
    return isset( $current_foogallery->cached_values ) && is_array( $current_foogallery->cached_values ) && array_key_exists( $cache_key, $current_foogallery->cached_values );
}

/**
 * Stores a value against the current gallery
 *
 * @param $cache_key
 * @param $cache_value
 */
function foogallery_current_gallery_set_cached_value(  $cache_key, $cache_value  ) {
    global $current_foogallery;
    //get out early if there is no current gallery
    if ( !isset( $current_foogallery ) ) {
        return;
    }
    //check if we need to init the array
    if ( !isset( $current_foogallery->cached_values ) || !is_array( $current_foogallery->cached_values ) ) {
        $current_foogallery->cached_values = array();
    }
    //store the value for later use
    $current_foogallery->cached_values[$cache_key] = $cache_value;
}

/**
 * Set the value of a cached value for the current gallery
 *
 * @param $cache_value
 *
 * @return mixed
 */
function foogallery_current_gallery_get_cached_value(  $cache_value  ) {
    global $current_foogallery;
    //get out early if there is no current gallery
    if ( !isset( $current_foogallery ) ) {
        return false;
    }
    if ( isset( $current_foogallery->cached_values ) && is_array( $current_foogallery->cached_values ) && array_key_exists( $cache_value, $current_foogallery->cached_values ) ) {
        return $current_foogallery->cached_values[$cache_value];
    }
    return false;
}

/**
 * functions related to thumbnail generation within FooGallery
 */
/**
 * Returns the array of available engines
 *
 * @return array
 */
function foogallery_thumb_available_engines() {
    $shortpixel_link = '<a href="https://shortpixel.com/otp/af/foowww" target="_blank">' . __( 'ShortPixel Adaptive Images', 'foogallery' ) . '</a>';
    /* translators: %s: Link to ShortPixel Adaptive Images. */
    $shortpixel_description = sprintf( __( 'Uses %s to generate all your gallery thumbnails. They will be optimized and offloaded to the ShortPixel global CDN!', 'foogallery' ), $shortpixel_link );
    $engines = array(
        'default'    => array(
            'label'       => __( 'Default', 'foogallery' ),
            'description' => __( 'The default engine used to generate locally cached thumbnails.', 'foogallery' ),
            'class'       => 'FooGallery_Thumb_Engine_Default',
        ),
        'shortpixel' => array(
            'label'       => __( 'ShortPixel', 'foogallery' ),
            'description' => $shortpixel_description,
            'class'       => 'FooGallery_Thumb_Engine_Shortpixel',
        ),
    );
    if ( foogallery_is_debug() ) {
        $engines['dummy'] = array(
            'label'       => __( 'Dummy', 'foogallery' ),
            'description' => __( 'A dummy thumbnail engine that can be used for testing. (uses dummyimage.com)', 'foogallery' ),
            'class'       => 'FooGallery_Thumb_Engine_Dummy',
        );
    }
    return apply_filters( 'foogallery_thumb_available_engines', $engines );
}

/**
 * Returns the active thumb engine, based on settings
 *
 * @return FooGallery_Thumb_Engine
 */
function foogallery_thumb_active_engine() {
    global $foogallery_thumb_engine;
    //if we already have an engine, return it early
    if ( isset( $foogallery_thumb_engine ) && is_a( $foogallery_thumb_engine, 'FooGallery_Thumb_Engine' ) ) {
        return $foogallery_thumb_engine;
    }
    $engine = foogallery_get_setting( 'thumb_engine', 'default' );
    $engines = foogallery_thumb_available_engines();
    if ( array_key_exists( $engine, $engines ) ) {
        $active_engine = $engines[$engine];
        $foogallery_thumb_engine = new $active_engine['class']();
    } else {
        $foogallery_thumb_engine = new FooGallery_Thumb_Engine_Default();
    }
    return $foogallery_thumb_engine;
}

/**
 * Resizes a given image using the active thumb engine.
 *
 * @param       $url
 * @param array $args
 *
 * @return string|void (string) url to the image
 */
function foogallery_thumb(  $url, $args = array()  ) {
    $engine = foogallery_thumb_active_engine();
    return $engine->generate( $url, $args );
}

/**
 * @param $url string
 *
 * @return string
 */
function foogallery_process_image_url(  $url  ) {
    return apply_filters( 'foogallery_process_image_url', $url );
}

/**
 * Build up a link to be used in the admin with the correct utm parameters
 *
 * @param      $url             string The original full URL
 * @param      $utm_campaign    string The campaign or page that the link is on
 * @param null $utm_medium      string The medium, so in this case we want to differentiate btw free and pro
 * @param null $utm_content     string Optional extra data that can be used to differentiate between links in the same campaign
 * @param      $utm_source      string The platform where the traffic originates. Should probably always be wp_plugin
 *
 * @return string
 */
function foogallery_admin_url(
    $url,
    $utm_campaign,
    $utm_content = null,
    $utm_medium = null,
    $utm_source = 'wp_plugin'
) {
    if ( is_null( $utm_source ) ) {
        $utm_source = 'wp_plugin';
    }
    if ( is_null( $utm_medium ) ) {
        if ( foogallery_is_pro() ) {
            $utm_medium = 'foogallery_pro';
        } else {
            $utm_medium = 'foogallery_free';
        }
    }
    $params = array(
        'utm_source'   => $utm_source,
        'utm_medium'   => $utm_medium,
        'utm_campaign' => $utm_campaign,
    );
    if ( !is_null( $utm_content ) ) {
        $params['utm_content'] = $utm_content;
    }
    return add_query_arg( $params, $url );
}

/**
 * Returns true if on the plugin activation page
 *
 * @return bool
 */
function foogallery_is_activation_page() {
    $fs = foogallery_fs();
    return $fs->is_activation_page();
}

/**
 * Render an array of debug info
 *
 * @param array $array an array of data to render.
 */
function foogallery_render_debug_array(  $array, $level = 0  ) {
    if ( !is_array( $array ) ) {
        return;
    }
    foreach ( $array as $key => $value ) {
        if ( !empty( $value ) ) {
            if ( $level > 0 ) {
                echo esc_html( str_repeat( '   ', $level ) );
            }
            echo esc_html( $key ) . ' => ';
            if ( is_array( $value ) ) {
                echo "\r\n";
                foogallery_render_debug_array( $value, $level + 1 );
            } else {
                echo esc_html( $value );
                echo "\r\n";
            }
        }
    }
}

/**
 * Validates an attachment import URL and ensures every resolved address is public.
 *
 * WordPress safe HTTP requests protect redirect destinations as well. Imports do
 * not follow redirects, but this additional check also rejects link-local and
 * metadata-service ranges that older supported WordPress versions do not cover.
 *
 * @param mixed $url URL supplied by the import file.
 * @return string|WP_Error The normalized URL, or an error when it is unsafe.
 */
function foogallery_validate_attachment_import_url(  $url  ) {
    if ( !is_scalar( $url ) ) {
        return new WP_Error('foogallery_import_attachment_invalid_url', __( 'The remote image URL is invalid.', 'foogallery' ));
    }
    $url = esc_url_raw( trim( (string) $url ), array('http', 'https') );
    if ( '' === $url || false === wp_http_validate_url( $url ) ) {
        return new WP_Error('foogallery_import_attachment_invalid_url', __( 'The remote image URL is invalid or unsafe.', 'foogallery' ));
    }
    $parsed_url = wp_parse_url( $url );
    $host = ( isset( $parsed_url['host'] ) ? strtolower( trim( $parsed_url['host'], '.[]' ) ) : '' );
    if ( '' === $host || 'localhost' === $host || preg_match( '/\\.(?:localhost|local|internal)$/', $host ) ) {
        return new WP_Error('foogallery_import_attachment_unsafe_host', __( 'The remote image host is not publicly accessible.', 'foogallery' ));
    }
    if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        $resolved_ips = array($host);
    } else {
        $resolved_ips = gethostbynamel( $host );
    }
    if ( !is_array( $resolved_ips ) || empty( $resolved_ips ) ) {
        return new WP_Error('foogallery_import_attachment_unresolved_host', __( 'The remote image host could not be resolved.', 'foogallery' ));
    }
    foreach ( $resolved_ips as $resolved_ip ) {
        $is_public_ip = filter_var( $resolved_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
        if ( false === $is_public_ip ) {
            return new WP_Error('foogallery_import_attachment_unsafe_host', __( 'The remote image host is not publicly accessible.', 'foogallery' ));
        }
    }
    return $url;
}

/**
 * Normalizes an array of imported taxonomy term names.
 *
 * @param mixed $terms Imported terms.
 * @return string[] Sanitized, unique term names.
 */
function foogallery_sanitize_imported_attachment_terms(  $terms  ) {
    if ( !is_array( $terms ) ) {
        return array();
    }
    $sanitized_terms = array();
    foreach ( $terms as $term ) {
        if ( !is_scalar( $term ) ) {
            continue;
        }
        $term = sanitize_text_field( (string) $term );
        if ( '' !== $term ) {
            $sanitized_terms[] = $term;
        }
    }
    return array_values( array_unique( $sanitized_terms ) );
}

/**
 * Insert a new attachment from a URL.
 *
 * @param array $attachment_data The image attachment data.
 *
 * @return int|WP_Error
 */
function foogallery_import_attachment(  $attachment_data  ) {
    if ( !is_array( $attachment_data ) || !isset( $attachment_data['url'] ) ) {
        return new WP_Error('foogallery_import_attachment_invalid_data', __( 'The imported attachment data is invalid.', 'foogallery' ));
    }
    $url = foogallery_validate_attachment_import_url( $attachment_data['url'] );
    if ( is_wp_error( $url ) ) {
        return $url;
    }
    // Include the WordPress sideload and image metadata APIs.
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $url_path = wp_parse_url( $url, PHP_URL_PATH );
    $source_filename = ( is_string( $url_path ) ? sanitize_file_name( rawurldecode( wp_basename( $url_path ) ) ) : '' );
    if ( '' === $source_filename ) {
        $source_filename = 'foogallery-import';
    }
    $temp_file = wp_tempnam( $source_filename );
    if ( !$temp_file ) {
        return new WP_Error('foogallery_import_attachment_temp_file_error', __( 'A temporary file could not be created for the remote image.', 'foogallery' ));
    }
    $max_file_size = (int) apply_filters( 'foogallery_import_attachment_max_file_size', 10 * MB_IN_BYTES, $url );
    if ( $max_file_size < 1 ) {
        $max_file_size = 10 * MB_IN_BYTES;
    }
    $timeout = (int) apply_filters( 'foogallery_import_attachment_timeout', 15, $url );
    $timeout = max( 1, min( 30, $timeout ) );
    $response = wp_safe_remote_get( $url, array(
        'timeout'             => $timeout,
        'redirection'         => 0,
        'stream'              => true,
        'filename'            => $temp_file,
        'limit_response_size' => $max_file_size + 1,
    ) );
    if ( is_wp_error( $response ) ) {
        wp_delete_file( $temp_file );
        return new WP_Error('foogallery_import_attachment_download_failed', __( 'The remote image could not be downloaded safely.', 'foogallery' ));
    }
    $response_code = (int) wp_remote_retrieve_response_code( $response );
    if ( 200 !== $response_code ) {
        wp_delete_file( $temp_file );
        return new WP_Error('foogallery_import_attachment_http_error', sprintf( 
            /* translators: %d: HTTP response code. */
            __( 'The remote image server returned HTTP %d.', 'foogallery' ),
            $response_code
         ));
    }
    $content_length = wp_remote_retrieve_header( $response, 'content-length' );
    if ( is_array( $content_length ) ) {
        $content_length = reset( $content_length );
    }
    $file_size = ( file_exists( $temp_file ) ? filesize( $temp_file ) : false );
    if ( false === $file_size || $file_size < 1 ) {
        wp_delete_file( $temp_file );
        return new WP_Error('foogallery_import_attachment_empty_file', __( 'The remote image was empty.', 'foogallery' ));
    }
    if ( is_scalar( $content_length ) && (int) $content_length > $max_file_size || $file_size > $max_file_size ) {
        wp_delete_file( $temp_file );
        return new WP_Error('foogallery_import_attachment_file_too_large', __( 'The remote image exceeds the allowed file size.', 'foogallery' ));
    }
    $detected_mime = wp_get_image_mime( $temp_file );
    $allowed_mimes = array();
    foreach ( get_allowed_mime_types() as $extensions => $mime_type ) {
        if ( 0 === strpos( $mime_type, 'image/' ) ) {
            $allowed_mimes[$extensions] = $mime_type;
        }
    }
    $detected_extension = '';
    foreach ( $allowed_mimes as $extensions => $mime_type ) {
        if ( $detected_mime === $mime_type ) {
            $extension_parts = explode( '|', $extensions );
            $detected_extension = reset( $extension_parts );
            break;
        }
    }
    if ( !$detected_mime || '' === $detected_extension ) {
        wp_delete_file( $temp_file );
        return new WP_Error('foogallery_import_attachment_invalid_image', __( 'The downloaded file is not a supported image.', 'foogallery' ));
    }
    $filename_base = sanitize_file_name( pathinfo( $source_filename, PATHINFO_FILENAME ) );
    if ( '' === $filename_base ) {
        $filename_base = 'foogallery-import';
    }
    $filename = $filename_base . '.' . $detected_extension;
    $checked_filetype = wp_check_filetype_and_ext( $temp_file, $filename, $allowed_mimes );
    if ( empty( $checked_filetype['ext'] ) || empty( $checked_filetype['type'] ) || $detected_mime !== $checked_filetype['type'] ) {
        wp_delete_file( $temp_file );
        return new WP_Error('foogallery_import_attachment_invalid_image', __( 'The downloaded file is not a supported image.', 'foogallery' ));
    }
    $file_array = array(
        'name'     => $filename,
        'tmp_name' => $temp_file,
    );
    $upload = wp_handle_sideload( $file_array, array(
        'test_form' => false,
        'mimes'     => $allowed_mimes,
    ) );
    if ( isset( $upload['error'] ) ) {
        wp_delete_file( $temp_file );
        return new WP_Error('foogallery_import_attachment_upload_fail', __( 'The validated remote image could not be added to uploads.', 'foogallery' ));
    }
    $title = ( isset( $attachment_data['title'] ) && is_scalar( $attachment_data['title'] ) ? sanitize_text_field( (string) $attachment_data['title'] ) : $filename_base );
    $caption = ( isset( $attachment_data['caption'] ) && is_scalar( $attachment_data['caption'] ) ? wp_kses_post( (string) $attachment_data['caption'] ) : '' );
    $description = ( isset( $attachment_data['description'] ) && is_scalar( $attachment_data['description'] ) ? wp_kses_post( (string) $attachment_data['description'] ) : '' );
    $attachment_args = wp_slash( array(
        'guid'           => $upload['url'],
        'post_title'     => ( '' !== $title ? $title : $filename_base ),
        'post_excerpt'   => $caption,
        'post_content'   => $description,
        'post_mime_type' => $upload['type'],
    ) );
    $attachment_id = wp_insert_attachment(
        $attachment_args,
        $upload['file'],
        0,
        true
    );
    if ( is_wp_error( $attachment_id ) ) {
        wp_delete_file( $upload['file'] );
        return $attachment_id;
    }
    $attachment_meta = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
    if ( is_array( $attachment_meta ) ) {
        wp_update_attachment_metadata( $attachment_id, $attachment_meta );
    }
    if ( isset( $attachment_data['alt'] ) && is_scalar( $attachment_data['alt'] ) ) {
        $alt = sanitize_text_field( (string) $attachment_data['alt'] );
        if ( '' !== $alt ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
        }
    }
    if ( isset( $attachment_data['custom_url'] ) && is_scalar( $attachment_data['custom_url'] ) ) {
        $custom_url = foogallery_sanitize_attachment_custom_url( (string) $attachment_data['custom_url'] );
        if ( '' !== $custom_url ) {
            update_post_meta( $attachment_id, '_foogallery_custom_url', $custom_url );
        }
    }
    if ( isset( $attachment_data['custom_target'] ) && is_scalar( $attachment_data['custom_target'] ) ) {
        $custom_target = foogallery_sanitize_attachment_custom_target( (string) $attachment_data['custom_target'] );
        if ( '' !== $custom_target ) {
            update_post_meta( $attachment_id, '_foogallery_custom_target', $custom_target );
        }
    }
    if ( isset( $attachment_data['video'] ) && is_scalar( $attachment_data['video'] ) ) {
        $video_url = esc_url_raw( (string) $attachment_data['video'], array('http', 'https') );
        if ( '' !== $video_url ) {
            update_post_meta( $attachment_id, '_foogallery_video_data', array(
                'url' => $video_url,
            ) );
        }
    }
    // Save the validated original URL so that it is not imported again.
    update_post_meta( $attachment_id, '_foogallery_imported_from', $url );
    $tags = ( isset( $attachment_data['tags'] ) ? foogallery_sanitize_imported_attachment_terms( $attachment_data['tags'] ) : array() );
    if ( !empty( $tags ) && taxonomy_exists( FOOGALLERY_ATTACHMENT_TAXONOMY_TAG ) ) {
        wp_set_object_terms(
            $attachment_id,
            $tags,
            FOOGALLERY_ATTACHMENT_TAXONOMY_TAG,
            false
        );
    }
    $categories = ( isset( $attachment_data['categories'] ) ? foogallery_sanitize_imported_attachment_terms( $attachment_data['categories'] ) : array() );
    if ( !empty( $categories ) && taxonomy_exists( FOOGALLERY_ATTACHMENT_TAXONOMY_CATEGORY ) ) {
        wp_set_object_terms(
            $attachment_id,
            $categories,
            FOOGALLERY_ATTACHMENT_TAXONOMY_CATEGORY,
            false
        );
    }
    return $attachment_id;
}

/**
 * Returns an array of data associated with the attachment, including full size image URL, full size width and height.
 *
 * @param int $attachment_id The attachment ID.
 *
 * @return array|false
 */
function foogallery_get_full_size_image_data(  $attachment_id  ) {
    // Get the URL to the full size image.
    $src = wp_get_attachment_url( $attachment_id );
    // If we cannot get an attachment URL, then get out early.
    if ( false === $src ) {
        return false;
    }
    // First try to get the image metadata.
    $image_data = wp_get_attachment_metadata( $attachment_id );
    $width = 0;
    $height = 0;
    if ( is_array( $image_data ) ) {
        if ( isset( $image_data['width'] ) ) {
            $width = absint( $image_data['width'] );
        }
        if ( isset( $image_data['height'] ) ) {
            $height = absint( $image_data['height'] );
        }
    } else {
        $image_src = wp_get_attachment_image_src( $attachment_id, 'full' );
        if ( is_array( $image_src ) ) {
            $width = ( isset( $image_src[1] ) ? absint( $image_src[1] ) : 0 );
            $height = ( isset( $image_src[2] ) ? absint( $image_src[2] ) : 0 );
        }
    }
    // If metadata is missing, inspect only a readable local file. Front-end rendering must not fetch an attachment URL.
    if ( 0 === $width && 0 === $height ) {
        $attached_file = get_attached_file( $attachment_id );
        if ( is_string( $attached_file ) && '' !== $attached_file && !wp_is_stream( $attached_file ) && is_readable( $attached_file ) ) {
            // phpcs:ignore -- Compatibility is guarded by function_exists().
            $image_size = ( function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $attached_file ) : getimagesize( $attached_file ) );
            if ( is_array( $image_size ) ) {
                $width = ( isset( $image_size[0] ) ? absint( $image_size[0] ) : 0 );
                $height = ( isset( $image_size[1] ) ? absint( $image_size[1] ) : 0 );
            }
        }
    }
    return array($src, $width, $height);
}

/**
 * Generate an SVG image placeholder
 *
 * @param $w
 * @param $h
 *
 * @return string
 */
function foogallery_get_svg_placeholder_image(  $w, $h  ) {
    return 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22' . $w . '%22%20height%3D%22' . $h . '%22%20viewBox%3D%220%200%20' . $w . '%20' . $h . '%22%3E%3C%2Fsvg%3E';
}

/**
 * Extracts the gallery ID from a full gallery ID
 *
 * @param $full_gallery_id
 *
 * @return int
 */
function foogallery_extract_gallery_id(  $full_gallery_id  ) {
    preg_match_all( '/^.*?(\\d+?)(?:_|$)/', $full_gallery_id, $matches );
    if ( is_array( $matches ) ) {
        return intval( $matches[1][0] );
    }
    return 0;
}

/**
 * Return the index of a specific section with the gallery template fields array
 *
 * @param array  $fields  Gallery template fields.
 * @param string $section Section or subsection ID, or a legacy label.
 *
 * @return int
 */
function foogallery_admin_fields_find_index_of_section(  $fields, $section  ) {
    $requested_section = sanitize_title( apply_filters( 'foogallery_gallery_settings_metabox_section_slug', $section ) );
    $sections = foogallery_get_gallery_setting_sections();
    foreach ( $sections as $section_id => $section_config ) {
        if ( isset( $section_config['label'] ) && (string) $section_config['label'] === (string) $section ) {
            $requested_section = sanitize_key( $section_id );
            break;
        }
        if ( !empty( $section_config['subsections'] ) ) {
            foreach ( $section_config['subsections'] as $subsection_id => $subsection_config ) {
                if ( isset( $subsection_config['label'] ) && (string) $subsection_config['label'] === (string) $section ) {
                    $requested_section = sanitize_key( $subsection_id );
                    break 2;
                }
            }
        }
    }
    $index = 0;
    foreach ( $fields as $field ) {
        $field = foogallery_normalize_gallery_setting_field_sections( $field, $sections );
        if ( $requested_section === $field['section_id'] || isset( $field['subsection_id'] ) && $requested_section === $field['subsection_id'] ) {
            return $index;
        }
        ++$index;
    }
    return $index;
}

/**
 * Return the index of a specific field within the gallery template fields array
 *
 * @param $fields
 * @param $field_id
 *
 * @return int
 */
function foogallery_admin_fields_find_index_of_field(  $fields, $field_id  ) {
    $index = 0;
    foreach ( $fields as $field ) {
        if ( isset( $field['id'] ) && $field_id === $field['id'] ) {
            return $index;
        }
        $index++;
    }
    return $index;
}

/**
 * Returns true if the field exists in the array
 *
 * @param $fields
 * @param $field_id
 * @return bool
 */
function foogallery_admin_fields_has_field(  $fields, $field_id  ) {
    foreach ( $fields as $field ) {
        if ( isset( $field['id'] ) && $field_id === $field['id'] ) {
            return true;
        }
    }
    return false;
}

/**
 * Returns the path of the URL
 *
 * @param $url
 *
 * @return false|string
 */
function foogallery_local_url_to_path(  $url  ) {
    return FooGallery_Thumb_Generator::get_file_path( $url );
}

/**
 * Sanitizes a code field for saving to the database.
 *
 * @param string $text
 *
 * @return false|string
 */
function foogallery_sanitize_code(  $text  ) {
    if ( !empty( $text ) ) {
        $text = wp_check_invalid_utf8( $text, true );
        return apply_filters( 'foogallery_sanitize_code', $text );
    }
    return false;
}

/**
 * Prepares a code field for use after it has been sanitized.
 *
 * @param string $text
 *
 * @return false|string
 */
function foogallery_prepare_code(  $text  ) {
    if ( !empty( $text ) ) {
        $text = html_entity_decode( $text, ENT_COMPAT | ENT_HTML401, get_bloginfo( 'charset' ) );
        return apply_filters( 'foogallery_prepare_code', $text );
    }
    return false;
}

/**
 * Returns true if the feature is enabled.
 *
 * @param $feature
 * @return bool
 */
function foogallery_feature_enabled(  $feature  ) {
    global $foogallery_features;
    if ( empty( $foogallery_features ) ) {
        $api = new FooGallery_Extensions_API();
        $foogallery_features = $api->get_all_for_view();
    }
    return array_key_exists( $feature, $foogallery_features ) && $foogallery_features[$feature]['is_active'];
}

/**
 * Register a candidate runtime for the Protection feature.
 *
 * Each candidate should provide:
 * - id
 * - priority
 * - enabled_callback
 * - bootstrap_callback
 *
 * @param array $candidate Runtime candidate.
 * @return bool
 */
function foogallery_protection_register_runtime(  $candidate  ) {
    global $foogallery_protection_runtime_candidates;
    if ( !is_array( $candidate ) || empty( $candidate['id'] ) || empty( $candidate['bootstrap_callback'] ) ) {
        return false;
    }
    if ( !isset( $foogallery_protection_runtime_candidates ) || !is_array( $foogallery_protection_runtime_candidates ) ) {
        $foogallery_protection_runtime_candidates = array();
    }
    $candidate = array_merge( array(
        'priority'         => 10,
        'enabled_callback' => '__return_true',
        'label'            => $candidate['id'],
    ), $candidate );
    $foogallery_protection_runtime_candidates[sanitize_key( $candidate['id'] )] = $candidate;
    return true;
}

/**
 * Returns the current Protection runtime owner.
 *
 * @return array|null
 */
function foogallery_protection_runtime_owner() {
    global $foogallery_protection_runtime_owner;
    return ( isset( $foogallery_protection_runtime_owner ) ? $foogallery_protection_runtime_owner : null );
}

/**
 * Boots the highest-priority enabled Protection runtime.
 *
 * @return array|null
 */
function foogallery_protection_boot_runtime() {
    global $foogallery_protection_runtime_booted, $foogallery_protection_runtime_candidates, $foogallery_protection_runtime_owner;
    if ( !empty( $foogallery_protection_runtime_booted ) ) {
        return foogallery_protection_runtime_owner();
    }
    $foogallery_protection_runtime_booted = true;
    if ( empty( $foogallery_protection_runtime_candidates ) || !is_array( $foogallery_protection_runtime_candidates ) ) {
        return null;
    }
    uasort( $foogallery_protection_runtime_candidates, function ( $a, $b ) {
        return intval( $b['priority'] ) <=> intval( $a['priority'] );
    } );
    foreach ( $foogallery_protection_runtime_candidates as $candidate ) {
        $enabled = ( is_callable( $candidate['enabled_callback'] ) ? call_user_func( $candidate['enabled_callback'], $candidate ) : true );
        if ( !$enabled || !is_callable( $candidate['bootstrap_callback'] ) ) {
            continue;
        }
        $foogallery_protection_runtime_owner = $candidate;
        call_user_func( $candidate['bootstrap_callback'], $candidate );
        do_action( 'foogallery_protection_runtime_booted', $candidate );
        return $candidate;
    }
    return null;
}

add_action( 'plugins_loaded', 'foogallery_protection_boot_runtime', 20 );
/**
 * Returns an array of the pro features available in FooGallery.
 *
 * @return array
 */
function foogallery_pro_features() {
    global $foogallery_pro_features;
    if ( !isset( $foogallery_pro_features ) ) {
        $foogallery_pro_features = (include FOOGALLERY_PATH . 'includes/admin/pro-features.php');
    }
    return $foogallery_pro_features;
}

/**
 * Retrieves the target options for FooGallery attachments.
 *
 * The function retrieves an array of target options that can be used for customizing
 * the behavior of attachment links within the FooGallery plugin.
 *
 * @return array An associative array of target options with keys like '_blank', '_self', etc.
 *
 * @since 1.0.0
 */
function foogallery_get_target_options() {
    /**
     * Filter the target options for FooGallery attachments.
     *
     * @param array $target_options An associative array of target options.
     */
    $target_options = apply_filters( 'foogallery_attachment_field_custom_target_options', array(
        'default' => __( 'Default', 'foogallery' ),
        '_blank'  => __( 'New tab (_blank)', 'foogallery' ),
        '_self'   => __( 'Same tab (_self)', 'foogallery' ),
        'foobox'  => __( 'FooBox', 'foogallery' ),
    ) );
    return $target_options;
}

/**
 * If the user can, then create some FooGallery demo content!
 *
 * @return false|int[]
 */
function foogallery_create_demo_content() {
    if ( is_admin() && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
        $importer = new FooGallery_Admin_Demo_Content();
        $results = $importer->import_demo_content();
        foogallery_set_setting( 'demo_content', 'on' );
        return $results;
    }
    return false;
}

/**
 * Returns if Freemius is in anonymous mode.
 *
 * @return false
 */
function foogallery_freemius_is_anonymous() {
    if ( defined( 'FOOPLUGINS_FREEMIUS_ANONYMOUS' ) ) {
        return FOOPLUGINS_FREEMIUS_ANONYMOUS;
    }
    return false;
}

/**
 * Returns the gallery creator role that has been saved in settings.
 *
 * @return string
 */
function foogallery_setting_gallery_creator_role() {
    $gallery_creator_role = foogallery_get_setting( 'gallery_creator_role', 'administrator' );
    if ( empty( $gallery_creator_role ) ) {
        $gallery_creator_role = 'administrator';
    }
    return $gallery_creator_role;
}

/**
 * Returns the role and all roles with higher privileges.
 *
 * @param $role
 * @return array|string[]
 */
function foogallery_get_roles_and_higher(  $role  ) {
    // Define roles in hierarchical order
    $roles_hierarchy = array(
        'subscriber',
        'contributor',
        'author',
        'editor',
        'administrator',
        'super_admin'
    );
    // Find the index of the input role
    $role_index = array_search( $role, $roles_hierarchy );
    // If the input role is not found, return the input role.
    if ( $role_index === false ) {
        // Return the input role, and also admin, as we always want admins to be able to create galleries, when custom roles are set.
        return array($role, 'administrator', 'super_admin');
    }
    // Get the roles with the same or higher privileges
    return array_slice( $roles_hierarchy, $role_index );
}

/**
 * Returns a translated string if the 'init' action has been executed.
 *
 * This function acts as a wrapper for the WordPress translation function `__`.
 * If the 'init' action has been triggered, it returns the translated string
 * using the specified domain. Otherwise, it returns the original translation string.
 *
 * @param string $translation The text to be translated.
 * @param string $domain Optional. The text domain. Default 'foogallery'.
 *
 * @return string The translated text if 'init' action has been executed,
 *                otherwise the original text.
 */
function foogallery__(  $translation, $domain = 'foogallery'  ) {
    if ( did_action( 'init' ) ) {
        return __( $translation, $domain );
    }
    return $translation;
}

/**
 * Formats the caption text for a gallery.
 *
 * @param string $text The caption text to format.
 *
 * @return string The formatted caption text.
 */
function foogallery_format_caption_text(  $text  ) {
    global $current_foogallery;
    if ( empty( $current_foogallery ) ) {
        return $text;
    }
    //if text contains {{gallery-count}}
    if ( strpos( $text, '{{gallery-count}}' ) !== false ) {
        $text = str_replace( '{{gallery-count}}', $current_foogallery->attachment_count(), $text );
    }
    //if text contains {{gallery-title}}
    if ( strpos( $text, '{{gallery-title}}' ) !== false ) {
        $text = str_replace( '{{gallery-title}}', $current_foogallery->name, $text );
    }
    //if text contains {{gallery-description}}
    if ( strpos( $text, '{{gallery-description}}' ) !== false ) {
        $desc = $current_foogallery->_post->post_content;
        $text = str_replace( '{{gallery-description}}', $desc, $text );
    }
    //if text contains {{attachment
    if ( strpos( $text, '{{attachment' ) !== false ) {
        $featured_attachment = $current_foogallery->featured_attachment();
        if ( $featured_attachment ) {
            //if text contains {{attachment-title}}
            if ( strpos( $text, '{{attachment-title}}' ) !== false ) {
                $text = str_replace( '{{attachment-title}}', $featured_attachment->title, $text );
            }
            //if text contains {{attachment-caption}}
            if ( strpos( $text, '{{attachment-caption}}' ) !== false ) {
                $text = str_replace( '{{attachment-caption}}', $featured_attachment->caption, $text );
            }
            //if text contains {{attachment-alt}}
            if ( strpos( $text, '{{attachment-alt}}' ) !== false ) {
                $text = str_replace( '{{attachment-alt}}', $featured_attachment->alt, $text );
            }
            //if text contains {{attachment-description}}
            if ( strpos( $text, '{{attachment-description}}' ) !== false ) {
                $text = str_replace( '{{attachment-description}}', $featured_attachment->description, $text );
            }
        }
    }
    return $text;
}

/**
 * Safely convert a value to an int.
 *
 * @param $value
 * @param int $default
 *
 * @return int
 */
function foogallery_intval(  $value, $default = 0  ) {
    // Already a plain number
    if ( is_numeric( $value ) ) {
        return (int) $value;
    }
    // Backwards compat: extract int
    if ( preg_match( '/\\d+$/', $value, $matches ) ) {
        return (int) $matches[0];
    }
    return $default;
}

/**
 * Returns true if we are currently showing a glalery preview.
 */
function foogallery_is_preview() {
    return isset( $GLOBALS['foogallery_gallery_preview'] ) && $GLOBALS['foogallery_gallery_preview'];
}

/**
 * Sort the retrieved attachment posts after the query has executed.
 *
 * @param FooGalleryAttachment[] $attachments Array of attachment objects.
 * @param string $orderby Orderby clause used for the query.
 * @param string $order Order clause used for the query.
 *
 * @return FooGalleryAttachment[] Sorted array of attachment objects.
 */
function foogallery_sort_attachments(
    $attachments,
    $orderby,
    $order,
    $sort = ''
) {
    if ( empty( $attachments ) ) {
        return $attachments;
    }
    $order = ( strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC' );
    switch ( $orderby ) {
        case 'date':
            usort( $attachments, function ( $a, $b ) use($order) {
                $first_source = $a->date ?? '';
                $second_source = $b->date ?? '';
                $first = ( strtotime( $first_source ) ?: 0 );
                $second = ( strtotime( $second_source ) ?: 0 );
                $comparison = 0;
                if ( $first < $second ) {
                    $comparison = -1;
                } elseif ( $first > $second ) {
                    $comparison = 1;
                }
                return ( 'ASC' === $order ? $comparison : -$comparison );
            } );
            break;
        case 'modified':
            usort( $attachments, function ( $a, $b ) use($order) {
                $first_source = $a->modified ?? '';
                $second_source = $b->modified ?? '';
                $first = ( strtotime( $first_source ) ?: 0 );
                $second = ( strtotime( $second_source ) ?: 0 );
                $comparison = 0;
                if ( $first < $second ) {
                    $comparison = -1;
                } elseif ( $first > $second ) {
                    $comparison = 1;
                }
                return ( 'ASC' === $order ? $comparison : -$comparison );
            } );
            break;
        case 'title':
            usort( $attachments, function ( $a, $b ) use($order) {
                $comparison = strnatcasecmp( $a->title ?? '', $b->title ?? '' );
                if ( 'ASC' === $order ) {
                    return $comparison;
                }
                return -$comparison;
            } );
            break;
        case 'rand':
            shuffle( $attachments );
            break;
        default:
            // For 'post__in' and any other unsupported orderby values we keep the original order when no sort override is set.
            // Check if the attachments have a sort property, and use that to sort.
            $sortable_attachments = array_filter( $attachments, static function ( $attachment ) {
                return isset( $attachment->sort ) && '' !== $attachment->sort && null !== $attachment->sort;
            } );
            if ( !empty( $sortable_attachments ) ) {
                usort( $attachments, function ( $a, $b ) use($order) {
                    $first = $a->sort ?? '';
                    $second = $b->sort ?? '';
                    $first_numeric = is_numeric( $first );
                    $second_numeric = is_numeric( $second );
                    $comparison = 0;
                    if ( $first_numeric || $second_numeric ) {
                        $first = ( $first_numeric ? (float) $first : PHP_INT_MAX );
                        $second = ( $second_numeric ? (float) $second : PHP_INT_MAX );
                        if ( $first < $second ) {
                            $comparison = -1;
                        } elseif ( $first > $second ) {
                            $comparison = 1;
                        }
                    } else {
                        $comparison = strnatcasecmp( (string) $first, (string) $second );
                    }
                    return $comparison;
                } );
            }
            break;
    }
    return apply_filters(
        'foogallery_sort_attachments',
        $attachments,
        $orderby,
        $order,
        $sort
    );
}

/**
 * Returns the lightbox name for the plugin, that is whitelable safe.
 *
 * @return string
 */
function foogallery_lightbox_name() {
    /* translators: %s: Value inserted at runtime. */
    return sprintf( __( '%s Lightbox', 'foogallery' ), foogallery_plugin_name() );
}

/**
 * Resolve a relative asset path OR a full plugin URL into a fingerprinted URL.
 * External URLs (non-plugin) are returned unchanged.
 *
 * @param string $path Relative path OR full URL.
 * @return string Fully-resolved URL (fingerprinted if applicable, otherwise original).
 */
function foogallery_resolve_asset_url(  $path  ) {
    static $manifest = null;
    // Load manifest only once
    if ( $manifest === null ) {
        $manifest_file = FOOGALLERY_PATH . 'includes/asset-manifest.php';
        $manifest = ( file_exists( $manifest_file ) ? include $manifest_file : [] );
    }
    $plugin_url = rtrim( FOOGALLERY_URL, '/' );
    // First, check if $path is a full URL
    if ( preg_match( '#^https?://#i', $path ) ) {
        // If NOT a local asset, then get out early.
        if ( strpos( $path, $plugin_url ) !== 0 ) {
            return $path;
        }
        // Normalize plugin full URL to a relative path.
        $relative_path = ltrim( substr( $path, strlen( $plugin_url ) ), '/' );
    } else {
        $relative_path = $path;
    }
    // Try to resolve through manifest.
    $resolved = ( isset( $manifest[$relative_path] ) ? $manifest[$relative_path] : null );
    // If we do not resolve to anything then return the original AS IS.
    if ( $resolved === null ) {
        return $path;
    }
    // Finally, reconstruct full URL.
    return trailingslashit( $plugin_url ) . ltrim( $resolved, '/' );
}

/**
 * Returns true if the current request is a REST API request.
 *
 * @return bool
 */
function foogallery_is_rest_request() {
    // Must be a real REST API request
    if ( !(defined( 'REST_REQUEST' ) && REST_REQUEST) ) {
        return false;
    }
    // Must be targeting a REST route (sanity check)
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $uri, '/' . rest_get_url_prefix() . '/' ) === false ) {
        return false;
    }
    return true;
}

/**
 * Returns true if the current request is a REST API request from the admin.
 *
 * @return bool
 */
function foogallery_is_rest_request_from_admin() {
    if ( !foogallery_is_rest_request() ) {
        return false;
    }
    // Must have an admin referer
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ( empty( $ref ) ) {
        return false;
    }
    // Check referer starts with /wp-admin/
    if ( strpos( $ref, admin_url() ) === 0 ) {
        // Finally, ensures the user is logged in.
        return is_user_logged_in();
    }
    return false;
}

require_once __DIR__ . '/gallery-management-functions.php';