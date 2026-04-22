<?php
	/**
	 * "Direct URLs" storage backend for Madara-Core.
	 *
	 * Lets the user paste a list of individual image URLs (one per line)
	 * in the chapter-upload screen; those URLs become the chapter's pages
	 * directly. Useful when images are hosted somewhere we don't have a
	 * dedicated backend for.
	 *
	 * Auto-discovered by Madara-Core via
	 *   class_exists( 'wp_manga_storage_direct' )
	 * the same way as the ImgChest backend.
	 *
	 * @package mangazscans
	 */

	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	if ( ! class_exists( 'wp_manga_storage_direct' ) ) {

		class wp_manga_storage_direct {

			private static $instance = null;

			public static function get_instance() {
				if ( self::$instance === null ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			public function get_album_images( $album ) {
				if ( ! is_string( $album ) || trim( $album ) === '' ) {
					return new WP_Error(
						'direct_empty',
						esc_html__( 'Paste one image URL per line.', 'mangazscans' )
					);
				}

				$lines = preg_split( '/[\r\n,]+/', $album );
				$urls  = array();
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( $line === '' ) {
						continue;
					}
					if ( filter_var( $line, FILTER_VALIDATE_URL ) ) {
						$urls[] = esc_url_raw( $line );
					}
				}

				if ( empty( $urls ) ) {
					return new WP_Error(
						'direct_invalid',
						esc_html__( 'No valid image URLs found. Enter one full https URL per line.', 'mangazscans' )
					);
				}

				return $urls;
			}
		}

	}
