<?php
	/**
	 * MangazScans — Chapter Reading page.
	 *
	 * Rewritten from madara-core's manga-single-reading.php. Clean,
	 * mobile-first layout with a sticky top bar (title + prev/next +
	 * chapter picker + back) and a mirror sticky bottom bar on the
	 * chapter images. Own mz-reader__* class namespace.
	 *
	 * Still delegates the actual image rendering to the plugin's
	 * content-reading-{list,paged,content}.php so every storage
	 * backend (local, imgchest, direct, page, zip) keeps working.
	 *
	 * Preserves plugin hooks:
	 *   wp_manga_before_chapter_content / _after_chapter_content
	 *   madara_ads_before_content / _after_content
	 *   wp_manga_discussion
	 *   after_manga_single
	 *
	 * @package mangazscans
	 */

	use App\MangazScans;

	$manga_id = get_the_ID();

	$reading_chapter = function_exists( 'madara_permalink_reading_chapter' )
		? madara_permalink_reading_chapter()
		: false;

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
	$reading_style      = isset( $_GET['style'] ) ? sanitize_key( $_GET['style'] ) : $wp_manga_functions->get_reading_style();

	// Build our own prev/next/chapter-select data from the DB instead
	// of re-running madara's manga_nav() markup. That way we emit our
	// own clean mz-reader__* layout without fighting the plugin's CSS.
	global $wp_manga_chapter;
	$all_chapters = array();
	if ( is_object( $wp_manga_chapter ) && method_exists( $wp_manga_chapter, 'get_chapters' ) ) {
		$all_chapters = $wp_manga_chapter->get_chapters( array( 'post_id' => $manga_id ) );
		if ( ! is_array( $all_chapters ) ) {
			$all_chapters = array();
		}
	}

	// Sort setting (asc/desc) tells us which end of the array is "newer".
	global $wp_manga_database;
	$sort_setting = is_object( $wp_manga_database ) && method_exists( $wp_manga_database, 'get_sort_setting' )
		? $wp_manga_database->get_sort_setting()
		: array( 'sort' => 'desc' );
	$asc = isset( $sort_setting['sort'] ) ? ( $sort_setting['sort'] !== 'desc' ) : false;

	// Find current chapter's index in the flat list.
	$cur_idx = -1;
	foreach ( $all_chapters as $idx => $chap ) {
		if ( $chap['chapter_slug'] === $cur_chap ) {
			$cur_idx = $idx;
			break;
		}
	}

	// prev/next depend on asc/desc ordering.
	$prev_chap = $next_chap = null;
	if ( $cur_idx !== -1 ) {
		$prev_chap = isset( $all_chapters[ $asc ? $cur_idx - 1 : $cur_idx + 1 ] )
			? $all_chapters[ $asc ? $cur_idx - 1 : $cur_idx + 1 ]
			: null;
		$next_chap = isset( $all_chapters[ $asc ? $cur_idx + 1 : $cur_idx - 1 ] )
			? $all_chapters[ $asc ? $cur_idx + 1 : $cur_idx - 1 ]
			: null;
	}
	$prev_url = $prev_chap ? $wp_manga_functions->build_chapter_url( $manga_id, $prev_chap, $reading_style ) : '';
	$next_url = $next_chap ? $wp_manga_functions->build_chapter_url( $manga_id, $next_chap, $reading_style ) : '';

	$manga_title       = get_the_title( $manga_id );
	$manga_info_link   = get_permalink( $manga_id );
	$chapter_full_name = $reading_chapter['chapter_name']
		. ( isset( $reading_chapter['chapter_name_extend'] )
			? $wp_manga_functions->filter_extend_name( $reading_chapter['chapter_name_extend'] )
			: '' );

	// Helper: one render pass for both top + bottom nav bars.
	$render_nav = static function ( $position ) use (
		$manga_title,
		$chapter_full_name,
		$manga_info_link,
		$prev_url,
		$next_url,
		$all_chapters,
		$cur_chap,
		$manga_id,
		$reading_style,
		$wp_manga_functions
	) {
		?>
		<nav class="mz-reader__bar mz-reader__bar--<?php echo esc_attr( $position ); ?>" aria-label="<?php esc_attr_e( 'Chapter navigation', 'mangazscans' ); ?>">
			<?php if ( $position === 'top' ) : ?>
				<a class="mz-reader__back" href="<?php echo esc_url( $manga_info_link ); ?>" title="<?php echo esc_attr( $manga_title ); ?>">
					<span aria-hidden="true">←</span>
					<span class="mz-reader__back-label"><?php echo esc_html( $manga_title ); ?></span>
				</a>
				<span class="mz-reader__chapter-label"><?php echo esc_html( $chapter_full_name ); ?></span>
			<?php endif; ?>

			<div class="mz-reader__controls">
				<a class="mz-reader__nav mz-reader__nav--prev <?php echo $prev_url ? '' : 'is-disabled'; ?>"
				   href="<?php echo $prev_url ? esc_url( $prev_url ) : '#'; ?>"
				   aria-label="<?php esc_attr_e( 'Previous chapter', 'mangazscans' ); ?>"
				   <?php echo $prev_url ? '' : 'aria-disabled="true" tabindex="-1"'; ?>>
					<span aria-hidden="true">←</span>
					<span class="mz-reader__nav-label"><?php esc_html_e( 'Prev', 'mangazscans' ); ?></span>
				</a>

				<div class="mz-reader__picker">
					<select class="mz-reader__select" aria-label="<?php esc_attr_e( 'Select chapter', 'mangazscans' ); ?>"
					        onchange="if(this.value)window.location.href=this.value;">
						<?php foreach ( $all_chapters as $ch ) :
							$link = $wp_manga_functions->build_chapter_url( $manga_id, $ch, $reading_style );
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

				<a class="mz-reader__nav mz-reader__nav--next <?php echo $next_url ? '' : 'is-disabled'; ?>"
				   href="<?php echo $next_url ? esc_url( $next_url ) : '#'; ?>"
				   aria-label="<?php esc_attr_e( 'Next chapter', 'mangazscans' ); ?>"
				   <?php echo $next_url ? '' : 'aria-disabled="true" tabindex="-1"'; ?>>
					<span class="mz-reader__nav-label"><?php esc_html_e( 'Next', 'mangazscans' ); ?></span>
					<span aria-hidden="true">→</span>
				</a>
			</div>

			<?php if ( $position === 'top' ) : ?>
				<a class="mz-reader__info" href="<?php echo esc_url( $manga_info_link ); ?>"
				   title="<?php esc_attr_e( 'Manga info', 'mangazscans' ); ?>"
				   aria-label="<?php esc_attr_e( 'Manga info', 'mangazscans' ); ?>">
					<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
						<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
						<path d="M12 8h.01M11 12h1v5h1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</a>
			<?php endif; ?>
		</nav>
		<?php
	};

	get_header();
