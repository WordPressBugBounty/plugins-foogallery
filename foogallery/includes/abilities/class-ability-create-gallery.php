<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create gallery ability.
 */

if ( ! class_exists( 'FooGallery_Ability_Create_Gallery' ) ) {

	class FooGallery_Ability_Create_Gallery {

		const ID = 'foogallery/create-gallery';

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
					'label'               => __( 'Create Gallery', 'foogallery' ),
					'description'         => __( 'Create a new media-library-backed FooGallery gallery. `attachment_ids` is required, `layout` selects the gallery layout slug, and `settings` should be an array of setting update objects using the normalized IDs returned by the `foogallery/get-gallery-layout-schema` tool for that layout.', 'foogallery' ),
					'input_schema'        => array(
						'type'                 => 'object',
						'required'             => array( 'attachment_ids' ),
						'properties'           => array(
							'title'          => array(
								'type'        => 'string',
								'description' => __( 'Optional gallery title. If omitted, FooGallery creates an `Untitled Gallery` draft title.', 'foogallery' ),
							),
							'status'         => array(
								'type'        => 'string',
								'enum'        => array( 'draft', 'publish', 'private' ),
								'default'     => 'draft',
								'description' => __( 'Optional initial gallery post status. Defaults to `draft`.', 'foogallery' ),
							),
							'layout'         => array(
								'type'        => 'string',
								'description' => __( 'Optional gallery layout slug. If omitted, FooGallery uses the current default gallery layout.', 'foogallery' ),
							),
							'attachment_ids' => array(
								'type'        => 'array',
								'minItems'    => 1,
								'description' => __( 'The media library attachment IDs to include in the new gallery.', 'foogallery' ),
								'items'       => array(
									'type' => 'integer',
								),
							),
							'settings'       => array(
								'type'        => 'array',
								'description' => __( 'Optional array of layout setting update objects. Use the IDs and field types returned by the `foogallery/get-gallery-layout-schema` tool for the selected layout.', 'foogallery' ),
								'items'       => foogallery_abilities_get_setting_update_schema(),
							),
							'sort'           => array(
								'type'        => 'string',
								'description' => __( 'Optional FooGallery sort mode to store for the gallery.', 'foogallery' ),
							),
							'custom_css'     => array(
								'type'        => 'string',
								'description' => __( 'Optional custom CSS to save with the gallery.', 'foogallery' ),
							),
						),
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'gallery' => array_merge(
								foogallery_abilities_get_gallery_detail_schema( false ),
								array(
									'description' => __( 'The newly created gallery record.', 'foogallery' ),
								)
							),
						),
					),
					'execute_callback'    => array( $this, 'execute' ),
					'permission_callback' => array( $this, 'can_execute' ),
					'meta'                => array(
						'annotations' => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => false,
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

			$status = isset( $args['status'] ) ? foogallery_abilities_sanitize_gallery_status( $args['status'], 'draft' ) : 'draft';

			if ( 'publish' === $status && ! current_user_can( 'publish_foogalleries' ) ) {
				return false;
			}

			return current_user_can( 'create_foogalleries' );
		}

		/**
		 * Execute the ability.
		 *
		 * @param array $args Ability arguments.
		 *
		 * @return array|WP_Error
		 */
		public function execute( $args ) {
			$args = foogallery_abilities_normalize_input( $args );

			$layout   = foogallery_abilities_get_requested_layout( $args, foogallery_default_gallery_template() );
			$template = foogallery_abilities_get_template( $layout );

			if ( is_wp_error( $template ) ) {
				return $template;
			}

			$attachment_ids = foogallery_normalize_attachment_ids(
				isset( $args['attachment_ids'] ) ? $args['attachment_ids'] : array()
			);

			if ( empty( $attachment_ids ) ) {
				return new WP_Error(
					'foogallery_ability_missing_attachments',
					__( 'At least one valid attachment ID is required to create a gallery.', 'foogallery' )
				);
			}

			$status = isset( $args['status'] ) ? foogallery_abilities_sanitize_gallery_status( $args['status'], 'draft' ) : 'draft';
			$title  = isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '';

			if ( '' === $title ) {
				$title = __( 'Untitled Gallery', 'foogallery' );
			}

			$request_context = array(
				'ability'        => self::ID,
				'layout'         => $template['slug'],
				'template'       => $template['slug'],
				'attachment_ids' => $attachment_ids,
				'settings'       => foogallery_abilities_prepare_template_setting_updates(
					$template['slug'],
					isset( $args['settings'] ) ? $args['settings'] : array()
				),
			);

			if ( is_wp_error( $request_context['settings'] ) ) {
				return $request_context['settings'];
			}

			$settings = foogallery_abilities_build_template_settings_base( $template['slug'] );
			$settings = foogallery_abilities_normalize_template_settings(
				$template['slug'],
				$request_context['settings'],
				$settings,
				0,
				$request_context
			);

			$insert_args = array(
				'title'          => $title,
				'status'         => $status,
				'template'       => $template['slug'],
				'settings'       => $settings,
				'attachment_ids' => $attachment_ids,
			);

			if ( isset( $args['sort'] ) ) {
				$insert_args['sort'] = foogallery_abilities_sanitize_gallery_sort( $args['sort'] );
			}

			if ( isset( $args['custom_css'] ) ) {
				$insert_args['custom_css'] = foogallery_sanitize_full( $args['custom_css'] );
			}

			$gallery_id = foogallery_insert_gallery( $insert_args, $request_context );

			if ( is_wp_error( $gallery_id ) ) {
				return foogallery_abilities_map_gallery_management_error( $gallery_id );
			}

			$gallery = foogallery_abilities_get_gallery( $gallery_id );

			if ( is_wp_error( $gallery ) ) {
				return $gallery;
			}

			return array(
				'gallery' => foogallery_abilities_prepare_gallery_details( $gallery ),
			);
		}
	}
}
