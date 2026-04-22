<?php
	/**
	 * @package mangazscans
	 */

	$mz_postMeta = new App\Views\ParseMeta();
	$mz_showtags = \App\MangazScans::getOption( 'single_tags', 'on' );
    $mz_single_excerpt = \App\MangazScans::getOption('single_excerpt', 'on');
    $mz_featured_image = \App\MangazScans::getOption('single_featured_image', 'on');
	$thumb_size      = 'full';
?>


<div id="post-<?php the_ID(); ?>" <?php post_class( 'c-blog-post' ); ?>>

    <div class="entry-header">
        <div class="entry-header_wrap">
            <div class="entry-title">
                <h2 class="item-title"><?php the_title(); ?></h2>
            </div>
			<?php $mz_postMeta->renderPostMeta(); ?>
        </div>
    </div>

	<?php if ( has_excerpt() && $mz_single_excerpt == 'on' ) { ?>
        <div class="c-blog__excerpt">
			<?php the_excerpt(); ?>
        </div>
	<?php } ?>

	<?php if ( has_post_thumbnail() && $mz_featured_image == 'on' ) { ?>
        <div class="c-blog__thumbnail">
            <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
				<?php echo madara_thumbnail( $thumb_size ); ?>
            </a>
        </div>
	<?php } ?>

    <div class="entry-content">
        <div class="entry-content_wrap">
			<?php the_content(); ?>
        </div>
    </div>

	<?php if ( $mz_showtags == 'on' && has_tag() ): ?>
        <div class="item-tags">
            <h5><?php esc_html_e('Tags: ', 'mangazscans');?></h5>
			<?php the_tags( '<ul class="list-inline">
                <li>', '</li><li>', '</li></ul>' );
			?>
        </div>
	<?php endif; ?>

	<?php
		wp_link_pages( array(
			'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'mangazscans' ),
			'after'  => '</div>',
		) );
	?>

</div>