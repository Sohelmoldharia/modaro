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
