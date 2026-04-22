<?php
	/**
	 * ImgChest storage backend for Madara-Core.
	 *
	 * Makes "ImgChest" appear in the chapter-upload "Choose where to import"
	 * dropdown. The user pastes an ImgChest post URL (or just the post id);
	 * we call the public ImgChest API to retrieve the ordered list of image
	 * URLs and hand them back to Madara-Core, which stores them as the
	 * chapter's pages. No local file upload, no credentials required for
	 * public posts.
	 *
	 * Madara-Core's inc/ajax/upload.php::import_album_chapter() routes any
	 * unknown storage slug through
	 *   class_exists( 'wp_manga_storage_' . $slug )
	 *   $class::get_instance()->get_album_images( $album )
	 * so this class is auto-discovered without plugin modifications.
	 *
	 * @package mangazscans
	 */

	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	if ( ! class_exists( 'wp_manga_storage_imgchest' ) ) {

		class wp_manga_storage_imgchest {

			const API_BASE = 'https://api.imgchest.com/v1/post/';
			const URL_RE   = '#imgchest\.com/p/([a-zA-Z0-9]+)#i';

			private static $instance = null;

			public static function get_instance() {
				if ( self::$instance === null ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			/**
			 * Extract the ImgChest post id from whatever the user pasted.
			 * Accepts either a full URL (https://imgchest.com/p/abc123) or
			 * just the id (abc123).
			 */
			private function parse_post_id( $input ) {
				$input = is_string( $input ) ? trim( $input ) : '';
				if ( $input === '' ) {
					return '';
				}
				if ( preg_match( self::URL_RE, $input, $m ) ) {
					return $m[1];
				}
				if ( preg_match( '#^[a-zA-Z0-9]+$#', $input ) ) {
					return $input;
				}
				return '';
			}

			/**
			 * Called by Madara-Core with whatever value is in the chapter
			 * upload form's #imgchest-albums input. Must return either an
			 * indexed array of image URLs (ordered) or a WP_Error.
			 */
			public function get_album_images( $album ) {
				$post_id = $this->parse_post_id( $album );
				if ( $post_id === '' ) {
					return new WP_Error(
						'imgchest_invalid_url',
						esc_html__( 'Enter a valid ImgChest post URL (https://imgchest.com/p/XXXXX) or post id.', 'mangazscans' )
					);
				}

				// Token resolution order, first match wins:
				//   1. Theme Options > Misc > ImgChest API token
				//      (\App\Madara::getOption — what the admin UI writes)
				//   2. wp_manga[imgchest_api_token] option
				//      (legacy slot, kept so an existing site doesn't break)
				$api_token = '';
				if ( class_exists( '\\App\\Madara' ) ) {
					$api_token = (string) \App\Madara::getOption( 'mangazscans_imgchest_token', '' );
				}
				if ( $api_token === '' ) {
					$opts      = get_option( 'wp_manga', array() );
					$api_token = isset( $opts['imgchest_api_token'] ) ? (string) $opts['imgchest_api_token'] : '';
				}
				$api_token = trim( $api_token );

				$args = array(
					'timeout' => 20,
					'headers' => array(
						'Accept' => 'application/json',
					),
				);
				if ( $api_token !== '' ) {
					$args['headers']['Authorization'] = 'Bearer ' . $api_token;
				}

				$response = wp_remote_get( self::API_BASE . rawurlencode( $post_id ), $args );

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				$code = wp_remote_retrieve_response_code( $response );
				if ( $code === 401 || $code === 403 ) {
					return new WP_Error(
						'imgchest_auth',
						esc_html__( 'ImgChest rejected the request. If the post is private, add an API token under WP Manga Settings.', 'mangazscans' )
					);
				}
				if ( $code === 404 ) {
					return new WP_Error(
						'imgchest_not_found',
						esc_html__( 'ImgChest post not found. Check the URL or post id.', 'mangazscans' )
					);
				}
				if ( $code < 200 || $code >= 300 ) {
					return new WP_Error(
						'imgchest_http_' . $code,
						sprintf(
							/* translators: %d is the HTTP status code from api.imgchest.com */
							esc_html__( 'ImgChest API returned HTTP %d.', 'mangazscans' ),
							(int) $code
						)
					);
				}

				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! is_array( $body ) || empty( $body['data']['images'] ) || ! is_array( $body['data']['images'] ) ) {
					return new WP_Error(
						'imgchest_empty',
						esc_html__( 'ImgChest post has no images.', 'mangazscans' )
					);
				}

				// Preserve order by the API's "position" field when present.
				$images = $body['data']['images'];
				usort( $images, function( $a, $b ) {
					$pa = isset( $a['position'] ) ? (int) $a['position'] : 0;
					$pb = isset( $b['position'] ) ? (int) $b['position'] : 0;
					return $pa <=> $pb;
				} );

				$urls = array();
				foreach ( $images as $img ) {
					if ( ! empty( $img['link'] ) && filter_var( $img['link'], FILTER_VALIDATE_URL ) ) {
						$urls[] = esc_url_raw( $img['link'] );
					}
				}

				if ( empty( $urls ) ) {
					return new WP_Error(
						'imgchest_no_links',
						esc_html__( 'ImgChest response contained no usable image links.', 'mangazscans' )
					);
				}

				return $urls;
			}
		}

	}
