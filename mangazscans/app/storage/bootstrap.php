<?php
	/**
	 * Register the theme-level chapter storage backends (ImgChest, Direct
	 * URLs) with Madara-Core and wire up their admin UI bits so the
	 * "Select Uploaded Album from Cloud Server" flow actually works for
	 * the MangazScans site out of the box.
	 *
	 * Three pieces plug in:
	 *   1. Require the PHP classes so class_exists() finds them when the
	 *      Madara-Core ajax handler routes a custom storage slug.
	 *   2. Filter wp_manga_available_storages so the backends show up in
	 *      the chapter-upload dropdown.
	 *   3. Inject the input sections (#imgchest-albums, #direct-albums)
	 *      into the chapter-upload screen and extend the JS
	 *      cloudStorageURLRegex so client-side validation accepts them.
	 *
	 * @package mangazscans
	 */

	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	require_once __DIR__ . '/imgchest.php';
	require_once __DIR__ . '/direct.php';

	// 2. Register backends in the "Choose where to import" dropdown.
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

	// 3. Inject admin UI: HTML sections + JS regex map + JS change-handler
	//    that reads #imgchest-albums / #direct-albums. Scoped to the
	//    wp-manga edit screen only.
	add_action( 'admin_footer-post.php',     'mangazscans_storage_admin_ui' );
	add_action( 'admin_footer-post-new.php', 'mangazscans_storage_admin_ui' );

	function mangazscans_storage_admin_ui() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->post_type !== 'wp-manga' ) {
			return;
		}
		?>
		<template id="mangazscans-imgchest-section">
			<div class="imgchest-import wp-manga-form-group" style="display:none;">
				<h2>
					<label for="imgchest-albums">
						<?php esc_html_e( 'ImgChest post URL or id', 'mangazscans' ); ?>
					</label>
				</h2>
				<input type="url" id="imgchest-albums" name="imgchest-albums"
					placeholder="https://imgchest.com/p/XXXXXX"
					style="width:100%;max-width:640px;" />
				<p class="description">
					<?php esc_html_e( 'Paste the full ImgChest post URL. All images in the post will be imported in order. Public posts need no API token.', 'mangazscans' ); ?>
				</p>
			</div>
		</template>
		<template id="mangazscans-direct-section">
			<div class="direct-import wp-manga-form-group" style="display:none;">
				<h2>
					<label for="direct-albums">
						<?php esc_html_e( 'Direct image URLs (one per line)', 'mangazscans' ); ?>
					</label>
				</h2>
				<textarea id="direct-albums" name="direct-albums" rows="8"
					placeholder="https://cdn.example.com/ch1/01.jpg&#10;https://cdn.example.com/ch1/02.jpg"
					style="width:100%;max-width:640px;font-family:monospace;"></textarea>
				<p class="description">
					<?php esc_html_e( 'One full https URL per line. Images appear in the chapter in the order you list them.', 'mangazscans' ); ?>
				</p>
			</div>
		</template>
		<script>
		( function ( $ ) {
			$( function () {
				var $albumContainer = $( '.select-album' );
				if ( ! $albumContainer.length ) {
					return;
				}

				// Inject our UI sections into the .select-album wrapper if
				// they aren't already there (madara-core appends the
				// dropdown itself; we append the inputs after the
				// existing cloud-storage sections).
				[ 'imgchest', 'direct' ].forEach( function ( slug ) {
					if ( $albumContainer.find( '.' + slug + '-import' ).length ) return;
					var tpl = document.getElementById( 'mangazscans-' + slug + '-section' );
					if ( tpl && tpl.content ) {
						$albumContainer.append( tpl.content.cloneNode( true ) );
					}
				} );

				// Extend the client-side URL validation map that
				// madara-core checks before firing the import ajax.
				if ( typeof window.cloudStorageURLRegex !== 'object' || window.cloudStorageURLRegex === null ) {
					window.cloudStorageURLRegex = {};
				}
				// ImgChest: https://imgchest.com/p/XXXX  OR plain alphanumeric id
				window.cloudStorageURLRegex.imgchest = '^(https:\\/\\/imgchest\\.com\\/p\\/[A-Za-z0-9]+|[A-Za-z0-9]+)\\s*$';
				// Direct URLs: any line that looks URL-ish (multiline textarea)
				window.cloudStorageURLRegex.direct = '^[\\s\\S]*https?:\\/\\/\\S+';

				// When the user picks one of our backends, the upload.js
				// else-branch reads #<slug>-albums. That matches our
				// injected input IDs above, so nothing else is needed.
			} );
		} )( jQuery );
		</script>
		<?php
	}
