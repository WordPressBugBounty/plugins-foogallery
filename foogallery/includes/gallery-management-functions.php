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
 * Get normalized attachment IDs belonging to a gallery.
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
