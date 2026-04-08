<?php
/**
 * Class FooGalleryAttachment
 *
 * An easy to use wrapper class for a FooGallery Attachment
 */
if ( ! class_exists( 'FooGalleryAttachment' ) ) {

	class FooGalleryAttachment extends stdClass {
		/**
		 * public constructor
		 *
		 * @param null $post
		 */
		public function __construct( $post = null ) {
			$this->set_defaults();

			if ( $post !== null ) {
				$this->load( $post );
			}
		}

		/**
		 *  Sets the default when a new gallery is instantiated
		 */
		private function set_defaults() {
			$this->_post = null;
			$this->ID = 0;
			$this->type = 'image'; // set the default type to image.
			$this->title = '';
			$this->caption = '';
			$this->description = '';
			$this->alt = '';
			$this->url = '';
			$this->width = 0;
			$this->height = 0;
			$this->parent_post_id = 0;
			$this->parent_post_url = '';
			$this->custom_url = '';
			$this->custom_target = '';
			$this->custom_rel = '';
		}

		/**
		 * private attachment load function
		 * @param $post | WP_Post
		 */
		private function load( $post ) {
			$this->_post = $post;
			$this->ID = $post->ID;
			$this->title = trim( $post->post_title );
			$this->caption = trim( $post->post_excerpt );
			$this->description = trim( $post->post_content );
			$this->alt = trim( get_post_meta( $this->ID, '_wp_attachment_image_alt', true ) );
			$this->custom_url = foogallery_sanitize_attachment_custom_url( get_post_meta( $this->ID, '_foogallery_custom_url', true ) );
			$this->custom_target = foogallery_sanitize_attachment_custom_target( get_post_meta( $this->ID, '_foogallery_custom_target', true ) );
			$this->custom_rel = foogallery_sanitize_attachment_custom_rel( get_post_meta( $this->ID, '_foogallery_custom_rel', true ) );
			$this->parent_post_id = (int) $post->post_parent;
			if ( $this->parent_post_id > 0 ) {
				$this->parent_post_url = get_permalink( $this->parent_post_id );
			}
			$this->load_attachment_image_data( $this->ID );

			$this->date = !empty( $post->post_date_gmt ) ? $post->post_date_gmt : $post->post_date;
			$this->modified = !empty( $post->post_modified_gmt ) ? $post->post_modified_gmt : $post->post_modified;

			do_action( 'foogallery_attachment_instance_after_load', $this, $post );
		}

		public function load_attachment_image_data( $attachment_id ) {
			$image_attributes = foogallery_get_full_size_image_data( $attachment_id );
			if ( $image_attributes ) {
				$this->url = $image_attributes[0];
				$this->width = $image_attributes[1];
				$this->height = $image_attributes[2];
			}
		}

		/**
		 * Static function to load a FooGalleryAttachment instance by passing in a post object
		 * @static
		 *
		 * @param $post
		 *
		 * @return FooGalleryAttachment
		 */
		public static function get( $post ) {
			return new self( $post );
		}

		/**
		 * Static function to load a FooGalleryAttachment instance by passing in an attachment_id
		 * @static
		 *
		 * @param $attachment_id
		 *
		 * @return FooGalleryAttachment
		 */
		public static function get_by_id( $attachment_id ) {
			$post = get_post( $attachment_id );
			return new self( $post );
		}

		/**
		 * Returns the image source only
		 *
		 * @param array $args
		 * @return string
		 */
		public function html_img_src( $args = array() ) {
			return esc_url( apply_filters( 'foogallery_attachment_resize_thumbnail', $this->url, $args, $this ) );
		}

		/**
		 * @deprecated 1.9.24 Functions inside render-functions.php should rather be used
		 *
		 * Returns the HTML img tag for the attachment
		 * @param array $args
		 *
		 * @return string
		 */
		public function html_img( $args = array() ) {
			return foogallery_attachment_html_image( $this, $args );
		}

		/**
		 * @deprecated 1.9.24 Functions inside render-functions.php should rather be used
		 *
		 * Returns HTML for the attachment
		 * @param array $args
		 * @param bool $output_image
		 * @param bool $output_closing_tag
		 *
		 * @return string
		 */
		public function html( $args = array(), $output_image = true, $output_closing_tag = true ) {
			return foogallery_attachment_html_anchor( $this, $args, $output_image, $output_closing_tag );
		}

		/**
		 * @deprecated 1.9.24 Functions inside render-functions.php should rather be used
		 *
		 * Returns generic html for captions
		 *
		 * @param $caption_content string Include title, desc, or both
		 *
		 * @return string
		 */
		public function html_caption( $caption_content ) {
			return foogallery_attachment_html_caption( $this );
		}
	}
}
