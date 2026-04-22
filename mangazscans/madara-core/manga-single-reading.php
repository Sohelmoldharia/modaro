<?php
	/**
	 * MangazScans — Chapter reader (own markup).
	 *
	 * Replaces madara-core/templates/manga-single-reading.php. Emits
	 * our own nav entirely in the .mz-reader__* namespace so nothing
	 * in Madara's compiled style.css can cascade into it.
	 *
	 * What we build ourselves:
	 *   - 'Back to manga' link + chapter title (top strip)
	 *   - Reading-mode toggle ( All pages / One by one )
	 *   - Chapter selector (dropdown of every chapter in this manga)
	 *   - Page pager (only rendered in paged mode; ‹ Page N / Total ›)
	 *   - Chapter prev / Manga Info / Chapter next (bottom row of nav)
	 *
	 * What we delegate to the plugin:
	 *   - The actual chapter-image rendering, via its content-reading-
	 *     {list,paged,content}.php templates. Those are self-contained
	 *     and we don't restyle them.
	 *
	 * Preserves plugin hooks: before_manga_single, madara_ads_before_content,
	 * madara_ads_after_content, wp_manga_before_chapter_content,
	 * wp_manga_after_chapter_content, wp_manga_discussion,
	 * after_manga_single.
	 *
	 * @package mangazscans
	 */

	use App\MangazScans;

	$manga_id = get_the_ID();
	$reading_chapter = function_exists( 'madara_permalink_reading_chapter' )
		? madara_permalink_reading_chapter()
		: false;

	// Fallback resolution for the pre-1.6 URL shape.
	if ( ! $reading_chapter ) {
		if ( $chapter_slug = get_query_var( 'chapter' ) ) {
			global $wp_manga_functions;
			$reading_chapter = $wp_manga_functions->get_chapter_by_slug( $manga_id, $chapter_slug );
		}
		if ( ! $reading_chapter ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			get_template_part( 404 );
			exit;
		}
	}

	$cur_chap           = $reading_chapter['chapter_slug'];
	$wp_manga           = madara_get_global_wp_manga();
	$wp_manga_functions = madara_get_global_wp_manga_functions();
	$style              = isset( $_GET['style'] ) ? sanitize_key( wp_unslash( $_GET['style'] ) ) : $wp_manga_functions->get_reading_style();
	if ( ! in_array( $style, array( 'list', 'paged' ), true ) ) {
		$style = 'list';
	}

	// All chapters in this manga + which one we're on.
	global $wp_manga_chapter, $wp_manga_database;
	$all_chapters = is_object( $wp_manga_chapter ) && method_exists( $wp_manga_chapter, 'get_chapters' )
		? $wp_manga_chapter->get_chapters( array( 'post_id' => $manga_id ) )
		: array();
	if ( ! is_array( $all_chapters ) ) {
		$all_chapters = array();
	}
	$sort_setting = is_object( $wp_manga_database ) && method_exists( $wp_manga_database, 'get_sort_setting' )
		? $wp_manga_database->get_sort_setting()
		: array( 'sort' => 'desc' );
	$asc = isset( $sort_setting['sort'] ) && $sort_setting['sort'] !== 'desc';

	$cur_idx = -1;
	foreach ( $all_chapters as $idx => $ch ) {
		if ( $ch['chapter_slug'] === $cur_chap ) {
			$cur_idx = $idx;
			break;
		}
	}
	$prev_chapter = null;
	$next_chapter = null;
	if ( $cur_idx !== -1 ) {
		$prev_chapter = isset( $all_chapters[ $asc ? $cur_idx - 1 : $cur_idx + 1 ] )
			? $all_chapters[ $asc ? $cur_idx - 1 : $cur_idx + 1 ]
			: null;
		$next_chapter = isset( $all_chapters[ $asc ? $cur_idx + 1 : $cur_idx - 1 ] )
			? $all_chapters[ $asc ? $cur_idx + 1 : $cur_idx - 1 ]
			: null;
	}
	$prev_chapter_url = $prev_chapter ? $wp_manga_functions->build_chapter_url( $manga_id, $prev_chapter, $style ) : '';
	$next_chapter_url = $next_chapter ? $wp_manga_functions->build_chapter_url( $manga_id, $next_chapter, $style ) : '';

	// Paged-mode page context.
	$cur_page    = 1;
	$total_pages = 1;
	if ( $style === 'paged' ) {
		$q_page = get_query_var( $wp_manga->manga_paged_var );
		if ( ! $q_page && isset( $_GET[ $wp_manga->manga_paged_var ] ) ) {
			$q_page = absint( $_GET[ $wp_manga->manga_paged_var ] );
		}
		$cur_page = max( 1, (int) $q_page );

		$single_chap = $wp_manga_functions->get_single_chapter( $manga_id, $reading_chapter['chapter_id'] );
		if ( is_array( $single_chap ) && isset( $single_chap['total_page'] ) ) {
			$total_pages = max( 1, (int) $single_chap['total_page'] );
		}
		if ( $cur_page > $total_pages ) {
			$cur_page = $total_pages;
		}
	}

	$manga_title       = get_the_title( $manga_id );
	$manga_permalink   = get_permalink( $manga_id );
	$chapter_full_name = $reading_chapter['chapter_name']
		. ( isset( $reading_chapter['chapter_name_extend'] )
			? $wp_manga_functions->filter_extend_name( $reading_chapter['chapter_name_extend'] )
			: '' );

	// URLs for the style toggle (same URL, swap ?style).
	$list_url  = $wp_manga_functions->build_chapter_url( $manga_id, $reading_chapter, 'list' );
	$paged_url = $wp_manga_functions->build_chapter_url( $manga_id, $reading_chapter, 'paged' );

	// Render one nav group (used at top + bottom of the reader).
	$render_nav = static function ( $position )
		use (
			$manga_id, $reading_chapter, $cur_chap, $all_chapters,
			$style, $list_url, $paged_url,
			$cur_page, $total_pages,
			$prev_chapter_url, $next_chapter_url,
			$manga_permalink, $wp_manga_functions
		) {
		?>
		<nav class="mz-reader__bar mz-reader__bar--<?php echo esc_attr( $position ); ?>" aria-label="<?php esc_attr_e( 'Chapter navigation', 'mangazscans' ); ?>">

			<div class="mz-reader__row mz-reader__row--toggle">
				<div class="mz-style-toggle" role="group" aria-label="<?php esc_attr_e( 'Reading mode', 'mangazscans' ); ?>">
					<a class="mz-style-toggle__btn <?php echo $style === 'list' ? 'is-active' : ''; ?>"
					   href="<?php echo esc_url( $list_url ); ?>"
					   aria-pressed="<?php echo $style === 'list' ? 'true' : 'false'; ?>">
						<?php esc_html_e( 'All pages', 'mangazscans' ); ?>
					</a>
					<a class="mz-style-toggle__btn <?php echo $style === 'paged' ? 'is-active' : ''; ?>"
					   href="<?php echo esc_url( $paged_url ); ?>"
					   aria-pressed="<?php echo $style === 'paged' ? 'true' : 'false'; ?>">
						<?php esc_html_e( 'One by one', 'mangazscans' ); ?>
					</a>
				</div>
			</div>

			<div class="mz-reader__row mz-reader__row--chapter-select">
				<select class="mz-reader__select mz-reader__chapter-select"
				        aria-label="<?php esc_attr_e( 'Jump to chapter', 'mangazscans' ); ?>"
				        onchange="if(this.value)window.location.href=this.value;">
					<?php foreach ( $all_chapters as $ch ) :
						$link = $wp_manga_functions->build_chapter_url( $manga_id, $ch, $style );
						$name = $ch['chapter_name']
							. ( isset( $ch['chapter_name_extend'] )
								? $wp_manga_functions->filter_extend_name( $ch['chapter_name_extend'] )
								: '' );
					?>
						<option value="<?php echo esc_url( $link ); ?>" <?php selected( $ch['chapter_slug'], $cur_chap ); ?>>
							<?php echo esc_html( $name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<?php // Page pager — only in paged mode, only if more than 1 page. ?>
			<?php if ( $style === 'paged' && $total_pages > 1 ) :
				$page_urls = array();
				for ( $p = 1; $p <= $total_pages; $p++ ) {
					$page_urls[ $p ] = $wp_manga_functions->build_chapter_url( $manga_id, $reading_chapter, 'paged', null, $p );
				}
				$prev_page_url = isset( $page_urls[ $cur_page - 1 ] ) ? $page_urls[ $cur_page - 1 ] : '';
				$next_page_url = isset( $page_urls[ $cur_page + 1 ] ) ? $page_urls[ $cur_page + 1 ] : '';
			?>
				<div class="mz-reader__row mz-reader__row--pager">
					<a class="mz-reader__pager-btn <?php echo $prev_page_url ? '' : 'is-disabled'; ?>"
					   href="<?php echo $prev_page_url ? esc_url( $prev_page_url ) : '#'; ?>"
					   aria-label="<?php esc_attr_e( 'Previous page', 'mangazscans' ); ?>"
					   <?php echo $prev_page_url ? '' : 'aria-disabled="true" tabindex="-1"'; ?>>
						<span aria-hidden="true">‹</span>
					</a>
					<select class="mz-reader__select mz-reader__page-select"
					        aria-label="<?php esc_attr_e( 'Jump to page', 'mangazscans' ); ?>"
					        onchange="if(this.value)window.location.href=this.value;">
						<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
							<option value="<?php echo esc_url( $page_urls[ $p ] ); ?>" <?php selected( $p, $cur_page ); ?>>
								<?php
								/* translators: 1: current page, 2: total pages */
								printf( esc_html__( 'Page %1$s of %2$s', 'mangazscans' ), esc_html( $p ), esc_html( $total_pages ) );
								?>
							</option>
						<?php endfor; ?>
					</select>
					<a class="mz-reader__pager-btn <?php echo $next_page_url ? '' : 'is-disabled'; ?>"
					   href="<?php echo $next_page_url ? esc_url( $next_page_url ) : '#'; ?>"
					   aria-label="<?php esc_attr_e( 'Next page', 'mangazscans' ); ?>"
					   <?php echo $next_page_url ? '' : 'aria-disabled="true" tabindex="-1"'; ?>>
						<span aria-hidden="true">›</span>
					</a>
				</div>
			<?php endif; ?>

			<div class="mz-reader__row mz-reader__row--chapter-nav">
				<a class="mz-reader__chapter-btn <?php echo $prev_chapter_url ? '' : 'is-disabled'; ?>"
				   href="<?php echo $prev_chapter_url ? esc_url( $prev_chapter_url ) : '#'; ?>"
				   <?php echo $prev_chapter_url ? '' : 'aria-disabled="true" tabindex="-1"'; ?>>
					<span aria-hidden="true">←</span>
					<span><?php esc_html_e( 'Prev', 'mangazscans' ); ?></span>
				</a>
				<a class="mz-reader__info-btn" href="<?php echo esc_url( $manga_permalink ); ?>"
				   aria-label="<?php esc_attr_e( 'Manga info', 'mangazscans' ); ?>"
				   title="<?php esc_attr_e( 'Manga info', 'mangazscans' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
						<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
						<path d="M12 8h.01M11 12h1v5h1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</a>
				<a class="mz-reader__chapter-btn mz-reader__chapter-btn--next <?php echo $next_chapter_url ? '' : 'is-disabled'; ?>"
				   href="<?php echo $next_chapter_url ? esc_url( $next_chapter_url ) : '#'; ?>"
				   <?php echo $next_chapter_url ? '' : 'aria-disabled="true" tabindex="-1"'; ?>>
					<span><?php esc_html_e( 'Next', 'mangazscans' ); ?></span>
					<span aria-hidden="true">→</span>
				</a>
			</div>
		</nav>
		<?php
	};

	do_action( 'before_manga_single' );
	get_header();
?>

<article class="mz-reader" data-style="<?php echo esc_attr( $style ); ?>"
         data-manga="<?php echo esc_attr( $manga_id ); ?>"
         data-chapter="<?php echo esc_attr( $cur_chap ); ?>">

	<header class="mz-reader__heading">
		<a class="mz-reader__back" href="<?php echo esc_url( $manga_permalink ); ?>"
		   title="<?php echo esc_attr( $manga_title ); ?>">
			<span aria-hidden="true">←</span>
			<span class="mz-reader__back-text"><?php echo esc_html( $manga_title ); ?></span>
		</a>
		<h1 class="mz-reader__chapter-title"><?php echo esc_html( $chapter_full_name ); ?></h1>
	</header>

	<?php $render_nav( 'top' ); ?>

	<div class="mz-reader__content reading-content-wrap chapter-type-manga"
	     data-site-url="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php echo apply_filters( 'madara_ads_before_content', madara_ads_position( 'ads_before_content', 'body-top-ads' ) ); ?>

		<div class="reading-content">
			<input type="hidden" id="wp-manga-current-chap"
			       data-id="<?php echo esc_attr( $reading_chapter['chapter_id'] ); ?>"
			       value="<?php echo esc_attr( $cur_chap ); ?>" />
			<?php
				global $post;
				if ( ! $post->post_password || ( $post->post_password && ! post_password_required() ) ) {
					$alternative_content = apply_filters( 'wp_manga_chapter_content_alternative', '' );
					if ( ! $alternative_content ) {
						do_action( 'wp_manga_before_chapter_content', $cur_chap, $manga_id );
						if ( $wp_manga->is_content_manga( $manga_id ) ) {
							$GLOBALS['wp_manga_template']->load_template( 'reading-content/content', 'reading-content', true );
						} else {
							$GLOBALS['wp_manga_template']->load_template( 'reading-content/content', 'reading-' . $style, true );
						}
						do_action( 'wp_manga_after_chapter_content', $cur_chap, $manga_id );
					} else {
						echo madara_filter_content( $alternative_content );
					}
				} else {
					the_content();
				}
			?>
		</div>

		<?php echo apply_filters( 'madara_ads_after_content', madara_ads_position( 'ads_after_content', 'body-bottom-ads' ) ); ?>
	</div>

	<?php $render_nav( 'bottom' ); ?>

	<?php
		$discussion = MangazScans::getOption( 'manga_reading_discussion', 'on' );
		if ( $discussion === 'on' && ( comments_open( $manga_id ) || get_comments_number( $manga_id ) > 0 ) ) :
	?>
		<section class="mz-reader__comments">
			<?php do_action( 'wp_manga_discussion' ); ?>
		</section>
	<?php endif; ?>

	<?php
		$minimal = MangazScans::getOption( 'minimal_reading_page', 'off' );
		$wp_manga_settings = get_option( 'wp_manga_settings' );
		$related = isset( $wp_manga_settings['related_manga'] ) ? (int) $wp_manga_settings['related_manga'] : 0;
		if ( $related === 1 && $minimal === 'off' ) :
	?>
		<section class="mz-reader__related">
			<?php get_template_part( '/madara-core/manga', 'related' ); ?>
		</section>
	<?php endif; ?>

	<?php do_action( 'after_manga_single' ); ?>
</article>

<?php get_footer();
