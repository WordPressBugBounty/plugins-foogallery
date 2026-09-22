<?php
/** Bounded source-header inspection for the compression review heuristic.
 *
 * @package FooGallery
 */

defined( 'ABSPATH' ) || exit;

/** Reads container headers only; never decodes or rewrites an image. */
class FooGallery_Media_Audit_Compression {

	const VERSION     = 'density-1';
	const FILE_BYTES  = 65536;
	const FILE_CHUNKS = 256;
	const RUN_BYTES   = 33554432;
	const RUN_SECONDS = 10;

	/** Open local source.
	 *
	 * @var resource|false
	 */
	private $handle;
	/** Source length from the open file descriptor.
	 *
	 * @var int
	 */
	private $size;
	/** Bytes actually read for this source.
	 *
	 * @var int
	 */
	private $read_bytes = 0;
	/** Remaining run allowance at inspection start.
	 *
	 * @var int
	 */
	private $remaining;
	/** Absolute worker/header deadline.
	 *
	 * @var float
	 */
	private $deadline;
	/** Fixed limitation returned on failure.
	 *
	 * @var string
	 */
	private $error = 'header_unavailable';

	/** Resolve filterable thresholds once, at job creation. */
	public static function settings() {
		$config  = foogallery_media_audit_config();
		$minimum = isset( $config['src07_min_bytes'] ) ? $config['src07_min_bytes'] : null;
		$out     = array(
			'version'   => self::VERSION,
			'min_bytes' => is_int( $minimum ) && $minimum > 0 ? $minimum : 262144,
			'density'   => array(),
		);
		foreach ( array(
			'JPEG' => 0.75,
			'PNG'  => 2.0,
			'WebP' => 0.75,
			'AVIF' => 0.75,
		) as $format => $default ) {
			$value                     = isset( $config['src07_density'][ $format ] ) ? $config['src07_density'][ $format ] : null;
			$out['density'][ $format ] = ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value ) && $value > 0 ? (float) $value : $default;
		}
		return $out;
	}

	/** Return whether positive, finite source measurements exceed both thresholds.
	 *
	 * @param int    $bytes Measured source bytes.
	 * @param mixed  $width Stored pixel width.
	 * @param mixed  $height Stored pixel height.
	 * @param string $format Normalized format.
	 * @param array  $settings Job's effective thresholds.
	 * @return bool
	 */
	public static function qualifies( $bytes, $width, $height, $format, $settings ) {
		return self::dimension( $width ) && self::dimension( $height ) && is_int( $bytes ) && $bytes > $settings['min_bytes']
			&& isset( $settings['density'][ $format ] ) && $bytes / ( (float) $width * $height ) > $settings['density'][ $format ];
	}

	/** Stored dimensions must be positive integers, not coerced strings or fractions.
	 *
	 * @param mixed $value Stored dimension.
	 * @return bool
	 */
	public static function dimension( $value ) {
		return is_int( $value ) && $value > 0 && $value <= 2147483647;
	}

	/**
	 * Inspect one local regular file, charging attempted reads and elapsed time.
	 *
	 * @param string $file Local attached source.
	 * @param string $expected Format from attachment metadata.
	 * @param array  $budget Persisted bytes/seconds counters, updated by reference.
	 * @param float  $deadline Current worker deadline.
	 * @return array State (still, excluded, unavailable), format, bytes or limitation.
	 */
	public static function inspect( $file, $expected, &$budget, $deadline ) {
		$unknown = array(
			'state'      => 'unavailable',
			'limitation' => 'header_unavailable',
		);
		if ( $budget['bytes'] >= self::RUN_BYTES || $budget['seconds'] >= self::RUN_SECONDS || microtime( true ) >= $deadline ) {
			$unknown['limitation'] = 'header_budget';
			return $unknown;
		}
		$started = microtime( true );
		$reader  = new static();
		try {
			if ( ! is_string( $file ) || '' === $file || false !== strpos( $file, '://' ) || ! is_file( $file ) || ! is_readable( $file ) ) {
				return $unknown; }
			// Native streams allow bounded reads/seeks without loading whole files.
			$reader->handle = @fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! $reader->handle ) {
				return $unknown; }
			$before = fstat( $reader->handle );
			if ( ! $before || ( $before['mode'] & 0170000 ) !== 0100000 || $before['size'] <= 0 ) {
				return $unknown; }
			$reader->size      = $before['size'];
			$reader->remaining = self::RUN_BYTES - $budget['bytes'];
			$reader->deadline  = min( $deadline, $started + self::RUN_SECONDS - $budget['seconds'] );
			$result            = $reader->parse();
			$after             = fstat( $reader->handle );
			clearstatcache( true, $file );
			$path_stat = @stat( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			foreach ( array( 'size', 'mtime', 'ino', 'dev' ) as $key ) {
				if ( ! $after || ! $path_stat || $before[ $key ] !== $after[ $key ] || $before[ $key ] !== $path_stat[ $key ] ) {
					return array(
						'state'      => 'unavailable',
						'limitation' => 'file_changed',
					);
				}
			}
			if ( ! $result || $result['format'] !== $expected ) {
				$unknown['limitation'] = $reader->error;
				return $unknown;
			}
			$result['bytes'] = $before['size'];
			return $result;
		} finally {
			if ( is_resource( $reader->handle ) ) {
				fclose( $reader->handle ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$budget['bytes']   += $reader->read_bytes;
			$budget['seconds'] += microtime( true ) - $started;
		}
	}

	/** Read an exact bounded header; failures never imply a static image.
	 *
	 * @param int $length Header byte count.
	 * @return string|false
	 */
	private function read( $length ) {
		if ( $length < 1 || $length > self::FILE_BYTES - $this->read_bytes || $length > $this->size - ftell( $this->handle ) ) {
			return false; }
		if ( $length > $this->remaining - $this->read_bytes || microtime( true ) >= $this->deadline ) {
			$this->error = 'header_budget';
			return false;
		}
		$data              = fread( $this->handle, $length ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$this->read_bytes += is_string( $data ) ? strlen( $data ) : 0;
		return is_string( $data ) && strlen( $data ) === $length ? $data : false;
	}

	/** Move over a validated chunk without reading its payload.
	 *
	 * @param int $position Absolute forward offset.
	 * @return bool
	 */
	private function seek( $position ) {
		return $position >= ftell( $this->handle ) && $position <= $this->size && 0 === fseek( $this->handle, $position );
	}

	/** Decode an unsigned 32-bit container length.
	 *
	 * @param string $bytes Four header bytes.
	 * @param bool   $little Whether the value is little endian.
	 * @return int
	 */
	private function uint( $bytes, $little = false ) {
		$value = unpack( $little ? 'Vvalue' : 'Nvalue', $bytes );
		return $value['value'];
	}

	/** Identify JPEG, PNG, WebP or AVIF using bounded container headers. */
	protected function parse() {
		$prefix = $this->read( 3 );
		if ( false === $prefix ) {
			return false; }
		if ( "\xff\xd8\xff" === $prefix ) {
			return array(
				'state'  => 'still',
				'format' => 'JPEG',
			); }
		$tail = $this->read( 5 );
		if ( false === $tail ) {
			return false; }
		$header = $prefix . $tail;
		if ( "\x89PNG\r\n\x1a\n" === $header ) {
			return $this->png(); }
		if ( 'RIFF' === substr( $header, 0, 4 ) ) {
			return $this->webp( $this->uint( substr( $header, 4, 4 ), true ) + 8 ); }
		return $this->avif( $header );
	}

	/** PNG animation control must precede the first IDAT chunk. */
	private function png() {
		for ( $n = 0; $n < self::FILE_CHUNKS; ++$n ) {
			$header = $this->read( 8 );
			if ( false === $header ) {
				return false; }
			$length = $this->uint( substr( $header, 0, 4 ) );
			$type   = substr( $header, 4, 4 );
			$end    = ftell( $this->handle ) + $length + 4;
			if ( $end > $this->size || ( 0 === $n && ( 'IHDR' !== $type || 13 !== $length ) ) ) {
				return false; }
			if ( 'acTL' === $type ) {
				return 8 === $length ? array(
					'state'  => 'excluded',
					'format' => 'PNG',
				) : false; }
			if ( 'IDAT' === $type ) {
				return array(
					'state'  => 'still',
					'format' => 'PNG',
				); }
			if ( 'IEND' === $type || ! $this->seek( $end ) ) {
				return false; }
		}
		return false;
	}

	/** Read WebP's animation bit independently of its alpha bit.
	 *
	 * @param int $end Validated RIFF container end.
	 * @return array|false
	 */
	private function webp( $end ) {
		if ( $end > $this->size || $end < 20 || 'WEBP' !== $this->read( 4 ) ) {
			return false; }
		for ( $n = 0; $n < self::FILE_CHUNKS; ++$n ) {
			if ( ftell( $this->handle ) + 8 > $end ) {
				return false; }
			$header = $this->read( 8 );
			if ( false === $header ) {
				return false; }
			$type   = substr( $header, 0, 4 );
			$length = $this->uint( substr( $header, 4, 4 ), true );
			$next   = ftell( $this->handle ) + $length + ( $length % 2 );
			if ( $next > $end ) {
				return false; }
			if ( 0 === $n && ! in_array( $type, array( 'VP8X', 'VP8 ', 'VP8L' ), true ) ) {
				return false; }
			if ( 'VP8X' === $type ) {
				if ( 0 !== $n || 10 !== $length ) {
					return false; }
				$extended = $this->read( 10 );
				if ( false === $extended ) {
					return false; }
				if ( ord( $extended[0] ) & 0x02 ) {
					return array(
						'state'  => 'excluded',
						'format' => 'WebP',
					); }
			} elseif ( 'ANIM' === $type || 'ANMF' === $type ) {
				return false; // Contradicts the declared static container.
			} elseif ( in_array( $type, array( 'VP8 ', 'VP8L' ), true ) ) {
				return $length > 0 ? array(
					'state'  => 'still',
					'format' => 'WebP',
				) : false;
			}
			if ( ! $this->seek( $next ) ) {
				return false; }
		}
		return false;
	}

	/** Inspect AVIF's complete file-type box, including compatible sequence brands.
	 *
	 * @param string $header First eight bytes of the first box.
	 * @return array|false
	 */
	private function avif( $header ) {
		for ( $n = 0; $n < self::FILE_CHUNKS; ++$n ) {
			$start       = ftell( $this->handle ) - 8;
			$length      = $this->uint( substr( $header, 0, 4 ) );
			$type        = substr( $header, 4, 4 );
			$header_size = 8;
			if ( 1 === $length ) {
				$extended = $this->read( 8 );
				if ( false === $extended || 0 !== $this->uint( substr( $extended, 0, 4 ) ) ) {
					return false; }
				$length      = $this->uint( substr( $extended, 4, 4 ) );
				$header_size = 16;
			}
			if ( $length < $header_size || $length > $this->size - $start ) {
				return false; }
			if ( 'ftyp' === $type ) {
				$payload = $length - $header_size;
				if ( $payload < 8 || 0 !== $payload % 4 ) {
					return false; }
				$brands = $this->read( $payload );
				if ( false === $brands ) {
					return false; }
				$list = str_split( substr( $brands, 0, 4 ) . substr( $brands, 8 ), 4 );
				if ( ! array_intersect( array( 'avif', 'avis' ), $list ) ) {
					return false; }
				if ( array_intersect( array( 'avis', 'msf1' ), $list ) ) {
					return array(
						'state'  => 'excluded',
						'format' => 'AVIF',
					); }
				return in_array( 'avif', $list, true ) ? array(
					'state'  => 'still',
					'format' => 'AVIF',
				) : false;
			}
			// Only padding may precede the file-type declaration.
			if ( ! in_array( $type, array( 'free', 'skip' ), true ) || ! $this->seek( $start + $length ) ) {
				return false; }
			$header = $this->read( 8 );
			if ( false === $header ) {
				return false; }
		}
		return false;
	}
}
