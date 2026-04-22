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
