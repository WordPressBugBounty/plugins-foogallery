<?php
/** Private, paginated affected-image queries.
 *
 * @package FooGallery
 */

defined( 'ABSPATH' ) || exit;

/** Identify the exact report displayed by the client.
 *
 * @param array $report Detailed report.
 * @return string
 */
function foogallery_media_audit_revision( $report ) {
	return hash( 'sha256', wp_json_encode( $report ) );
}

/** Describe a subject using current permissions; missing records retain their ID.
 *
 * @param int    $id Post ID.
 * @param string $kind Image or gallery.
 * @return array|null
 */
function foogallery_media_audit_subject( $id, $kind ) {
	if ( ! $id ) {
		return null; }
	$post     = get_post( $id );
	$expected = 'image' === $kind ? 'attachment' : 'foogallery';
	$exists   = $post && $expected === $post->post_type && 'trash' !== $post->post_status;
	return array(
		'id'        => (int) $id,
		'label'     => $exists && '' !== get_the_title( $post ) ? get_the_title( $post ) : sprintf(
			/* translators: %d: the saved subject ID. */
			'image' === $kind ? __( 'Image #%d', 'foogallery' ) : __( 'Gallery #%d', 'foogallery' ),
			$id
		),
		'missing'   => ! $exists,
		'edit_url'  => $exists && current_user_can( 'edit_post', $id ) ? esc_url_raw( get_edit_post_link( $id, 'raw' ) ) : '',
		'thumbnail' => 'image' === $kind && $exists && current_user_can( 'read_post', $id ) ? esc_url_raw( wp_get_attachment_image_url( $id, 'thumbnail' ) ) : '',
	);
}

/** Return display-ready dimensions.
 *
 * @param int $width Image width.
 * @param int $height Image height.
 * @return string
 */
function foogallery_media_audit_dimensions_label( $width, $height ) {
	if ( $width && $height ) {
		return sprintf(
			/* translators: 1: image width in pixels, 2: image height in pixels. */
			__( '%1$s × %2$s px', 'foogallery' ),
			number_format_i18n( $width ),
			number_format_i18n( $height )
		);
	}
	return sprintf(
		/* translators: %s: image width in pixels. */
		__( '%s px wide', 'foogallery' ),
		number_format_i18n( $width )
	);
}

/** Return the alt text observed for an accessibility finding.
 *
 * New reports contain the exact rendered value. Existing reports fall back to
 * the attachment's current Media Library value.
 *
 * @param array $subject Finding subject.
 * @return string
 */
function foogallery_media_audit_subject_alt_text( $subject ) {
	if ( isset( $subject['alt'] ) && is_string( $subject['alt'] ) ) {
		return $subject['alt'];
	}

	return isset( $subject['image'] )
		? trim( (string) get_post_meta( $subject['image'], '_wp_attachment_image_alt', true ) )
		: '';
}

/** Return the minimum useful evidence for one affected item.
 *
 * @param string $rule Finding rule.
 * @param array  $subject Finding subject.
 * @param int    $repeat_count Repeated alt count for this gallery.
 * @return string[]
 */
