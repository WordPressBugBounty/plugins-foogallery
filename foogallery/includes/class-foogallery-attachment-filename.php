<?php
/**
 * Attachment filename support in FooGallery.
 *
 * @package FooGallery
 */

if ( ! class_exists( 'FooGallery_Attachment_Filename' ) ) {

	class FooGallery_Attachment_Filename {

		const CAPTION_SOURCE = 'filename';
		const SORT_ASC       = 'filename_asc';
		const SORT_DESC      = 'filename_desc';

		/**
		 * FooGallery_Attachment_Filename constructor.
		 */
		public function __construct() {
			add_action( 'foogallery_attachment_instance_after_load', array( $this, 'load_attachment_filename' ), 10, 2 );

			add_filter( 'foogallery_admin_settings_override', array( $this, 'add_attachment_filename_settings_choice' ), 20 );
			add_filter( 'foogallery_override_gallery_template_fields', array( $this, 'add_attachment_filename_gallery_choice' ), 30, 2 );
			add_filter( 'foogallery_gallery_template_lightbox_caption_title_choices', array( $this, 'add_attachment_filename_choice' ) );

			add_filter( 'foogallery_sorting_options', array( $this, 'add_filename_sorting_options' ), 10, 2 );
			add_filter( 'foogallery_sorting_get_posts_order_arg', array( $this, 'get_posts_order_arg' ), 10, 2 );
			add_filter( 'foogallery_sorting_should_defer_query_args', array( $this, 'should_defer_query_args' ), 10, 2 );
			add_filter( 'foogallery_sort_attachments', array( $this, 'sort_attachments_by_filename' ), 10, 4 );
		}

		/**
		 * Store the attachment filename on the FooGallery attachment instance.
		 *
		 * @param FooGalleryAttachment $foogallery_attachment FooGallery attachment.
		 * @param WP_Post              $post                  Attachment post.
		 *
		 * @return void
		 */
		public function load_attachment_filename( $foogallery_attachment, $post ) {
			$foogallery_attachment->filename = self::get_filename( $post );
		}

		/**
		 * Add the filename source to global FooGallery caption settings.
		 *
		 * @param array $settings FooGallery settings definition.
		 *
		 * @return array
		 */
		public function add_attachment_filename_settings_choice( $settings ) {
			if ( empty( $settings['settings'] ) || ! is_array( $settings['settings'] ) ) {
				return $settings;
			}

			foreach ( $settings['settings'] as &$field ) {
				if ( ! is_array( $field ) || empty( $field['id'] ) || ! in_array( $field['id'], array( 'caption_title_source', 'caption_desc_source' ), true ) ) {
					continue;
				}

				$field['choices'] = $this->add_attachment_filename_choice( isset( $field['choices'] ) ? $field['choices'] : array() );
			}
			unset( $field );

			return $settings;
		}

		/**
		 * Add the filename source to per-gallery caption source fields.
		 *
		 * @param array $fields   Gallery template fields.
		 * @param array $template Gallery template.
		 *
		 * @return array
		 */
		public function add_attachment_filename_gallery_choice( $fields, $template ) {
			if ( ! is_array( $fields ) ) {
				return $fields;
			}

			$caption_source_fields = array(
				'caption_title_source',
				'caption_desc_source',
				'lightbox_caption_override_title',
				'lightbox_caption_override_desc',
				'foobox_caption_override_title',
				'foobox_caption_override_desc',
			);

			foreach ( $fields as &$field ) {
				if ( ! is_array( $field ) || empty( $field['id'] ) || ! in_array( $field['id'], $caption_source_fields, true ) ) {
					continue;
				}

				$field['choices'] = $this->add_attachment_filename_choice( isset( $field['choices'] ) ? $field['choices'] : array() );
			}
			unset( $field );

			return $fields;
		}

		/**
		 * Add the filename source to a caption source choice list.
		 *
		 * @param array $choices Caption source choices.
		 *
		 * @return array
		 */
		public function add_attachment_filename_choice( $choices ) {
			if ( ! is_array( $choices ) || array_key_exists( self::CAPTION_SOURCE, $choices ) ) {
				return $choices;
			}

			$with_filename = array();
			$added         = false;

			foreach ( $choices as $value => $label ) {
				if ( 'desc' === $value ) {
					$with_filename[ self::CAPTION_SOURCE ] = __( 'Attachment Filename', 'foogallery' );
					$added                                = true;
				}

				$with_filename[ $value ] = $label;
			}

			if ( ! $added ) {
				$with_filename[ self::CAPTION_SOURCE ] = __( 'Attachment Filename', 'foogallery' );
			}

			return $with_filename;
		}

		/**
		 * Add filename sorting options.
		 *
		 * @param array  $options Sorting options.
		 * @param string $context Sorting context.
		 *
		 * @return array
		 */
		public function add_filename_sorting_options( $options, $context = 'gallery' ) {
			if ( 'album' === $context || ! is_array( $options ) || array_key_exists( self::SORT_ASC, $options ) ) {
				return $options;
			}

			$with_filename = array();
			$added         = false;

			foreach ( $options as $value => $label ) {
				$with_filename[ $value ] = $label;

				if ( 'title_desc' === $value ) {
					$with_filename[ self::SORT_ASC ]  = __( 'Filename - alphabetically', 'foogallery' );
					$with_filename[ self::SORT_DESC ] = __( 'Filename - reverse', 'foogallery' );
					$added                            = true;
				}
			}

			if ( ! $added ) {
				$with_filename[ self::SORT_ASC ]  = __( 'Filename - alphabetically', 'foogallery' );
				$with_filename[ self::SORT_DESC ] = __( 'Filename - reverse', 'foogallery' );
			}

			return $with_filename;
		}

		/**
		 * Return the sort direction for filename sorting.
		 *
		 * @param string $order_arg      Existing order argument.
		 * @param string $sorting_option Selected sorting option.
		 *
		 * @return string
		 */
		public function get_posts_order_arg( $order_arg, $sorting_option ) {
			if ( self::SORT_ASC === $sorting_option ) {
				return 'ASC';
			}

			if ( self::SORT_DESC === $sorting_option ) {
				return 'DESC';
			}

			return $order_arg;
		}

		/**
		 * Defer query slicing until after attachments have been sorted by filename.
		 *
		 * @param bool   $defer          Existing defer value.
		 * @param string $sorting_option Selected sorting option.
		 *
		 * @return bool
		 */
		public function should_defer_query_args( $defer, $sorting_option ) {
			return $defer || $this->is_filename_sort( $sorting_option );
		}

		/**
		 * Sort attachment objects by their filename.
		 *
		 * @param FooGalleryAttachment[] $attachments Array of attachment objects.
		 * @param string                 $orderby     Orderby clause used for the query.
		 * @param string                 $order       Order clause used for the query.
		 * @param string                 $sort        Selected sorting option.
		 *
		 * @return FooGalleryAttachment[]
		 */
		public function sort_attachments_by_filename( $attachments, $orderby, $order, $sort = '' ) {
			if ( ! $this->is_filename_sort( $sort ) || empty( $attachments ) || ! is_array( $attachments ) ) {
				return $attachments;
			}

			$order = ( strtoupper( $order ) === 'ASC' ) ? 'ASC' : 'DESC';

			usort( $attachments, array( $this, 'compare_attachments_by_filename' ) );

			if ( 'DESC' === $order ) {
				$attachments = array_reverse( $attachments );
			}

			return $attachments;
		}

		/**
		 * Check if the selected sort is a filename sort.
		 *
		 * @param string $sorting_option Selected sorting option.
		 *
		 * @return bool
		 */
		private function is_filename_sort( $sorting_option ) {
			return self::SORT_ASC === $sorting_option || self::SORT_DESC === $sorting_option;
		}

		/**
		 * Compare two attachments by filename.
		 *
		 * @param FooGalleryAttachment|WP_Post|int $first  First attachment.
		 * @param FooGalleryAttachment|WP_Post|int $second Second attachment.
		 *
		 * @return int
		 */
		public function compare_attachments_by_filename( $first, $second ) {
			return strnatcasecmp( self::get_filename( $first ), self::get_filename( $second ) );
		}

		/**
		 * Get an attachment filename without its folder path.
		 *
		 * @param WP_Post|FooGalleryAttachment|int $attachment Attachment post, FooGallery attachment, or attachment ID.
		 *
		 * @return string
		 */
		public static function get_filename( $attachment ) {
			$attachment_id = 0;
			$url           = '';

			if ( is_numeric( $attachment ) ) {
				$attachment_id = absint( $attachment );
			} else if ( $attachment instanceof WP_Post ) {
				$attachment_id = absint( $attachment->ID );
				$url           = $attachment->guid;
			} else if ( is_object( $attachment ) ) {
				$attachment_id = isset( $attachment->ID ) ? absint( $attachment->ID ) : 0;

				if ( isset( $attachment->filename ) && '' !== $attachment->filename ) {
					return trim( wp_basename( $attachment->filename ) );
				}

				$url = isset( $attachment->url ) ? $attachment->url : '';
			}

			if ( $attachment_id > 0 ) {
				$file = get_attached_file( $attachment_id );
				if ( ! empty( $file ) ) {
					return trim( wp_basename( $file ) );
				}

				$attachment_url = wp_get_attachment_url( $attachment_id );
				if ( ! empty( $attachment_url ) ) {
					$url = $attachment_url;
				}
			}

			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( empty( $path ) ) {
				$path = $url;
			}

			return trim( wp_basename( $path ) );
		}
	}
}
