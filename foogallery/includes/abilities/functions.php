<?php
/**
 * FooGallery abilities helper functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determine whether the WordPress core Abilities API is available.
 *
 * @return bool
 */
function foogallery_abilities_wp_api_available() {
	return class_exists( 'WP_Ability' ) &&
		function_exists( 'wp_register_ability' ) &&
		function_exists( 'wp_register_ability_category' );
}

/**
 * Normalize ability input.
 *
 * @param mixed $input Ability input.
 *
 * @return array
 */
function foogallery_abilities_normalize_input( $input ) {
	return is_array( $input ) ? $input : array();
}

/**
 * Return a stable RFC3339-ish string for a post datetime field.
 *
 * WordPress can return `false` for GMT timestamps when the `_gmt` column is empty,
 * which happens on real FooGallery draft rows. Fall back to the local timestamp so
 * ability output always matches the declared string schema.
 *
 * @param int|WP_Post $post Post ID or instance.
 * @param string      $type Datetime field type. Accepts `date` or `modified`.
 *
 * @return string
 */
function foogallery_abilities_get_post_datetime_string( $post, $type = 'modified' ) {
	$type = 'date' === $type ? 'date' : 'modified';
	$time = call_user_func( 'get_post_' . $type . '_time', 'c', true, $post );

	if ( false === $time ) {
		$time = call_user_func( 'get_post_' . $type . '_time', 'c', false, $post );
	}

	return is_string( $time ) ? $time : '';
}

/**
 * Resolve a requested gallery layout slug from ability input.
 *
 * Accepts `layout` as the canonical key and `template` as a legacy fallback.
 *
 * @param array  $args    Ability input.
 * @param string $default Default layout slug.
 *
 * @return string
 */
function foogallery_abilities_get_requested_layout( $args, $default = '' ) {
	$args   = is_array( $args ) ? $args : array();
	$layout = '';

	if ( isset( $args['layout'] ) ) {
		$layout = sanitize_key( $args['layout'] );
	} elseif ( isset( $args['template'] ) ) {
		$layout = sanitize_key( $args['template'] );
	}

	if ( '' !== $layout ) {
		return $layout;
	}

	return sanitize_key( $default );
}

/**
 * Get the public setting ID for a layout field.
 *
 * Uses the field alias when it exists, otherwise falls back to the field ID.
 *
 * @param array $field Layout field definition.
 *
 * @return string
 */
function foogallery_abilities_get_public_setting_id( $field ) {
	if ( ! is_array( $field ) ) {
		return '';
	}

	if ( ! empty( $field['alias'] ) ) {
		return sanitize_key( $field['alias'] );
	}

	return isset( $field['id'] ) ? sanitize_key( $field['id'] ) : '';
}

/**
 * Build lookup indexes for a layout's field definitions.
 *
 * @param string $template Layout slug.
 *
 * @return array
 */
function foogallery_abilities_get_template_field_indexes( $template ) {
	$template       = sanitize_key( $template );
	$fields         = foogallery_get_fields_for_template( $template );
	$base_fields    = $fields;
	$registered_ids = array();

	foreach ( $base_fields as $field ) {
		if ( ! empty( $field['id'] ) ) {
			$registered_ids[] = sanitize_key( $field['id'] );
		}
	}

	foreach ( $base_fields as $field ) {
		$mobile_field = foogallery_get_mobile_field_for_template_field( $field, null, $template );
		if ( false !== $mobile_field && ! in_array( sanitize_key( $mobile_field['id'] ), $registered_ids, true ) ) {
			$fields[] = $mobile_field;
			$registered_ids[] = sanitize_key( $mobile_field['id'] );
		}
	}

	$indexes = array(
		'fields'  => $fields,
		'lookup'  => array(),
		'storage' => array(),
	);

	foreach ( $fields as $field ) {
		if ( empty( $field['id'] ) ) {
			continue;
		}

		$field_id            = sanitize_key( $field['id'] );
		$public_setting_id   = foogallery_abilities_get_public_setting_id( $field );
		$storage_setting_key = $template . '_' . $field_id;

		$indexes['lookup'][ $field_id ] = $field;
		$indexes['storage'][ $storage_setting_key ] = $field;

		if ( '' !== $public_setting_id ) {
			$indexes['lookup'][ $public_setting_id ] = $field;
		}
	}

	return $indexes;
}

/**
 * Return the supported media search fields.
 *
 * @return array
 */
function foogallery_abilities_get_media_searchable_fields() {
	return array(
		'title',
		'caption',
		'description',
		'alt',
		'url',
		'taxonomy_terms',
	);
}

/**
 * Normalize the requested media search fields.
 *
 * @param mixed $search_by Requested fields.
 *
 * @return array
 */
function foogallery_abilities_normalize_media_search_fields( $search_by ) {
	$aliases = array(
		'taxonomy'   => 'taxonomy_terms',
		'taxonomies' => 'taxonomy_terms',
		'terms'      => 'taxonomy_terms',
	);

	if ( is_string( $search_by ) ) {
		$search_by = false !== strpos( $search_by, ',' ) ? explode( ',', $search_by ) : array( $search_by );
	}

	if ( ! is_array( $search_by ) || empty( $search_by ) ) {
		return foogallery_abilities_get_media_searchable_fields();
	}

	$normalized = array();
	$supported  = foogallery_abilities_get_media_searchable_fields();

	foreach ( $search_by as $field ) {
		$field = sanitize_key( $field );

		if ( isset( $aliases[ $field ] ) ) {
			$field = $aliases[ $field ];
		}

		if ( in_array( $field, $supported, true ) && ! in_array( $field, $normalized, true ) ) {
			$normalized[] = $field;
		}
	}

	return $normalized;
}

/**
 * Search media-library attachments across attachment metadata fields.
 *
 * @param string $search    Search phrase.
 * @param mixed  $search_by Requested search fields.
 * @param int    $limit     Result limit.
 * @param int    $offset    Result offset.
 *
 * @return array|WP_Error
 */
