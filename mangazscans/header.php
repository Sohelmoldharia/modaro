<?php
	/**
	 * MangazScans header.
	 *
	 * This replaces the original Madara header.php. It's deliberately
	 * compact and uses its own 'mz-*' class namespace so our styles
	 * (css/mangazscans-chrome.css) don't fight Madara's legacy CSS
	 * cascade.
	 *
	 * Preserved from Madara:
	 *   - body.text-ui-light / text-ui-dark classes (madara-core reader
	 *     and some other plugins read them)
	 *   - do_action( 'madara_before_body' )
	 *   - do_action( 'madara_before_body_content' )
	 *   - .wrap > .body-wrap > .site-content outer structure
	 *
	 * @package mangazscans
	 */

	use App\Madara;

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	<?php // Restore user schema preference BEFORE CSS paints to avoid a flash. ?>
	<script>(function(){try{var p=localStorage.getItem('mz-schema');if(!p)return;var apply=function(){var b=document.body;if(!b)return;b.classList.remove('text-ui-light','text-ui-dark');b.classList.add(p==='dark'?'text-ui-light':'text-ui-dark');};if(document.body){apply();}else{document.addEventListener('DOMContentLoaded',apply,{once:true});}}catch(e){}})();</script>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<?php if ( ! is_404() ) :
	do_action( 'madara_before_body' );

	$minimal_reading_page = Madara::getOption( 'minimal_reading_page', 'off' );
	$is_reading_minimal   = ( function_exists( 'is_manga_reading_page' ) && is_manga_reading_page() && $minimal_reading_page === 'on' );
	$search_action        = esc_url( home_url( '/' ) );

	// Resolve logo URL once. Admin-uploaded option wins; else we use
	// the theme's shipped dark/light wordmark.
	$logo_opt = Madara::getOption( 'logo_image', '' );
	$logo_dark  = $logo_opt !== '' ? $logo_opt : get_parent_theme_file_uri( '/images/logo-light.svg' );
	$logo_light = $logo_opt !== '' ? $logo_opt : get_parent_theme_file_uri( '/images/logo.svg' );

	$site_name = get_bloginfo( 'name' );
?>

<div class="wrap">
	<div class="body-wrap">

		<?php if ( ! $is_reading_minimal ) : ?>
		<header class="mz-header" role="banner">
			<div class="mz-header__bar">
				<button class="mz-hamburger" type="button"
					aria-label="<?php esc_attr_e( 'Open menu', 'mangazscans' ); ?>"
					aria-expanded="false" aria-controls="mz-drawer">
					<span></span><span></span><span></span>
				</button>

				<a class="mz-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"
				   title="<?php echo esc_attr( $site_name ); ?>">
					<img class="mz-brand__logo mz-brand__logo--dark"
					     src="<?php echo esc_url( $logo_dark ); ?>"
					     alt="<?php echo esc_attr( $site_name ); ?>" width="200" height="40" />
					<img class="mz-brand__logo mz-brand__logo--light"
					     src="<?php echo esc_url( $logo_light ); ?>"
					     alt="<?php echo esc_attr( $site_name ); ?>" width="200" height="40" />
				</a>

				<nav class="mz-nav" aria-label="<?php esc_attr_e( 'Primary', 'mangazscans' ); ?>">
					<?php
						if ( has_nav_menu( 'primary_menu' ) ) {
							wp_nav_menu( array(
								'theme_location' => 'primary_menu',
								'container'      => false,
								'menu_class'     => 'mz-nav__list',
								'depth'          => 2,
								'fallback_cb'    => false,
							) );
						} else {
							echo '<ul class="mz-nav__list">';
							echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'mangazscans' ) . '</a></li>';
							echo '</ul>';
						}
					?>
				</nav>

				<div class="mz-actions">
					<button class="mz-iconbtn mz-search-toggle" type="button"
						aria-label="<?php esc_attr_e( 'Search', 'mangazscans' ); ?>"
						aria-expanded="false" aria-controls="mz-search">
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
					</button>

					<button class="mz-iconbtn mz-schema-toggle" type="button"
						aria-label="<?php esc_attr_e( 'Toggle dark mode', 'mangazscans' ); ?>">
						<svg class="mz-icon-moon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M20 15.5A8 8 0 1 1 8.5 4a6.5 6.5 0 0 0 11.5 11.5Z" fill="currentColor"/></svg>
						<svg class="mz-icon-sun"  viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="currentColor"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5"/></g></svg>
					</button>

					<?php if ( is_user_logged_in() ) :
						$user = wp_get_current_user();
						?>
						<a class="mz-iconbtn mz-user" href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>"
							title="<?php echo esc_attr( $user->display_name ); ?>">
							<?php echo get_avatar( $user->ID, 24 ); ?>
						</a>
					<?php else : ?>
						<a class="mz-btn mz-btn--ghost mz-signin" href="<?php echo esc_url( wp_login_url( $_SERVER['REQUEST_URI'] ?? home_url( '/' ) ) ); ?>">
							<?php esc_html_e( 'Sign in', 'mangazscans' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<form id="mz-search" class="mz-search" role="search" method="get" action="<?php echo $search_action; ?>" aria-hidden="true">
				<input type="search" name="s" placeholder="<?php esc_attr_e( 'Search manga, author, genre…', 'mangazscans' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" />
				<button class="mz-btn mz-btn--primary" type="submit"><?php esc_html_e( 'Search', 'mangazscans' ); ?></button>
			</form>
		</header>

		<div class="mz-drawer" id="mz-drawer" aria-hidden="true">
			<div class="mz-drawer__scrim"></div>
			<aside class="mz-drawer__panel" role="dialog" aria-label="<?php esc_attr_e( 'Menu', 'mangazscans' ); ?>">
				<div class="mz-drawer__head">
					<span class="mz-drawer__title"><?php echo esc_html( $site_name ); ?></span>
					<button class="mz-drawer__close" type="button" aria-label="<?php esc_attr_e( 'Close menu', 'mangazscans' ); ?>">&times;</button>
				</div>
				<?php
					if ( has_nav_menu( 'mobile_menu' ) ) {
						wp_nav_menu( array(
							'theme_location' => 'mobile_menu',
							'container'      => false,
							'menu_class'     => 'mz-drawer__list',
							'depth'          => 2,
							'fallback_cb'    => false,
						) );
					} elseif ( has_nav_menu( 'primary_menu' ) ) {
						wp_nav_menu( array(
							'theme_location' => 'primary_menu',
							'container'      => false,
							'menu_class'     => 'mz-drawer__list',
							'depth'          => 2,
							'fallback_cb'    => false,
						) );
					}
				?>
			</aside>
		</div>
		<?php endif; ?>

		<div class="site-content">
			<?php do_action( 'madara_before_body_content' ); ?>
<?php endif; // ! is_404() ?>
