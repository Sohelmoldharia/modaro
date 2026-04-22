<?php
	/**
	 * Page-URL scraper storage backend.
	 *
	 * Fetches a given page URL, parses the HTML, and returns the list of
	 * <img> URLs it found, in DOM order. Best-effort: many scanlation
	 * and CDN sites block cross-origin fetches, lazy-load images from
	 * data-src / data-lazy-src attributes, or serve images behind
	 * Cloudflare/JS gates. We handle the common cases (data-src,
	 * data-lazy-src, srcset) but some sites just won't work; that's why
	 * the handler reports the count so the user can sanity-check it.
	 *
	 * Auto-discovered by the main AJAX handler when source=page.
	 *
	 * @package mangazscans
	 */

	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	if ( ! class_exists( 'wp_manga_storage_page' ) ) {

		class wp_manga_storage_page {

			const TIMEOUT = 25;

			private static $instance = null;

			public static function get_instance() {
				if ( self::$instance === null ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			public function get_album_images( $album ) {
				$page_url = is_string( $album ) ? trim( $album ) : '';
				if ( ! filter_var( $page_url, FILTER_VALIDATE_URL ) ) {
					return new WP_Error(
						'page_invalid_url',
						esc_html__( 'Enter a full page URL (https://…).', 'mangazscans' )
					);
				}

				$response = wp_remote_get( $page_url, array(
					'timeout'   => self::TIMEOUT,
					'headers'   => array(
						// Pretend to be a browser; most sites serve the
						// real HTML only to something that looks like one.
						'User-Agent' => 'Mozilla/5.0 (compatible; MangazScans/2.0; +wordpress)',
						'Accept'     => 'text/html,application/xhtml+xml',
					),
					'sslverify' => true,
				) );

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				$code = wp_remote_retrieve_response_code( $response );
				if ( $code < 200 || $code >= 300 ) {
					return new WP_Error(
						'page_http_' . $code,
						sprintf(
							/* translators: %d: HTTP status code */
							esc_html__( 'Page returned HTTP %d.', 'mangazscans' ),
							(int) $code
						)
					);
				}

				$html = wp_remote_retrieve_body( $response );
				if ( trim( $html ) === '' ) {
					return new WP_Error( 'page_empty', esc_html__( 'Empty response body.', 'mangazscans' ) );
				}

				$urls = $this->extract_image_urls( $html, $page_url );
				if ( empty( $urls ) ) {
					return new WP_Error(
						'page_no_images',
						esc_html__( 'No images found on that page. The site may be lazy-loading via JS or blocking scraping.', 'mangazscans' )
					);
				}

				return $urls;
			}

			/**
			 * Pull <img> URLs out of the HTML. Checks src, data-src,
			 * data-lazy-src, data-original, data-lazy, and srcset (largest
			 * candidate). Resolves relative URLs against the page URL,
			 * de-dupes, and drops obvious non-chapter images (base64 data
			 * URIs, 1-pixel spacers, icons under /wp-admin/, etc.).
			 */
			private function extract_image_urls( $html, $base_url ) {
				$urls = array();

				$internal_errors = libxml_use_internal_errors( true );
				$doc = new DOMDocument();
				// Force UTF-8 interpretation; DOMDocument otherwise assumes
				// ISO-8859-1 and mangles multibyte characters in URLs.
				$loaded = $doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
				libxml_clear_errors();
				libxml_use_internal_errors( $internal_errors );

				if ( ! $loaded ) {
					return array();
				}

				$imgs = $doc->getElementsByTagName( 'img' );
				foreach ( $imgs as $img ) {
					$candidate = '';
					foreach ( array( 'data-src', 'data-lazy-src', 'data-original', 'data-lazy', 'src' ) as $attr ) {
						$val = $img->getAttribute( $attr );
						if ( $val && strpos( $val, 'data:' ) !== 0 ) {
							$candidate = $val;
							break;
						}
					}

					// If still empty, pick the largest from srcset.
					if ( $candidate === '' && $img->getAttribute( 'srcset' ) ) {
						$candidate = $this->largest_from_srcset( $img->getAttribute( 'srcset' ) );
					}

					if ( $candidate === '' ) {
						continue;
					}

					$abs = $this->absolutize( $candidate, $base_url );
					if ( ! $abs ) {
						continue;
					}

					if ( $this->looks_like_junk( $abs ) ) {
						continue;
					}

					if ( ! in_array( $abs, $urls, true ) ) {
						$urls[] = $abs;
					}
				}

				return $urls;
			}

			private function largest_from_srcset( $srcset ) {
				$parts = preg_split( '/\s*,\s*/', $srcset );
				$best  = '';
				$best_w = -1;
				foreach ( $parts as $p ) {
					$bits = preg_split( '/\s+/', trim( $p ) );
					$u    = isset( $bits[0] ) ? $bits[0] : '';
					$w    = 0;
					if ( isset( $bits[1] ) && preg_match( '/(\d+)w/', $bits[1], $m ) ) {
						$w = (int) $m[1];
					}
					if ( $u && $w > $best_w ) {
						$best   = $u;
						$best_w = $w;
					}
				}
				return $best;
			}

			private function absolutize( $url, $base_url ) {
				$url = trim( $url );
				if ( $url === '' ) {
					return '';
				}
				if ( preg_match( '#^https?://#i', $url ) ) {
					return esc_url_raw( $url );
				}
				$base = wp_parse_url( $base_url );
				if ( ! $base || empty( $base['scheme'] ) || empty( $base['host'] ) ) {
					return '';
				}
				if ( strpos( $url, '//' ) === 0 ) {
					return esc_url_raw( $base['scheme'] . ':' . $url );
				}
				$root = $base['scheme'] . '://' . $base['host'];
				if ( strpos( $url, '/' ) === 0 ) {
					return esc_url_raw( $root . $url );
				}
				$path = isset( $base['path'] ) ? $base['path'] : '/';
				$dir  = substr( $path, 0, strrpos( $path, '/' ) + 1 );
				return esc_url_raw( $root . $dir . $url );
			}

			private function looks_like_junk( $url ) {
				$lower = strtolower( $url );
				// Skip WP admin icons, gravatar beacons, 1x1 spacers, etc.
				$bad_substrings = array(
					'/wp-admin/', '/wp-includes/', 'gravatar.com/avatar/',
					'spacer.gif', 'blank.gif', 'pixel.gif',
				);
				foreach ( $bad_substrings as $bad ) {
					if ( strpos( $lower, $bad ) !== false ) {
						return true;
					}
				}
				return false;
			}
		}

	}
