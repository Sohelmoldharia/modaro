<?php
	/**
	 * MangazScans homepage.
	 *
	 * WordPress picks this template automatically for the site root
	 * (front-page.php > home.php > index.php in the template
	 * hierarchy) — no admin configuration needed.
	 *
	 * Sections:
	 *   1. Hero     — brand + tagline + "browse" / "support" CTAs
	 *   2. Latest   — most recently updated manga cards
	 *   3. Support  — prominent "buy official / support creators" block
	 *   4. Browse   — genre grid
	 *
	 * Own mz-home__* class namespace. Paired stylesheet:
	 *   css/mangazscans-home.css (enqueued from theme.php only on home).
	 *
	 * @package mangazscans
	 */

	use App\MangazScans;

	get_header();

	// ---- Pull data ------------------------------------------------
	// Latest manga: most recent modified date means most recently
	// updated (madara bumps the manga post's modified-time on new
	// chapter upload, so this sorts by "newest chapter" in practice).
	$latest_query = new WP_Query( array(
		'post_type'      => 'wp-manga',
		'posts_per_page' => 12,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	) );

	// Genres — cap at a reasonable display count; sorted by count.
	$genres = get_terms( array(
		'taxonomy'   => 'wp-manga-genre',
		'number'     => 24,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'hide_empty' => true,
	) );

	global $wp_manga_chapter, $wp_manga_functions;

	// Resolve archive link for the "Browse all" CTA. Fall back to
	// home URL if the post type archive isn't rewritten.
	$archive_link = get_post_type_archive_link( 'wp-manga' );
	if ( ! $archive_link ) {
		$archive_link = home_url( '/' );
	}

	$site_name = get_bloginfo( 'name' );
	$tagline   = get_bloginfo( 'description' );
?>