?>

<article class="mz-reader" data-chapter="<?php echo esc_attr( $cur_chap ); ?>" data-id="<?php echo esc_attr( $manga_id ); ?>">

	<?php $render_nav( 'top' ); ?>

	<div class="mz-reader__content read-container reading-content-wrap chapter-type-manga"
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
							// Text / video chapter — plugin renders its own content block.
							$GLOBALS['wp_manga_template']->load_template( 'reading-content/content', 'reading-content', true );
						} else {
							// Image chapter — ALWAYS render list mode (all pages in
							// one long scroll). The paged template shows one image
							// at a time and relies on a page pager that this
							// rewrite intentionally doesn't emit — so forcing list
							// mode is what makes the chapter actually readable end
							// to end. Chapter-level prev/next is in our own
							// .mz-reader__bar; no need for page-within-chapter nav.
							$GLOBALS['wp_manga_template']->load_template( 'reading-content/content', 'reading-list', true );
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
		$manga_reading_discussion = MangazScans::getOption( 'manga_reading_discussion', 'on' );
		if ( $manga_reading_discussion === 'on' && ( comments_open( $manga_id ) || get_comments_number( $manga_id ) > 0 ) ) :
	?>
		<section class="mz-reader__comments">
			<?php do_action( 'wp_manga_discussion' ); ?>
		</section>
	<?php endif; ?>

	<?php
		$minimal_reading_page = MangazScans::getOption( 'minimal_reading_page', 'off' );
		$wp_manga_settings    = get_option( 'wp_manga_settings' );
		$related_manga        = isset( $wp_manga_settings['related_manga'] ) ? (int) $wp_manga_settings['related_manga'] : 0;
		if ( $related_manga === 1 && $minimal_reading_page === 'off' ) :
	?>
		<section class="mz-reader__related">
			<?php get_template_part( '/madara-core/manga', 'related' ); ?>
		</section>
	<?php endif; ?>

	<?php do_action( 'after_manga_single' ); ?>
</article>

<?php get_footer();