function foogallery_abilities_search_media_attachments( $search, $search_by = array(), $limit = 20, $offset = 0 ) {
	global $wpdb;

	$search        = sanitize_text_field( $search );
	$search_fields = foogallery_abilities_normalize_media_search_fields( $search_by );
	$limit         = max( 1, min( 100, absint( $limit ) ) );
	$offset        = max( 0, absint( $offset ) );

	if ( '' === $search ) {
		return new WP_Error(
			'foogallery_ability_missing_search',
			__( 'A non-empty search string is required.', 'foogallery' )
		);
	}

	if ( empty( $search_fields ) ) {
		return new WP_Error(
			'foogallery_ability_invalid_search_fields',
			__( 'Provide at least one valid search field.', 'foogallery' )
		);
	}

	$like          = '%' . $wpdb->esc_like( $search ) . '%';
	$where_sql     = array();
	$where_params  = array();
	$base_params   = array( 'attachment', 'trash' );
	$posts_table   = $wpdb->posts;
	$postmeta_table = $wpdb->postmeta;
	$term_relationships_table = $wpdb->term_relationships;
	$term_taxonomy_table      = $wpdb->term_taxonomy;
	$terms_table              = $wpdb->terms;

	foreach ( $search_fields as $field ) {
		switch ( $field ) {
			case 'title':
				$where_sql[]    = 'p.post_title LIKE %s';
				$where_params[] = $like;
				break;
			case 'caption':
				$where_sql[]    = 'p.post_excerpt LIKE %s';
				$where_params[] = $like;
				break;
			case 'description':
				$where_sql[]    = 'p.post_content LIKE %s';
				$where_params[] = $like;
				break;
			case 'alt':
				$where_sql[]    = "EXISTS ( SELECT 1 FROM {$postmeta_table} pm WHERE pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value LIKE %s )";
				$where_params[] = '_wp_attachment_image_alt';
				$where_params[] = $like;
				break;
			case 'url':
				$where_sql[]    = 'p.guid LIKE %s';
				$where_params[] = $like;
				break;
			case 'taxonomy_terms':
				$where_sql[]    = "EXISTS ( SELECT 1 FROM {$term_relationships_table} tr INNER JOIN {$term_taxonomy_table} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN {$terms_table} t ON tt.term_id = t.term_id WHERE tr.object_id = p.ID AND ( t.name LIKE %s OR t.slug LIKE %s ) )";
				$where_params[] = $like;
				$where_params[] = $like;
				break;
		}
	}

	$sql_where = implode( ' OR ', $where_sql );

	if ( '' === $sql_where ) {
		return new WP_Error(
			'foogallery_ability_invalid_search_fields',
			__( 'Provide at least one valid search field.', 'foogallery' )
		);
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- The dynamic WHERE clause contains only hardcoded fragments selected by the strict search-field allowlist above.
	$total = (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name comes from wpdb and the WHERE clause contains only allowlisted hardcoded fragments; all values use placeholders.
			"SELECT COUNT( DISTINCT p.ID ) FROM {$posts_table} p WHERE p.post_type = %s AND p.post_status <> %s AND ( {$sql_where} )",
			array_merge( $base_params, $where_params )
		)
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- The dynamic WHERE clause contains only hardcoded fragments selected by the strict search-field allowlist above.
	$attachment_ids = $wpdb->get_col(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name comes from wpdb and the WHERE clause contains only allowlisted hardcoded fragments; all values use placeholders.
			"SELECT DISTINCT p.ID FROM {$posts_table} p WHERE p.post_type = %s AND p.post_status <> %s AND ( {$sql_where} ) ORDER BY p.post_date DESC, p.ID DESC LIMIT %d OFFSET %d",
			array_merge( $base_params, $where_params, array( $limit, $offset ) )
		)
	);

	return array(
		'total'          => $total,
		'attachment_ids' => array_map( 'absint', $attachment_ids ),
	);
}

/**
 * Load a gallery or return a WP_Error.
 *
 * @param int $gallery_id Gallery post ID.
 *
 * @return FooGallery|WP_Error
 */
function foogallery_abilities_get_gallery( $gallery_id ) {
	$gallery_id = absint( $gallery_id );

	if ( $gallery_id <= 0 ) {
		return new WP_Error(
			'foogallery_ability_invalid_gallery',
			__( 'A valid gallery ID is required.', 'foogallery' )
		);
	}

	$gallery = FooGallery::get_by_id( $gallery_id );

	if ( false === $gallery || ! $gallery->does_exist() ) {
		return new WP_Error(
			'foogallery_ability_gallery_not_found',
			__( 'The requested gallery could not be found.', 'foogallery' )
		);
	}

	return $gallery;
}

/**
 * Load a layout definition or return a WP_Error.
 *
 * @param string $layout Layout slug.
 *
 * @return array|WP_Error
 */
function foogallery_abilities_get_template( $layout ) {
	$layout = sanitize_key( $layout );

	if ( empty( $layout ) ) {
		$layout = foogallery_default_gallery_template();
	}

	$template_definition = foogallery_get_gallery_template( $layout );

	if ( false === $template_definition ) {
		return new WP_Error(
			'foogallery_ability_invalid_layout',
			__( 'The requested gallery layout is not registered.', 'foogallery' )
		);
	}

	return $template_definition;
}

/**
 * Normalize a list of attachment IDs.
 *
 * @param mixed $attachment_ids Attachment IDs as an array or comma separated string.
 *
 * @return int[]
 */
function foogallery_abilities_normalize_attachment_ids( $attachment_ids ) {
	return foogallery_normalize_attachment_ids( $attachment_ids );
}

/**
 * Validate a list of attachment IDs.
 *
 * @param mixed $attachment_ids Attachment IDs as an array or comma separated string.
 *
 * @return int[]|WP_Error
 */
function foogallery_abilities_validate_attachment_ids( $attachment_ids ) {
	return foogallery_validate_attachment_ids( $attachment_ids );
}

/**
 * Map a core gallery-management error to the existing Ability error namespace.
 *
 * Authorization is the caller's responsibility.
 *
 * @param WP_Error $error Core gallery-management error.
 *
 * @return WP_Error
 */
