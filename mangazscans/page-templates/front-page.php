<?php
	/**
	 * Template Name: Front-Page
	 *
	 * @package mangazscans
	 */

	use App\MangazScans;

	$page_content = MangazScans::getOption( 'page_content' );
	if ( $page_content == 'page_content' ) {
		get_template_part( 'page' );
		exit;
	}
	if ( ! class_exists( 'WP_MANGA' ) && $page_content == 'manga' ) {
		get_template_part( 'page' );
		exit;
	}
    
    $page_id = get_the_ID();

	$mz_archive_heading_text = MangazScans::getOption( 'archive_heading_text', '' );
	$mz_archive_heading_icon = MangazScans::getOption( 'archive_heading_icon', '' );
	$archive_content_columns     = MangazScans::getOption( 'archive_content_columns', 3 );
	$archive_margin_top          = get_post_meta( $page_id, 'archive_margin_top', true );
	$archive_margin_top          = isset( $archive_margin_top ) && $archive_margin_top != '' ? $archive_margin_top : '';
	
	
	$mz_sidebar              = madara_get_theme_sidebar_setting();
	$nav_type                    = \App\MangazScans::getOption( 'archive_navigation', 'default' );

	$template = 'html/loop/content';
	
	$manga_type = '';
	
	if ( $page_content == 'manga' ) {
		$post_type = 'wp-manga';
		$manga_type = get_post_meta($page_id, 'manga_type', true);
		$class_1   = 'c-page';
		$class_2   = 'c-page__content manga_content';
		$template  = 'madara-core/content/content-archive';
	} else {
		$post_type = 'post';
		$class_1   = '';
		$class_2   = '';
	}

	if ( ! empty( get_query_var( 'paged' ) ) ) {
		$paged = get_query_var( 'paged' );
	} elseif ( ! empty( get_query_var( 'page' ) ) ) {
		$paged = get_query_var( 'page' );
	} else {
		$paged = 1;
	}
	
	if ( isset($_GET['start']) && $_GET['start'] != '' ) {
		$start = $_GET['start'];
		
		set_query_var( 'starts_with', $start == '0' ? '-0' : $start);
		
		add_filter('madara_ajax_query_arguments', function($args){
					$args['starts_with'] = ($_GET['start'] == '0' ? '-0' : $_GET['start']);
					
					return $args;
		});
	}
	
	$orderby        = MangazScans::getOption( 'page_post_orderby' );
	if($orderby == 'name'){
		$show_alphabeta_filters = MangazScans::getOption( 'manga_filter_by_characters', 'on' ) == 'on' ? true : false;
	} else {
		$show_alphabeta_filters = false;
	}

	if ( $page_content == 'manga' ) {
        $timerange = '';
        $orderby2 = $orderby;
		
		switch($orderby){
			case 'name':
				$orderby2 = 'alphabet';
				break;
			case 'modified':
				$orderby2 = 'latest';
				break;
			case 'latest':
				$orderby2 = 'new-manga';
				break;
			case 'rand':
				$orderby2 = 'random';
				break;
            case 'trending':
                $timerange        = MangazScans::getOption( 'frontpage_orderby_trending_timerange', 'week' );
                break;
			default:
				$orderby2 = $orderby;
				break;
		}

		$args = array('orderby' => $orderby2, 'paged' => $paged, 'timerange' => $timerange);
		
		$meta_query = array('relation' => 'AND');
		if($manga_type != ''){
			$meta_query[] = array('key' => '_wp_manga_chapter_type',
									'value' => $manga_type
									);
		}
		
		$posts_per_page = MangazScans::getOption('page_post_count');
		if($posts_per_page) $args['posts_per_page'] = $posts_per_page;
		
		$args['tax_query'] = array();
		$args['tax_query']['relation'] = 'OR';
		
		$manga_tags = get_post_meta($page_id, 'manga_tags', true);
		if($manga_tags != ''){
			
			$manga_tags_array = explode( ',', $manga_tags );
			foreach ( $manga_tags_array as $g ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'wp-manga-tag',
					'terms'    => sanitize_title(trim($g)),
					'field'    => 'slug',
				);
			}
		}
		
		$manga_genres = get_post_meta($page_id, 'manga_genres', true);
		if($manga_genres != ''){
			
			$manga_genres_array = explode( ',', $manga_genres );
			foreach ( $manga_genres_array as $g ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'wp-manga-genre',
					'terms'    => sanitize_title(trim($g)),
					'field'    => 'slug',
				);
			}
		}
		
		$manga_status = get_post_meta($page_id, 'manga_status', true);
		if($manga_status){
			$meta_query[] = array('key' => '_wp_manga_status',
									'value' => $manga_status
									);
		}
		
		$args['meta_query'] = $meta_query;
        
        $ignore_ids = MangazScans::getOption('ignore_ids');
        if($ignore_ids){
            $args['post__not_in'] = explode(",", $ignore_ids);
        }
		
		$mz_custom_query = madara_manga_query( $args );

		set_query_var( 'sidebar', $mz_sidebar );

		$set_query_var = array(
			'sidebar' => $mz_sidebar,
		);


	} else {
		$mz_custom_query = madara_get_front_page_query( $post_type, $paged, $manga_type );

		set_query_var( 'archive_content_columns', $archive_content_columns );

		$set_query_var = array(
			'archive_content_columns' => $archive_content_columns,
		);

	}

	$mz_custom_query->query = array_merge( $mz_custom_query->query, $set_query_var );

	$mz_post_count = $mz_custom_query->post_count;
	
	if($mz_post_count == 0) {
		global $wp_query;
		  $wp_query->set_404();
		  status_header( 404 );
		  get_template_part( 404 ); exit();
	}
	
	$manga_archives_item_layout = MangazScans::getOption( 'manga_archives_item_layout', 'default' );
	
	get_header();

