<?php
	/**
	 * Register theme-level chapter storage backends (ImgChest, Direct URLs)
	 * and wire up the admin UI + AJAX path.
	 *
	 * Architecture:
	 *   imgchest.php / direct.php  — wp_manga_storage_* classes that
	 *       produce an ordered array of image URLs from whatever the user
	 *       pasted in the admin form.
	 *   ajax.php                  — our own wp_ajax_mangazscans_import_chapter
	 *       handler. It calls insert_chapter + insert chapter_data directly
	 *       and returns a precise error message if either DB write fails,
	 *       instead of the silent-success behaviour the stock madara-core
	 *       handler has. Also rolls the chapter row back on partial failure.
	 *   admin UI                  — we inject our #imgchest-albums /
	 *       #direct-albums fields via the 'manga_chapter_upload_url_form_fields'
	 *       action (fires inside .select-album, BEFORE the "Create Chapter"
	 *       button, exactly where madara-core expects extra backends), and
	 *       we hijack clicks on #import-album when our storage is selected
	 *       so the request routes to our own AJAX endpoint.
	 *
	 * @package mangazscans
	 */

	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	require_once __DIR__ . '/imgchest.php';
	require_once __DIR__ . '/direct.php';
	require_once __DIR__ . '/ajax.php';

	// 1. Register backends in the "Choose where to import" dropdown.
	add_filter( 'wp_manga_available_storages', function ( $storages ) {
		if ( ! is_array( $storages ) ) {
			$storages = array();
		}
		$storages['imgchest'] = array(
			'value' => 'imgchest',
			'text'  => __( 'ImgChest', 'mangazscans' ),
		);
		$storages['direct'] = array(
			'value' => 'direct',
			'text'  => __( 'Direct URLs', 'mangazscans' ),
		);
		return $storages;
	} );

	// 2. Render the input fields at the RIGHT spot. This action fires
	//    inside .select-album, just before the Create Chapter button.
	add_action( 'manga_chapter_upload_url_form_fields', 'mangazscans_storage_render_fields' );

	function mangazscans_storage_render_fields( $manga_post ) {
		?>
		<div class="imgchest-import" style="display:none;">
			<div class="wp-manga-form-group">
				<h2>
					<label for="imgchest-albums">
						<?php esc_html_e( 'ImgChest post URL or id', 'mangazscans' ); ?>
					</label>
				</h2>
				<input type="url" id="imgchest-albums" name="imgchest-albums"
					placeholder="https://imgchest.com/p/XXXXXX"
					class="regular-text"
					style="width:100%;max-width:640px;" />
				<p class="description">
					<?php esc_html_e( 'Paste the full ImgChest post URL. Public posts need no API token; all images are imported in order.', 'mangazscans' ); ?>
				</p>
			</div>
		</div>

		<div class="direct-import" style="display:none;">
			<div class="wp-manga-form-group">
				<h2>
					<label for="direct-albums">
						<?php esc_html_e( 'Direct image URLs (one per line)', 'mangazscans' ); ?>
					</label>
				</h2>
				<textarea id="direct-albums" name="direct-albums" rows="8"
					placeholder="https://cdn.example.com/ch1/01.jpg&#10;https://cdn.example.com/ch1/02.jpg"
					style="width:100%;max-width:640px;font-family:monospace;"></textarea>
				<p class="description">
					<?php esc_html_e( 'One full https URL per line. Images appear in the chapter in the order listed.', 'mangazscans' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	// 3. Hijack the Create Chapter click for our backends, routing the
	//    request to our own AJAX handler which has real error reporting.
	add_action( 'admin_footer-post.php',     'mangazscans_storage_admin_js' );
	add_action( 'admin_footer-post-new.php', 'mangazscans_storage_admin_js' );

	function mangazscans_storage_admin_js() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->post_type !== 'wp-manga' ) {
			return;
		}
		?>
		<script>
		( function ( $ ) {
			$( function () {
				// Teach madara-core's built-in client-side URL validator
				// about our two slugs so its regex.exec() doesn't block.
				if ( typeof window.cloudStorageURLRegex !== 'object' || window.cloudStorageURLRegex === null ) {
					window.cloudStorageURLRegex = {};
				}
				window.cloudStorageURLRegex.imgchest = '.+';
				window.cloudStorageURLRegex.direct   = '.+';

				// Intercept clicks on #import-album BEFORE madara-core's
				// upload.js handler runs. jQuery fires handlers in the
				// order they were bound; ours binds on document-ready
				// alongside theirs but we stop propagation to take over
				// completely for our storage slugs.
				$( document ).on( 'click', '#import-album', function ( e ) {
					var $select = $( '#wp-manga-cloud-storage' );
					if ( ! $select.length ) return;
					var storage = $select.val();
					if ( storage !== 'imgchest' && storage !== 'direct' ) {
						return; // let madara-core handle its own backends
					}

					e.preventDefault();
					e.stopImmediatePropagation();

					var album = $( '#' + storage + '-albums' ).val();
					if ( ! album || ! String( album ).replace( /\s+/g, '' ) ) {
						mzMsg( 'Paste a URL (or URLs, one per line for Direct URLs).', false );
						return;
					}

					var postID = $( 'input[name="post_ID"], input[name="postID"]' ).first().val();
					var name   = $( '#wp-manga-chapter-name' ).val();
					if ( ! name ) {
						mzMsg( 'Chapter name is required.', false );
						return;
					}

					var payload = {
						action:     'mangazscans_import_chapter',
						post:       postID,
						name:       name,
						nameExtend: $( '#wp-manga-chapter-name-extend' ).val() || '',
						volume:     $( '#chapter-upload #wp-manga-volume' ).val() || 0,
						storage:    storage,
						album:      album,
						nonce:      ( typeof wpManga !== 'undefined' ) ? wpManga.nonce : ''
					};

					mzMsg( 'Importing…', true, true );
					if ( typeof showLoading === 'function' ) showLoading();

					$.ajax( {
						url: ( typeof wpManga !== 'undefined' ) ? wpManga.ajax_url : ajaxurl,
						method: 'POST',
						dataType: 'json',
						data: payload
					} ).done( function ( resp ) {
						if ( typeof hideLoading === 'function' ) hideLoading();
						if ( resp && resp.success ) {
							mzMsg( ( resp.data && resp.data.message ) || 'Chapter imported.', true );
							if ( typeof updateChaptersList === 'function' ) updateChaptersList();
							if ( typeof clearFormFields === 'function' ) clearFormFields( '.chapter-input' );
							$( '#imgchest-albums, #direct-albums' ).val( '' );
						} else {
							var m = ( resp && resp.data && resp.data.message ) ? resp.data.message : 'Import failed.';
							mzMsg( m, false );
						}
					} ).fail( function ( xhr ) {
						if ( typeof hideLoading === 'function' ) hideLoading();
						var body;
						try { body = JSON.parse( xhr.responseText ); } catch ( _ ) { body = null; }
						var m = ( body && body.data && body.data.message )
							? body.data.message
							: 'Import failed. HTTP ' + xhr.status + '.';
						mzMsg( m, false );
					} );
				} );

				function mzMsg( text, ok, progress ) {
					if ( typeof mangaSingleMessage === 'function' ) {
						mangaSingleMessage( text, '#chapter-upload-msg', !! ok );
						return;
					}
					// Fallback: write into the msg container directly.
					var $msg = $( '#chapter-upload-msg' );
					if ( $msg.length ) {
						$msg.text( text );
						$msg.css( { background: ok ? '#6aa84f' : '#cc0000', color: '#fff', padding: '8px 12px' } );
					}
				}
			} );
		} )( jQuery );
		</script>
		<?php
	}
