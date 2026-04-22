<?php
	/**
	 * Own AJAX handler for creating a chapter from any supported source.
	 *
	 * Replaces madara-core's chapter-creation path entirely. That path
	 * ended with wp_send_json_success() regardless of whether the DB
	 * insert succeeded, so users saw "Created Complete!" while nothing
	 * was persisted. This handler inserts the rows itself, verifies both
	 * writes, rolls back on partial failure, and returns actionable
	 * errors (including $wpdb->last_error) when anything fails.
	 *
	 * Supported source types (via $_POST['source']):
	 *   zip       — uploaded .zip file of images     (wp_manga_storage_zip)
	 *   direct    — pasted image URLs, one per line  (wp_manga_storage_direct)
	 *   imgchest  — imgchest.com post URL            (wp_manga_storage_imgchest)
	 *   page      — any page URL, scraped for <img>  (wp_manga_storage_page)
	 *
	 * @package mangazscans
	 */

	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	add_action( 'wp_ajax_mangazscans_create_chapter', 'mangazscans_create_chapter_handler' );

	function mangazscans_create_chapter_handler() {

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp-manga-admin' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed. Reload the edit page and try again.', 'mangazscans' ) ), 403 );
		}

		$post_id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You cannot edit this manga.', 'mangazscans' ) ), 403 );
		}

		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$extend = isset( $_POST['nameExtend'] ) ? sanitize_text_field( wp_unslash( $_POST['nameExtend'] ) ) : '';
		$volume = isset( $_POST['volume'] ) ? absint( $_POST['volume'] ) : 0;
		$album  = isset( $_POST['album'] ) ? wp_unslash( $_POST['album'] ) : '';

		if ( $name === '' ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Chapter name is required.', 'mangazscans' ) ), 400 );
		}

		global $wp_manga_storage, $wp_manga_functions, $wp_manga_chapter, $wp_manga_chapter_data;
		if ( empty( $wp_manga_storage ) || empty( $wp_manga_chapter ) || empty( $wp_manga_chapter_data ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Madara-Core is not initialized. Activate the plugin and retry.', 'mangazscans' ) ), 500 );
		}

		// Duplicate-name guard (mirrors madara-core behaviour).
		if ( ! isset( $_POST['overwrite'] ) ) {
			$existing = $wp_manga_functions->check_unique_chapter( $name, $volume, $post_id );
			if ( $existing && isset( $existing['output'] ) ) {
				wp_send_json_error( array(
					'error'   => 'chapter_existed',
					'message' => esc_html__( 'A chapter with this name already exists in this volume. Rename, change volume, or enable overwrite.', 'mangazscans' ),
					'output'  => $existing['output'],
				), 409 );
			}
		}

		$slug = $wp_manga_storage->slugify( $name );
		if ( $slug === '' ) {
			$slug = 'chapter-' . time();
		}

		// 1. Resolve image URLs from whatever source the user chose.
		$urls = null;
		switch ( $source ) {
			case 'zip':
				require_once __DIR__ . '/zip.php';
				$file = isset( $_FILES['file'] ) ? $_FILES['file'] : null;
				$urls = wp_manga_storage_zip::get_instance()->import_uploaded_zip( $file, $post_id, $slug );
				$storage_slug = 'local'; // extracted into /uploads — treat as local
				break;

			case 'direct':
				require_once __DIR__ . '/direct.php';
				$urls = wp_manga_storage_direct::get_instance()->get_album_images( $album );
				$storage_slug = 'direct';
				break;

			case 'imgchest':
				require_once __DIR__ . '/imgchest.php';
				$urls = wp_manga_storage_imgchest::get_instance()->get_album_images( $album );
				$storage_slug = 'imgchest';
				break;

			case 'page':
				require_once __DIR__ . '/page.php';
				$urls = wp_manga_storage_page::get_instance()->get_album_images( $album );
				$storage_slug = 'page';
				break;

			default:
				wp_send_json_error( array( 'message' => esc_html__( 'Unknown source type.', 'mangazscans' ) ), 400 );
		}

		if ( is_wp_error( $urls ) ) {
			wp_send_json_error( array( 'message' => $urls->get_error_message() ), 400 );
		}
		if ( ! is_array( $urls ) || empty( $urls ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No images were produced for that source.', 'mangazscans' ) ), 400 );
		}

		// 2. Insert chapter row.
		$chapter_args = array(
			'post_id'             => $post_id,
			'volume_id'           => $volume,
			'chapter_name'        => $name,
			'chapter_name_extend' => $extend,
			'chapter_slug'        => $slug,
			'storage_in_use'      => $storage_slug,
		);

		$chapter_id = $wp_manga_chapter->insert_chapter( $chapter_args );
		if ( empty( $chapter_id ) || ! is_numeric( $chapter_id ) ) {
			global $wpdb;
			$err = ( isset( $wpdb->last_error ) && $wpdb->last_error ) ? $wpdb->last_error : 'unknown';
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: MySQL error text */
					esc_html__( 'Chapter row insert failed (DB said: %s). Check the wp_manga_chapters table exists and is writable.', 'mangazscans' ),
					esc_html( $err )
				),
			), 500 );
		}
		$chapter_id = (int) $chapter_id;

		// 3. Build chapter data JSON — same shape madara-core's reader expects.
		$pages = array();
		$page  = 1;
		foreach ( $urls as $url ) {
			$pages[ $page ] = array(
				'src'  => $url,
				'mime' => $wp_manga_storage->mime_content_type( $url ),
			);
			$page++;
		}
		$data_json = wp_json_encode( apply_filters( 'madara_chapter_data', $pages, $post_id, $chapter_id, $storage_slug ) );

		// 4. Insert chapter data row.
		$data_id = $wp_manga_chapter_data->insert( array(
			'chapter_id' => $chapter_id,
			'storage'    => $storage_slug,
			'data'       => $data_json,
		) );
		if ( empty( $data_id ) || ! is_numeric( $data_id ) ) {
			// Roll back the chapter row so we don't leave phantoms.
			if ( method_exists( $wp_manga_chapter, 'delete_chapter' ) ) {
				$wp_manga_chapter->delete_chapter( array( 'chapter_id' => $chapter_id ) );
			}
			global $wpdb;
			$err = ( isset( $wpdb->last_error ) && $wpdb->last_error ) ? $wpdb->last_error : 'unknown';
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: MySQL error text */
					esc_html__( 'Chapter data insert failed (DB said: %s). Rolled back the chapter row.', 'mangazscans' ),
					esc_html( $err )
				),
			), 500 );
		}

		// NB: insert_chapter() already fires 'manga_chapter_inserted'
		// internally; don't double-fire it here.

		wp_send_json_success( array(
			'chapter_id' => $chapter_id,
			'pages'      => count( $urls ),
			'source'     => $source,
			'message'    => sprintf(
				/* translators: %d: number of images */
				esc_html__( 'Chapter created with %d image(s).', 'mangazscans' ),
				count( $urls )
			),
		) );
	}
