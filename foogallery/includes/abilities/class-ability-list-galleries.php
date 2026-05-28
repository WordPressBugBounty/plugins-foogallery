<?php
/**
 * List galleries ability.
 */

if ( ! class_exists( 'FooGallery_Ability_List_Galleries' ) ) {

	class FooGallery_Ability_List_Galleries {

		const ID = 'foogallery/list-galleries';

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
					'label'               => __( 'List Galleries', 'foogallery' ),
					'description'         => __( 'List FooGallery galleries. Use `search` to match gallery titles, `status` to filter by post status, `layout` to filter by gallery layout slug, and `limit`/`offset` for pagination.', 'foogallery' ),
					'input_schema'        => array(
						'type'                 => 'object',
						'properties'           => array(
							'search'   => array(
								'type'        => 'string',
								'description' => __( 'Optional text search against the gallery title.', 'foogallery' ),
							),
							'status'   => array(
								'type'        => 'string',
								'enum'        => array( 'draft', 'publish', 'private', 'any' ),
								'default'     => 'any',
								'description' => __( 'Optional gallery post status filter. Use `any` to include draft, publish, and private galleries.', 'foogallery' ),
							),
							'layout'   => array(
								'type'        => 'string',
								'description' => __( 'Optional gallery layout slug, such as `default` or `masonry`, used to filter the results.', 'foogallery' ),
							),
							'limit'    => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'maximum'     => 100,
								'default'     => 20,
								'description' => __( 'Maximum number of galleries to return. Defaults to 20.', 'foogallery' ),
							),
							'offset'   => array(
								'type'        => 'integer',
								'minimum'     => 0,
								'default'     => 0,
								'description' => __( 'Number of matching galleries to skip before returning results. Use with `limit` for pagination.', 'foogallery' ),
							),
						),
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'total'     => array(
								'type'        => 'integer',
								'description' => __( 'Total number of galleries matching the supplied filters.', 'foogallery' ),
							),
							'galleries' => array(
								'type'        => 'array',
								'description' => __( 'Gallery summary records for the current page of results.', 'foogallery' ),
								'items'       => foogallery_abilities_get_gallery_summary_schema(),
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
			return current_user_can( 'edit_foogalleries' );
		}

		/**
		 * Execute the ability.
		 *
		 * @param array $args Ability arguments.
		 *
		 * @return array
		 */
		public function execute( $args ) {
			$args = foogallery_abilities_normalize_input( $args );

			$status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'any';
			$limit  = isset( $args['limit'] ) ? max( 1, min( 100, absint( $args['limit'] ) ) ) : 20;
			$offset = isset( $args['offset'] ) ? max( 0, absint( $args['offset'] ) ) : 0;
			$query_args = array(
				'post_type'      => FOOGALLERY_CPT_GALLERY,
				'post_status'    => 'any' === $status ? array( 'draft', 'publish', 'private' ) : $status,
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			);

			if ( ! empty( $args['search'] ) ) {
				$query_args['s'] = sanitize_text_field( $args['search'] );
			}

			$layout = foogallery_abilities_get_requested_layout( $args );

			if ( '' !== $layout ) {
				$query_args['meta_query'] = array(
					array(
						'key'   => FOOGALLERY_META_TEMPLATE,
						'value' => $layout,
					),
				);
			}

			$query     = new WP_Query( $query_args );
			$galleries = array();

			foreach ( $query->posts as $post ) {
				$gallery = FooGallery::get( $post );
				$galleries[] = foogallery_abilities_prepare_gallery_summary( $gallery );
			}

			return array(
				'total'     => intval( $query->found_posts ),
				'galleries' => $galleries,
			);
		}
	}
}
