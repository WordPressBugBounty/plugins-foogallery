<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Update gallery ability.
 */

if ( ! class_exists( 'FooGallery_Ability_Update_Gallery' ) ) {

	class FooGallery_Ability_Update_Gallery {

		const ID = 'foogallery/update-gallery';

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
					'label'               => __( 'Update Gallery', 'foogallery' ),
					'description'         => __( 'Update an existing FooGallery gallery by `gallery_id`. Use `layout` to switch layouts, derive valid `settings` IDs from the `foogallery/get-gallery-layout-schema` tool for that layout, and pass `settings` as an array of setting update objects. You can also update `title`, `status`, `sort`, or `custom_css`.', 'foogallery' ),
					'input_schema'        => array(
						'type'                 => 'object',
						'required'             => array( 'gallery_id' ),
						'properties'           => array(
							'gallery_id'  => array(
								'type'        => 'integer',
								'description' => __( 'The numeric ID of the FooGallery gallery to update.', 'foogallery' ),
							),
							'title'       => array(
								'type'        => 'string',
								'description' => __( 'Optional replacement gallery title.', 'foogallery' ),
							),
							'status'      => array(
								'type'        => 'string',
								'enum'        => array( 'draft', 'publish', 'private' ),
								'description' => __( 'Optional replacement post status for the gallery.', 'foogallery' ),
							),
							'layout'      => array(
								'type'        => 'string',
								'description' => __( 'Optional gallery layout slug. When changed, FooGallery rebuilds the stored settings base for the new layout before applying any supplied `settings`.', 'foogallery' ),
							),
							'settings'    => array(
								'type'        => 'array',
								'description' => __( 'Optional array of layout setting update objects. Use the IDs and field types returned by the `foogallery/get-gallery-layout-schema` tool for the active or target layout.', 'foogallery' ),
								'items'       => foogallery_abilities_get_setting_update_schema(),
							),
							'sort'        => array(
								'type'        => 'string',
								'description' => __( 'Optional FooGallery sort mode to store for the gallery.', 'foogallery' ),
							),
							'custom_css'  => array(
								'type'        => 'string',
								'description' => __( 'Optional custom CSS to save with the gallery. Pass an empty string to clear it.', 'foogallery' ),
							),
						),
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'gallery' => array_merge(
								foogallery_abilities_get_gallery_detail_schema( false ),
								array(
									'description' => __( 'The updated gallery record after all changes have been applied.', 'foogallery' ),
								)
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
			if ( $gallery_id <= 0 || ! current_user_can( 'edit_post', $gallery_id ) ) {
				return false;
			}

			if ( isset( $args['status'] ) ) {
				$status = foogallery_abilities_sanitize_gallery_status( $args['status'], '' );
				if ( 'publish' === $status && ! current_user_can( 'publish_foogalleries' ) ) {
					return false;
				}
			}

			return true;
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

			$gallery = foogallery_abilities_get_gallery( isset( $args['gallery_id'] ) ? $args['gallery_id'] : 0 );

			if ( is_wp_error( $gallery ) ) {
				return $gallery;
			}

			$target_template = foogallery_abilities_get_requested_layout( $args, $gallery->gallery_template );
			$template        = foogallery_abilities_get_template( $target_template );

			if ( is_wp_error( $template ) ) {
				return $template;
			}

			$update_args = array(
				'template' => $template['slug'],
			);

			if ( array_key_exists( 'settings', $args ) ) {
				$update_args['settings'] = $args['settings'];
			}

			foreach ( array( 'title', 'sort', 'custom_css' ) as $field ) {
				if ( array_key_exists( $field, $args ) ) {
					$update_args[ $field ] = $args[ $field ];
				}
			}

			if ( array_key_exists( 'status', $args ) ) {
				$update_args['status'] = foogallery_abilities_sanitize_gallery_status( $args['status'], $gallery->post_status );
			}

			$result = foogallery_update_gallery_for_ability(
				$gallery->ID,
				$update_args,
				array(
					'ability' => self::ID,
				)
			);

			if ( is_wp_error( $result ) ) {
				return foogallery_abilities_map_gallery_management_error( $result );
			}

			$gallery = foogallery_abilities_get_gallery( $gallery->ID );

			if ( is_wp_error( $gallery ) ) {
				return $gallery;
			}

			return array(
				'gallery' => foogallery_abilities_prepare_gallery_details( $gallery ),
			);
		}
	}
}
