<?php
	/**
	 * @package mangazscans
	 */

	$mz_postMeta       = new App\Views\ParseMeta();
	$mz_showtags       = \App\MangazScans::getOption( 'single_tags', 'on' );
	$mz_page_meta_tags = \App\MangazScans::getOption( 'page_meta_tags', 'on' );
	$mz_page_title = \App\MangazScans::getOption( 'page_title', 'on' );
	$thumb_size            = 'full';
?>


<div id="post-<?php the_ID(); ?>" <?php post_class( 'c-blog-post' ); ?>>
	<?php if($mz_page_title == 'on' || $mz_page_meta_tags == 'on'){?>
    <div class="entry-header">
        <div class="entry-header_wrap">
			<?php if($mz_page_title == 'on'){?>
            <div class="entry-title">
                <h1 class="item-title h2"><?php the_title(); ?></h1>
            </div>
			<?php } ?>

			<?php if ( $mz_page_meta_tags == 'on' ) {
				$mz_postMeta->renderPostMeta();
			} ?>
        </div>
    </div>
	<?php } ?>

	<?php if ( has_excerpt() ) { ?>
        <div class="c-blog__excerpt">
			<?php the_excerpt(); ?>
        </div>
	<?php } ?>

	<?php if ( has_post_thumbnail() ) { ?>
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
			<?php the_tags( '<ul class="list-inline">
                <li>
                    <h4 class="heading">' . esc_html__( 'Tags: ', 'mangazscans' ) . '</h4>
                </li><li>', '</li> <li>', '</li></ul>' );
			?>
        </div>
	<?php endif; ?>

	<?php
		wp_link_pages( array(
			'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'mangazscans' ),
			'after'  => '</div>',
		) );
	?>

	<?php 
    
    if(current_user_can('manage_options')){
        edit_post_link( esc_html__( 'Edit', 'mangazscans' ), '<span class="edit-link">', '</span>' );
    }    ?>


</div>