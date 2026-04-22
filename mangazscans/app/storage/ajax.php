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

		// 2. Insert chapter row. We go straight to $wpdb instead of
		//    routing through $wp_manga_chapter->insert_chapter() so we
		//    know exactly which step (table existence, schema mismatch,
		//    INSERT failure, insert_id=0) caused a failure. The madara
		//    wrapper has internal early-returns that swallow context.
		global $wpdb;

		$chapters_table      = $wpdb->prefix . 'manga_chapters';
		$chapters_data_table = $wpdb->prefix . 'manga_chapters_data';

		// Sanity-check the tables actually exist. SHOW TABLES LIKE is
		// cheap and gives us a precise error message if they don't.
		$have_chapters      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $chapters_table ) );
		$have_chapters_data = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $chapters_data_table ) );
		if ( $have_chapters !== $chapters_table ) {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: table name */
					esc_html__( 'Table %s does not exist. Re-activate the Madara-Core plugin to run its schema migration.', 'mangazscans' ),
					esc_html( $chapters_table )
				),
			), 500 );
		}
		if ( $have_chapters_data !== $chapters_data_table ) {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: table name */
					esc_html__( 'Table %s does not exist. Re-activate the Madara-Core plugin to run its schema migration.', 'mangazscans' ),
					esc_html( $chapters_data_table )
				),
			), 500 );
		}

		// If a chapter with this slug already exists, disambiguate.
		$existing_slug = $wpdb->get_var( $wpdb->prepare(
			"SELECT chapter_id FROM {$chapters_table} WHERE post_id = %d AND chapter_slug = %s LIMIT 1",
			$post_id, $slug
		) );
		if ( $existing_slug ) {
			$slug = $slug . '-' . time();
		}

		$chapter_row = array(
			'post_id'             => $post_id,
			'volume_id'           => $volume,
			'chapter_name'        => $name,
			'chapter_name_extend' => $extend,
			'chapter_slug'        => $slug,
			'storage_in_use'      => $storage_slug,
			'date'                => current_time( 'mysql' ),
			'date_gmt'            => current_time( 'mysql', true ),
		);
		$chapter_row = apply_filters( 'wp_manga_chapter_insert_args', $chapter_row );

		$wpdb->insert_id = 0;                // reset before insert so 0 really means "this insert failed"
		$wpdb->last_error = '';
		$insert_result    = $wpdb->insert( $chapters_table, $chapter_row );
		$chapter_id       = (int) $wpdb->insert_id;
		$insert_error     = $wpdb->last_error;

		if ( $insert_result === false || $chapter_id < 1 ) {
			$hint = $insert_error !== '' ? $insert_error
				: ( $insert_result === false
					? 'INSERT returned false with no error — check mysql error log.'
					: 'INSERT succeeded but insert_id came back 0 — table may be missing AUTO_INCREMENT on chapter_id.' );

			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: diagnostic text */
					esc_html__( 'Chapter row insert failed: %s', 'mangazscans' ),
					esc_html( $hint )
				),
				'debug' => array(
					'table'          => $chapters_table,
					'insert_result'  => $insert_result,
					'insert_id'      => $wpdb->insert_id,
					'wpdb_error'     => $insert_error,
					'row_keys'       => array_keys( $chapter_row ),
				),
			), 500 );
		}

		// Fire the same hook insert_chapter() would have, so anything
		// that listens for new chapters still gets notified.
		do_action( 'manga_chapter_inserted', $chapter_id, $chapter_row );


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

		// 4. Insert chapter data row directly, same pattern as above.
		$data_row = array(
			'chapter_id' => $chapter_id,
			'storage'    => $storage_slug,
			'data'       => $data_json,
		);
		$wpdb->insert_id  = 0;
		$wpdb->last_error = '';
		$data_result      = $wpdb->insert( $chapters_data_table, $data_row );
		$data_id          = (int) $wpdb->insert_id;
		$data_error       = $wpdb->last_error;

		if ( $data_result === false || $data_id < 1 ) {
			// Roll back chapter row so we don't leave a phantom empty chapter.
			$wpdb->delete( $chapters_table, array( 'chapter_id' => $chapter_id ), array( '%d' ) );

			$hint = $data_error !== '' ? $data_error
				: ( $data_result === false
					? 'INSERT returned false with no error — check mysql error log.'
					: 'INSERT succeeded but insert_id came back 0.' );

			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: diagnostic text */
					esc_html__( 'Chapter data insert failed: %s (chapter row rolled back).', 'mangazscans' ),
					esc_html( $hint )
				),
			), 500 );
		}

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
