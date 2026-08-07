<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Update gallery attachments ability.
 */

if ( ! class_exists( 'FooGallery_Ability_Update_Gallery_Attachments' ) ) {

	class FooGallery_Ability_Update_Gallery_Attachments {

		const ID = 'foogallery/update-gallery-attachments';

		/**
		 * Register the ability.
		 */
		public function __construct() {
			if ( foogallery_abilities_wp_api_available() ) {
				add_action( 'foogallery_register_abilities', array( $this, 'register' ) );
			}
		}

		/**
		 * Register the ability with the WordPress core Abilities API.
		 */
		public function register() {
			// phpcs:ignore -- This callback is registered only when the WordPress Abilities API is available.
			wp_register_ability(
				self::ID,
				array(
					'category'            => FooGallery_Abilities::CATEGORY,
					'label'               => __( 'Update Gallery Attachments', 'foogallery' ),
					'description'         => __( 'Update gallery attachments for the gallery identified by `gallery_id`. Use `replace_attachment_ids` by itself to replace the full list, or use `append_attachment_ids` and/or `remove_attachment_ids` together for incremental changes.', 'foogallery' ),
					'input_schema'        => array(
						'type'                 => 'object',
						'required'             => array( 'gallery_id' ),
						'properties'           => array(
							'gallery_id'             => array(
								'type'        => 'integer',
								'description' => __( 'The numeric ID of the FooGallery gallery to update.', 'foogallery' ),
							),
							'replace_attachment_ids' => array(
								'type'        => 'array',
								'description' => __( 'Replace the gallery attachment list with this exact set of attachment IDs. Do not combine with append/remove.', 'foogallery' ),
								'items'       => array(
									'type' => 'integer',
								),
							),
							'append_attachment_ids'  => array(
								'type'        => 'array',
								'description' => __( 'Attachment IDs to append to the existing gallery without duplicating IDs already present.', 'foogallery' ),
								'items'       => array(
									'type' => 'integer',
								),
							),
							'remove_attachment_ids'  => array(
								'type'        => 'array',
								'description' => __( 'Attachment IDs to remove from the existing gallery. Use on its own or together with `append_attachment_ids`, but not with `replace_attachment_ids`.', 'foogallery' ),
								'items'       => array(
									'type' => 'integer',
								),
							),
						),
						'additionalProperties' => false,
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'gallery'          => array_merge(
								foogallery_abilities_get_gallery_summary_schema(),
								array(
									'description' => __( 'The updated gallery summary after the attachment change has been applied.', 'foogallery' ),
								)
							),
							'attachment_ids'   => array(
								'type'        => 'array',
								'description' => __( 'The gallery attachment IDs after the update.', 'foogallery' ),
								'items'       => array(
									'type' => 'integer',
								),
							),
							'attachment_count' => array(
								'type'        => 'integer',
								'description' => __( 'The number of attachments remaining in the gallery after the update.', 'foogallery' ),
							),
						),
					),
					'execute_callback'    => array( $this, 'execute' ),
					'permission_callback' => array( $this, 'can_execute' ),
					'meta'                => array(
						'annotations' => array(
							'readonly'    => false,
							'destructive' => true,
							'idempotent'  => true,
						),
						'show_in_rest' => true,
					),
				)
			);
		}

		/**
		 * Check if the current user can execute the ability.
		 *
		 * @param array $args Ability arguments.
		 *
		 * @return bool
		 */
		public function can_execute( $args ) {
			$args = foogallery_abilities_normalize_input( $args );

			$gallery_id = isset( $args['gallery_id'] ) ? absint( $args['gallery_id'] ) : 0;

			return $gallery_id > 0 && current_user_can( 'edit_post', $gallery_id );
		}

		/**
		 * Execute the ability.
		 *
		 * @param array $args Ability arguments.
		 *
		 * @return array|WP_Error
		 */
		public function execute( $args ) {
			$args       = foogallery_abilities_normalize_input( $args );
			$gallery_id = isset( $args['gallery_id'] ) ? absint( $args['gallery_id'] ) : 0;
			$changes    = array();

			if ( array_key_exists( 'replace_attachment_ids', $args ) ) {
				$changes['replace'] = foogallery_normalize_attachment_ids( $args['replace_attachment_ids'] );
			}

			if ( array_key_exists( 'append_attachment_ids', $args ) ) {
				$changes['add'] = foogallery_normalize_attachment_ids( $args['append_attachment_ids'] );
			}

			if ( array_key_exists( 'remove_attachment_ids', $args ) ) {
				$changes['remove'] = foogallery_normalize_attachment_ids( $args['remove_attachment_ids'] );
			}

			$new_attachment_ids = foogallery_update_gallery_attachments(
				$gallery_id,
				$changes,
				array(
					'ability' => self::ID,
				)
			);

			if ( is_wp_error( $new_attachment_ids ) ) {
				return foogallery_abilities_map_gallery_management_error( $new_attachment_ids );
			}

			$gallery = foogallery_abilities_get_gallery( $gallery_id );

			if ( is_wp_error( $gallery ) ) {
				return $gallery;
			}

			return array(
				'gallery'          => foogallery_abilities_prepare_gallery_summary( $gallery ),
				'attachment_ids'   => $gallery->item_attachment_ids(),
				'attachment_count' => intval( $gallery->attachment_count() ),
			);
		}
	}
}
