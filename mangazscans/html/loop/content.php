<?php
	/**
	 * @package mangazscans
	 */

	use App\MangazScans;

	$mz_postMeta = new App\Views\ParseMeta();
	$mz_sidebar  = madara_get_theme_sidebar_setting();
	$thumb_size      = array( 360, 206 );

	$archive_content_columns = get_query_var( 'archive_content_columns', MangazScans::getOption( 'archive_content_columns', 3 ) );
	$archive_post_excerpt    = MangazScans::getOption( 'archive_post_excerpt', 'on' );

	$columns_class = 'col-md-4';
	if ( $archive_content_columns == 2 ) {
		$columns_class = 'col-md-6';
	}

	$mz_loop_index = get_query_var( 'madara_loop_index' );
	$mz_post_count = get_query_var( 'madara_post_count' );

?>


<?php if ( $mz_loop_index % $archive_content_columns == 1 ) { ?>
    <div class="row c-row">
<?php } ?>
    <div id="post-<?php the_ID(); ?>" <?php post_class( 'col-12  ' . $columns_class . ' ' ); ?>>
        <div class="c-blog_item <?php echo has_post_thumbnail() ? '' : 'no-thumb'; ?>">

			<?php if ( has_post_thumbnail() ) { ?>
                <div class="c-blog__thumbnail c-image-hover">
                    <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
						<?php echo madara_thumbnail( $thumb_size ); ?>
                    </a>
                </div>
			<?php } ?>

            <div class="c-blog__summary">
                <div class="post-meta total-count font-meta">
					<?php $mz_postMeta->renderPostViews( 1 ); ?><?php $mz_postMeta->renderPostTotalShareCounter( 1, 1 ); ?>
                </div>
                <div class="post-title font-title">
					<?php if ( get_the_title() != '' ) { ?>

						<?php $mz_postMeta->renderPostTitle( 'h4' ); ?>

					<?php } else { ?>
                        <h4 class="heading">
                            <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
								<?php echo get_the_time( get_option( 'date_format' ) ); ?>
                            </a>
                        </h4>
					<?php } ?>

                </div>
				<?php if ( $archive_post_excerpt == 'on' ) { ?>
                    <div class="item-summary">
						<?php the_excerpt(); ?>
                    </div>
				<?php } ?>
            </div>
        </div>
    </div>

<?php if ( $mz_loop_index % $archive_content_columns == 0 || $mz_loop_index == $mz_post_count ) { ?>
    </div>
<?php } ?>