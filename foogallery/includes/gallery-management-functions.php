<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FooGallery gallery-management functions.
 *
 * @package FooGallery
 */

/**
 * Normalize a list of attachment IDs.
 *
 * Authorization is the caller's responsibility.
 *
 * @param mixed $attachment_ids Attachment IDs as an array or comma-separated string.
 *
 * @return int[]
 */
function foogallery_normalize_attachment_ids( $attachment_ids ) {
	if ( is_string( $attachment_ids ) ) {
		$attachment_ids = array_filter( array_map( 'trim', explode( ',', $attachment_ids ) ) );
	} elseif ( ! is_array( $attachment_ids ) ) {
		$attachment_ids = array( $attachment_ids );
	}

	$normalized = array();

	foreach ( $attachment_ids as $attachment_id ) {
		if ( ! is_scalar( $attachment_id ) ) {
			continue;
		}

		$attachment_id = absint( $attachment_id );

		if ( $attachment_id > 0 && ! in_array( $attachment_id, $normalized, true ) ) {
			$normalized[] = $attachment_id;
		}
	}

	return $normalized;
}

/**
 * Validate a list of attachment IDs.
 *
 * Authorization is the caller's responsibility.
 *
 * @param mixed $attachment_ids Attachment IDs as an array or comma-separated string.
 *
 * @return int[]|WP_Error
 */
function foogallery_validate_attachment_ids( $attachment_ids ) {
	if ( is_string( $attachment_ids ) ) {
		$candidates = array_filter( array_map( 'trim', explode( ',', $attachment_ids ) ), 'strlen' );
	} elseif ( is_array( $attachment_ids ) ) {
		$candidates = $attachment_ids;
	} elseif ( null === $attachment_ids || false === $attachment_ids || '' === $attachment_ids ) {
		$candidates = array();
	} else {
		$candidates = array( $attachment_ids );
	}

	$attachment_ids = foogallery_normalize_attachment_ids( $candidates );
	$invalid_ids    = array();

	foreach ( $candidates as $candidate ) {
		if ( ! is_scalar( $candidate ) ) {
			$invalid_ids[] = 0;
			continue;
		}

		$attachment_id = absint( $candidate );
		$post          = get_post( $attachment_id );

		if ( ! $attachment_id || ! $post || 'attachment' !== $post->post_type ) {
			$invalid_ids[] = $attachment_id;
		}
	}

	if ( ! empty( $invalid_ids ) ) {
		$invalid_ids = array_values( array_unique( $invalid_ids ) );

		return new WP_Error(
			'foogallery_invalid_attachments',
			__( 'One or more attachment IDs are invalid.', 'foogallery' ),
			array(
				'invalid_attachment_ids' => $invalid_ids,
			)
		);
	}

	return $attachment_ids;
}

/**
 * Clear all cached state for a gallery after a write.
 *
 * Authorization is the caller's responsibility.
 *
 * @param int $gallery_id Gallery ID.
 *
 * @return void
 */
function foogallery_clear_gallery_cache( $gallery_id ) {
	$gallery_id = absint( $gallery_id );

	if ( ! $gallery_id ) {
		return;
	}

	delete_post_meta( $gallery_id, FOOGALLERY_META_CACHE );
	clean_post_cache( $gallery_id );
}

/**
 * Create a media-library-backed FooGallery.
 *
 * Authorization is the caller's responsibility.
 *
 * @param array $args {
 *     Optional gallery arguments.
 *
 *     @type string $title             Gallery title. Default "Untitled Gallery".
 *     @type string $status            draft, publish, or private. Default draft.
 *     @type int    $author_id         Post author. Default current user ID.
 *     @type string $template          Registered gallery template slug.
 *     @type array  $settings          Complete storage-format settings array.
 *     @type array  $attachment_ids    Media Library attachment IDs.
 *     @type int    $source_gallery_id Gallery whose configuration should be copied.
 *     @type string $sort              Optional gallery sort value.
 *     @type string $custom_css        Optional gallery custom CSS.
 * }
 * @param array $context Optional caller-provided context passed to hooks.
 *
 * @return int|WP_Error New gallery ID or an error.
 */
