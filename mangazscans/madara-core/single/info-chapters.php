<?php
	/**
	 * MangazScans — Chapter list on the manga detail page.
	 *
	 * Overrides madara-core's single/info-chapters.php with our own
	 * 'mz-chapter__*' / 'mz-chapters-*' markup so the compiled
	 * style.css can't reach into this list. Paired stylesheet:
	 * css/mangazscans-manga.css.
	 *
	 * Inputs injected by madara-core via set_query_var() before the
	 * template part is included:
	 *   $manga        array — volumes keyed by volume_id, each with
	 *                 {volume_name, chapters: [...]}
	 *   $manga_id     int   — post id of the manga
	 *   $current_read_chapter int — chapter_id the user is on, if any
	 *
	 * @package mangazscans
	 */

	use App\MangazScans;

	global $wp_manga_storage, $wp_manga_user_actions, $wp_manga_functions;

	// madara-core passes $manga/$manga_id through $args/set_query_var;
	// make sure we have them regardless of how we were included.
	if ( ! isset( $manga ) ) {
		$manga = get_query_var( 'manga', null );
	}
	if ( ! isset( $manga_id ) ) {
		$manga_id = get_query_var( 'manga_id', get_the_ID() );
	}
	if ( ! isset( $current_read_chapter ) ) {
		$current_read_chapter = get_query_var( 'current_read_chapter', 0 );
	}

	$show_more      = MangazScans::getOption( 'manga_single_chapters_list', 'on' ) === 'on';
	$user_id        = get_current_user_id();

	// Collapse the nested-by-volume structure into a single flat list
	// for the common case (most manga have no volumes). When volumes
	// DO exist, keep them but use a lightweight <details> grouping.
	$flat_chapters = array();
	$volumed       = array();

	if ( is_array( $manga ) ) {
		foreach ( $manga as $vol_id => $vol ) {
			if ( (int) $vol_id === 0 && isset( $vol['chapters'] ) ) {
				foreach ( $vol['chapters'] as $chapter ) {
					$flat_chapters[] = $chapter;
				}
			} elseif ( isset( $vol['chapters'] ) && ! empty( $vol['chapters'] ) ) {
				$volumed[] = $vol;
			}
		}
	}

	$total = count( $flat_chapters );
	foreach ( $volumed as $v ) { $total += count( $v['chapters'] ); }

	$unread_chapters = $wp_manga_user_actions
		? $wp_manga_user_actions->get_unread_chapters( $user_id, $manga_id )
		: array();

	$reading_style = $wp_manga_functions->get_reading_style();

	// Render one <li> for a chapter row. Closure so we can reuse it in
	// both the flat list and the volumed list below.
	$render_row = static function ( $chapter ) use ( $manga_id, $reading_style, $current_read_chapter, $unread_chapters, $wp_manga_functions ) {
		$link      = $wp_manga_functions->build_chapter_url( $manga_id, $chapter, $reading_style );
		$time_txt  = $wp_manga_functions->get_time_diff( $chapter['date'] );
		$extend    = isset( $chapter['chapter_name_extend'] )
			? $wp_manga_functions->filter_extend_name( $chapter['chapter_name_extend'] )
			: '';
		$is_unread  = in_array( $chapter['chapter_id'], (array) $unread_chapters, true );
		$is_reading = (int) $current_read_chapter === (int) $chapter['chapter_id'];

		$classes = 'mz-chapter wp-manga-chapter';
		if ( $is_unread )  $classes .= ' is-unread unread';
		if ( $is_reading ) $classes .= ' is-reading reading';

		ob_start();
		?>
		<li class="<?php echo esc_attr( $classes ); ?>">
			<a class="mz-chapter__link" href="<?php echo esc_url( $link ); ?>">
				<span class="mz-chapter__name"><?php echo esc_html( $chapter['chapter_name'] . $extend ); ?></span>
			</a>
			<?php if ( $time_txt ) : ?>
				<time class="mz-chapter__date"
				      datetime="<?php echo esc_attr( mysql2date( 'c', $chapter['date'] ) ); ?>">
					<?php echo esc_html( $time_txt ); ?>
				</time>
			<?php endif; ?>
		</li>
		<?php
		return ob_get_clean();
	};
?>

<?php if ( $total === 0 ) : ?>
	<div class="mz-chapters">
		<header class="mz-chapters__head">
			<h2 class="mz-chapters__title"><?php esc_html_e( 'Chapters', 'mangazscans' ); ?></h2>
		</header>
		<p class="mz-chapters__empty"><?php esc_html_e( 'No chapters yet.', 'mangazscans' ); ?></p>
	</div>
	<?php return; ?>
<?php endif; ?>

<div class="mz-chapters<?php echo $show_more ? ' has-showmore' : ''; ?>">
	<header class="mz-chapters__head">
		<h2 class="mz-chapters__title">
			<?php esc_html_e( 'Chapters', 'mangazscans' ); ?>
			<span class="mz-chapters__count"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
		</h2>
		<button type="button"
			class="mz-chapters__sort btn-reverse-order"
			aria-label="<?php esc_attr_e( 'Reverse order', 'mangazscans' ); ?>"
			title="<?php esc_attr_e( 'Reverse order', 'mangazscans' ); ?>">
			<span aria-hidden="true">↑↓</span>
		</button>
	</header>

	<ul class="mz-chapters__list main version-chap">
		<?php
			do_action( 'madara_before_chapter_listing' );

			foreach ( $flat_chapters as $c ) {
				echo $render_row( $c );
			}

			foreach ( $volumed as $vol ) : ?>
				<li class="mz-chapters__volume">
					<details class="mz-vol" open>
						<summary class="mz-vol__summary"><?php echo esc_html( $vol['volume_name'] ); ?></summary>
						<ul class="mz-vol__list">
							<?php foreach ( $vol['chapters'] as $c ) { echo $render_row( $c ); } ?>
						</ul>
					</details>
				</li>
			<?php endforeach;

			do_action( 'madara_after_chapter_listing' );
		?>
	</ul>

	<?php if ( $show_more && $total > 10 ) : ?>
		<div class="mz-chapters__more">
			<button type="button" class="btn btn-link chapter-readmore mz-chapters__more-btn">
				<?php esc_html_e( 'Show more', 'mangazscans' ); ?>
			</button>
		</div>
	<?php endif; ?>
</div>