?>

    <div class="c-page-content style-1">
        <div class="content-area" style="<?php echo esc_attr( $archive_margin_top != '' ? 'margin-top: ' . $archive_margin_top . 'px' : ''); ?>">
            <div class="container">
                <div class="row <?php echo esc_attr( $mz_sidebar == 'left' ? 'sidebar-left' : '') ?>">
                    <?php do_action('madara_before_page_content'); ?>
                    <div class="main-col <?php echo esc_attr( $mz_sidebar != 'full' && ( is_active_sidebar( 'manga_archive_sidebar' ) || is_active_sidebar( 'main_sidebar' ) ) ? 'col-md-8 col-sm-8' : 'sidebar-hidden col-md-12 col-sm-12'); ?>">

						<?php get_template_part( 'html/main-bodytop' ); ?>


                        <div class="main-col-inner <?php echo esc_attr( $class_1 ); ?>">

							<?php if ( $mz_archive_heading_text != '' ) { ?>
                                <div class="c-blog__heading style-2 font-heading <?php echo esc_attr( $mz_archive_heading_icon == '' ? 'no-icon' : '' ); ?>">

                                    <h1 class="h4">

										<?php if ( $mz_archive_heading_icon != '' ) { ?>
                                            <i class="<?php echo esc_attr( $mz_archive_heading_icon ); ?>"></i>
										<?php } ?>

										<?php echo esc_html( $mz_archive_heading_text ); ?>

                                    </h1>
                                </div>
							<?php } ?>

                            <div class="c-blog-listing <?php echo esc_attr( $class_2 ); ?>">
                                <div class="c-blog__inner">
                                    <div class="c-blog__content">
											<?php 
											if($show_alphabeta_filters){
												global $wp;
												manga_listing_alphabeta_bars($wp->request ? home_url('/') . $wp->request : home_url('/'));
											}
											?>
										<?php if ( $mz_custom_query->have_posts() ) : ?>

                                            <div id="loop-content" class="page-content-listing <?php echo esc_attr('item-' . $manga_archives_item_layout);?>">


												<?php
													$index = 1;
													set_query_var( 'madara_post_count', $mz_post_count );
													set_query_var('manga_archives_item_layout', $manga_archives_item_layout);

												?>

												<?php while ( $mz_custom_query->have_posts() ) : $mz_custom_query->the_post(); ?>

													<?php
													set_query_var( 'madara_loop_index', $index );

													if ( $page_content == 'manga' ) {
														get_template_part( 'madara-core/content/content-archive' );
														
													} else {
														get_template_part( 'html/loop/content' );
													}

													$index ++;
													
													do_action('madara-manga-archive-loop', $index);

													?>

												<?php endwhile;
													wp_reset_postdata(); ?>

                                            </div>

										<?php else : ?>

											<?php get_template_part( 'html/loop/content', 'none' ); ?>

										<?php endif; ?>

                                        <script type="text/javascript">
											var manga_args = <?php echo str_replace( '\/', '/', json_encode( $mz_custom_query->query_vars ) ); ?>;
                                        </script>

										<?php
											//Get Pagination
											$mz_pagination = new App\Views\ParsePagination();
											$mz_pagination->renderPageNavigation( '#loop-content', $template, $mz_custom_query, $nav_type );
										?>

                                    </div>
                                </div>
                            </div>

                        </div>


						<?php get_template_part( 'html/main-bodybottom' ); ?>

                    </div>


					<?php
						if ( $mz_sidebar != 'full' && is_active_sidebar( 'main_sidebar' ) ) {
							?>
                            <div class="sidebar-col col-md-4 col-sm-4">
								<?php get_sidebar(); ?>
                            </div>
						<?php }
					?>
                    <?php do_action('madara_after_body_content'); ?>
                </div>
            </div>
        </div>
    </div>


<?php

	get_footer();