function foogallery_abilities_map_gallery_management_error( $error ) {
	$error_code  = $error->get_error_code();
	$code_map    = array(
		'foogallery_invalid_gallery'                => 'foogallery_ability_invalid_gallery',
		'foogallery_gallery_not_found'              => 'foogallery_ability_gallery_not_found',
		'foogallery_invalid_source_gallery'         => 'foogallery_ability_invalid_source_gallery',
		'foogallery_invalid_template'               => 'foogallery_ability_invalid_layout',
		'foogallery_invalid_status'                 => 'foogallery_ability_invalid_status',
		'foogallery_invalid_settings'               => 'foogallery_ability_invalid_settings',
		'foogallery_update_failed'                  => 'foogallery_ability_update_failed',
		'foogallery_invalid_attachments'            => 'foogallery_ability_invalid_attachments',
		'foogallery_unsupported_datasource'         => 'foogallery_ability_unsupported_datasource',
		'foogallery_no_attachment_changes'          => 'foogallery_ability_no_attachment_changes',
		'foogallery_conflicting_attachment_changes' => 'foogallery_ability_conflicting_attachment_changes',
		'foogallery_invalid_attachment_changes'     => 'foogallery_ability_invalid_attachment_changes',
	);
	$message_map = array(
		'foogallery_invalid_template'               => __( 'The requested gallery layout is not registered.', 'foogallery' ),
		'foogallery_unsupported_datasource'         => __( 'This ability currently supports media-library-backed galleries only.', 'foogallery' ),
		'foogallery_no_attachment_changes'          => __( 'Provide replace_attachment_ids, append_attachment_ids, remove_attachment_ids, or a combination of append/remove.', 'foogallery' ),
		'foogallery_conflicting_attachment_changes' => __( 'replace_attachment_ids cannot be combined with append_attachment_ids or remove_attachment_ids. Use replace on its own, or use append/remove together.', 'foogallery' ),
	);

	if ( ! isset( $code_map[ $error_code ] ) ) {
		return $error;
	}

	return new WP_Error(
		$code_map[ $error_code ],
		isset( $message_map[ $error_code ] ) ? $message_map[ $error_code ] : $error->get_error_message( $error_code ),
		$error->get_error_data( $error_code )
	);
}

/**
 * Sanitize an arbitrary settings value.
 *
 * @param mixed $value The incoming value.
 *
 * @return mixed
 */
function foogallery_abilities_sanitize_value( $value ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = foogallery_abilities_sanitize_value( $item );
		}

		return $value;
	}

	if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
		return $value;
	}

	if ( null === $value ) {
		return '';
	}

	return sanitize_text_field( wp_unslash( $value ) );
}

/**
 * Sanitize a template field value based on the field definition.
 *
 * @param array $field Field definition.
 * @param mixed $value Incoming value.
 *
 * @return mixed
 */
function foogallery_abilities_sanitize_template_field_value( $field, $value ) {
	$field_type = isset( $field['type'] ) ? $field['type'] : '';

	if ( 'thumb_size' === $field_type || 'thumb_size_no_crop' === $field_type ) {
		$sanitized = array();

		if ( ! is_array( $value ) ) {
			return $sanitized;
		}

		if ( isset( $value['width'] ) ) {
			$sanitized['width'] = absint( $value['width'] );
		}

		if ( isset( $value['height'] ) ) {
			$sanitized['height'] = absint( $value['height'] );
		}

		if ( isset( $value['crop'] ) ) {
			$sanitized['crop'] = ! empty( $value['crop'] );
		}

		return $sanitized;
	}

	if ( 'text' === $field_type || 'textarea' === $field_type ) {
		return foogallery_sanitize_full( is_scalar( $value ) ? wp_unslash( (string) $value ) : '' );
	}

	if ( 'number' === $field_type || 'slider' === $field_type ) {
		if ( '' === $value || null === $value ) {
			return '';
		}

		$step = isset( $field['step'] ) ? (string) $field['step'] : '1';
		if ( false !== strpos( $step, '.' ) ) {
			$value = (float) $value;
		} else {
			$value = intval( $value );
		}

		if ( isset( $field['min'] ) && is_numeric( $field['min'] ) && $value < $field['min'] ) {
			$value = 0 + $field['min'];
		}

		if ( isset( $field['max'] ) && is_numeric( $field['max'] ) && $value > $field['max'] ) {
			$value = 0 + $field['max'];
		}

		return $value;
	}

	if ( 'checkbox' === $field_type ) {
		return empty( $value ) ? '' : 'on';
	}

	if ( isset( $field['choices'] ) && is_array( $field['choices'] ) && ! is_array( $value ) ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		if ( array_key_exists( $value, $field['choices'] ) ) {
			return $value;
		}

		return array_key_exists( 'default', $field ) ? $field['default'] : '';
	}

	return foogallery_abilities_sanitize_value( $value );
}

/**
 * Normalize settings for a template and merge them into a base settings array.
 *
 * @param string $template      Template slug.
 * @param array  $settings      Incoming template settings.
 * @param array  $base_settings Existing settings.
 * @param int    $gallery_id    Gallery ID for filter compatibility.
 * @param array  $context       Context passed through save filters.
 * @param bool   $strict        Whether each public save-filter boundary must return a safe array.
 *
 * @return array|WP_Error
 */