function foogallery_insert_gallery( $args = array(), $context = array() ) {
	$args = apply_filters( 'foogallery_insert_gallery_args', $args, $context );

	if ( ! is_array( $args ) || ! is_array( $context ) ) {
		return new WP_Error(
			'foogallery_invalid_gallery',
			__( 'Gallery arguments and context must be arrays.', 'foogallery' )
		);
	}

	$has_template_arg    = array_key_exists( 'template', $args );
	$explicit_template   = $has_template_arg && is_scalar( $args['template'] ) && '' !== trim( (string) $args['template'] );
	$explicit_settings   = array_key_exists( 'settings', $args ) && null !== $args['settings'];
	$explicit_sort       = array_key_exists( 'sort', $args );
	$explicit_custom_css = array_key_exists( 'custom_css', $args );
	$explicit_source     = array_key_exists( 'source_gallery_id', $args );
	$defaults            = array(
		'title'             => __( 'Untitled Gallery', 'foogallery' ),
		'status'            => 'draft',
		'author_id'         => get_current_user_id(),
		'template'          => '',
		'settings'          => null,
		'attachment_ids'    => array(),
		'source_gallery_id' => 0,
	);
	$args                = wp_parse_args( $args, $defaults );
	$title               = is_scalar( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : '';
	$status              = is_scalar( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
	$author_id           = is_scalar( $args['author_id'] ) ? absint( $args['author_id'] ) : 0;
	$template            = is_scalar( $args['template'] ) ? sanitize_key( (string) $args['template'] ) : '';
	$source_gallery_id   = is_scalar( $args['source_gallery_id'] ) ? absint( $args['source_gallery_id'] ) : 0;

	if ( '' === $title ) {
		$title = $defaults['title'];
	}

	if ( ! in_array( $status, array( 'draft', 'publish', 'private' ), true ) ) {
		return new WP_Error(
			'foogallery_invalid_status',
			__( 'Gallery status must be draft, publish, or private.', 'foogallery' )
		);
	}

	if ( $has_template_arg && ! is_scalar( $args['template'] ) ) {
		return new WP_Error(
			'foogallery_invalid_template',
			__( 'The requested gallery template is not registered.', 'foogallery' )
		);
	}

	if ( $explicit_template && '' === $template ) {
		return new WP_Error(
			'foogallery_invalid_template',
			__( 'The requested gallery template is not registered.', 'foogallery' )
		);
	}

	$empty_source_values = array( 0, '0', '', null );

	if ( $explicit_source && ! in_array( $args['source_gallery_id'], $empty_source_values, true ) && ( ! is_scalar( $args['source_gallery_id'] ) || ! is_numeric( $args['source_gallery_id'] ) || (int) $args['source_gallery_id'] <= 0 ) ) {
		return new WP_Error(
			'foogallery_invalid_source_gallery',
			__( 'A valid source gallery ID is required.', 'foogallery' )
		);
	}

	if ( $explicit_settings && ! is_array( $args['settings'] ) ) {
		return new WP_Error(
			'foogallery_invalid_gallery',
			__( 'Gallery settings must be an array.', 'foogallery' )
		);
	}

	$attachment_ids = foogallery_validate_attachment_ids( $args['attachment_ids'] );

	if ( is_wp_error( $attachment_ids ) ) {
		return $attachment_ids;
	}

	$settings   = $explicit_settings ? $args['settings'] : array();
	$sort       = $explicit_sort && is_scalar( $args['sort'] ) ? sanitize_text_field( (string) $args['sort'] ) : '';
	$custom_css = $explicit_custom_css && is_scalar( $args['custom_css'] ) ? foogallery_sanitize_full( (string) $args['custom_css'] ) : '';

	if ( $source_gallery_id > 0 ) {
		$source_post = get_post( $source_gallery_id );

		if ( ! $source_post || FOOGALLERY_CPT_GALLERY !== $source_post->post_type ) {
			return new WP_Error(
				'foogallery_invalid_source_gallery',
				__( 'The requested source gallery could not be found.', 'foogallery' )
			);
		}

		$source_datasource = get_post_meta( $source_gallery_id, FOOGALLERY_META_DATASOURCE, true );

		if ( '' === $source_datasource ) {
			$source_datasource = foogallery_default_datasource();
		}

		if ( foogallery_default_datasource() !== $source_datasource ) {
			return new WP_Error(
				'foogallery_unsupported_datasource',
				__( 'Only media-library-backed source galleries are supported.', 'foogallery' )
			);
		}

		$source_template = sanitize_key( (string) get_post_meta( $source_gallery_id, FOOGALLERY_META_TEMPLATE, true ) );

		if ( ! $explicit_template ) {
			$template = $source_template;
		}

		if ( ! $explicit_settings && ( ! $explicit_template || $template === $source_template ) ) {
			$source_settings = get_post_meta( $source_gallery_id, FOOGALLERY_META_SETTINGS, true );
			$settings        = is_array( $source_settings ) ? $source_settings : array();
		}

		if ( ! $explicit_sort ) {
			$sort = (string) get_post_meta( $source_gallery_id, FOOGALLERY_META_SORT, true );
		}

		if ( ! $explicit_custom_css ) {
			$custom_css = (string) get_post_meta( $source_gallery_id, FOOGALLERY_META_CUSTOM_CSS, true );
		}
	}

	if ( '' === $template ) {
		$template = sanitize_key( (string) foogallery_default_gallery_template() );
	}

	if ( false === foogallery_get_gallery_template( $template ) ) {
		return new WP_Error(
			'foogallery_invalid_template',
			__( 'The requested gallery template is not registered.', 'foogallery' )
		);
	}

	if ( ! $explicit_settings && ! $source_gallery_id ) {
		$settings = foogallery_build_initial_mobile_settings( $template );
	}

	$gallery_id = wp_insert_post(
		array(
			'post_type'   => FOOGALLERY_CPT_GALLERY,
			'post_title'  => $title,
			'post_status' => $status,
			'post_author' => $author_id,
		),
		true
	);

	if ( is_wp_error( $gallery_id ) ) {
		return $gallery_id;
	}

	$metadata = array(
		FOOGALLERY_META_TEMPLATE    => $template,
		FOOGALLERY_META_SETTINGS    => $settings,
		FOOGALLERY_META_ATTACHMENTS => $attachment_ids,
		FOOGALLERY_META_DATASOURCE  => foogallery_default_datasource(),
		FOOGALLERY_META_SORT        => $sort,
		FOOGALLERY_META_CUSTOM_CSS  => $custom_css,
	);

	foreach ( $metadata as $meta_key => $meta_value ) {
		$updated = update_post_meta( $gallery_id, $meta_key, $meta_value );

		if ( false === $updated && get_post_meta( $gallery_id, $meta_key, true ) !== $meta_value ) {
			wp_delete_post( $gallery_id, true );

			return new WP_Error(
				'foogallery_invalid_gallery',
				__( 'The gallery could not be created.', 'foogallery' )
			);
		}
	}

	delete_post_meta( $gallery_id, FOOGALLERY_META_DATASOURCE_VALUE );
	foogallery_clear_gallery_cache( $gallery_id );

	if ( function_exists( 'foogallery_set_gallery_video_count' ) ) {
		foogallery_set_gallery_video_count( $gallery_id );
	}

	do_action(
		'foogallery_after_save_gallery',
		$gallery_id,
		array_merge(
			$context,
			array(
				'operation'      => 'insert',
				'gallery_id'     => $gallery_id,
				'attachment_ids' => $attachment_ids,
			)
		)
	);

	return $gallery_id;
}

/**
 * Update gallery post fields without invoking FooGallery's legacy form saver.
 *
 * WordPress lifecycle hooks still run. The guard only prevents the admin metabox
 * callback from reprocessing ambient form data while the public API owns the write.
 *
 * @param array $postarr  Normalized, unslashed post fields for wp_update_post().
 * @param bool  $wp_error Whether to return a WP_Error on failure.
 *
 * @return int|WP_Error
 */
function foogallery_update_gallery_post_fields( $postarr, $wp_error = false ) {
	$had_previous = array_key_exists( 'foogallery_gallery_management_update', $GLOBALS );
	$previous     = $had_previous ? $GLOBALS['foogallery_gallery_management_update'] : null;

	$GLOBALS['foogallery_gallery_management_update'] = true;

	try {
		// The gallery API normalizes values before this boundary; WordPress expects slashed post data.
		return wp_update_post( wp_slash( $postarr ), $wp_error );
	} finally {
		if ( $had_previous ) {
			$GLOBALS['foogallery_gallery_management_update'] = $previous;
		} else {
			unset( $GLOBALS['foogallery_gallery_management_update'] );
		}
	}
}

/**
 * Restore a gallery post row without re-running the lifecycle filter that invalidated it.
 *
 * This compensation path is used only after WordPress accepted a post update but a
 * lifecycle callback changed API-owned identity or status fields. Re-entering
 * wp_update_post() would allow that same callback to corrupt the restoration.
 *
 * @param WP_Post $post Original gallery post snapshot.
 *
 * @return bool Whether every persisted post field was restored.
 */
function foogallery_restore_gallery_post_row( $post ) {
	global $wpdb;

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	$post_columns = array(
		'post_author',
		'post_date',
		'post_date_gmt',
		'post_content',
		'post_title',
		'post_excerpt',
		'post_status',
		'comment_status',
		'ping_status',
		'post_password',
		'post_name',
		'to_ping',
		'pinged',
		'post_modified',
		'post_modified_gmt',
		'post_content_filtered',
		'post_parent',
		'guid',
		'menu_order',
		'post_type',
		'post_mime_type',
		'comment_count',
	);
	$row          = array();
	foreach ( $post_columns as $column ) {
		$row[ $column ] = $post->$column;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Compensation must bypass the lifecycle filter that caused the invalid mutation.
	$restored = $wpdb->update( $wpdb->posts, $row, array( 'ID' => $post->ID ) );
	if ( false === $restored ) {
		return false;
	}

	clean_post_cache( $post->ID );
	$restored_post = get_post( $post->ID );
	if ( ! $restored_post ) {
		return false;
	}

	$restored_row = array();
	foreach ( $post_columns as $column ) {
		$restored_row[ $column ] = $restored_post->$column;
	}

	return $row === $restored_row;
}

/**
 * Remove private and API-owned values from caller-provided update context.
 *
 * Keys beginning with an underscore are reserved for private implementation
 * details. Hook fields produced by the update operation are also reserved so
 * callers cannot forge or collide with authoritative values.
 *
 * @param array $context Caller-provided hook context.
 *
 * @return array|WP_Error
 */
function foogallery_prepare_gallery_update_context( $context ) {
	$item_count = 0;
	$valid      = true;
	$context    = foogallery_prepare_gallery_update_context_value( $context, 0, $item_count, $valid );

	if ( ! $valid ) {
		return new WP_Error(
			'foogallery_invalid_update',
			__( 'Gallery update context must contain only bounded arrays, scalar values, and null.', 'foogallery' )
		);
	}

	return $context;
}

/**
 * Recursively sanitize a gallery update context value within conservative bounds.
 *
 * @param mixed $value      Context value.
 * @param int   $depth      Current array depth.
 * @param int   $item_count Number of array entries inspected.
 * @param bool  $valid      Whether the complete context remains valid.
 *
 * @return mixed Sanitized context value.
 */
function foogallery_prepare_gallery_update_context_value( $value, $depth, &$item_count, &$valid ) {
	if ( null === $value || is_scalar( $value ) ) {
		return $value;
	}

	if ( ! is_array( $value ) || $depth >= 20 ) {
		$valid = false;
		return null;
	}

	$reserved_keys = array(
		'operation',
		'gallery_id',
		'layout',
		'template',
		'settings',
		'updated_fields',
	);
	$sanitized     = array();

	foreach ( $value as $key => $item ) {
		++$item_count;
		if ( $item_count > 10000 ) {
			$valid = false;
			return null;
		}

		if ( is_string( $key ) && ( 0 === strpos( $key, '_' ) || in_array( $key, $reserved_keys, true ) ) ) {
			continue;
		}

		$sanitized[ $key ] = foogallery_prepare_gallery_update_context_value( $item, $depth + 1, $item_count, $valid );
		if ( ! $valid ) {
			return null;
		}
	}

	return $sanitized;
}

/**
 * Sanitize a complete public update hook context.
 *
 * Authoritative top-level fields such as `operation` and `settings` remain
 * available for backwards compatibility. Their values are projected through
 * the same recursive private/reserved-key stripping used for caller context,
 * so framework-owned structured values cannot expose nested control markers.
 *
 * @param array $context Complete authoritative hook context.
 *
 * @return array|WP_Error
 */
function foogallery_prepare_gallery_update_hook_context( $context ) {
	$item_count = 0;
	$valid      = true;
	$sanitized  = array();

	foreach ( $context as $key => $value ) {
		++$item_count;
		if ( $item_count > 10000 ) {
			$valid = false;
			break;
		}

		if ( is_string( $key ) && 0 === strpos( $key, '_' ) ) {
			continue;
		}

		$sanitized[ $key ] = foogallery_prepare_gallery_update_context_value( $value, 1, $item_count, $valid );
		if ( ! $valid ) {
			break;
		}
	}

	if ( ! $valid ) {
		return new WP_Error(
			'foogallery_invalid_update',
			__( 'Public gallery hook context must contain only bounded arrays, scalar values, and null.', 'foogallery' )
		);
	}

	return $sanitized;
}

/**
 * Update an existing FooGallery gallery.
 *
 * This is a low-level API. Authorization and nonce checks are the caller's
 * responsibility. Omitted fields are not changed, and attachments must be
 * managed through the dedicated gallery attachment APIs.
 *
 * Settings use the same update-object format as the FooGallery update Ability.
 * Existing settings are retained when the layout is unchanged. Switching the
 * layout replaces the settings base with the registered target layout defaults
 * before applying supplied setting updates.
 *
 * A successful no-op returns the gallery ID without clearing caches or firing
 * mutation actions. WordPress reports unchanged metadata writes as false, so a
 * write is considered failed only when its read-back value differs. If a later
 * write fails, completed post and metadata writes are rolled back where
 * practical before a `foogallery_update_failed` error is returned. This is not
 * transactional: `wp_update_post()` lifecycle hooks may already have run.
 * Compensation bypasses those hooks, but their arbitrary external side effects
 * cannot be rolled back. FooGallery success hooks and cache clearing run only
 * after all writes succeed.
 *
 * Stable validation errors use `foogallery_invalid_gallery`,
 * `foogallery_gallery_not_found`, `foogallery_invalid_update`,
 * `foogallery_invalid_status`, `foogallery_invalid_template`, and
 * `foogallery_invalid_settings`.
 *
 * @param int   $gallery_id Gallery post ID.
 * @param array $args {
 *     Optional gallery fields to update.
 *
 *     @type string $title      Replacement gallery title.
 *     @type string $status     draft, publish, or private.
 *     @type string $layout     Registered gallery layout slug. Preferred alias.
 *     @type string $template   Registered gallery template slug.
 *     @type array  $settings   Ability-format setting update objects.
 *     @type string $sort       FooGallery sort value, or empty to clear.
 *     @type string $custom_css Gallery custom CSS, or empty to clear.
 * }
 * @param array $context Optional caller-provided hook context. Do not include secrets.
 *
 * @return int|WP_Error Gallery ID on success or a stable error.
 */
function foogallery_update_gallery( $gallery_id, $args = array(), $context = array() ) {
	return foogallery_update_gallery_internal( $gallery_id, $args, $context, false, false );
}

/**
 * Update a gallery while retaining the update Ability's legacy no-op effects.
 *
 * This internal channel keeps Ability compatibility outside public caller
 * context, so no context key can enable save actions or cache invalidation.
 *
 * @internal
 *
 * @param int   $gallery_id Gallery post ID.
 * @param array $args       Gallery fields to update.
 * @param array $context    Hook context.
 *
 * @return int|WP_Error Gallery ID on success or a stable error.
 */
function foogallery_update_gallery_for_ability( $gallery_id, $args, $context ) {
	return foogallery_update_gallery_internal( $gallery_id, $args, $context, true, true );
}

/**
 * Perform a validated gallery update.
 *
 * @internal
 *
 * @param int   $gallery_id        Gallery post ID.
 * @param array $args              Gallery fields to update.
 * @param array $context           Hook context.
 * @param bool  $force_save_actions              Whether no-op updates retain save effects.
 * @param bool  $include_legacy_settings_context Whether omitted Ability settings remain an authoritative empty array in hook context.
 *
 * @return int|WP_Error Gallery ID on success or a stable error.
 */
function foogallery_update_gallery_internal( $gallery_id, $args, $context, $force_save_actions, $include_legacy_settings_context ) {
	if ( ! is_scalar( $gallery_id ) || absint( $gallery_id ) <= 0 ) {
		return new WP_Error(
			'foogallery_invalid_gallery',
			__( 'A valid gallery ID is required.', 'foogallery' )
		);
	}

	if ( ! is_array( $args ) || ! is_array( $context ) ) {
		return new WP_Error(
			'foogallery_invalid_update',
			__( 'Gallery update arguments and context must be arrays.', 'foogallery' )
		);
	}

	$gallery_id = absint( $gallery_id );
	$context    = foogallery_prepare_gallery_update_context( $context );
	if ( is_wp_error( $context ) ) {
		return $context;
	}

	$args = apply_filters( 'foogallery_update_gallery_args', $args, $gallery_id, $context );

	if ( ! is_array( $args ) ) {
		return new WP_Error(
			'foogallery_invalid_update',
			__( 'Gallery update argument filters must return an array.', 'foogallery' )
		);
	}

	$post = get_post( $gallery_id );

	if ( ! $post || FOOGALLERY_CPT_GALLERY !== $post->post_type ) {
		return new WP_Error(
			'foogallery_gallery_not_found',
			__( 'The requested gallery could not be found.', 'foogallery' )
		);
	}

	if ( ! in_array( $post->post_status, array( 'draft', 'publish', 'private', 'pending', 'future' ), true ) ) {
		return new WP_Error(
			'foogallery_gallery_not_found',
			__( 'The requested gallery is not in an editable state.', 'foogallery' )
		);
	}

	$post_update       = array( 'ID' => $gallery_id );
	$meta_updates      = array();
	$updated_fields    = array();
	$current_template  = sanitize_key( (string) get_post_meta( $gallery_id, FOOGALLERY_META_TEMPLATE, true ) );
	$target_template   = $current_template;
	$has_layout        = array_key_exists( 'layout', $args );
	$has_template      = array_key_exists( 'template', $args );
	$updates_settings  = array_key_exists( 'settings', $args );
	$requests_template = $has_layout || ( ! $has_layout && $has_template );
	$changes_template  = false;

	if ( array_key_exists( 'title', $args ) ) {
		if ( ! is_string( $args['title'] ) ) {
			return new WP_Error(
				'foogallery_invalid_update',
				__( 'Gallery title must be a string.', 'foogallery' )
			);
		}

		$title = sanitize_text_field( $args['title'] );

		if ( $title !== $post->post_title ) {
			$post_update['post_title'] = $title;
			$updated_fields[]          = 'title';
		}
	}

	if ( array_key_exists( 'status', $args ) ) {
		$status = is_string( $args['status'] ) ? sanitize_key( $args['status'] ) : '';

		if ( ! in_array( $status, array( 'draft', 'publish', 'private' ), true ) ) {
			return new WP_Error(
				'foogallery_invalid_status',
				__( 'Gallery status must be draft, publish, or private.', 'foogallery' )
			);
		}

		if ( $status !== $post->post_status ) {
			$post_update['post_status'] = $status;
			$updated_fields[]           = 'status';
		}
	}

	if ( $requests_template ) {
		$template_arg = $has_layout ? $args['layout'] : $args['template'];

		if ( ! is_string( $template_arg ) ) {
			return new WP_Error(
				'foogallery_invalid_template',
				__( 'The requested gallery template is not registered.', 'foogallery' )
			);
		}

		$requested_template = sanitize_key( $template_arg );
		if ( '' === $requested_template ) {
			return new WP_Error(
				'foogallery_invalid_template',
				__( 'The requested gallery template is not registered.', 'foogallery' )
			);
		}

		$target_template  = $requested_template;
		$changes_template = $target_template !== $current_template;
	}

	if ( ( $has_layout || $has_template ) && false === foogallery_get_gallery_template( $target_template ) ) {
		return new WP_Error(
			'foogallery_invalid_template',
			__( 'The requested gallery template is not registered.', 'foogallery' )
		);
	}

	if ( $changes_template || $updates_settings ) {
		if ( '' === $target_template || false === foogallery_get_gallery_template( $target_template ) ) {
			return new WP_Error(
				'foogallery_invalid_template',
				__( 'The requested gallery template is not registered.', 'foogallery' )
			);
		}

		if ( $updates_settings && ! is_array( $args['settings'] ) ) {
			return new WP_Error(
				'foogallery_invalid_settings',
				__( 'Gallery settings must be an array of setting update objects.', 'foogallery' )
			);
		}

		$setting_updates = foogallery_abilities_prepare_template_setting_updates(
			$target_template,
			$updates_settings ? $args['settings'] : array()
		);

		if ( is_wp_error( $setting_updates ) ) {
			return new WP_Error(
				'foogallery_invalid_settings',
				$setting_updates->get_error_message(),
				$setting_updates->get_error_data()
			);
		}

		if ( $current_template === $target_template ) {
			$current_settings = get_post_meta( $gallery_id, FOOGALLERY_META_SETTINGS, true );
			$settings_base    = array_merge(
				foogallery_abilities_build_template_settings_base( $target_template ),
				is_array( $current_settings ) ? $current_settings : array()
			);
		} else {
			$settings_base = foogallery_abilities_build_template_settings_base( $target_template );
		}

		$settings_context = array_merge(
			$context,
			array(
				'operation'  => 'update',
				'gallery_id' => $gallery_id,
				'layout'     => $target_template,
				'template'   => $target_template,
				'settings'   => $setting_updates,
			)
		);
		$settings_context = foogallery_prepare_gallery_update_hook_context( $settings_context );
		if ( is_wp_error( $settings_context ) ) {
			return $settings_context;
		}

		$settings         = foogallery_abilities_normalize_template_settings(
			$target_template,
			$setting_updates,
			$settings_base,
			$gallery_id,
			$settings_context,
			true
		);

		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		if ( ! is_array( $settings ) || ! foogallery_abilities_is_supported_setting_value( $settings ) ) {
			return new WP_Error(
				'foogallery_invalid_settings',
				__( 'Gallery settings filters must return a safely serializable array.', 'foogallery' )
			);
		}

		if ( $target_template !== $current_template ) {
			$meta_updates[ FOOGALLERY_META_TEMPLATE ] = $target_template;
			$updated_fields[]                         = 'template';
		}

		$current_settings = get_post_meta( $gallery_id, FOOGALLERY_META_SETTINGS, true );
		$current_settings = is_array( $current_settings ) ? $current_settings : array();
		if ( $settings !== $current_settings ) {
			$meta_updates[ FOOGALLERY_META_SETTINGS ] = $settings;
			$updated_fields[]                         = 'settings';
		}
	} else {
		$setting_updates = array();
	}

	if ( array_key_exists( 'sort', $args ) ) {
		if ( ! is_string( $args['sort'] ) ) {
			return new WP_Error(
				'foogallery_invalid_update',
				__( 'Gallery sort must be a string.', 'foogallery' )
			);
		}

		$sort         = foogallery_abilities_sanitize_gallery_sort( $args['sort'] );
		$current_sort = (string) get_post_meta( $gallery_id, FOOGALLERY_META_SORT, true );

		if ( $sort !== $current_sort ) {
			$meta_updates[ FOOGALLERY_META_SORT ] = '' === $sort ? null : $sort;
			$updated_fields[]                     = 'sort';
		}
	}

	if ( array_key_exists( 'custom_css', $args ) ) {
		if ( ! is_string( $args['custom_css'] ) ) {
			return new WP_Error(
				'foogallery_invalid_update',
				__( 'Gallery custom CSS must be a string.', 'foogallery' )
			);
		}

		$custom_css         = foogallery_sanitize_full( $args['custom_css'] );
		$current_custom_css = (string) get_post_meta( $gallery_id, FOOGALLERY_META_CUSTOM_CSS, true );

		if ( $custom_css !== $current_custom_css ) {
			$meta_updates[ FOOGALLERY_META_CUSTOM_CSS ] = '' === $custom_css ? null : $custom_css;
			$updated_fields[]                           = 'custom_css';
		}
	}

	$hook_context = array_merge(
		$context,
		array(
			'operation'      => 'update',
			'gallery_id'     => $gallery_id,
			'layout'         => $target_template,
			'template'       => $target_template,
			'updated_fields' => $updated_fields,
		)
	);
	if ( $changes_template || $updates_settings || $include_legacy_settings_context ) {
		$hook_context['settings'] = $setting_updates;
	}
	$hook_context = foogallery_prepare_gallery_update_hook_context( $hook_context );
	if ( is_wp_error( $hook_context ) ) {
		return $hook_context;
	}

	if ( empty( $updated_fields ) && ! $force_save_actions ) {
		return $gallery_id;
	}

	$old_metadata = array();
	foreach ( $meta_updates as $meta_key => $meta_value ) {
		$old_metadata[ $meta_key ] = array(
			'exists' => metadata_exists( 'post', $gallery_id, $meta_key ),
			'value'  => get_post_meta( $gallery_id, $meta_key, true ),
		);
	}

	$post_changed = count( $post_update ) > 1;
	if ( $post_changed ) {
		$post_result = foogallery_update_gallery_post_fields( $post_update, true );

		if ( is_wp_error( $post_result ) || $gallery_id !== (int) $post_result ) {
			$restored = foogallery_restore_gallery_post_row( $post );

			return new WP_Error(
				'foogallery_update_failed',
				__( 'The gallery post could not be updated.', 'foogallery' ),
				array(
					'wordpress_error'     => is_wp_error( $post_result ) ? $post_result->get_error_code() : '',
					'restore_failed'      => ! $restored,
					'post_restore_failed' => ! $restored,
				)
			);
		}

		$updated_post       = get_post( $gallery_id );
		$supported_statuses = array( 'draft', 'publish', 'private', 'pending', 'future' );
		$expected_status    = isset( $post_update['post_status'] ) ? $post_update['post_status'] : $post->post_status;
		$expected_title     = isset( $post_update['post_title'] ) ? $post_update['post_title'] : $post->post_title;
		$post_matches       = $updated_post
			&& FOOGALLERY_CPT_GALLERY === $updated_post->post_type
			&& in_array( $updated_post->post_status, $supported_statuses, true )
			&& $expected_status === $updated_post->post_status
			&& $expected_title === $updated_post->post_title;

		if ( ! $post_matches ) {
			$restored = foogallery_restore_gallery_post_row( $post );

			return new WP_Error(
				'foogallery_update_failed',
				__( 'The gallery post update did not persist the requested values or valid lifecycle state.', 'foogallery' ),
				array(
					'restore_failed'      => ! $restored,
					'post_restore_failed' => ! $restored,
				)
			);
		}
	}

	$write_failed = false;
	foreach ( $meta_updates as $meta_key => $meta_value ) {
		if ( null === $meta_value ) {
			delete_post_meta( $gallery_id, $meta_key );
			$matches = ! metadata_exists( 'post', $gallery_id, $meta_key );
		} else {
			// WordPress unslashes metadata recursively; preserve the normalized value.
			update_post_meta( $gallery_id, $meta_key, wp_slash( $meta_value ) );
			$matches = get_post_meta( $gallery_id, $meta_key, true ) === $meta_value;
		}

		if ( ! $matches ) {
			$write_failed = true;
			break;
		}
	}

	if ( $write_failed ) {
		$metadata_restored = true;
		foreach ( $old_metadata as $meta_key => $old_meta ) {
			if ( $old_meta['exists'] ) {
				if ( get_post_meta( $gallery_id, $meta_key, true ) !== $old_meta['value'] ) {
					update_post_meta( $gallery_id, $meta_key, wp_slash( $old_meta['value'] ) );
				}
				$metadata_matches = metadata_exists( 'post', $gallery_id, $meta_key )
					&& get_post_meta( $gallery_id, $meta_key, true ) === $old_meta['value'];
			} elseif ( metadata_exists( 'post', $gallery_id, $meta_key ) ) {
				delete_post_meta( $gallery_id, $meta_key );
				$metadata_matches = ! metadata_exists( 'post', $gallery_id, $meta_key );
			} else {
				$metadata_matches = true;
			}

			if ( ! $metadata_matches ) {
				$metadata_restored = false;
			}
		}

		$post_restored = ! $post_changed || foogallery_restore_gallery_post_row( $post );

		return new WP_Error(
			'foogallery_update_failed',
			__( 'The gallery metadata could not be updated. Completed writes were rolled back where practical.', 'foogallery' ),
			array(
				'restore_failed'          => ! $post_restored || ! $metadata_restored,
				'post_restore_failed'     => ! $post_restored,
				'metadata_restore_failed' => ! $metadata_restored,
			)
		);
	}

	foogallery_clear_gallery_cache( $gallery_id );

	if ( ! empty( $updated_fields ) ) {
		do_action( 'foogallery_gallery_updated', $gallery_id, $updated_fields, $hook_context );
	}
	do_action( 'foogallery_after_save_gallery', $gallery_id, $hook_context );

	return $gallery_id;
}

/**
 * Return normalized attachment IDs stored on a gallery.
 *
 * Authorization is the caller's responsibility.
 *
 * @param int $gallery_id Gallery ID.
 *
 * @return int[]|WP_Error
 */
function foogallery_get_gallery_attachment_ids( $gallery_id ) {
	$gallery_id = absint( $gallery_id );

	if ( ! $gallery_id ) {
		return new WP_Error(
			'foogallery_invalid_gallery',
			__( 'A valid gallery ID is required.', 'foogallery' )
		);
	}

	$gallery_post = get_post( $gallery_id );

	if ( ! $gallery_post || FOOGALLERY_CPT_GALLERY !== $gallery_post->post_type ) {
		return new WP_Error(
			'foogallery_gallery_not_found',
			__( 'The requested gallery could not be found.', 'foogallery' )
		);
	}

	$datasource = get_post_meta( $gallery_id, FOOGALLERY_META_DATASOURCE, true );

	if ( '' === $datasource ) {
		$datasource = foogallery_default_datasource();
	}

	if ( foogallery_default_datasource() !== $datasource ) {
		return new WP_Error(
			'foogallery_unsupported_datasource',
			__( 'Only media-library-backed galleries are supported.', 'foogallery' )
		);
	}

	return foogallery_normalize_attachment_ids( get_post_meta( $gallery_id, FOOGALLERY_META_ATTACHMENTS, true ) );
}

/**
 * Update gallery attachments.
 *
 * Authorization is the caller's responsibility.
 *
 * @param int   $gallery_id Gallery ID.
 * @param array $changes {
 *     Attachment changes.
 *
 *     @type array $replace Replace the complete attachment list.
 *     @type array $add     Append attachments that are not already present.
 *     @type array $remove  Remove attachments from the gallery.
 * }
 * @param array $context Optional caller-provided hook context.
 *
 * @return int[]|WP_Error Final attachment IDs.
 */
function foogallery_update_gallery_attachments( $gallery_id, $changes, $context = array() ) {
	if ( ! is_array( $changes ) || ! is_array( $context ) ) {
		return new WP_Error(
			'foogallery_no_attachment_changes',
			__( 'Attachment changes and context must be arrays.', 'foogallery' )
		);
	}

	$old_attachment_ids = foogallery_get_gallery_attachment_ids( $gallery_id );

	if ( is_wp_error( $old_attachment_ids ) ) {
		return $old_attachment_ids;
	}

	$allowed_keys = array( 'replace', 'add', 'remove' );
	$unknown_keys = array_diff( array_keys( $changes ), $allowed_keys );

	if ( ! empty( $unknown_keys ) ) {
		return new WP_Error(
			'foogallery_invalid_attachment_changes',
			__( 'One or more attachment change operations are not supported.', 'foogallery' ),
			array(
				'invalid_change_keys' => array_values( $unknown_keys ),
			)
		);
	}

	$has_replace = array_key_exists( 'replace', $changes );
	$has_add     = array_key_exists( 'add', $changes );
	$has_remove  = array_key_exists( 'remove', $changes );

	if ( ! $has_replace && ! $has_add && ! $has_remove ) {
		return new WP_Error(
			'foogallery_no_attachment_changes',
			__( 'Provide replace, add, remove, or a combination of add and remove.', 'foogallery' )
		);
	}

	if ( $has_replace && ( $has_add || $has_remove ) ) {
		return new WP_Error(
			'foogallery_conflicting_attachment_changes',
			__( 'replace cannot be combined with add or remove.', 'foogallery' )
		);
	}

	if ( $has_replace ) {
		$new_attachment_ids = foogallery_validate_attachment_ids( $changes['replace'] );

		if ( is_wp_error( $new_attachment_ids ) ) {
			return $new_attachment_ids;
		}

		$operation = 'replace';
	} else {
		$new_attachment_ids = $old_attachment_ids;

		if ( $has_add ) {
			$add_attachment_ids = foogallery_validate_attachment_ids( $changes['add'] );

			if ( is_wp_error( $add_attachment_ids ) ) {
				return $add_attachment_ids;
			}

			foreach ( $add_attachment_ids as $attachment_id ) {
				if ( ! in_array( $attachment_id, $new_attachment_ids, true ) ) {
					$new_attachment_ids[] = $attachment_id;
				}
			}
		}

		if ( $has_remove ) {
			$remove_attachment_ids = foogallery_normalize_attachment_ids( $changes['remove'] );
			$new_attachment_ids    = array_values( array_diff( $new_attachment_ids, $remove_attachment_ids ) );
		}

		if ( $has_add && $has_remove ) {
			$operation = 'add_remove';
		} elseif ( $has_add ) {
			$operation = 'add';
		} else {
			$operation = 'remove';
		}
	}

	if ( $new_attachment_ids === $old_attachment_ids ) {
		return $new_attachment_ids;
	}

	$gallery_id = absint( $gallery_id );
	$updated    = update_post_meta( $gallery_id, FOOGALLERY_META_ATTACHMENTS, $new_attachment_ids );

	if ( false === $updated && get_post_meta( $gallery_id, FOOGALLERY_META_ATTACHMENTS, true ) !== $new_attachment_ids ) {
		return new WP_Error(
			'foogallery_invalid_gallery',
			__( 'The gallery attachments could not be updated.', 'foogallery' )
		);
	}

	update_post_meta( $gallery_id, FOOGALLERY_META_DATASOURCE, foogallery_default_datasource() );
	delete_post_meta( $gallery_id, FOOGALLERY_META_DATASOURCE_VALUE );
	foogallery_clear_gallery_cache( $gallery_id );

	if ( function_exists( 'foogallery_set_gallery_video_count' ) ) {
		foogallery_set_gallery_video_count( $gallery_id );
	}

	do_action(
		'foogallery_gallery_attachments_updated',
		$gallery_id,
		$old_attachment_ids,
		$new_attachment_ids,
		$operation,
		$context
	);

	do_action(
		'foogallery_after_save_gallery',
		$gallery_id,
		array_merge(
			$context,
			array(
				'operation'          => 'update_attachments',
				'attachment_action'  => $operation,
				'gallery_id'         => $gallery_id,
				'attachment_ids'     => $new_attachment_ids,
				'old_attachment_ids' => $old_attachment_ids,
			)
		)
	);

	return $new_attachment_ids;
}

/**
 * Replace all gallery attachments.
 *
 * Authorization is the caller's responsibility.
 *
 * @param int   $gallery_id    Gallery ID.
 * @param array $attachment_ids Attachment IDs.
 * @param array $context       Optional caller-provided hook context.
 *
 * @return int[]|WP_Error Final attachment IDs.
 */
function foogallery_set_gallery_attachments( $gallery_id, $attachment_ids, $context = array() ) {
	return foogallery_update_gallery_attachments(
		$gallery_id,
		array(
			'replace' => $attachment_ids,
		),
		$context
	);
}

/**
 * Add attachments without adding duplicates.
 *
 * Authorization is the caller's responsibility.
 *
 * @param int   $gallery_id    Gallery ID.
 * @param array $attachment_ids Attachment IDs.
 * @param array $context       Optional caller-provided hook context.
 *
 * @return int[]|WP_Error Final attachment IDs.
 */
function foogallery_add_gallery_attachments( $gallery_id, $attachment_ids, $context = array() ) {
	return foogallery_update_gallery_attachments(
		$gallery_id,
		array(
			'add' => $attachment_ids,
		),
		$context
	);
}

/**
 * Remove attachments from a gallery.
 *
 * Authorization is the caller's responsibility.
 *
 * @param int   $gallery_id    Gallery ID.
 * @param array $attachment_ids Attachment IDs.
 * @param array $context       Optional caller-provided hook context.
 *
 * @return int[]|WP_Error Final attachment IDs.
 */
function foogallery_remove_gallery_attachments( $gallery_id, $attachment_ids, $context = array() ) {
	return foogallery_update_gallery_attachments(
		$gallery_id,
		array(
			'remove' => $attachment_ids,
		),
		$context
	);
}