function foogallery_media_audit_subject_details( $rule, $subject, $repeat_count = 0 ) {
	$details = array();
	if ( 'FG-SRC-07' === $rule && isset( $subject['source_format'], $subject['source_width'], $subject['source_height'], $subject['bytes'] ) && $subject['source_width'] > 0 && $subject['source_height'] > 0 ) {
		$details[] = sprintf(
			/* translators: 1: source format, 2: image dimensions, 3: source file size. */
			__( 'Source: %1$s · %2$s · %3$s', 'foogallery' ),
			$subject['source_format'],
			foogallery_media_audit_dimensions_label( $subject['source_width'], $subject['source_height'] ),
			size_format( $subject['bytes'], 2 )
		);
		$details[] = sprintf(
			/* translators: %s: source bytes divided by pixel count, with two decimal places. */
			__( 'File size relative to dimensions: %s bytes/pixel', 'foogallery' ),
			number_format_i18n( $subject['bytes'] / ( (float) $subject['source_width'] * $subject['source_height'] ), 2 )
		);
	}

	if ( 'FG-DEL-02' === $rule ) {
		if ( isset( $subject['source_width'], $subject['source_height'] ) ) {
			$details[] = sprintf(
				/* translators: %s: source image dimensions. */
				__( 'Source: %s', 'foogallery' ),
				foogallery_media_audit_dimensions_label( $subject['source_width'], $subject['source_height'] )
			);
		}
		if ( isset( $subject['requested_width'], $subject['requested_height'] ) ) {
			$details[] = sprintf(
				/* translators: %s: requested thumbnail dimensions. */
				__( 'Requested: %s', 'foogallery' ),
				foogallery_media_audit_dimensions_label( $subject['requested_width'], $subject['requested_height'] )
			);
		}
	}

	if ( 'FG-SRC-01' === $rule ) {
		$width  = isset( $subject['source_width'] ) ? $subject['source_width'] : 0;
		$height = isset( $subject['source_height'] ) ? $subject['source_height'] : 0;
		$bytes  = isset( $subject['bytes'] ) ? $subject['bytes'] : 0;
		if ( $width ) {
			$details[] = foogallery_media_audit_dimensions_label( $width, $height );
		}
		if ( $bytes ) {
			$details[] = size_format( $bytes );
		}
	}

	if ( 'FG-SRC-04' === $rule ) {
		if ( ! empty( $subject['source_format'] ) ) {
			$details[] = sprintf(
				/* translators: %s: normalized image source format, such as JPEG or PNG. */
				__( 'Source format: %s', 'foogallery' ),
				$subject['source_format']
			);
		}
		if ( ! empty( $subject['source_width'] ) ) {
			$details[] = foogallery_media_audit_dimensions_label( $subject['source_width'], isset( $subject['source_height'] ) ? $subject['source_height'] : 0 );
		}
		if ( ! empty( $subject['bytes'] ) ) {
			$details[] = size_format( $subject['bytes'] );
		}
	}

	if ( in_array( $rule, array( 'FG-SRC-05', 'FG-INT-02' ), true ) && ! empty( $subject['http_status'] ) ) {
		$status_text = get_status_header_desc( $subject['http_status'] );
		$details[]   = trim( $subject['http_status'] . ' ' . $status_text );
	}

	if ( 'FG-INT-01' === $rule ) {
		$reasons = array(
			'empty_attachment_id'  => __( 'Empty attachment ID', 'foogallery' ),
			'attachment_not_found' => __( 'Attachment not found', 'foogallery' ),
			'attachment_trashed'   => __( 'Attachment is in the Trash', 'foogallery' ),
			'not_attachment'       => __( 'Saved ID is not an attachment', 'foogallery' ),
		);
		$reason  = isset( $subject['reason'], $reasons[ $subject['reason'] ] ) ? $reasons[ $subject['reason'] ] : __( 'Image cannot be found', 'foogallery' );
		if ( $subject['image'] ) {
			$details[] = sprintf(
				/* translators: 1: integrity failure reason, 2: saved attachment ID. */
				__( '%1$s · ID %2$d', 'foogallery' ),
				$reason,
				$subject['image']
			);
		} else {
			$details[] = $reason;
		}
	}

	if ( 'FG-A11Y-04' === $rule && $repeat_count ) {
		$details[] = sprintf(
			/* translators: %s: number of images using the same alt text. */
			_n( 'Used %s time', 'Used %s times', $repeat_count, 'foogallery' ),
			number_format_i18n( $repeat_count )
		);
	}
	if ( 'FG-A11Y-05' === $rule ) {
		$length    = isset( $subject['alt_length'] ) ? $subject['alt_length'] : foogallery_media_audit_text_length( foogallery_media_audit_subject_alt_text( $subject ) );
		$details[] = sprintf(
			/* translators: %s: alt-text length in characters. */
			_n( '%s character', '%s characters', $length, 'foogallery' ),
			number_format_i18n( $length )
		);
	}

	if ( in_array( $rule, array( 'FG-CFG-01', 'FG-CFG-02' ), true ) ) {
		$details[] = sprintf(
			/* translators: %s: number of gallery images. */
			__( 'Images: %s', 'foogallery' ),
			number_format_i18n( isset( $subject['member_count'] ) ? $subject['member_count'] : 0 )
		);
		$details[] = 'FG-CFG-01' === $rule ? __( 'Lazy loading: Off', 'foogallery' ) : __( 'Paging: None', 'foogallery' );
	}

	return $details;
}

/** Return at most 25 image/gallery contexts, hydrating only the requested page.
 *
 * @param array  $report Saved report.
 * @param string $rule Registered rule ID.
 * @param int    $page One-based page.
 * @return array
 */
function foogallery_media_audit_affected_page( $report, $rule, $page ) {
	$subjects = array();
	$repeated = array();
	foreach ( $report['findings'] as $finding ) {
		if ( $finding['rule'] !== $rule ) {
			continue; }
		foreach ( $finding['subjects'] as $subject ) {
			if ( 'FG-A11Y-04' === $rule ) {
				$alt = foogallery_media_audit_subject_alt_text( $subject );
				++$repeated[ $subject['gallery'] ][ hash( 'sha256', strtolower( $alt ) ) ];
			}
			$galleries = ! $subject['gallery'] && ! empty( $report['gallery_membership'][ $subject['image'] ] ) ? array_keys( $report['gallery_membership'][ $subject['image'] ] ) : array( $subject['gallery'] );
			foreach ( $galleries as $gallery ) {
				$key                = $subject['image'] . '-' . $gallery;
				$subject['gallery'] = (int) $gallery;
				if ( ! isset( $subjects[ $key ] ) || ( ! $subjects[ $key ]['sampled_url'] && $subject['sampled_url'] ) ) {
					$subjects[ $key ] = $subject; }
			}
		}
	}
	$total = count( $subjects );
	$pages = max( 1, (int) ceil( $total / 25 ) );
	$page  = min( $pages, max( 1, $page ) );
	$items = array();
	foreach ( array_slice( $subjects, ( $page - 1 ) * 25, 25 ) as $subject ) {
		$alt          = 0 === strpos( $rule, 'FG-A11Y-' ) ? foogallery_media_audit_subject_alt_text( $subject ) : null;
		$repeat_count = 'FG-A11Y-04' === $rule && isset( $repeated[ $subject['gallery'] ][ hash( 'sha256', strtolower( $alt ) ) ] ) ? $repeated[ $subject['gallery'] ][ hash( 'sha256', strtolower( $alt ) ) ] : 0;
		$items[]      = array(
			'image'       => foogallery_media_audit_subject( $subject['image'], 'image' ),
			'gallery'     => foogallery_media_audit_subject( $subject['gallery'], 'gallery' ),
			'sampled_url' => esc_url_raw( $subject['sampled_url'], array( 'http', 'https' ) ),
			'details'     => foogallery_media_audit_subject_details( $rule, $subject, $repeat_count ),
			'alt_text'    => $alt,
		);
	}
	$meta  = foogallery_media_audit_rule_meta( $rule );
	$stats = foogallery_media_audit_finding_stats( $report['checks'][ $rule ] );
	return array(
		'items'    => $items,
		'page'     => $page,
		'pages'    => $pages,
		'total'    => $total,
		'title'    => $meta['label'],
		'guidance' => $meta['fix'],
		'stats'    => $stats,
	);
}
