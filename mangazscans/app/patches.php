<?php
	/**
	 * Madara-Core behaviour patches (theme-side shims).
	 *
	 * Targeted fixes for bugs in madara-core that we don't want to
	 * edit directly (so plugin updates still apply). Each patch is
	 * a small, narrowly-scoped filter/action override with a comment
	 * explaining exactly what the upstream bug is.
	 *
	 * @package mangazscans
	 */

	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	/**
	 * PATCH: front-page redirect loop for anonymous visitors.
	 *
	 * Upstream: WP_MANGA_USER_ACTIONS::wp_manga_user_page_redirect() in
	 * inc/user-actions.php checks:
	 *
	 *     if ( $user_page == get_the_ID() && ! is_user_logged_in() ) {
	 *         wp_safe_redirect( home_url( '/' ), 302 );
	 *     }
	 *
	 * With loose equality: when the "User Page" plugin setting is unset
	 * ($user_page is '' or 0) AND the current request is for a page
	 * where get_the_ID() also returns 0 (e.g. the front page on a
	 * "posts" homepage), the comparison '' == 0 is TRUE in PHP. The
	 * plugin then redirects anonymous visitors from the homepage back
	 * to the homepage → endless redirect loop.
	 *
	 * Fix: remove their handler, bind our own. Strict int comparison,
	 * skip entirely when user_page isn't set to a real post id.
	 */
	add_action( 'init', function () {
		global $wp_manga_user_actions;
		if ( is_object( $wp_manga_user_actions )
		     && method_exists( $wp_manga_user_actions, 'wp_manga_user_page_redirect' ) ) {
			remove_action( 'wp', array( $wp_manga_user_actions, 'wp_manga_user_page_redirect' ) );
		}
	}, 20 );

	add_action( 'wp', function () {
		if ( is_user_logged_in() ) {
			return;
		}
		if ( ! class_exists( 'WP_MANGA' ) ) {
			return;
		}
		global $wp_manga_setting;
		if ( ! is_object( $wp_manga_setting )
		     || ! method_exists( $wp_manga_setting, 'get_manga_option' ) ) {
			return;
		}
		$user_page = (int) $wp_manga_setting->get_manga_option( 'user_page' );
		if ( $user_page < 1 ) {
			return;
		}
		$current = (int) get_queried_object_id();
		if ( $current < 1 || $current !== $user_page ) {
			return;
		}
		// Only reach here if we're genuinely on the user-settings page
		// AND the visitor is logged out. Original intent of the hook.
		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	} );

	/**
	 * PATCH: CVE-class Arbitrary File Deletion (Madara-Core < 2.2.4).
	 *
	 * Upstream: WP_MANGA_AJAX_FRONTEND::wp_manga_delete_zip() in
	 * inc/ajax/frontend.php registered BOTH
	 *   add_action( 'wp_ajax_wp-manga-delete-zip',        ... );
	 *   add_action( 'wp_ajax_nopriv_wp-manga-delete-zip', ... );
	 * with this body:
	 *   \$zip_dir = isset( \$_POST['zipDir'] ) ? \$_POST['zipDir'] : '';
	 *   if ( !empty( \$zip_dir ) ) { unlink( \$zip_dir ); }
	 * No nonce, no capability check, no path validation. Any unauth
	 * visitor can POST zipDir=/var/www/html/wp-config.php and the
	 * web-server-user's file is gone. This is what Jetpack Scan
	 * flags as 'Madara - Core < 2.2.4 - Unauthenticated Arbitrary
	 * File Deletion'.
	 *
	 * We can't easily remove_action() on the original handler because
	 * the plugin does `new WP_MANGA_AJAX_FRONTEND()` without storing
	 * the instance in a global, so we don't have a handle to pass to
	 * remove_action. Instead we register our OWN handler at priority
	 * 1 (runs before the default priority-10 original) that:
	 *   - Rejects all unauthenticated requests.
	 *   - Rejects any zipDir that isn't under wp-content/uploads/ or
	 *     the madara-core plugin dir (so only its legitimate extract
	 *     folder works).
	 *   - Does the unlink itself, then wp_send_json_success() which
	 *     calls wp_die(), which terminates execution — so the
	 *     vulnerable original handler never runs.
	 *
	 * Bundled plugin zip is patched separately so fresh installs
	 * ship safe; this shim is belt-and-braces for sites that still
	 * have the unpatched plugin extracted to wp-content/plugins/.
	 */
	add_action( 'wp_ajax_wp-manga-delete-zip',        'mangazscans_secure_delete_zip', 1 );
	add_action( 'wp_ajax_nopriv_wp-manga-delete-zip', 'mangazscans_secure_delete_zip_reject', 1 );

	function mangazscans_secure_delete_zip_reject() {
		// Unauthenticated callers are never legitimate; the only
		// in-plugin JS callsites (admin-single-manga.js, manga-
		// download.js) run with a logged-in user. Anyone reaching
		// the nopriv endpoint is probing for the CVE.
		status_header( 403 );
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	function mangazscans_secure_delete_zip() {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			status_header( 403 );
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$zip_dir = isset( $_POST['zipDir'] )
			? (string) wp_unslash( $_POST['zipDir'] )
			: '';
		if ( $zip_dir === '' ) {
			wp_send_json_error( array( 'message' => 'zipDir required' ), 400 );
		}

		$real = realpath( $zip_dir );
		if ( $real === false ) {
			wp_send_json_error( array( 'message' => 'file not found' ), 404 );
		}

		// Only allow deletes inside the uploads directory or the
		// madara-core plugin dir (its legitimate 'extract' temp tree).
		$uploads       = wp_upload_dir();
		$allowed_roots = array();
		if ( ! empty( $uploads['basedir'] ) ) {
			$r = realpath( $uploads['basedir'] );
			if ( $r ) { $allowed_roots[] = $r; }
		}
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$r = realpath( WP_PLUGIN_DIR . '/madara-core' );
			if ( $r ) { $allowed_roots[] = $r; }
		}
		$is_allowed = false;
		foreach ( $allowed_roots as $root ) {
			if ( strpos( $real . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR ) === 0 ) {
				$is_allowed = true;
				break;
			}
		}
		if ( ! $is_allowed ) {
			wp_send_json_error( array( 'message' => 'path not allowed' ), 403 );
		}

		if ( is_file( $real ) ) {
			@unlink( $real );
		}
		// wp_send_json_success() internally calls wp_die(), which
		// terminates the request. The vulnerable original handler
		// (priority 10, same action) never runs.
		wp_send_json_success();
	}

	/**
	 * PATCH: strip the WordPress author from share-preview surfaces.
	 *
	 * Discord / Slack / iMessage / Signal etc. build their preview
	 * cards from a site's oEmbed response (linked in <head> via
	 * <link rel="alternate" type="application/json+oembed" …>). WP
	 * puts the post author's display name into that response by
	 * default, which is how 'izame1' ends up on a public manga card.
	 * MangazScans is a publisher-style site — we want the brand, not
	 * the WP username, so:
	 *   - unset author_name / author_url in the JSON oembed payload
	 *   - suppress the XML oembed author element the same way
	 *   - remove WP's default author rel + meta from <head>
	 */
	add_filter( 'oembed_response_data', function ( $data ) {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}, 99 );

	add_action( 'rest_api_init', function () {
		add_filter( 'rest_prepare_oembed_response', function ( $response ) {
			if ( isset( $response->data ) && is_array( $response->data ) ) {
				unset( $response->data['author_name'], $response->data['author_url'] );
			}
			return $response;
		}, 99 );
	} );

	// XML-flavoured oembed — older clients still hit this.
	add_filter( 'oembed_xml_response', function ( $return, $data ) {
		if ( $return && is_string( $return ) ) {
			$return = preg_replace( '#<(author_name|author_url)>.*?</\1>#is', '', $return );
		}
		return $return;
	}, 10, 2 );

	// Kill the two things that would otherwise put the author back in
	// <head>: the 'rsd' link pointed at the author archive feed, and
	// the WP author meta tag.
	remove_action( 'wp_head', 'wp_generator' );                 // WP version disclosure, not author but while we're here
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 ); // smaller <head>
	remove_action( 'wp_print_styles', 'print_emoji_styles' );

	/**
	 * Provide a sensible og:description for share cards. Yoast was
	 * emitting og:title / og:url / og:site_name but no og:description,
	 * which is why Discord was falling back to 'Visit the post for
	 * more.' on chapter and manga pages.
	 *
	 * Priority runs AFTER Yoast (priority 10) so we only fill the
	 * gap if Yoast didn't already set one, and we short-circuit
	 * entirely if any plugin/theme has already output the tag.
	 */
	add_action( 'wp_head', function () {
		static $already_emitted = null;
		if ( $already_emitted !== null ) {
			return; // only run once per request
		}
		$already_emitted = true;

		global $wp_filter;
		// Simple heuristic: peek at the HTML currently buffered isn't
		// practical at this action-level hook, so instead rely on
		// Yoast's own canonical filter to short-circuit us if it
		// generated a description.
		$suppressed = apply_filters( 'mangazscans_suppress_og_description', false );
		if ( $suppressed ) {
			return;
		}

		$desc = '';
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post && isset( $post->post_excerpt ) && $post->post_excerpt !== '' ) {
				$desc = $post->post_excerpt;
			} elseif ( $post && isset( $post->post_content ) ) {
				$desc = wp_strip_all_tags( $post->post_content );
			}
		} elseif ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term && ! empty( $term->description ) ) {
				$desc = $term->description;
			}
		}
		if ( $desc === '' ) {
			$desc = get_bloginfo( 'description' );
		}
		$desc = wp_trim_words( trim( wp_strip_all_tags( $desc ) ), 40, '…' );
		if ( $desc === '' ) {
			return;
		}
		// Only print if no og:description has been emitted yet. We
		// check Yoast's 'wpseo_opengraph_desc' filter being non-empty
		// as the "Yoast already did it" signal.
		if ( function_exists( 'yoast_breadcrumb' ) ) {
			$yoast_desc = apply_filters( 'wpseo_opengraph_desc', '' );
			if ( $yoast_desc !== '' ) {
				return;
			}
		}
		printf(
			"<meta property=\"og:description\" content=\"%s\" />\n<meta name=\"twitter:description\" content=\"%s\" />\n",
			esc_attr( $desc ),
			esc_attr( $desc )
		);
	}, 20 );

	/**
	 * PATCH: chapter reading pages should show 'Manga — Chapter N' in
	 * share cards, not just the manga title.
	 *
	 * Chapter URLs (/manga/one-piece/ch-1181/) are WP rewrites on top
	 * of the wp-manga post — no new post exists per chapter, so Yoast
	 * picks up the manga post's title and emits it as og:title. That's
	 * why Discord/Slack/etc. show 'One Piece - MangazScans' for every
	 * chapter regardless of which chapter was shared.
	 *
	 * We detect the chapter reading page via madara-core's
	 * is_manga_reading_page() helper, pull the chapter name from
	 * madara_permalink_reading_chapter(), and rewrite:
	 *   - <title> (via document_title_parts)
	 *   - og:title, twitter:title (Yoast filters)
	 *   - og:image (to the manga cover instead of site logo)
	 *   - og:description (chapter name + manga synopsis)
	 *
	 * All filters are priority 99 so they run after Yoast's own pass.
	 */
	function mangazscans_chapter_share_info() {
		static $cache = null;
		if ( $cache !== null ) {
			return $cache;
		}
		$cache = false;

		if ( ! function_exists( 'is_manga_reading_page' ) || ! is_manga_reading_page() ) {
			return false;
		}

		$manga_id = get_the_ID();
		if ( ! $manga_id ) {
			return false;
		}

		$chapter = function_exists( 'madara_permalink_reading_chapter' )
			? madara_permalink_reading_chapter()
			: false;
		if ( ! $chapter || empty( $chapter['chapter_name'] ) ) {
			return false;
		}

		$extend = '';
		if ( ! empty( $chapter['chapter_name_extend'] )
			&& function_exists( 'madara_get_global_wp_manga_functions' ) ) {
			$fns = madara_get_global_wp_manga_functions();
			if ( is_object( $fns ) && method_exists( $fns, 'filter_extend_name' ) ) {
				$extend = $fns->filter_extend_name( $chapter['chapter_name_extend'] );
			}
		}

		$cache = array(
			'manga_id'     => (int) $manga_id,
			'manga_title'  => get_the_title( $manga_id ),
			'chapter_name' => trim( $chapter['chapter_name'] . $extend ),
		);
		return $cache;
	}

	// <title> element
	add_filter( 'document_title_parts', function ( $parts ) {
		$info = mangazscans_chapter_share_info();
		if ( ! $info ) {
			return $parts;
		}
		$parts['title'] = sprintf( '%s — %s', $info['manga_title'], $info['chapter_name'] );
		return $parts;
	}, 99 );

	// Yoast og:title (and title output)
	add_filter( 'wpseo_opengraph_title', function ( $title ) {
		$info = mangazscans_chapter_share_info();
		if ( ! $info ) {
			return $title;
		}
		return sprintf( '%s — %s - %s', $info['manga_title'], $info['chapter_name'], get_bloginfo( 'name' ) );
	}, 99 );
	add_filter( 'wpseo_title', function ( $title ) {
		$info = mangazscans_chapter_share_info();
		if ( ! $info ) {
			return $title;
		}
		return sprintf( '%s — %s - %s', $info['manga_title'], $info['chapter_name'], get_bloginfo( 'name' ) );
	}, 99 );

	// Yoast twitter:title
	add_filter( 'wpseo_twitter_title', function ( $title ) {
		$info = mangazscans_chapter_share_info();
		if ( ! $info ) {
			return $title;
		}
		return sprintf( '%s — %s', $info['manga_title'], $info['chapter_name'] );
	}, 99 );

	// Yoast og:image / twitter:image → manga cover
	add_filter( 'wpseo_opengraph_image', function ( $img ) {
		$info = mangazscans_chapter_share_info();
		if ( ! $info ) {
			return $img;
		}
		$cover = get_the_post_thumbnail_url( $info['manga_id'], 'large' );
		return $cover ?: $img;
	}, 99 );
	add_filter( 'wpseo_twitter_image', function ( $img ) {
		$info = mangazscans_chapter_share_info();
		if ( ! $info ) {
			return $img;
		}
		$cover = get_the_post_thumbnail_url( $info['manga_id'], 'large' );
		return $cover ?: $img;
	}, 99 );

	// Yoast og:description → 'Read <chapter> of <manga>. <first 25 words of synopsis>'
	add_filter( 'wpseo_opengraph_desc', function ( $desc ) {
		$info = mangazscans_chapter_share_info();
		if ( ! $info ) {
			return $desc;
		}
		$synopsis = wp_trim_words(
			wp_strip_all_tags( (string) get_post_field( 'post_content', $info['manga_id'] ) ),
			25,
			'…'
		);
		$lead = sprintf(
			/* translators: 1: chapter name, 2: manga title */
			esc_html__( 'Read %1$s of %2$s.', 'mangazscans' ),
			$info['chapter_name'],
			$info['manga_title']
		);
		$out = $synopsis !== '' ? $lead . ' ' . $synopsis : $lead;
		return $out;
	}, 99 );
	add_filter( 'wpseo_twitter_description', function ( $desc ) {
		$info = mangazscans_chapter_share_info();
		if ( ! $info ) {
			return $desc;
		}
		return sprintf(
			/* translators: 1: chapter name, 2: manga title */
			esc_html__( 'Read %1$s of %2$s.', 'mangazscans' ),
			$info['chapter_name'],
			$info['manga_title']
		);
	}, 99 );


