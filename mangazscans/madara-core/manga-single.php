<?php
	/**
	 * MangazScans — Manga Detail page.
	 *
	 * Completely rewritten from the madara-core template. Uses its own
	 * 'mz-manga__*' class namespace so the compiled style.css can't
	 * reach in. Paired stylesheet: css/mangazscans-manga.css.
	 *
	 * Preserved contract with the plugin:
	 *   - before_manga_single / after_manga_single actions
	 *   - wp-manga-chapter-listing action (fires the chapter list)
	 *   - wp_manga_discussion action (comments block)
	 *   - wp_manga_related_manga section (optional)
	 *
	 * @package mangazscans
	 */

	use App\MangazScans;

	get_header();

	$wp_manga           = madara_get_global_wp_manga();
	$wp_manga_functions = madara_get_global_wp_manga_functions();
	$post_id            = get_the_ID();

	// One-shot manga have their own template.
	if ( function_exists( 'is_manga_oneshot' ) && is_manga_oneshot( $post_id ) ) {
		get_template_part( '/madara-core/manga', 'oneshot' );
		get_footer();
		exit;
	}

	// Pull everything we'll render up front so the markup stays clean.
	$title          = get_the_title();
	$alt_names      = get_post_meta( $post_id, '_wp_manga_alternative', true );
	$cover_url      = has_post_thumbnail() ? get_the_post_thumbnail_url( $post_id, 'full' ) : '';
	$synopsis_html  = apply_filters( 'the_content', get_post( $post_id )->post_content );
	$status_terms   = $wp_manga_functions->get_manga_status( $post_id );   // array w/ names
	$author_html    = $wp_manga_functions->get_manga_authors( $post_id );  // HTML list
	$artist_html    = $wp_manga_functions->get_manga_artists( $post_id );
	$genre_html     = $wp_manga_functions->get_manga_genres( $post_id );
	$type_terms     = $wp_manga_functions->get_manga_type( $post_id );
	$release_terms  = $wp_manga_functions->get_manga_release( $post_id );

	// Rating / views — madara stores these in post-meta; fall back to 0.
	$rating      = (float) ( get_post_meta( $post_id, '_manga_total_votes_values', true ) ?: 0 );
	$vote_count  = (int) ( get_post_meta( $post_id, '_manga_total_votes', true ) ?: 0 );
	$avg_rating  = $vote_count > 0 ? round( $rating / $vote_count, 1 ) : 0;
	$views_m     = (int) ( get_post_meta( $post_id, '_wp_manga_month_views', true ) ?: 0 );
	$views_all   = (int) ( get_post_meta( $post_id, '_wp_manga_views', true )       ?: 0 );
	$bookmarks   = (int) ( get_post_meta( $post_id, '_wp_manga_bookmark_count', true ) ?: 0 );
	$comments_n  = (int) get_comments_number( $post_id );

	// Continue-reading / Start-reading button target. Madara doesn't
	// expose "get first chapter" or "get last read" helpers directly
	// on $wp_manga_functions, so we query $wp_manga_chapter ourselves.
	global $wp_manga_chapter;
	$user_id          = get_current_user_id();
	$first_chapter    = null;
	$last_chapter     = null;
	$reading_style    = $wp_manga_functions->get_reading_style();

	if ( is_object( $wp_manga_chapter ) && method_exists( $wp_manga_chapter, 'get_chapters' ) ) {
		// get_chapters returns newest-first by default per madara's sort
		// setting; take the oldest for "start reading".
		$all = $wp_manga_chapter->get_chapters( array( 'post_id' => $post_id ) );
		if ( is_array( $all ) && ! empty( $all ) ) {
			$first_chapter = end( $all );
			reset( $all );
		}
	}
	// If the user has a saved reading position (madara stores it in
	// user meta as '_manga_last_read_chapter_{post_id}') try to use it.
	if ( $user_id ) {
		$saved_slug = get_user_meta( $user_id, '_manga_last_read_chapter_' . $post_id, true );
		if ( $saved_slug && is_object( $wp_manga_chapter ) && method_exists( $wp_manga_chapter, 'get_chapter_by_slug' ) ) {
			$maybe = $wp_manga_chapter->get_chapter_by_slug( $post_id, $saved_slug );
			if ( is_array( $maybe ) && ! empty( $maybe ) ) {
				$last_chapter = $maybe;
			}
		}
	}
	$start_chapter = $last_chapter ?: $first_chapter;
	$start_link    = $start_chapter
		? $wp_manga_functions->build_chapter_url( $post_id, $start_chapter, $reading_style )
		: '';
	$start_label   = $last_chapter
		? esc_html__( 'Continue reading', 'mangazscans' )
		: esc_html__( 'Start reading', 'mangazscans' );

	// Simple helper to render a star row. $avg = 0..5 float.
	$render_stars = static function ( $avg ) {
		$full  = (int) floor( $avg );
		$half  = ( $avg - $full ) >= 0.5 ? 1 : 0;
		$empty = 5 - $full - $half;
		$out   = '';
		for ( $i = 0; $i < $full;  $i++ ) { $out .= '<span class="mz-star is-full">★</span>'; }
		for ( $i = 0; $i < $half;  $i++ ) { $out .= '<span class="mz-star is-half">★</span>'; }
		for ( $i = 0; $i < $empty; $i++ ) { $out .= '<span class="mz-star">☆</span>'; }
		return $out;
	};

	do_action( 'before_manga_single' );
