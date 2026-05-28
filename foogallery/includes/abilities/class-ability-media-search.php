<?php
/**
 * Media search ability.
 */

if ( ! class_exists( 'FooGallery_Ability_Media_Search' ) ) {

	class FooGallery_Ability_Media_Search {

		const ID = 'foogallery/media-search';

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
					'label'               => __( 'Media Search', 'foogallery' ),
					'description'         => __( 'Search media-library attachments using the required `search` text. Use `search_by` to restrict the search to specific attachment fields such as title, alt text, URL, or taxonomy terms, and `limit`/`offset` for pagination.', 'foogallery' ),
					'input_schema'        => array(
						'type'                 => 'object',
						'required'             => array( 'search' ),
						'properties'           => array(
							'search'    => array(
								'type'        => 'string',
								'description' => __( 'The search text to match against attachment metadata.', 'foogallery' ),
							),
							'search_by' => array(
								'type'        => 'array',
								'description' => __( 'Optional list of attachment fields to search. If omitted, the search runs across title, caption, description, alt text, URL, and taxonomy terms.', 'foogallery' ),
								'items'       => array(
									'type' => 'string',
									'enum' => foogallery_abilities_get_media_searchable_fields(),
								),
							),
							'limit'     => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'maximum'     => 100,
								'default'     => 20,
								'description' => __( 'Maximum number of attachments to return. Defaults to 20.', 'foogallery' ),
							),
							'offset'    => array(
								'type'        => 'integer',
								'minimum'     => 0,
								'default'     => 0,
								'description' => __( 'Number of matching attachments to skip before returning results. Use with `limit` for pagination.', 'foogallery' ),
							),
						),
						'additionalProperties' => false,
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'total'       => array(
								'type'        => 'integer',
								'description' => __( 'Total number of matching attachments.', 'foogallery' ),
							),
							'attachments' => array(
								'type'        => 'array',
								'description' => __( 'Attachment records for the current page of results.', 'foogallery' ),
								'items'       => foogallery_abilities_get_attachment_schema(),
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
		 * @return bool
		 */
		public function can_execute( $args = null ) {
			return current_user_can( 'upload_files' );
		}

		/**
		 * Execute the ability.
		 *
		 * @param array $args Ability arguments.
		 *
		 * @return array|WP_Error
		 */
		public function execute( $args ) {
			$args         = foogallery_abilities_normalize_input( $args );
			$search       = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';
			$search_by    = isset( $args['search_by'] ) ? $args['search_by'] : array();
			$limit        = isset( $args['limit'] ) ? max( 1, min( 100, absint( $args['limit'] ) ) ) : 20;
			$offset       = isset( $args['offset'] ) ? max( 0, absint( $args['offset'] ) ) : 0;
			$search_result = foogallery_abilities_search_media_attachments( $search, $search_by, $limit, $offset );

			if ( is_wp_error( $search_result ) ) {
				return $search_result;
			}

			$attachments = array();

			foreach ( $search_result['attachment_ids'] as $attachment_id ) {
				$attachments[] = foogallery_abilities_prepare_attachment( FooGalleryAttachment::get_by_id( $attachment_id ) );
			}

			return array(
				'total'       => $search_result['total'],
				'attachments' => $attachments,
			);
		}
	}
}
