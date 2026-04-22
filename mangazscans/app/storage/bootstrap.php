<?php
	/**
	 * Replacement chapter-upload UI.
	 *
	 * Earlier iterations tried to hook into madara-core's existing
	 * "Upload Single Chapter" tab (its #import-album button path). That
	 * path is fundamentally broken: its AJAX handler calls
	 * wp_send_json_success() unconditionally even when the DB insert
	 * fails, so users saw "Created Complete!" while no chapter row was
	 * created. And its click handler is bound directly on the button
	 * at document-ready, so jQuery fires it BEFORE any delegated
	 * handler we could add on top — we couldn't intercept it cleanly.
	 *
	 * Approach here: ignore that path entirely. Hide the two broken
	 * madara-core upload tabs with CSS, inject our own "Add Chapter"
	 * tab + panel, and route everything through our own AJAX handler
	 * (mangazscans_create_chapter — see ajax.php) which actually
	 * validates DB writes.
	 *
	 * @package mangazscans
	 */

	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	// Storage backend classes — lazy-loaded by ajax.php per source type.
	// We still require direct.php + imgchest.php at boot so their classes
	// are available for anything else that introspects storages; zip.php
	// and page.php are only needed during a chapter create request.
	require_once __DIR__ . '/imgchest.php';
	require_once __DIR__ . '/direct.php';
	require_once __DIR__ . '/ajax.php';

	// The tab + form live on the wp-manga post edit screen only.
	add_action( 'admin_footer-post.php',     'mangazscans_add_chapter_ui' );
	add_action( 'admin_footer-post-new.php', 'mangazscans_add_chapter_ui' );

	function mangazscans_add_chapter_ui() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->post_type !== 'wp-manga' ) {
			return;
		}

		global $wp_manga_functions, $post;
		if ( empty( $post ) ) {
			return;
		}
		$volume_dropdown = '';
		if ( is_object( $wp_manga_functions ) && method_exists( $wp_manga_functions, 'volume_dropdown' ) ) {
			$volume_dropdown = $wp_manga_functions->volume_dropdown( $post->ID, false );
		}
		$nonce = wp_create_nonce( 'wp-manga-admin' );
		?>
		<style id="mangazscans-add-chapter-style">
			/* Hide madara-core's broken upload tabs. Our replacement panel
			   handles every source type with a working backend. */
			.wp-manga-tabs li.manga-tab-select { display: none !important; }

			/* Our panel */
			#mz-add-chapter { padding: 16px 4px; }
			#mz-add-chapter h2 { margin: 0 0 6px; font-size: 16px; }
			#mz-add-chapter .mz-row { margin-bottom: 16px; }
			#mz-add-chapter label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px; }
			#mz-add-chapter input[type="text"],
			#mz-add-chapter input[type="url"],
			#mz-add-chapter input[type="number"],
			#mz-add-chapter textarea,
			#mz-add-chapter select {
				width: 100%; max-width: 640px; box-sizing: border-box;
			}
			#mz-add-chapter textarea { font-family: monospace; min-height: 140px; }
			#mz-add-chapter .mz-inline { display: flex; gap: 12px; flex-wrap: wrap; }
			#mz-add-chapter .mz-inline .mz-row { flex: 1 1 200px; margin-bottom: 0; }
			#mz-add-chapter .mz-source-panel { display: none; padding: 12px; background: #fafafa; border: 1px solid #e6e6ec; border-radius: 6px; }
			#mz-add-chapter .mz-source-panel.is-active { display: block; }
			#mz-add-chapter .mz-source-panel p.description { margin: 6px 0 0; color: #6a6a78; }
			#mz-add-chapter .mz-submit {
				background: #ff6b35; border: 1px solid #ff6b35; color: #fff;
				padding: 8px 18px; border-radius: 6px; font-weight: 600;
				cursor: pointer; font-size: 14px;
			}
			#mz-add-chapter .mz-submit:hover:not(:disabled) { background: #e95822; border-color: #e95822; }
			#mz-add-chapter .mz-submit:disabled { opacity: 0.55; cursor: wait; }
			#mz-add-chapter .mz-msg { margin-top: 12px; padding: 8px 12px; border-radius: 4px; display: none; }
			#mz-add-chapter .mz-msg.is-ok    { display: block; background: #e7f4ea; color: #1f5c2f; border: 1px solid #9fd1ab; }
			#mz-add-chapter .mz-msg.is-err   { display: block; background: #fdecea; color: #801f1a; border: 1px solid #e2a9a6; }
			#mz-add-chapter .mz-msg.is-busy  { display: block; background: #eef2f7; color: #2a3d52; border: 1px solid #bdcbd9; }
		</style>

		<!-- Tab link + panel are injected into the WP Manga metabox via JS below. -->
		<template id="mangazscans-tab-link">
			<li class="mz-tab-select"><a href="#mz-add-chapter"><?php esc_html_e( 'Add Chapter', 'mangazscans' ); ?></a></li>
		</template>

		<template id="mangazscans-tab-panel">
			<div id="mz-add-chapter" class="tab-content" style="display:none;">
				<h2><?php esc_html_e( 'Add Chapter', 'mangazscans' ); ?></h2>

				<div class="mz-inline">
					<div class="mz-row">
						<label for="mz-chapter-name"><?php esc_html_e( 'Chapter name', 'mangazscans' ); ?> *</label>
						<input type="text" id="mz-chapter-name" placeholder="Chapter 1" />
					</div>
					<div class="mz-row">
						<label for="mz-chapter-extend"><?php esc_html_e( 'Name extend (optional)', 'mangazscans' ); ?></label>
						<input type="text" id="mz-chapter-extend" placeholder="The Beginning" />
					</div>
					<div class="mz-row" style="flex:0 1 180px;">
						<label for="mz-chapter-volume"><?php esc_html_e( 'Volume', 'mangazscans' ); ?></label>
						<?php
						if ( $volume_dropdown ) {
							// madara-core renders the dropdown with
							// id="wp-manga-volume"; we want a distinct id.
							echo str_replace( 'id="wp-manga-volume"', 'id="mz-chapter-volume"', $volume_dropdown ); // phpcs:ignore
						} else {
							echo '<select id="mz-chapter-volume"><option value="0">' . esc_html__( 'None', 'mangazscans' ) . '</option></select>';
						}
						?>
					</div>
				</div>

				<div class="mz-row">
					<label for="mz-source"><?php esc_html_e( 'Image source', 'mangazscans' ); ?></label>
					<select id="mz-source">
						<option value="direct"><?php esc_html_e( 'Direct image URLs', 'mangazscans' ); ?></option>
						<option value="imgchest"><?php esc_html_e( 'ImgChest post', 'mangazscans' ); ?></option>
						<option value="page"><?php esc_html_e( 'Fetch from a page URL', 'mangazscans' ); ?></option>
						<option value="zip"><?php esc_html_e( 'Upload .zip file', 'mangazscans' ); ?></option>
					</select>
				</div>

				<div class="mz-source-panel is-active" data-source="direct">
					<label for="mz-direct"><?php esc_html_e( 'Image URLs (one per line)', 'mangazscans' ); ?></label>
					<textarea id="mz-direct" placeholder="https://cdn.example.com/ch1/01.jpg&#10;https://cdn.example.com/ch1/02.jpg"></textarea>
					<p class="description"><?php esc_html_e( 'One full https URL per line. Images appear in the order listed.', 'mangazscans' ); ?></p>
				</div>

				<div class="mz-source-panel" data-source="imgchest">
					<label for="mz-imgchest"><?php esc_html_e( 'ImgChest post URL or id', 'mangazscans' ); ?></label>
					<input type="url" id="mz-imgchest" placeholder="https://imgchest.com/p/XXXXXX" />
					<p class="description"><?php esc_html_e( 'Public posts need no credentials; all images in the post are imported in order.', 'mangazscans' ); ?></p>
				</div>

				<div class="mz-source-panel" data-source="page">
					<label for="mz-page"><?php esc_html_e( 'Page URL to scrape', 'mangazscans' ); ?></label>
					<input type="url" id="mz-page" placeholder="https://example.com/manga/one-piece/chapter-1000" />
					<p class="description"><?php esc_html_e( 'Best-effort scrape of <img> tags (handles lazy-loaded data-src / srcset). Cloudflare-protected or heavily-JS pages may return nothing.', 'mangazscans' ); ?></p>
				</div>

				<div class="mz-source-panel" data-source="zip">
					<label for="mz-zip"><?php esc_html_e( 'Zip file of images', 'mangazscans' ); ?></label>
					<input type="file" id="mz-zip" accept=".zip,application/zip,application/x-zip-compressed" />
					<p class="description"><?php esc_html_e( 'Extracted into wp-content/uploads/mangazscans/{id}/{slug}/ and sorted naturally by file name.', 'mangazscans' ); ?></p>
				</div>

				<div class="mz-row" style="margin-top:20px;">
					<button type="button" id="mz-create-chapter" class="mz-submit"><?php esc_html_e( 'Create Chapter', 'mangazscans' ); ?></button>
				</div>

				<div id="mz-msg" class="mz-msg" role="status" aria-live="polite"></div>
			</div>
		</template>

		<script>
		( function ( $ ) {
			$( function () {
				var postID = $( 'input#post_ID, input[name="post_ID"], input[name="postID"]' ).first().val();
				if ( ! postID ) return;

				var $tabs = $( '.wp-manga-tabs ul' );
				var $content = $( '.wp-manga-content' );
				if ( ! $tabs.length || ! $content.length ) return;

				// Inject tab link (idempotent)
				if ( ! $tabs.find( 'a[href="#mz-add-chapter"]' ).length ) {
					var tl = document.getElementById( 'mangazscans-tab-link' );
					if ( tl && tl.content ) {
						$tabs.append( tl.content.cloneNode( true ) );
					}
				}

				// Inject tab panel (idempotent)
				if ( ! $content.find( '#mz-add-chapter' ).length ) {
					var tp = document.getElementById( 'mangazscans-tab-panel' );
					if ( tp && tp.content ) {
						$content.find( '.wp-manga-popup-loading' ).before( tp.content.cloneNode( true ) );
					}
				}

				// Source selector — toggle which panel is active.
				$( document ).on( 'change', '#mz-source', function () {
					var v = $( this ).val();
					$( '#mz-add-chapter .mz-source-panel' ).each( function () {
						$( this ).toggleClass( 'is-active', $( this ).data( 'source' ) === v );
					} );
				} );

				// Submit handler — our own button, no conflict with
				// madara-core's #import-album or #upload-chapter.
				$( document ).on( 'click', '#mz-create-chapter', function ( e ) {
					e.preventDefault();
					var $btn = $( this );
					var source = $( '#mz-source' ).val();
					var name   = $.trim( $( '#mz-chapter-name' ).val() );
					var extend = $.trim( $( '#mz-chapter-extend' ).val() );
					var volume = $( '#mz-chapter-volume' ).val() || 0;

					if ( ! name ) { return setMsg( 'Chapter name is required.', 'err' ); }

					var fd = new FormData();
					fd.append( 'action', 'mangazscans_create_chapter' );
					fd.append( 'nonce', '<?php echo esc_js( $nonce ); ?>' );
					fd.append( 'post', postID );
					fd.append( 'name', name );
					fd.append( 'nameExtend', extend );
					fd.append( 'volume', volume );
					fd.append( 'source', source );

					if ( source === 'zip' ) {
						var file = $( '#mz-zip' )[ 0 ].files[ 0 ];
						if ( ! file ) { return setMsg( 'Pick a .zip file first.', 'err' ); }
						fd.append( 'file', file );
					} else if ( source === 'direct' ) {
						var txt = $( '#mz-direct' ).val();
						if ( ! $.trim( txt ) ) { return setMsg( 'Paste at least one image URL.', 'err' ); }
						fd.append( 'album', txt );
					} else if ( source === 'imgchest' ) {
						var ic = $.trim( $( '#mz-imgchest' ).val() );
						if ( ! ic ) { return setMsg( 'Paste an ImgChest post URL.', 'err' ); }
						fd.append( 'album', ic );
					} else if ( source === 'page' ) {
						var pg = $.trim( $( '#mz-page' ).val() );
						if ( ! pg ) { return setMsg( 'Paste the page URL to scrape.', 'err' ); }
						fd.append( 'album', pg );
					}

					$btn.prop( 'disabled', true );
					setMsg( 'Importing…', 'busy' );

					var ajaxUrl = ( typeof wpManga !== 'undefined' && wpManga.ajax_url ) ? wpManga.ajax_url
						: ( typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>' );

					$.ajax( {
						url: ajaxUrl,
						type: 'POST',
						data: fd,
						processData: false,
						contentType: false,
						dataType: 'json'
					} ).done( function ( resp ) {
						$btn.prop( 'disabled', false );
						if ( resp && resp.success ) {
							var msg = ( resp.data && resp.data.message ) ? resp.data.message : 'Chapter created.';
							setMsg( msg, 'ok' );
							// Clear inputs so the next chapter is a fresh form.
							$( '#mz-chapter-name, #mz-chapter-extend, #mz-direct, #mz-imgchest, #mz-page' ).val( '' );
							if ( $( '#mz-zip' ).length ) $( '#mz-zip' )[ 0 ].value = '';
							// Refresh madara-core's chapter list.
							if ( typeof updateChaptersList === 'function' ) {
								updateChaptersList();
							}
						} else {
							var m = ( resp && resp.data && resp.data.message ) ? resp.data.message : 'Create failed.';
							setMsg( m, 'err' );
						}
					} ).fail( function ( xhr ) {
						$btn.prop( 'disabled', false );
						var body = null;
						try { body = JSON.parse( xhr.responseText ); } catch ( _ ) { /* ignore */ }
						var m = ( body && body.data && body.data.message )
							? body.data.message
							: ( 'Request failed (HTTP ' + xhr.status + ').' );
						setMsg( m, 'err' );
					} );
				} );

				function setMsg( text, kind ) {
					$( '#mz-msg' )
						.removeClass( 'is-ok is-err is-busy' )
						.addClass( 'is-' + kind )
						.text( text );
				}
			} );
		} )( jQuery );
		</script>
		<?php
	}
