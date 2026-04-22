<?php
	/**
	 * Own AJAX handler for importing a chapter from ImgChest / Direct URLs.
	 *
	 * Bypasses Madara-Core's inc/ajax/upload.php::import_album_chapter()
	 * because that function has silent-failure semantics: on a bad insert
	 * it still returns wp_send_json_success() with chapter_id === false,
	 * so the frontend shows "Created Complete!" while the DB has no
	 * chapter row. Our handler:
	 *
	 *   1. Validates input (nonce, caps, storage, album).
	 *   2. Fetches image URLs via our wp_manga_storage_* class.
	 *   3. Inserts the chapter row via Madara-Core's internal
	 *      $wp_manga_chapter->insert_chapter() and verifies a non-zero id.
	 *   4. Inserts the chapter data row via $wp_manga_chapter_data->insert()
	 *      and verifies it.
	 *   5. Only returns success if both rows landed.
	 *   6. On failure, returns a precise WP_Error-style message instead of
	 *      fake success.
	 *
	 * @package mangazscans
	 */

	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	add_action( 'wp_ajax_mangazscans_import_chapter', 'mangazscans_import_chapter_handler' );

	function mangazscans_import_chapter_handler() {

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp-manga-admin' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed. Reload the edit page and try again.', 'mangazscans' ) ), 403 );
		}

		$post_id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You cannot edit this manga.', 'mangazscans' ) ), 403 );
		}

		$storage = isset( $_POST['storage'] ) ? sanitize_key( wp_unslash( $_POST['storage'] ) ) : '';
		$album   = isset( $_POST['album'] ) ? wp_unslash( $_POST['album'] ) : '';
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$extend  = isset( $_POST['nameExtend'] ) ? sanitize_text_field( wp_unslash( $_POST['nameExtend'] ) ) : '';
		$volume  = isset( $_POST['volume'] ) ? absint( $_POST['volume'] ) : 0;

		if ( $name === '' ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Chapter name is required.', 'mangazscans' ) ), 400 );
		}

		$class_name = 'wp_manga_storage_' . $storage;
		if ( ! in_array( $storage, array( 'imgchest', 'direct' ), true ) || ! class_exists( $class_name ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unknown storage backend.', 'mangazscans' ) ), 400 );
		}

		$uploader = call_user_func( array( $class_name, 'get_instance' ) );
		$urls     = $uploader->get_album_images( $album );

		if ( is_wp_error( $urls ) ) {
			wp_send_json_error( array( 'message' => $urls->get_error_message() ), 400 );
		}
		if ( ! is_array( $urls ) || empty( $urls ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No images found for that source.', 'mangazscans' ) ), 400 );
		}

		global $wp_manga_storage, $wp_manga_functions, $wp_manga_chapter, $wp_manga_chapter_data;

		if ( empty( $wp_manga_storage ) || empty( $wp_manga_chapter ) || empty( $wp_manga_chapter_data ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Madara-Core is not initialized. Make sure the plugin is active.', 'mangazscans' ) ), 500 );
		}

		// Duplicate-name guard (same behaviour as madara-core, just explicit).
		if ( ! isset( $_POST['overwrite'] ) ) {
			$existing = $wp_manga_functions->check_unique_chapter( $name, $volume, $post_id );
			if ( $existing && isset( $existing['output'] ) ) {
				wp_send_json_error( array(
					'error'   => 'chapter_existed',
					'message' => esc_html__( 'A chapter with this name already exists. Rename or enable overwrite.', 'mangazscans' ),
					'output'  => $existing['output'],
				), 409 );
			}
		}

		$slug = $wp_manga_storage->slugify( $name );
		if ( $slug === '' ) {
			$slug = 'chapter-' . time();
		}

		$chapter_args = array(
			'post_id'             => $post_id,
			'volume_id'           => $volume,
			'chapter_name'        => $name,
			'chapter_name_extend' => $extend,
			'chapter_slug'        => $slug,
			'storage_in_use'      => $storage,
		);

		$chapter_id = $wp_manga_chapter->insert_chapter( $chapter_args );

		if ( empty( $chapter_id ) || ! is_numeric( $chapter_id ) ) {
			global $wpdb;
			$last_error = ( isset( $wpdb->last_error ) && $wpdb->last_error ) ? $wpdb->last_error : 'unknown';
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: database error message */
					esc_html__( 'Chapter row insert failed (DB said: %s). Check that wp_manga_chapters table exists.', 'mangazscans' ),
					esc_html( $last_error )
				),
			), 500 );
		}
		$chapter_id = (int) $chapter_id;

		// Build chapter data JSON the same shape Madara-Core expects:
		//   { "1": { "src": "url", "mime": "image/png" }, "2": {...} }
		$pages = array();
		$page  = 1;
		foreach ( $urls as $url ) {
			$pages[ $page ] = array(
				'src'  => $url,
				'mime' => $wp_manga_storage->mime_content_type( $url ),
			);
			$page++;
		}
		$data_json = wp_json_encode( apply_filters( 'madara_chapter_data', $pages, $post_id, $chapter_id, $storage ) );

		$data_id = $wp_manga_chapter_data->insert( array(
			'chapter_id' => $chapter_id,
			'storage'    => $storage,
			'data'       => $data_json,
		) );

		if ( empty( $data_id ) || ! is_numeric( $data_id ) ) {
			// Roll back the chapter row so the user doesn't see an empty
			// phantom chapter in the list next time.
			if ( method_exists( $wp_manga_chapter, 'delete_chapter' ) ) {
				$wp_manga_chapter->delete_chapter( array( 'chapter_id' => $chapter_id ) );
			}
			global $wpdb;
			$last_error = ( isset( $wpdb->last_error ) && $wpdb->last_error ) ? $wpdb->last_error : 'unknown';
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: database error message */
					esc_html__( 'Chapter data insert failed (DB said: %s). Chapter row rolled back.', 'mangazscans' ),
					esc_html( $last_error )
				),
			), 500 );
		}

		// NB: $wp_manga_chapter->insert_chapter() already fires
		// 'manga_chapter_inserted' internally; don't fire it again here
		// or subscribers like update_manga_latest_meta run twice.

		wp_send_json_success( array(
			'chapter_id' => $chapter_id,
			'pages'      => count( $urls ),
			'storage'    => $storage,
			'message'    => sprintf(
				/* translators: %d: number of images imported */
				esc_html__( 'Imported %d image(s).', 'mangazscans' ),
				count( $urls )
			),
		) );
	}