<article class="mz-home">

	<?php // ---- HERO ----------------------------------------------- ?>
	<section class="mz-home__hero">
		<div class="mz-home__hero-inner">
			<span class="mz-home__hero-eyebrow"><?php esc_html_e( 'Welcome to', 'mangazscans' ); ?></span>
			<h1 class="mz-home__hero-title"><?php echo esc_html( $site_name ); ?></h1>
			<?php if ( $tagline ) : ?>
				<p class="mz-home__hero-tagline"><?php echo esc_html( $tagline ); ?></p>
			<?php else : ?>
				<p class="mz-home__hero-tagline">
					<?php esc_html_e( 'Original comics and curated reads — always pointing you to where the creators get paid.', 'mangazscans' ); ?>
				</p>
			<?php endif; ?>
			<div class="mz-home__hero-actions">
				<a class="mz-btn mz-btn--primary" href="<?php echo esc_url( $archive_link ); ?>">
					<?php esc_html_e( 'Browse all manga', 'mangazscans' ); ?>
				</a>
				<a class="mz-btn mz-btn--ghost" href="#mz-support">
					<?php esc_html_e( 'Support the creators', 'mangazscans' ); ?>
				</a>
			</div>
		</div>
	</section>

	<?php // ---- LATEST RELEASES --------------------------------- ?>
	<?php if ( $latest_query->have_posts() ) : ?>
		<section class="mz-home__section">
			<header class="mz-home__section-head">
				<h2 class="mz-home__section-title"><?php esc_html_e( 'Latest releases', 'mangazscans' ); ?></h2>
				<a class="mz-home__see-all" href="<?php echo esc_url( $archive_link ); ?>">
					<?php esc_html_e( 'See all', 'mangazscans' ); ?> →
				</a>
			</header>

			<div class="mz-home__grid">
				<?php while ( $latest_query->have_posts() ) : $latest_query->the_post();
					$pid         = get_the_ID();
					$permalink   = get_permalink();
					$cover_url   = has_post_thumbnail() ? get_the_post_thumbnail_url( $pid, 'medium' ) : '';
					$title       = get_the_title();
					$recent_cap  = array();
					if ( $wp_manga_chapter && method_exists( $wp_manga_chapter, 'get_chapters' ) ) {
						$all = $wp_manga_chapter->get_chapters( array( 'post_id' => $pid ), false, '', '', 2 );
						if ( is_array( $all ) ) { $recent_cap = array_slice( $all, 0, 2 ); }
					}
				?>
					<article class="mz-card">
						<a class="mz-card__cover" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
							<?php if ( $cover_url ) : ?>
								<img src="<?php echo esc_url( $cover_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" decoding="async" />
							<?php else : ?>
								<div class="mz-card__cover-placeholder" aria-hidden="true">
									<?php echo esc_html( mb_substr( $title, 0, 1 ) ); ?>
								</div>
							<?php endif; ?>
						</a>
						<div class="mz-card__info">
							<h3 class="mz-card__title">
								<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
							</h3>
							<?php if ( ! empty( $recent_cap ) && $wp_manga_functions ) : ?>
								<ul class="mz-card__chapters">
									<?php foreach ( $recent_cap as $chapter ) :
										$ch_link = $wp_manga_functions->build_chapter_url( $pid, $chapter, $wp_manga_functions->get_reading_style() );
										$time    = $wp_manga_functions->get_time_diff( $chapter['date'] );
									?>
										<li>
											<a href="<?php echo esc_url( $ch_link ); ?>"><?php echo esc_html( $chapter['chapter_name'] ); ?></a>
											<span><?php echo esc_html( $time ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</article>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php // ---- SUPPORT THE CREATORS -------------------------- ?>
	<section class="mz-home__support" id="mz-support">
		<div class="mz-home__support-inner">
			<span class="mz-home__support-eyebrow"><?php esc_html_e( 'Support the creators', 'mangazscans' ); ?></span>
			<h2 class="mz-home__support-title">
				<?php esc_html_e( 'Love what you\'re reading? Buy it official.', 'mangazscans' ); ?>
			</h2>
			<p class="mz-home__support-copy">
				<?php esc_html_e( 'The artists and publishers who make this work possible only keep going when readers pay for the original. If a series clicks for you here, please pick up the volumes, subscribe on the official platforms, or tip the creator — every sale counts more than a thousand views.', 'mangazscans' ); ?>
			</p>
			<ul class="mz-home__support-links">
				<li>
					<a href="https://global.bookwalker.jp/" target="_blank" rel="noopener">
						<strong>BookWalker</strong>
						<span><?php esc_html_e( 'Digital manga worldwide', 'mangazscans' ); ?></span>
					</a>
				</li>
				<li>
					<a href="https://mangaplus.shueisha.co.jp/" target="_blank" rel="noopener">
						<strong>MANGA Plus</strong>
						<span><?php esc_html_e( 'Free Shueisha simulpub', 'mangazscans' ); ?></span>
					</a>
				</li>
				<li>
					<a href="https://www.viz.com/shonenjump" target="_blank" rel="noopener">
						<strong>Shonen Jump</strong>
						<span><?php esc_html_e( 'Viz subscription', 'mangazscans' ); ?></span>
					</a>
				</li>
				<li>
					<a href="https://www.amazon.com/manga/b?node=4367" target="_blank" rel="noopener">
						<strong>Amazon / Kindle</strong>
						<span><?php esc_html_e( 'Paperback & digital', 'mangazscans' ); ?></span>
					</a>
				</li>
			</ul>
			<p class="mz-home__support-note">
				<?php esc_html_e( 'MangazScans hosts original comics and features other works for discovery. If you\'re a rights-holder and want something removed, reach out — we take that seriously.', 'mangazscans' ); ?>
			</p>
		</div>
	</section>

	<?php // ---- BROWSE BY GENRE ---------------------------------- ?>
	<?php if ( ! empty( $genres ) && ! is_wp_error( $genres ) ) : ?>
		<section class="mz-home__section">
			<header class="mz-home__section-head">
				<h2 class="mz-home__section-title"><?php esc_html_e( 'Browse by genre', 'mangazscans' ); ?></h2>
			</header>
			<div class="mz-home__genres">
				<?php foreach ( $genres as $genre ) : ?>
					<a class="mz-home__genre" href="<?php echo esc_url( get_term_link( $genre ) ); ?>">
						<span class="mz-home__genre-name"><?php echo esc_html( $genre->name ); ?></span>
						<span class="mz-home__genre-count"><?php echo esc_html( number_format_i18n( $genre->count ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

</article>

<?php get_footer();
