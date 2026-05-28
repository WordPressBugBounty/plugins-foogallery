<?php
/**
 * Get gallery ability.
 */

if ( ! class_exists( 'FooGallery_Ability_Get_Gallery' ) ) {

	class FooGallery_Ability_Get_Gallery {

		const ID = 'foogallery/get-gallery';

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
			wp_register_ability(
				self::ID,
				array(
					'category'            => FooGallery_Abilities::CATEGORY,
					'label'               => __( 'Get Gallery', 'foogallery' ),
					'description'         => __( 'Return one FooGallery gallery by `gallery_id`. Set `include_attachment_detail` to `true` only when full attachment metadata is needed; otherwise the response includes attachment IDs only.', 'foogallery' ),
					'input_schema'        => array(
						'type'                 => 'object',
						'required'             => array( 'gallery_id' ),
						'properties'           => array(
							'gallery_id' => array(
								'type'        => 'integer',
								'description' => __( 'The numeric ID of the FooGallery gallery to fetch.', 'foogallery' ),
							),
							'include_attachment_detail' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'When `true`, include the full `attachments` array with attachment metadata. When `false`, return only `attachment_ids`.', 'foogallery' ),
							),
						),
						'additionalProperties' => false,
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'gallery' => array_merge(
								foogallery_abilities_get_gallery_detail_schema( true ),
								array(
									'description' => __( 'The requested gallery record with normalized settings and, optionally, full attachment details.', 'foogallery' ),
								)
							),
						),
					),
					'execute_callback'    => array( $this, 'execute' ),
					'permission_callback' => array( $this, 'can_execute' ),
					'meta'                => array(
						'annotations' => array(
							'readonly'   => true,
							'idempotent' => true,
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
			$args = foogallery_abilities_normalize_input( $args );

			$include_attachment_detail = isset( $args['include_attachment_detail'] ) ? wp_validate_boolean( $args['include_attachment_detail'] ) : false;

			$gallery = foogallery_abilities_get_gallery( isset( $args['gallery_id'] ) ? $args['gallery_id'] : 0 );

			if ( is_wp_error( $gallery ) ) {
				return $gallery;
			}

			return array(
				'gallery' => foogallery_abilities_prepare_gallery_details( $gallery, $include_attachment_detail ),
			);
		}
	}
}