function foogallery_abilities_normalize_template_settings( $template, $settings, $base_settings = array(), $gallery_id = 0, $context = array(), $strict = false ) {
	$template      = sanitize_key( $template );
	$settings      = is_array( $settings ) ? $settings : array();
	$merged        = is_array( $base_settings ) ? $base_settings : array();
	$field_indexes = foogallery_abilities_get_template_field_indexes( $template );
	$field_map     = $field_indexes['lookup'];

	foreach ( $settings as $key => $value ) {
		$field_id = sanitize_key( (string) $key );

		if ( 0 === strpos( $field_id, $template . '_' ) ) {
			$field_id = substr( $field_id, strlen( $template ) + 1 );
		}

		if ( ! array_key_exists( $field_id, $field_map ) ) {
			continue;
		}

		$merged[ $template . '_' . $field_map[ $field_id ]['id'] ] = foogallery_abilities_sanitize_template_field_value( $field_map[ $field_id ], $value );
	}

	if ( $strict ) {
		$merged = foogallery_abilities_apply_strict_settings_filter( 'foogallery_save_gallery_settings', $merged, $gallery_id, $context );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}

		$merged = foogallery_abilities_apply_strict_settings_filter( 'foogallery_save_gallery_settings-' . $template, $merged, $gallery_id, $context );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}
	} else {
		$merged = apply_filters( 'foogallery_save_gallery_settings', $merged, $gallery_id, $context );
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Existing template-specific hook uses a hyphen before the slug.
		$merged = apply_filters( 'foogallery_save_gallery_settings-' . $template, $merged, $gallery_id, $context );
	}

	foreach ( $merged as $key => $value ) {
		$field                     = $field_indexes['storage'][ sanitize_key( (string) $key ) ] ?? null;
		$valid_empty_mobile_choice = '' === $value
			&& is_array( $field )
			&& ! empty( $field['_foogallery_mobile_variant'] )
			&& isset( $field['choices'] )
			&& is_array( $field['choices'] )
			&& array_key_exists( '', $field['choices'] );

		if ( ( '' === $value && ! $valid_empty_mobile_choice ) || null === $value ) {
			unset( $merged[ $key ] );
		}
	}
	$merged = foogallery_normalize_mobile_settings_for_save( $merged, $template );
	$merged = foogallery_preserve_paid_mobile_settings( $merged, $template, $gallery_id );

	return $merged;
}

/**
 * Apply a settings filter and reject an unsafe return immediately after any callback.
 *
 * A later callback may coerce an invalid value back to an array. Temporarily wrapping
 * the registered callbacks lets the public API stop at the callback that violated the
 * contract while retaining WordPress's normal filter bookkeeping and argument handling.
 * Legacy callers continue to use the unwrapped filter chain.
 *
 * @param string $hook_name  Filter name.
 * @param array  $settings   Settings passed to the filter.
 * @param int    $gallery_id Gallery ID.
 * @param array  $context    Save context.
 *
 * @return array|WP_Error
 */
function foogallery_abilities_apply_strict_settings_filter( $hook_name, $settings, $gallery_id, $context ) {
	global $wp_filter;

	if ( ! is_array( $settings ) || ! foogallery_abilities_is_supported_setting_value( $settings ) ) {
		return new WP_Error(
			'foogallery_invalid_settings',
			__( 'Gallery settings filters must return a safely serializable array.', 'foogallery' )
		);
	}

	if ( ! isset( $wp_filter[ $hook_name ] ) || ! $wp_filter[ $hook_name ] instanceof WP_Hook ) {
		return $settings;
	}

	$hook           = $wp_filter[ $hook_name ];
	$wrapped        = array();
	$invalid_return = false;
	$wrap_callbacks = null;
	$wrap_callbacks = static function () use ( $hook, &$wrapped, &$invalid_return, &$wrap_callbacks ) {
		foreach ( $hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback_id => $callback ) {
				if (
					isset( $wrapped[ $priority ][ $callback_id ]['wrapper'] )
					&& $callback['function'] === $wrapped[ $priority ][ $callback_id ]['wrapper']
				) {
					continue;
				}

				$original = $callback['function'];
				$wrapper  = static function () use ( $original, &$invalid_return, &$wrap_callbacks ) {
					$arguments = func_get_args();

					if ( $invalid_return ) {
						return isset( $arguments[0] ) ? $arguments[0] : null;
					}

					$value = call_user_func_array( $original, $arguments );
					$wrap_callbacks();

					if ( ! is_array( $value ) || ! foogallery_abilities_is_supported_setting_value( $value ) ) {
						$invalid_return = true;
					}

					return $value;
				};

				$wrapped[ $priority ][ $callback_id ] = array(
					'original' => $original,
					'wrapper'  => $wrapper,
				);

				$hook->callbacks[ $priority ][ $callback_id ]['function'] = $wrapper;
			}
		}
	};
	$wrap_callbacks();

	try {
		// Current strict-save callers pass FooGallery-prefixed hooks with an optional template suffix.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Current callers use the FooGallery-prefixed base or template-specific hook.
		$settings = apply_filters( $hook_name, $settings, $gallery_id, $context );
	} finally {
		foreach ( $wrapped as $priority => $callbacks ) {
			foreach ( $callbacks as $callback_id => $callback ) {
				if (
					isset( $hook->callbacks[ $priority ][ $callback_id ]['function'] )
					&& $hook->callbacks[ $priority ][ $callback_id ]['function'] === $callback['wrapper']
				) {
					$hook->callbacks[ $priority ][ $callback_id ]['function'] = $callback['original'];
				}
			}
		}
	}

	if ( $invalid_return || ! is_array( $settings ) || ! foogallery_abilities_is_supported_setting_value( $settings ) ) {
		return new WP_Error(
			'foogallery_invalid_settings',
			__( 'Gallery settings filters must return a safely serializable array.', 'foogallery' )
		);
	}

	return $settings;
}

/**
 * Determine whether a setting value can be normalized without lossy object coercion.
 *
 * Registered custom field types may use nested arrays, while objects and resources are
 * unsupported by the settings normalizer.
 *
 * @param mixed $value Setting value.
 *
 * @return bool
 */
function foogallery_abilities_is_supported_setting_value( $value ) {
	$item_count = 0;

	return foogallery_abilities_is_supported_setting_value_recursive( $value, 0, $item_count );
}

/**
 * Recursively validate a settings value within conservative processing bounds.
 *
 * @param mixed $value      Setting value.
 * @param int   $depth      Current array depth.
 * @param int   $item_count Number of array items inspected.
 *
 * @return bool
 */