?>

<article <?php post_class( 'mz-manga' ); ?>>

	<?php // Breadcrumb — compact, muted, single line ?>
	<nav class="mz-manga__breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'mangazscans' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'mangazscans' ); ?></a>
		<span aria-hidden="true">/</span>
		<?php
			$archive = get_post_type_archive_link( 'wp-manga' );
			if ( $archive ) :
		?>
			<a href="<?php echo esc_url( $archive ); ?>"><?php esc_html_e( 'All manga', 'mangazscans' ); ?></a>
			<span aria-hidden="true">/</span>
		<?php endif; ?>
		<span class="mz-manga__breadcrumb-current"><?php echo esc_html( $title ); ?></span>
	</nav>

	<?php // ---- Hero block: cover + title + meta + actions ---- ?>
	<header class="mz-manga__hero">
		<div class="mz-manga__cover">
			<?php if ( $cover_url ) : ?>
				<img src="<?php echo esc_url( $cover_url ); ?>"
				     alt="<?php echo esc_attr( $title ); ?>"
				     loading="lazy" decoding="async" />
			<?php else : ?>
				<div class="mz-manga__cover-placeholder" aria-hidden="true">
					<span><?php echo esc_html( mb_substr( $title, 0, 1 ) ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<div class="mz-manga__info">
			<div class="mz-manga__titlebar">
				<h1 class="mz-manga__title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( ! empty( $status_terms ) ) : ?>
					<span class="mz-manga__status"><?php echo esc_html( wp_strip_all_tags( (string) $status_terms ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( $alt_names ) : ?>
				<p class="mz-manga__alt">
					<span class="mz-manga__alt-label"><?php esc_html_e( 'Also known as', 'mangazscans' ); ?>:</span>
					<?php echo esc_html( $alt_names ); ?>
				</p>
			<?php endif; ?>

			<dl class="mz-manga__meta">
				<?php if ( $author_html ) : ?>
					<div><dt><?php esc_html_e( 'Author', 'mangazscans' ); ?></dt><dd><?php echo wp_kses_post( $author_html ); ?></dd></div>
				<?php endif; ?>
				<?php if ( $artist_html ) : ?>
					<div><dt><?php esc_html_e( 'Artist', 'mangazscans' ); ?></dt><dd><?php echo wp_kses_post( $artist_html ); ?></dd></div>
				<?php endif; ?>
				<?php if ( ! empty( $release_terms ) ) : ?>
					<div><dt><?php esc_html_e( 'Released', 'mangazscans' ); ?></dt><dd><?php echo wp_kses_post( (string) $release_terms ); ?></dd></div>
				<?php endif; ?>
				<?php if ( ! empty( $type_terms ) ) : ?>
					<div><dt><?php esc_html_e( 'Type', 'mangazscans' ); ?></dt><dd><?php echo esc_html( wp_strip_all_tags( (string) $type_terms ) ); ?></dd></div>
				<?php endif; ?>
			</dl>

			<?php if ( $genre_html ) : ?>
				<div class="mz-manga__genres">
					<?php echo wp_kses_post( $genre_html ); ?>
				</div>
			<?php endif; ?>

			<div class="mz-manga__stats" role="group" aria-label="<?php esc_attr_e( 'Stats', 'mangazscans' ); ?>">
				<div class="mz-stat mz-stat--rating" title="<?php esc_attr_e( 'Average rating', 'mangazscans' ); ?>">
					<span class="mz-stat__stars"><?php echo $render_stars( $avg_rating ); ?></span>
					<span class="mz-stat__value"><?php echo esc_html( number_format_i18n( $avg_rating, 1 ) ); ?></span>
					<span class="mz-stat__label">
						<?php printf(
							esc_html( _n( '%s vote', '%s votes', $vote_count, 'mangazscans' ) ),
							esc_html( number_format_i18n( $vote_count ) )
						); ?>
					</span>
				</div>
				<div class="mz-stat" title="<?php esc_attr_e( 'Monthly views', 'mangazscans' ); ?>">
					<span aria-hidden="true">👁</span>
					<strong><?php echo esc_html( number_format_i18n( $views_m ) ); ?></strong>
					<span class="mz-stat__label"><?php esc_html_e( 'monthly views', 'mangazscans' ); ?></span>
				</div>
				<div class="mz-stat" title="<?php esc_attr_e( 'Bookmarks', 'mangazscans' ); ?>">
					<span aria-hidden="true">🔖</span>
					<strong><?php echo esc_html( number_format_i18n( $bookmarks ) ); ?></strong>
				</div>
				<div class="mz-stat" title="<?php esc_attr_e( 'Comments', 'mangazscans' ); ?>">
					<span aria-hidden="true">💬</span>
					<strong><?php echo esc_html( number_format_i18n( $comments_n ) ); ?></strong>
				</div>
			</div>

			<div class="mz-manga__actions">
				<?php if ( $start_link ) : ?>
					<a class="mz-btn mz-btn--primary" href="<?php echo esc_url( $start_link ); ?>">
						<?php echo $start_label; // already escaped above ?>
					</a>
				<?php endif; ?>
				<?php if ( is_user_logged_in() ) :
					do_action( 'wp_manga_bookmark_button', $post_id );
				else : ?>
					<a class="mz-btn mz-btn--ghost" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
						<?php esc_html_e( 'Sign in to bookmark', 'mangazscans' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</header>

	<?php // ---- Summary ---- ?>
	<?php if ( trim( wp_strip_all_tags( $synopsis_html ) ) !== '' ) : ?>
		<section class="mz-manga__section mz-manga__summary">
			<h2 class="mz-manga__h"><?php esc_html_e( 'Summary', 'mangazscans' ); ?></h2>
			<div class="mz-manga__summary-body"><?php echo wp_kses_post( $synopsis_html ); ?></div>
		</section>
	<?php endif; ?>

	<?php // ---- Chapters ---- ?>
	<section class="mz-manga__section mz-manga__chapters">
		<?php do_action( 'wp-manga-chapter-listing', $post_id ); ?>
	</section>

	<?php edit_post_link( esc_html__( 'Edit this manga', 'mangazscans' ), '<p class="mz-manga__edit">', '</p>' ); ?>

	<?php // ---- Related + comments (preserve plugin hooks) ---- ?>
	<?php
		$wp_manga_settings = get_option( 'wp_manga_settings' );
		$related_manga     = isset( $wp_manga_settings['related_manga'] ) ? $wp_manga_settings['related_manga'] : null;
		if ( $related_manga == 1 ) {
			echo '<section class="mz-manga__section mz-manga__related">';
			get_template_part( '/madara-core/manga', 'related' );
			echo '</section>';
		}
	?>

	<?php if ( comments_open( $post_id ) || get_comments_number( $post_id ) > 0 ) : ?>
		<section class="mz-manga__section mz-manga__comments">
			<?php do_action( 'wp_manga_discussion' ); ?>
		</section>
	<?php endif; ?>

	<?php do_action( 'after_manga_single' ); ?>
</article>

<?php get_footer();
