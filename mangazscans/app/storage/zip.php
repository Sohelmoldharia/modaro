<?php
	/**
	 * Zip-file storage backend.
	 *
	 * The user uploads a .zip containing chapter images; we extract it
	 * under wp-content/uploads/mangazscans/{post_id}/{chapter_slug}/ and
	 * return the public URLs of the extracted images, ordered by file
	 * name (natural sort, so 01.png, 02.png, 10.png sort correctly).
	 *
	 * Called by mangazscans_import_chapter_handler() when source=zip.
	 * Unlike imgchest / direct / page, this backend needs the uploaded
	 * file (from $_FILES) rather than a single $album string, so its
	 * entry point signature is different — we accept the raw $_FILES
	 * array and the chapter context.
	 *
	 * @package mangazscans
	 */

	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	if ( ! class_exists( 'wp_manga_storage_zip' ) ) {

		class wp_manga_storage_zip {

			const SUBDIR          = 'mangazscans';
			const ALLOWED_EXTS    = array( 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp' );

			private static $instance = null;

			public static function get_instance() {
				if ( self::$instance === null ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			/**
			 * $file     — entry from $_FILES (must be a valid uploaded file)
			 * $post_id  — manga post id (for the destination subdir)
			 * $slug     — chapter slug (for the destination subdir)
			 */
			public function import_uploaded_zip( $file, $post_id, $slug ) {
				if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
					return new WP_Error( 'zip_no_file', esc_html__( 'No file received.', 'mangazscans' ) );
				}
				if ( ! empty( $file['error'] ) ) {
					return new WP_Error(
						'zip_upload_error_' . $file['error'],
						sprintf(
							/* translators: %d: PHP upload error code */
							esc_html__( 'Upload failed (PHP error %d). Check upload_max_filesize / post_max_size in php.ini.', 'mangazscans' ),
							(int) $file['error']
						)
					);
				}
				if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
					return new WP_Error( 'zip_not_uploaded', esc_html__( 'Temp file is not a valid upload.', 'mangazscans' ) );
				}
				if ( ! class_exists( 'ZipArchive' ) ) {
					return new WP_Error( 'zip_no_ext', esc_html__( 'PHP ZipArchive extension is not installed on this server.', 'mangazscans' ) );
				}

				$uploads = wp_upload_dir();
				if ( ! empty( $uploads['error'] ) ) {
					return new WP_Error( 'zip_uploads_dir', $uploads['error'] );
				}

				$dest_rel = self::SUBDIR . '/' . (int) $post_id . '/' . sanitize_file_name( $slug );
				$dest_abs = trailingslashit( $uploads['basedir'] ) . $dest_rel;
				$dest_url = trailingslashit( $uploads['baseurl'] ) . $dest_rel;

				if ( ! wp_mkdir_p( $dest_abs ) ) {
					return new WP_Error( 'zip_mkdir', esc_html__( 'Could not create destination directory.', 'mangazscans' ) );
				}

				$zip = new ZipArchive();
				if ( $zip->open( $file['tmp_name'] ) !== true ) {
					return new WP_Error( 'zip_open_failed', esc_html__( 'Cannot open uploaded zip (corrupt or not a zip).', 'mangazscans' ) );
				}

				$extracted = array();

				for ( $i = 0; $i < $zip->numFiles; $i++ ) {
					$entry = $zip->statIndex( $i );
					if ( ! $entry || empty( $entry['name'] ) ) {
						continue;
					}
					$name = $entry['name'];

					// Skip directories and path traversal.
					if ( substr( $name, -1 ) === '/' || strpos( $name, '..' ) !== false ) {
						continue;
					}

					// Some zips (esp. macOS) include __MACOSX/ metadata; drop.
					if ( strpos( $name, '__MACOSX/' ) === 0 || basename( $name )[0] === '.' ) {
						continue;
					}

					$base = basename( $name );
					$ext  = strtolower( pathinfo( $base, PATHINFO_EXTENSION ) );
					if ( ! in_array( $ext, self::ALLOWED_EXTS, true ) ) {
						continue;
					}

					$stream = $zip->getStream( $name );
					if ( ! $stream ) {
						continue;
					}

					$safe_base = sanitize_file_name( $base );
					$target    = trailingslashit( $dest_abs ) . $safe_base;
					$fp        = @fopen( $target, 'wb' );
					if ( ! $fp ) {
						fclose( $stream );
						continue;
					}
					stream_copy_to_stream( $stream, $fp );
					fclose( $fp );
					fclose( $stream );

					$extracted[] = array(
						'name' => $safe_base,
						'url'  => trailingslashit( $dest_url ) . rawurlencode( $safe_base ),
					);
				}
				$zip->close();

				if ( empty( $extracted ) ) {
					return new WP_Error( 'zip_no_images', esc_html__( 'Zip contained no supported images (jpg, png, webp, gif, bmp).', 'mangazscans' ) );
				}

				// Natural sort so 01.png, 2.png, 10.png come out in the
				// order a human expects.
				usort( $extracted, function ( $a, $b ) {
					return strnatcasecmp( $a['name'], $b['name'] );
				} );

				return wp_list_pluck( $extracted, 'url' );
			}
		}

	}