function foogallery_abilities_is_supported_setting_value_recursive( $value, $depth, &$item_count ) {
	if ( null === $value || is_scalar( $value ) ) {
		return true;
	}

	if ( ! is_array( $value ) || $depth >= 20 ) {
		return false;
	}

	foreach ( $value as $key => $item ) {
		++$item_count;
		if ( $item_count > 10000 || ( ! is_int( $key ) && ! is_string( $key ) ) ) {
			return false;
		}

		if ( ! foogallery_abilities_is_supported_setting_value_recursive( $item, $depth + 1, $item_count ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Normalize a list of template setting update objects into a keyed settings array.
 *
 * @param string $template Template slug.
 * @param mixed  $settings Incoming setting update objects.
 *
 * @return array|WP_Error
 */
function foogallery_abilities_prepare_template_setting_updates( $template, $settings ) {
	$template      = sanitize_key( $template );
	$field_indexes = foogallery_abilities_get_template_field_indexes( $template );
	$field_map     = $field_indexes['lookup'];
	$normalized    = array();
	$invalid_ids   = array();
	$invalid_items = array();

	if ( ! is_array( $settings ) ) {
		return new WP_Error(
			'foogallery_ability_invalid_settings',
			__( 'Gallery settings must be an array of setting update objects.', 'foogallery' ),
			array( 'layout' => $template )
		);
	}

	foreach ( $settings as $index => $setting ) {
		if ( ! is_array( $setting ) || ! array_key_exists( 'id', $setting ) || ! is_string( $setting['id'] ) || '' === sanitize_key( $setting['id'] ) ) {
			$invalid_items[] = $index;
			continue;
		}

		$field_id = sanitize_key( $setting['id'] );

		if ( 0 === strpos( $field_id, $template . '_' ) ) {
			$field_id = substr( $field_id, strlen( $template ) + 1 );
		}

		if ( ! array_key_exists( $field_id, $field_map ) ) {
			$invalid_ids[] = isset( $setting['id'] ) ? sanitize_text_field( (string) $setting['id'] ) : '';
			continue;
		}

		$field      = $field_map[ $field_id ];
		$field_type = isset( $field['type'] ) ? $field['type'] : '';
		$value      = null;
		$valid      = true;

		if ( 'thumb_size' === $field_type || 'thumb_size_no_crop' === $field_type ) {
			$allowed_keys = 'thumb_size' === $field_type ? array( 'id', 'width', 'height', 'crop' ) : array( 'id', 'width', 'height' );
			$value        = array();
			$valid        = empty( array_diff( array_keys( $setting ), $allowed_keys ) );
			$valid        = $valid && ( array_key_exists( 'width', $setting ) || array_key_exists( 'height', $setting ) || ( 'thumb_size' === $field_type && array_key_exists( 'crop', $setting ) ) );

			if ( array_key_exists( 'width', $setting ) ) {
				$valid          = $valid && is_int( $setting['width'] );
				$value['width'] = absint( $setting['width'] );
			}

			if ( array_key_exists( 'height', $setting ) ) {
				$valid           = $valid && is_int( $setting['height'] );
				$value['height'] = absint( $setting['height'] );
			}

			if ( array_key_exists( 'crop', $setting ) ) {
				$valid         = $valid && is_bool( $setting['crop'] );
				$value['crop'] = $setting['crop'];
			}
		} else {
			$valid = empty( array_diff( array_keys( $setting ), array( 'id', 'value' ) ) ) && array_key_exists( 'value', $setting );
			$valid = $valid && foogallery_abilities_is_supported_setting_value( $setting['value'] ?? null );

			if ( $valid && is_array( $setting['value'] ) ) {
				$allows_structured_value = is_array( $field['default'] ?? null ) || ! empty( $field['allows_structured_value'] );
				$allows_structured_value = apply_filters( 'foogallery_abilities_setting_allows_structured_value', $allows_structured_value, $field, $setting, $template );
				$valid                   = $allows_structured_value;
			}

			if ( $valid && ! is_array( $setting['value'] ) ) {
				$valid = is_scalar( $setting['value'] ) || null === $setting['value'];
			}

			if ( $valid ) {
				$value = $setting['value'];
			}
		}

		if ( ! $valid ) {
			$invalid_items[] = $index;
			continue;
		}

		$normalized[ $field_id ] = $value;
	}

	if ( ! empty( $invalid_ids ) || ! empty( $invalid_items ) ) {
		$invalid_ids = array_values( array_unique( array_filter( $invalid_ids ) ) );

		return new WP_Error(
			'foogallery_ability_invalid_settings',
			__( 'One or more gallery setting updates are malformed or invalid for the selected layout.', 'foogallery' ),
			array(
				'layout'              => $template,
				'invalid_setting_ids' => $invalid_ids,
				'invalid_items'       => array_values( $invalid_items ),
			)
		);
	}

	return $normalized;
}

/**
 * Build a settings skeleton for a template using all registered fields.
 *
 * @param string $template Template slug.
 *
 * @return array
 */
function foogallery_abilities_build_template_settings_base( $template ) {
	$template      = sanitize_key( $template );
	$field_indexes = foogallery_abilities_get_template_field_indexes( $template );
	$settings      = array();

	foreach ( $field_indexes['fields'] as $field ) {
		if ( empty( $field['id'] ) ) {
			continue;
		}

		$default = array_key_exists( 'default', $field ) ? $field['default'] : '';
		if ( null !== $default ) {
			$settings[ $template . '_' . $field['id'] ] = $default;
		}
	}

	return $settings;
}

/**
 * Sanitize a gallery status.
 *
 * @param string $status  Requested status.
 * @param string $default Default status.
 *
 * @return string
 */
function foogallery_abilities_sanitize_gallery_status( $status, $default = 'draft' ) {
	$allowed_statuses = array( 'draft', 'publish', 'private' );
	$status           = sanitize_key( $status );

	if ( in_array( $status, $allowed_statuses, true ) ) {
		return $status;
	}

	return $default;
}

/**
 * Sanitize a gallery sort value.
 *
 * @param string $sort Sort value.
 *
 * @return string
 */
function foogallery_abilities_sanitize_gallery_sort( $sort ) {
	$sort = sanitize_text_field( $sort );

	if ( array_key_exists( $sort, foogallery_sorting_options() ) ) {
		return $sort;
	}

	return '';
}

/**
 * Return the common schema for a gallery setting update.
 *
 * @return array
 */
function foogallery_abilities_get_setting_update_schema() {
	return array(
		'type'                 => 'object',
		'required'             => array( 'id' ),
		'properties'           => array(
			'id'     => array(
				'type'        => 'string',
				'description' => __( 'The normalized gallery setting ID returned by the `foogallery/get-gallery-layout-schema` tool.', 'foogallery' ),
			),
			'value'  => array(
				'type'        => array( 'string', 'number', 'boolean', 'null', 'object', 'array' ),
				'description' => __( 'The scalar value for most settings, or a structured value supported by a registered custom field type.', 'foogallery' ),
			),
			'width'  => array(
				'type'        => 'integer',
				'description' => __( 'Width value for thumbnail size settings such as `thumbnail_size`.', 'foogallery' ),
			),
			'height' => array(
				'type'        => 'integer',
				'description' => __( 'Height value for thumbnail size settings such as `thumbnail_size`.', 'foogallery' ),
			),
			'crop'   => array(
				'type'        => 'boolean',
				'description' => __( 'Crop flag for thumbnail size settings that support cropping.', 'foogallery' ),
			),
		),
		'additionalProperties' => false,
	);
}

/**
 * Return the common schema for a FooGallery template field.
 *
 * @return array
 */
function foogallery_abilities_get_template_field_schema() {
	return array(
		'type'       => 'object',
		'properties' => array(
			'id'            => array(
				'type' => 'string',
			),
			'title'         => array(
				'type' => 'string',
			),
			'type'          => array(
				'type' => 'string',
			),
			'section'       => array(
				'type' => 'string',
			),
			'section_id'    => array(
				'type' => 'string',
			),
			'subsection'    => array(
				'type' => 'string',
			),
			'subsection_id' => array(
				'type' => 'string',
			),
			'description'   => array(
				'type' => 'string',
			),
			'choices'       => array(
				'type' => 'object',
			),
		),
	);
}

/**
 * Return the common schema for a FooGallery template.
 *
 * @param bool $include_fields Whether to include field definitions.
 *
 * @return array
 */
function foogallery_abilities_get_template_schema( $include_fields = true ) {
	$schema = array(
		'type'       => 'object',
		'properties' => array(
			'slug'                  => array(
				'type' => 'string',
			),
			'name'                  => array(
				'type' => 'string',
			),
		),
	);

	if ( $include_fields ) {
		$schema['properties']['fields'] = array(
			'type'  => 'array',
			'items' => foogallery_abilities_get_template_field_schema(),
		);
	}

	return $schema;
}

/**
 * Return the common schema for an attachment term.
 *
 * @return array
 */
function foogallery_abilities_get_term_schema() {
	return array(
		'type'       => 'object',
		'properties' => array(
			'id'   => array(
				'type' => 'integer',
			),
			'slug' => array(
				'type' => 'string',
			),
			'name' => array(
				'type' => 'string',
			),
		),
	);
}

/**
 * Return the common schema for a FooGallery attachment.
 *
 * @return array
 */
function foogallery_abilities_get_attachment_schema() {
	return array(
		'type'       => 'object',
		'properties' => array(
			'id'              => array(
				'type' => 'integer',
			),
			'title'           => array(
				'type' => 'string',
			),
			'caption'         => array(
				'type' => 'string',
			),
			'description'     => array(
				'type' => 'string',
			),
			'alt'             => array(
				'type' => 'string',
			),
			'url'             => array(
				'type'   => 'string',
				'format' => 'uri',
			),
			'width'           => array(
				'type' => 'integer',
			),
			'height'          => array(
				'type' => 'integer',
			),
			'custom_url'      => array(
				'type' => 'string',
			),
			'custom_target'   => array(
				'type' => 'string',
			),
			'custom_rel'      => array(
				'type' => 'string',
			),
				'parent_post_id'  => array(
					'type' => 'integer',
				),
			'date'            => array(
				'type' => 'string',
			),
			'modified'        => array(
				'type' => 'string',
			),
			'taxonomies'      => array(
				'type'                 => 'object',
				'additionalProperties' => array(
					'type'  => 'array',
					'items' => foogallery_abilities_get_term_schema(),
				),
			),
		),
	);
}

/**
 * Return the common schema for a FooGallery summary payload.
 *
 * @return array
 */
function foogallery_abilities_get_gallery_summary_schema() {
	return array(
		'type'       => 'object',
		'properties' => array(
			'id'               => array(
				'type' => 'integer',
			),
			'title'            => array(
				'type' => 'string',
			),
			'status'           => array(
				'type' => 'string',
			),
			'layout'           => array(
				'type' => 'string',
			),
			'datasource'       => array(
				'type' => 'string',
			),
			'is_dynamic'       => array(
				'type' => 'boolean',
			),
			'attachment_count' => array(
				'type' => 'integer',
			),
			'modified'         => array(
				'type' => 'string',
			),
		),
	);
}

/**
 * Return the common schema for a full FooGallery detail payload.
 *
 * @return array
 */
function foogallery_abilities_get_gallery_detail_schema( $include_attachment_detail ) {
	$schema = foogallery_abilities_get_gallery_summary_schema();

	$schema['properties']['settings'] = array(
		'type' => 'object',
	);
	$schema['properties']['sort'] = array(
		'type' => 'string',
	);
	$schema['properties']['custom_css'] = array(
		'type' => 'string',
	);
	$schema['properties']['retina'] = array(
		'type' => 'object',
	);
	$schema['properties']['attachment_ids'] = array(
		'type'  => 'array',
		'items' => array(
			'type' => 'integer',
		),
	);
	$schema['properties']['force_use_original_thumbs'] = array(
		'type' => 'boolean',
	);
	$schema['properties']['datasource_value'] = array(
		'type' => 'object',
	);
	if ( $include_attachment_detail ) {
		$schema['properties']['attachments'] = array(
			'type'  => 'array',
			'items' => foogallery_abilities_get_attachment_schema(),
		);
	}

	return $schema;
}

/**
 * Determine whether a layout field should be exposed in ability responses.
 *
 * @param array $field Field definition.
 *
 * @return bool
 */
function foogallery_abilities_should_expose_template_field( $field ) {
	if ( ! is_array( $field ) ) {
		return false;
	}

	return ! isset( $field['type'] ) || 'help' !== $field['type'];
}

/**
 * Convert raw field choices into a stable value => label map.
 *
 * @param array $choices Field choices.
 *
 * @return array
 */
function foogallery_abilities_prepare_template_field_choices( $choices ) {
	$prepared = array();

	if ( ! is_array( $choices ) ) {
		return $prepared;
	}

	foreach ( $choices as $value => $choice ) {
		if ( is_array( $choice ) && isset( $choice['label'] ) ) {
			$prepared[ (string) $value ] = wp_strip_all_tags( $choice['label'] );
			continue;
		}

		if ( is_scalar( $choice ) || ( is_object( $choice ) && method_exists( $choice, '__toString' ) ) ) {
			$prepared[ (string) $value ] = wp_strip_all_tags( (string) $choice );
		}
	}

	return $prepared;
}

/**
 * Convert a field subsection definition into a stable label.
 *
 * @param array $subsection Raw subsection definition.
 *
 * @return string
 */
function foogallery_abilities_prepare_template_field_subsection( $subsection ) {
	if ( ! is_array( $subsection ) || empty( $subsection ) ) {
		return '';
	}

	foreach ( $subsection as $subsection_name ) {
		return wp_strip_all_tags( $subsection_name );
	}

	return '';
}

/**
 * Convert a template definition into a stable data structure.
 *
 * @param mixed $template       Template slug or definition.
 * @param bool  $include_fields Whether to include template fields.
 *
 * @return array
 */
function foogallery_abilities_prepare_template( $template, $include_fields = false ) {
	if ( is_string( $template ) ) {
		$template = foogallery_get_gallery_template( $template );
	}

	if ( ! is_array( $template ) ) {
		return array();
	}

	$data = array(
		'slug'                  => isset( $template['slug'] ) ? $template['slug'] : '',
		'name'                  => isset( $template['name'] ) ? $template['name'] : '',
	);

	if ( $include_fields ) {
		$data['fields'] = array();

		$field_indexes = foogallery_abilities_get_template_field_indexes( $data['slug'] );
		foreach ( $field_indexes['fields'] as $field ) {
			$prepared_field = foogallery_abilities_prepare_template_field( $field );

			if ( ! empty( $prepared_field ) ) {
				$data['fields'][] = $prepared_field;
			}
		}
	}

	return $data;
}

/**
 * Convert a field definition into a stable data structure.
 *
 * @param array $field Field definition.
 *
 * @return array
 */
function foogallery_abilities_prepare_template_field( $field ) {
	if ( ! foogallery_abilities_should_expose_template_field( $field ) ) {
		return array();
	}

	$sections       = foogallery_get_gallery_setting_sections();
	$field          = foogallery_normalize_gallery_setting_field_sections( $field, $sections );
	$section_id     = $field['section_id'];
	$section_config = isset( $sections[ $section_id ] ) && is_array( $sections[ $section_id ] ) ? $sections[ $section_id ] : array();
	$section_label  = isset( $section_config['label'] )
		? $section_config['label']
		: ( isset( $field['section'] ) ? $field['section'] : ucwords( str_replace( array( '-', '_' ), ' ', $section_id ) ) );

	$data = array(
		'id'         => foogallery_abilities_get_public_setting_id( $field ),
		'title'      => isset( $field['title'] ) ? wp_strip_all_tags( $field['title'] ) : '',
		'type'       => isset( $field['type'] ) ? $field['type'] : '',
		'section'    => wp_strip_all_tags( $section_label ),
		'section_id' => $section_id,
		'default'    => array_key_exists( 'default', $field ) ? $field['default'] : '',
	);

	if ( isset( $field['desc'] ) ) {
		$data['description'] = wp_strip_all_tags( $field['desc'] );
	}

	if ( ! empty( $field['subsection_id'] ) ) {
		$subsection_id     = $field['subsection_id'];
		$subsection_config = isset( $section_config['subsections'][ $subsection_id ] ) && is_array( $section_config['subsections'][ $subsection_id ] )
			? $section_config['subsections'][ $subsection_id ]
			: array();
		$subsection_label  = isset( $subsection_config['label'] )
			? $subsection_config['label']
			: ( isset( $field['subsection'][ $subsection_id ] ) ? $field['subsection'][ $subsection_id ] : ucwords( str_replace( array( '-', '_' ), ' ', $subsection_id ) ) );

		$data['subsection']    = wp_strip_all_tags( $subsection_label );
		$data['subsection_id'] = $subsection_id;
	}

	if ( isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
		$choices = foogallery_abilities_prepare_template_field_choices( $field['choices'] );

		if ( ! empty( $choices ) ) {
			$data['choices'] = $choices;
		}
	}

	if ( isset( $field['min'] ) ) {
		$data['min'] = $field['min'];
	}

	if ( isset( $field['max'] ) ) {
		$data['max'] = $field['max'];
	}

	if ( isset( $field['step'] ) ) {
		$data['step'] = $field['step'];
	}

	return $data;
}

/**
 * Convert stored gallery settings into normalized public setting IDs.
 *
 * @param string $template Layout slug.
 * @param array  $settings Stored gallery settings.
 *
 * @return array
 */
function foogallery_abilities_prepare_gallery_settings( $template, $settings ) {
	$template      = sanitize_key( $template );
	$settings      = is_array( $settings ) ? $settings : array();
	$field_indexes = foogallery_abilities_get_template_field_indexes( $template );
	$prepared      = array();

	$paid_mobile_storage = array();
	foreach ( array_keys( foogallery_get_paid_mobile_setting_storage_keys( $template, $settings ) ) as $paid_storage_key ) {
		$paid_mobile_storage[ sanitize_key( $paid_storage_key ) ] = true;
	}

	foreach ( $settings as $key => $value ) {
		$storage_key = sanitize_key( (string) $key );
		if ( isset( $paid_mobile_storage[ $storage_key ] ) && ! foogallery_mobile_settings_is_entitled() ) {
			continue;
		}

		if ( array_key_exists( $storage_key, $field_indexes['storage'] ) ) {
			$prepared_key = foogallery_abilities_get_public_setting_id( $field_indexes['storage'][ $storage_key ] );
		} elseif ( 0 === strpos( $storage_key, $template . '_' ) ) {
			$prepared_key = substr( $storage_key, strlen( $template ) + 1 );
		} else {
			$prepared_key = $storage_key;
		}

		if ( '' === $prepared_key ) {
			continue;
		}

		$prepared[ $prepared_key ] = $value;
	}

	return $prepared;
}

/**
 * Convert stored datasource settings into a stable object payload.
 *
 * FooGallery stores datasource settings as an associative array in post meta.
 *
 * @param mixed $datasource_value Stored datasource value.
 *
 * @return array
 */
function foogallery_abilities_prepare_datasource_value( $datasource_value ) {
	if ( is_object( $datasource_value ) ) {
		$datasource_value = get_object_vars( $datasource_value );
	}

	if ( ! is_array( $datasource_value ) || empty( $datasource_value ) ) {
		return array();
	}

	return $datasource_value;
}

/**
 * Convert a WP_Term object into a stable data structure.
 *
 * @param WP_Term $term Term instance.
 *
 * @return array
 */
function foogallery_abilities_prepare_term( $term ) {
	return array(
		'id'   => isset( $term->term_id ) ? absint( $term->term_id ) : 0,
		'slug' => isset( $term->slug ) ? sanitize_title( $term->slug ) : '',
		'name' => isset( $term->name ) ? wp_strip_all_tags( $term->name ) : '',
	);
}

/**
 * Load taxonomy terms for an attachment in a generic taxonomy => terms shape.
 *
 * @param int $attachment_id Attachment post ID.
 *
 * @return array
 */
function foogallery_abilities_prepare_attachment_taxonomies( $attachment_id ) {
	global $wpdb;

	$attachment_id = absint( $attachment_id );

	if ( $attachment_id <= 0 ) {
		return array();
	}

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tt.taxonomy, tt.term_id, t.slug, t.name
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			WHERE tr.object_id = %d
			ORDER BY tt.taxonomy ASC, t.name ASC",
			$attachment_id
		)
	);

	if ( empty( $results ) ) {
		return array();
	}

	$prepared = array();

	foreach ( $results as $term ) {
		if ( empty( $term->taxonomy ) ) {
			continue;
		}

		if ( ! isset( $prepared[ $term->taxonomy ] ) ) {
			$prepared[ $term->taxonomy ] = array();
		}

		$prepared[ $term->taxonomy ][] = foogallery_abilities_prepare_term( $term );
	}

	return $prepared;
}

/**
 * Convert an attachment object into a stable data structure.
 *
 * @param FooGalleryAttachment $attachment Attachment instance.
 *
 * @return array
 */
function foogallery_abilities_prepare_attachment( $attachment ) {
	$data = array(
		'id'              => intval( $attachment->ID ),
		'title'           => $attachment->title,
		'caption'         => $attachment->caption,
		'description'     => $attachment->description,
		'alt'             => $attachment->alt,
		'url'             => $attachment->url,
		'width'           => intval( $attachment->width ),
		'height'          => intval( $attachment->height ),
		'custom_url'      => $attachment->custom_url,
		'custom_target'   => $attachment->custom_target,
		'custom_rel'      => $attachment->custom_rel,
		'parent_post_id'  => intval( $attachment->parent_post_id ),
		'date'            => isset( $attachment->date ) ? $attachment->date : '',
		'modified'        => isset( $attachment->modified ) ? $attachment->modified : '',
	);

	$taxonomies = foogallery_abilities_prepare_attachment_taxonomies( $attachment->ID );

	if ( ! empty( $taxonomies ) ) {
		$data['taxonomies'] = $taxonomies;
	}

	return $data;
}

/**
 * Convert a gallery object into a stable summary structure.
 *
 * @param FooGallery $gallery Gallery instance.
 *
 * @return array
 */
function foogallery_abilities_prepare_gallery_summary( $gallery ) {
	$template_slug = ! empty( $gallery->gallery_template ) ? $gallery->gallery_template : foogallery_default_gallery_template();

	return array(
		'id'               => intval( $gallery->ID ),
		'title'            => $gallery->name,
		'status'           => $gallery->post_status,
		'layout'           => $template_slug,
		'datasource'       => $gallery->datasource_name,
		'is_dynamic'       => $gallery->is_dynamic(),
		'attachment_count' => intval( $gallery->attachment_count() ),
		'modified'         => foogallery_abilities_get_post_datetime_string( $gallery->ID, 'modified' ),
	);
}

/**
 * Convert a gallery object into a stable detail structure.
 *
 * @param FooGallery $gallery                   Gallery instance.
 * @param bool       $include_attachment_detail Whether to include full attachment metadata.
 *
 * @return array
 */
function foogallery_abilities_prepare_gallery_details( $gallery, $include_attachment_detail = true ) {
	$data                              = foogallery_abilities_prepare_gallery_summary( $gallery );
	$data['settings']                  = foogallery_abilities_prepare_gallery_settings( $data['layout'], is_array( $gallery->settings ) ? $gallery->settings : array() );
	$data['sort']                      = $gallery->sorting;
	$data['custom_css']                = $gallery->custom_css;
	$data['retina']                    = is_array( $gallery->retina ) ? $gallery->retina : array();
	$data['attachment_ids']            = $gallery->item_attachment_ids();
	$data['force_use_original_thumbs'] = (bool) $gallery->force_use_original_thumbs;

	if ( isset( $gallery->datasource_value ) ) {
		$datasource_value = foogallery_abilities_prepare_datasource_value( $gallery->datasource_value );

		if ( ! empty( $datasource_value ) ) {
			$data['datasource_value'] = $datasource_value;
		}
	}

	if ( $include_attachment_detail ) {
		$data['attachments'] = array();

		foreach ( $gallery->attachments() as $attachment ) {
			$data['attachments'][] = foogallery_abilities_prepare_attachment( $attachment );
		}
	}

	return $data;
}

/**
 * Clear cached gallery HTML after a write operation.
 *
 * @param int $gallery_id Gallery ID.
 *
 * @return void
 */
function foogallery_abilities_clear_gallery_cache( $gallery_id ) {
	foogallery_clear_gallery_cache( $gallery_id );
}
