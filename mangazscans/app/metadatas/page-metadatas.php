<?php

	/**
	 * Initialize the Page Metaboxes. See /option-tree/assets/theme-mode/demo-meta-boxes.php for reference
	 *
	 * @since Madara Alpha 1.0
	 * @package mangazscans
	 */

	add_action( 'admin_init', 'madara_page_MetaBoxes' );

	if ( ! function_exists( 'madara_page_MetaBoxes' ) ) {
		function madara_page_MetaBoxes() {
			
			$page_meta_boxes = array(
				'id'       => 'page_meta_box',
				'title'    => esc_html__( 'Page Settings', 'mangazscans' ),
				'desc'     => '',
				'pages'    => array( 'page' ),
				'context'  => 'normal',
				'priority' => 'high',
				'fields'   => array(
					array(
						'id'      => 'page_sidebar',
						'label'   => esc_html__( 'Page Sidebar', 'mangazscans' ),
						'desc'    => esc_html__( 'Choose Sidebar Layout for Page', 'mangazscans' ),
						'std'     => 'default',
						'type'    => 'radio-image',
						'class'   => '',
						'choices' => array(
							array(
								'value' => 'default',
								'label' => esc_html__( 'Default', 'mangazscans' ),
								'src'   => get_parent_theme_file_uri( '/images/options/default.png' ),
							),
							array(
								'value' => 'left',
								'label' => esc_html__( 'Left', 'mangazscans' ),
								'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-left.png' ),
							),
							array(
								'value' => 'right',
								'label' => esc_html__( 'Right', 'mangazscans' ),
								'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-right.png' ),
							),
							array(
								'value' => 'full',
								'label' => esc_html__( 'Hidden', 'mangazscans' ),
								'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-hidden.png' ),
							)
						)
					),
				)
			);
			
			global $wp_manga_post_type;
			$manga_status_choices = array();
			if(isset($wp_manga_post_type) && method_exists($wp_manga_post_type,'get_manga_status')){
				$manga_status = $wp_manga_post_type->get_manga_status();
				$manga_status_choices = array(array('value' => '', 'label' => esc_html__( 'All', 'mangazscans' )));
				foreach($manga_status as $key => $value){
					array_push($manga_status_choices, array('value' => $key, 'label' => $value));
				}
			}

			$front_page_meta_boxes = array(
				'id'       => 'frontpage_meta_box',
				'title'    => esc_html__( 'Front Page Settings', 'mangazscans' ),
				'desc'     => '',
				'pages'    => array( 'page' ),
				'context'  => 'normal',
				'priority' => 'high',
				'fields'   => array(

					/*
					 * Page Content Tab
					 * */
					array(
						'label' => esc_html__( 'Page Content', 'mangazscans' ),
						'id'    => 'page_content_tab',
						'type'  => 'tab'
					),
					//Page Content
					array(
						'id'      => 'page_content',
						'label'   => esc_html__( 'Page Content', 'mangazscans' ),
						'desc'    => esc_html__('Choose the content source', 'mangazscans'),
						'std'     => 'page_content',
						'type'    => 'select',
						'choices' => array(
							array(
								'value' => 'page_content',
								'label' => esc_html__( 'Page Content', 'mangazscans' ),
								'src'   => ''
							),
							array(
								'value' => 'blog',
								'label' => esc_html__( 'Blog', 'mangazscans' ),
								'src'   => ''
							),
							array(
								'value' => 'manga',
								'label' => esc_html__( 'Manga', 'mangazscans' ),
								'src'   => ''
							),
						)
					),
					array(
						'id'      => 'manga_type',
						'label'   => esc_html__( 'Manga Type', 'mangazscans' ),
						'desc'    => esc_html__('Type of Manga to display', 'mangazscans'),
						'std'     => '',
						'type'    => 'select',
						'condition' => 'page_content:is(manga)',
						'choices' => array(
							array(
								'value' => '',
								'label' => esc_html__( 'All', 'mangazscans' ),
								'src'   => ''
							),
							array(
								'value' => 'manga',
								'label' => esc_html__( 'Web Comic', 'mangazscans' ),
								'src'   => ''
							),
							array(
								'value' => 'text',
								'label' => esc_html__( 'Web Novel (Text)', 'mangazscans' ),
								'src'   => ''
							),
							array(
								'value' => 'video',
								'label' => esc_html__( 'Web Drama (Video)', 'mangazscans' ),
								'src'   => ''
							)
						)
					),
					array(
						'id'      => 'manga_archives_item_layout',
						'label'   => esc_html__( 'Item Layout', 'mangazscans' ),
						'desc'    => esc_html__('Choose Item Layout', 'mangazscans'),
						'std'     => '',
						'type'    => 'select',
						'condition' => 'page_content:is(manga)',
						'choices' => array(
							array(
								'value' => '',
								'label' => esc_html__( 'Use Theme Options setting', 'mangazscans' )
							),
							array(
								'value' => 'small_thumbnail',
								'label' => esc_html__( 'Default (Small Thumbnail)', 'mangazscans' )
							),
							array(
								'value' => 'big_thumbnail',
								'label' => esc_html__( 'Big Thumbnail', 'mangazscans' )
							),
							array(
								'value' => 'simple',
								'label' => esc_html__( 'Simple List', 'mangazscans' ),
							)
						)
					),
					//Blog Style
					array(
						'id'        => 'archive_margin_top',
						'label'     => esc_html__( 'Content Margin Top', 'mangazscans' ),
						"desc"      => esc_html__( "Margin Top in Listing Content. Default's 50 (in pixel)", "mangazscans" ),
						'std'       => '',
						'type'      => 'text',
						'condition' => 'page_content:not(page_content)',
					),
					array(
						'id'        => 'archive_heading_text',
						'label'     => esc_html__( 'Archives Heading Text', 'mangazscans' ),
						'desc'      => esc_html__( 'Appear in Blog Listing or Manga Listing', 'mangazscans' ),
						'type'      => 'text',
						'condition' => 'page_content:not(page_content)',
					),
					array(
						'id'        => 'archive_heading_icon',
						'label'     => esc_html__( 'Archives Heading Icon', 'mangazscans' ),
						"desc"      => esc_html__( "Icon class, for example 'fa fa-home'", "mangazscans" ) . '</br><a href="http://fontawesome.io/icons/" target="_blank">' . esc_html__( "Font Awesome", "mangazscans" ) . '</a>, <a href="http://ionicons.com/" target="_blank">' . esc_html__( "Ionicons", "mangazscans" ) . '</a>',
						'type'      => 'text',
						'condition' => 'page_content:not(page_content)',
					),
					array(
						'id'        => 'archive_content_columns',
						'label'     => esc_html__( 'Blog Content Columns', 'mangazscans' ),
						'desc'      => esc_html__( 'Columns number of Blog Post', 'mangazscans' ),
						'type'      => 'select',
						'choices'   => array(
							array(
								'value' => 'default',
								'label' => esc_html__( 'Default', 'mangazscans' ),
							),
							array(
								'value' => '3',
								'label' => esc_html__( '3 Columns', 'mangazscans' ),
								'src'   => ''
							),
							array(
								'value' => '2',
								'label' => esc_html__( '2 Columns', 'mangazscans' ),
								'src'   => ''
							),
						),
						'condition' => 'page_content:is(blog)',
					),
					array(
						'id'        => 'archive_navigation',
						'label'     => esc_html__( 'Blog Navigation', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose type of navigation for blog and any listing page. For WP PageNavi, you will need to install WP PageNavi plugin', 'mangazscans' ),
						'std'       => 'default',
						'type'      => 'select',
						'rows'      => '',
						'post_type' => '',
						'taxonomy'  => '',
						'class'     => '',
						'choices'   => array(
							array(
								'value' => 'default',
								'label' => esc_html__( 'Default Theme Options', 'mangazscans' ),
								'src'   => ''
							),
							array(
								'value' => 'ajax',
								'label' => esc_html__( 'Ajax', 'mangazscans' ),
								'src'   => ''
							),
							array(
								'value' => 'wp_pagenavi',
								'label' => esc_html__( 'WP PageNavi', 'mangazscans' ),
								'src'   => ''
							)
						),
						'condition' => 'page_content:not(page_content)',
					),
					
					array(
						'id'        => 'manga_status',
						'label'     => esc_html__( 'Manga Status', 'mangazscans' ),
						'desc'      => esc_html__( 'Filter by manga status', 'mangazscans' ),
						'type'      => 'select',
						'choices'   => $manga_status_choices,
						'condition' => 'page_content:is(manga)',
					),
					
					array(
						'id'        => 'manga_tags',
						'label'     => esc_html__( 'Manga Tags', 'mangazscans' ),
						'desc'      => esc_html__( 'Enter manga tags to get mangs from, separated by a comma', 'mangazscans' ),
						'std'       => '',
						'type'      => 'text',
						'condition' => 'page_content:is(manga)',
					),
					array(
						'id'        => 'manga_genres',
						'label'     => esc_html__( 'Manga Genres', 'mangazscans' ),
						'desc'      => esc_html__( 'Enter manga genres to get mangs from, separated by a comma', 'mangazscans' ),
						'std'       => '',
						'type'      => 'text',
						'condition' => 'page_content:is(manga)',
					),
					array(
						'id'        => 'page_post_count',
						'label'     => esc_html__( 'Post Count', 'mangazscans' ),
						'desc'      => esc_html__( 'Number of posts per page. Default is 10', 'mangazscans' ),
						'std'       => '10',
						'type'      => 'text',
						'condition' => 'page_content:not(page_content)',
					),
					array(
						'id'        => 'page_post_cats',
						'label'     => esc_html__( 'Post Categories', 'mangazscans' ),
						'desc'      => esc_html__( 'Enter category Ids or slugs to get posts from, separated by a comma', 'mangazscans' ),
						'std'       => '',
						'type'      => 'text',
						'condition' => 'page_content:is(blog)',
					),
					array(
						'id'        => 'page_post_tags',
						'label'     => esc_html__( 'Post Tags', 'mangazscans' ),
						'desc'      => esc_html__( 'Enter tags to get posts from, separated by a comma', 'mangazscans' ),
						'std'       => '',
						'type'      => 'text',
						'condition' => 'page_content:is(blog)',
					),
					array(
						'id'        => 'page_post_ids',
						'label'     => esc_html__( 'Post Ids', 'mangazscans' ),
						'desc'      => esc_html__( 'Enter post IDs, separated by a comma.If this param is used, other params are ignored', 'mangazscans' ),
						'std'       => '',
						'type'      => 'text',
						'condition' => 'page_content:is(blog)',
					),
					array(
						'id'        => 'page_post_order',
						'label'     => esc_html__( 'Post Order', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose the order condition', 'mangazscans' ),
						'std'       => '',
						'type'      => 'select',
						'choices'   => array(
							array(
								'value' => 'DESC',
								'label' => esc_html__( 'Descending', 'mangazscans' )
							),
							array(
								'value' => 'ASC',
								'label' => esc_html__( 'Ascending', 'mangazscans' )
							)
						),
						'condition' => 'page_content:is(blog)',
					),
					array(
						'id'        => 'page_post_orderby',
						'label'     => esc_html__( 'Order By', 'mangazscans' ),
						'desc'      => '',
						'std'       => 'date',
						'type'      => 'select',
						'condition' => 'page_content:not(page_content)',
						'choices'   => array(
							array(
								'value' => 'latest',
								'label' => esc_html__( 'New Post', 'mangazscans' )
							),
							array(
								'value' => 'modified',
								'label' => esc_html__( 'Latest Update (or New Manga Chapter)', 'mangazscans' )
							),
							array(
								'value' => 'name',
								'label' => esc_html__( 'Name', 'mangazscans' )
							),
							array(
								'value' => 'rand',
								'label' => esc_html__( 'Random', 'mangazscans' )
							),array(
								'value' => 'rating',
								'label' => esc_html__( 'Rating', 'mangazscans' )
							),
							array(
								'value' => 'trending',
								'label' => esc_html__( 'Trending', 'mangazscans' )
							),
							array(
								'value' => 'views',
								'label' => esc_html__( 'All Time Views', 'mangazscans' )
							)
						)
					),
					array(
						'id'        => 'frontpage_orderby_trending_timerange',
						'label'     => esc_html__( 'Trending Time Range', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose time range to order trending items', 'mangazscans' ),
						'std'       => 'week',
						'type'      => 'select',
						'choices'   => array(
							array(
								'value' => 'all',
								'label' => esc_html__( 'All Time', 'mangazscans' )
							),
							array(
								'value' => 'day',
								'label' => esc_html__( '1 day', 'mangazscans' )
							),
							array(
								'value' => 'week',
								'label' => esc_html__( '1 week', 'mangazscans' )
							),
							array(
								'value' => 'month',
								'label' => esc_html__( '1 month', 'mangazscans' )
							),
							array(
								'value' => 'year',
								'label' => esc_html__( '1 year', 'mangazscans' )
							)
						),
						'condition' => 'page_post_orderby:is(trending)',
					),
					array(
						'id'    => 'manga_filter_by_characters',
						'label' => esc_html__( 'Filter Mangas by title\' first character', 'mangazscans' ),
						'desc'  => esc_html__( 'Show the Characters Filter Bar to filter Mangas by title\' first character', 'mangazscans' ),
						'std'   => 'on',
						'type'  => 'on-off',
						'condition' => 'page_post_orderby:is(name)',
					),
                    
                    array(
						'id'        => 'ignore_ids',
						'label'     => esc_html__( 'Ignore Ids', 'mangazscans' ),
						'desc'      => esc_html__( 'Enter post/manga IDs, separated by a comma to be excluded from the list', 'mangazscans' ),
						'std'       => '',
						'type'      => 'text',
						'condition' => 'page_content:not(page_content)',
					),

					/*
					 * Page Content Tab
					 * */
					array(
						'label' => esc_html__( 'Custom Color', 'mangazscans' ),
						'id'    => 'site_custom_colors_tab',
						'type'  => 'tab'
					),
					array(
						'id'    => 'custom_colors',
						'label' => esc_html__( 'Custom Colors', 'mangazscans' ),
						'desc'  => esc_html__( 'Show Custom Colors settings', 'mangazscans' ),
						'std'   => 'off',
						'type'  => 'on-off',
					),

					array(
						'id'        => 'body_schema',
						'label'     => esc_html__( 'Body Schema', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose Body Color Schema', 'mangazscans' ),
						'std'       => 'default',
						'type'      => 'select',
						'choices'   => array(
							array(
								'value' => 'default',
								'label' => esc_html__( 'Default', 'mangazscans' ),
							),
							array(
								'value' => 'light',
								'label' => esc_html__( 'Light', 'mangazscans' )
							),
							array(
								'value' => 'dark',
								'label' => esc_html__( 'Dark', 'mangazscans' )
							)
						),
						'condition' => 'custom_colors:is(on)'
					),

					array(
						'id'        => 'main_color',
						'label'     => esc_html__( 'Primary Color (Gradient - Start Color)', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose Primary Color of the theme (Gradient - Start Color). Default is: #eb3349', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'custom_colors:is(on)'
					),

					array(
						'id'        => 'main_color_end',
						'label'     => esc_html__( 'Primary Color (Gradient - End Color)', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose Primary Color of the theme (Gradient - End Color)', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'custom_colors:is(on)'
					),

					array(
						'id'        => 'link_color_hover',
						'label'     => esc_html__( 'Link Hover Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose Link Hover Color of the theme. Default is Primary Color', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'custom_colors:is(on)'
					),

					array(
						'id'        => 'star_color',
						'label'     => esc_html__( 'Star Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose Star Color rating in Manga Listing. Default is: #ffd900', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'custom_colors:is(on)'
					),

					array(
						'id'        => 'hot_badges_bg_color',
						'label'     => esc_html__( 'HOT Badges background color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose Background Color for HOT Badges in Manga Listing', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'custom_colors:is(on)'
					),

					array(
						'id'        => 'new_badges_bg_color',
						'label'     => esc_html__( 'NEW Badges backgroundcolor', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose Background Color for NEW Badges in Manga Listing', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'custom_colors:is(on)'
					),

					array(
						'id'        => 'custom_badges_bg_color',
						'label'     => esc_html__( 'CUSTOM Badges backgroundcolor', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose Background Color for Custom Badges in Manga Listing', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'section'   => 'custom_colors',
						'condition' => 'custom_colors:is(on)'
					),

					array(
						'id'        => 'btn_bg',
						'label'     => esc_html__( 'Button Background', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose default Background Color for Buttons', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'custom_colors:is(on)'
					),

					array(
						'id'        => 'btn_color',
						'label'     => esc_html__( 'Button Text Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose default Text Color for Buttons', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'custom_colors:is(on)'
					),

					array(
						'id'        => 'btn_hover_bg',
						'label'     => esc_html__( 'Button Background Hover Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose default Background Hover Color for Buttons', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'custom_colors:is(on)'
					),

					array(
						'id'        => 'btn_hover_color',
						'label'     => esc_html__( 'Button Text Hover Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose default Text Hover Color for Buttons', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'custom_colors:is(on)'
					),

					/*
					 * Header Settings Tab
					 * */
					array(
						'label' => esc_html__( 'Header Settings', 'mangazscans' ),
						'id'    => 'header_settings_tab',
						'type'  => 'tab'
					),
					array(
						'id'      => 'header_style',
						'label'   => esc_html__( 'Header Style', 'mangazscans' ),
						'desc'    => esc_html__( 'Choose Header style. Custom width is 1760px', 'mangazscans' ),
						'std'     => 'default',
						'type'    => 'radio-image',
						'choices' => array(
							array(
								'value' => 'default',
								'label' => esc_html__( 'Default', 'mangazscans' ),
								'src'   => get_parent_theme_file_uri( '/images/options/default.png' ),
							),
							array(
								'value' => '1',
								'label' => esc_html__( 'Container', 'mangazscans' ),
								'src'   => get_parent_theme_file_uri( '/images/options/header/header-container.png' ),
							),
							array(
								'value' => '2',
								'label' => esc_html__( 'Custom Width', 'mangazscans' ),
								'src'   => get_parent_theme_file_uri( '/images/options/header/header-custom-width.png' ),
							),
						),
					),
					array(
						'id'      => 'header_bottom_border',
						'label'   => esc_html__( 'Header Bottom - Border Bottom', 'mangazscans' ),
						'desc'    => esc_html__( 'Enable border bottom of the Header Bottom', 'mangazscans' ),
						'std'     => 'default',
						'type'    => 'select',
						'choices' => array(
							array(
								'value' => 'default',
								'label' => esc_html__( 'Default', 'mangazscans' ),
							),
							array(
								'value' => 'on',
								'label' => esc_html__( 'On', 'mangazscans' ),
							),
							array(
								'value' => 'off',
								'label' => esc_html__( 'Off', 'mangazscans' ),
							),
						),

					),
					array(
						'id'    => 'header_colors',
						'label' => esc_html__( 'Customize Header Colors', 'mangazscans' ),
						'desc'  => esc_html__( 'Change various color settings on Header', 'mangazscans' ),
						'std'   => 'off',
						'type'  => 'on-off',
					),

					array(
						'id'        => 'nav_item_color',
						'label'     => esc_html__( 'Navigation - Item Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose color for menu items on Navigation', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_colors:is(on)'
					),

					array(
						'id'        => 'nav_item_hover_color',
						'label'     => esc_html__( 'Navigation - Item Hover Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose hover color for menu items on Navigation', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_colors:is(on)'
					),

					array(
						'id'        => 'nav_sub_bg',
						'label'     => esc_html__( 'Navigation - Background Color For Sub Menu', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose background color for sub menu of Navigation', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_colors:is(on)'
					),

					array(
						'id'        => 'nav_sub_bg_border_color',
						'label'     => esc_html__( 'Navigation - Sub Menu Item Border Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose color for sub menu item border color', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_colors:is(on)'
					),

					array(
						'id'        => 'nav_sub_item_color',
						'label'     => esc_html__( 'Navigation - Sub Menu Item Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose color for sub menu item of Navigation', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_colors:is(on)'
					),

					array(
						'id'        => 'nav_sub_item_hover_color',
						'label'     => esc_html__( 'Navigation - Sub Menu Item Hover Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose hover color for sub menu item of Navigation', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_colors:is(on)'
					),

					array(
						'id'        => 'nav_sub_item_hover_bg',
						'label'     => esc_html__( 'Navigation - Sub Menu Item Hover Background Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose hover background color for sub menu item of Navigation', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_colors:is(on)'
					),

					array(
						'id'    => 'header_bottom_colors',
						'label' => esc_html__( 'Customize Header Bottom Colors', 'mangazscans' ),
						'desc'  => esc_html__( 'Change various color settings on Header Bottom', 'mangazscans' ),
						'std'   => 'off',
						'type'  => 'on-off',
					),
					array(
						'id'        => 'header_bottom_bg',
						'label'     => esc_html__( 'Header Bottom Background', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose background color for the Header Bottom', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_bottom_colors:is(on)'
					),
					array(
						'id'        => 'bottom_nav_item_color',
						'label'     => esc_html__( 'Second Navigation - Item Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose color for menu items on Navigation', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_bottom_colors:is(on)'
					),

					array(
						'id'        => 'bottom_nav_item_hover_color',
						'label'     => esc_html__( 'Second Navigation - Item Hover Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose hover color for menu items on Second Navigation', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_bottom_colors:is(on)'
					),

					array(
						'id'        => 'bottom_nav_sub_bg',
						'label'     => esc_html__( 'Second Navigation - Background Color For Sub Menu', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose background color for sub menu of Second Navigation', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_bottom_colors:is(on)'
					),

					array(
						'id'        => 'bottom_nav_sub_item_color',
						'label'     => esc_html__( 'Second Navigation - Sub Menu Item Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose color for sub menu item of Second Navigation', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_bottom_colors:is(on)'
					),

					array(
						'id'        => 'bottom_nav_sub_item_hover_color',
						'label'     => esc_html__( 'Second Navigation - Sub Menu Item Hover Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose hover color for sub menu item of Second Navigation', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_bottom_colors:is(on)'
					),

					array(
						'id'        => 'bottom_nav_sub_border_bottom',
						'label'     => esc_html__( 'Second Navigation - Border Bottom Color For Sub Menu', 'mangazscans' ),
						'desc'      => esc_html__( 'Choose border bottom color for sub menu of Second Navigation. Default is Primary Color', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'header_bottom_colors:is(on)'
					),

					array(
						'id'    => 'mobile_menu_color',
						'label' => esc_html__( 'Mobile Menu Custom Color', 'mangazscans' ),
						'desc'  => esc_html__( 'Change various color settings on Mobile Menu', 'mangazscans' ),
						'std'   => 'off',
						'type'  => 'on-off',
					),

					array(
						'id'        => 'canvas_menu_background',
						'label'     => esc_html__( 'Canvas Menu - Background', 'mangazscans' ),
						'desc'      => esc_html__( 'Set Background Color of Canvas Menu', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'mobile_menu_color:is(on)'
					),

					array(
						'id'        => 'canvas_menu_color',
						'label'     => esc_html__( 'Canvas Menu - Menu Item Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Set Color of Item of Canvas Menu', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'mobile_menu_color:is(on)'
					),

					array(
						'id'        => 'canvas_menu_hover',
						'label'     => esc_html__( 'Canvas Menu - Menu Item Hover Color', 'mangazscans' ),
						'desc'      => esc_html__( 'Set Hover Color of Item of Canvas Menu', 'mangazscans' ),
						'std'       => '',
						'type'      => 'colorpicker',
						'condition' => 'mobile_menu_color:is(on)'
					),

					/*
					 * Sidebar Settings Tab
					 * */
					array(
						'label' => esc_html__( 'Sidebar Settings', 'mangazscans' ),
						'id'    => 'sidebar_settings_tab',
						'type'  => 'tab'
					),
					array(
						'id'    => 'custom_sidebar_settings',
						'label' => esc_html__( 'Custom Sidebar Settings', 'mangazscans' ),
						'desc'  => esc_html__( 'Change various settings of Sidebars', 'mangazscans' ),
						'std'   => 'off',
						'type'  => 'on-off',
					),
					array(
						'id'        => 'main_top_sidebar_container',
						'label'     => esc_html__( 'Main Top Sidebar Container', 'mangazscans' ),
						'desc'      => esc_html__( 'Set container for Main Top Sidebar. Custom width is 1760px', 'mangazscans' ),
						'std'       => 'default',
						'type'      => 'select',
						'class'     => '',
						'choices'   => array(
							array(
								'value' => 'default',
								'label' => esc_html__( 'Default', 'mangazscans' ),
							),
							array(
								'value' => 'full_width',
								'label' => esc_html__( 'Full-Width', 'mangazscans' ),
							),
							array(
								'value' => 'container',
								'label' => esc_html__( 'Container', 'mangazscans' ),
							),
							array(
								'value' => 'custom_width',
								'label' => esc_html__( 'Custom Width', 'mangazscans' ),
							)
						),
						'condition' => 'custom_sidebar_settings:is(on)'
					),

					array(
						'id'        => 'main_top_sidebar_background',
						'label'     => esc_html__( 'Main Top Sidebar Background', 'mangazscans' ),
						'desc'      => esc_html__( 'Upload background image for Main Top Sidebar', 'mangazscans' ),
						'std'       => '',
						'type'      => 'background',
						'condition' => 'custom_sidebar_settings:is(on)'
					),

					array(
						'id'        => 'main_top_sidebar_spacing',
						'label'     => esc_html__( 'Main Top Sidebar - Padding', 'mangazscans' ),
						'desc'      => esc_html__( 'Padding in Main Bottom Top. Default value is 50 0 20 0 & unit is px', 'mangazscans' ),
						'std'       => '',
						'type'      => 'spacing',
						'condition' => 'custom_sidebar_settings:is(on)'
					),

					array(
						'id'        => 'main_top_second_sidebar_container',
						'label'     => esc_html__( 'Main Top Second Sidebar Container', 'mangazscans' ),
						'desc'      => esc_html__( 'Set container for Main Top Second Sidebar. Custom width is 1760px', 'mangazscans' ),
						'std'       => 'default',
						'type'      => 'select',
						'class'     => '',
						'choices'   => array(
							array(
								'value' => 'default',
								'label' => esc_html__( 'Default', 'mangazscans' ),
							),
							array(
								'value' => 'full_width',
								'label' => esc_html__( 'Full-Width', 'mangazscans' ),
							),
							array(
								'value' => 'container',
								'label' => esc_html__( 'Container', 'mangazscans' ),
							),
							array(
								'value' => 'custom_width',
								'label' => esc_html__( 'Custom Width', 'mangazscans' ),
							)
						),
						'condition' => 'custom_sidebar_settings:is(on)'
					),
					array(
						'id'        => 'main_top_second_sidebar_background',
						'label'     => esc_html__( 'Main Top Second Sidebar Background', 'mangazscans' ),
						'desc'      => esc_html__( 'Upload background image for Main Top Second Sidebar', 'mangazscans' ),
						'std'       => '',
						'type'      => 'background',
						'condition' => 'custom_sidebar_settings:is(on)'
					),

					array(
						'id'        => 'main_top_second_sidebar_spacing',
						'label'     => esc_html__( 'Main Top Second Sidebar - Padding', 'mangazscans' ),
						'desc'      => esc_html__( 'Padding in Main Top Second Sidebar. Default value is 50 0 20 0 & unit is px', 'mangazscans' ),
						'std'       => '',
						'type'      => 'spacing',
						'condition' => 'custom_sidebar_settings:is(on)'
					),

					array(
						'id'        => 'main_bottom_sidebar_container',
						'label'     => esc_html__( 'Main Bottom Sidebar Container', 'mangazscans' ),
						'desc'      => esc_html__( 'Set container for Main bottom Sidebar. Custom width is 1760px', 'mangazscans' ),
						'std'       => 'default',
						'type'      => 'select',
						'class'     => '',
						'choices'   => array(
							array(
								'value' => 'default',
								'label' => esc_html__( 'Default', 'mangazscans' ),
							),
							array(
								'value' => 'full_width',
								'label' => esc_html__( 'Full-Width', 'mangazscans' ),
							),
							array(
								'value' => 'container',
								'label' => esc_html__( 'Container', 'mangazscans' ),
							),
							array(
								'value' => 'custom_width',
								'label' => esc_html__( 'Custom Width', 'mangazscans' ),
								'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-custom-width.png' ),
							)
						),
						'condition' => 'custom_sidebar_settings:is(on)'
					),
					array(
						'id'        => 'main_bottom_sidebar_background',
						'label'     => esc_html__( 'Main bottom Sidebar Background', 'mangazscans' ),
						'desc'      => esc_html__( 'Upload background image for Main Bottom Sidebar', 'mangazscans' ),
						'std'       => '',
						'type'      => 'background',
						'condition' => 'custom_sidebar_settings:is(on)'
					),

					array(
						'id'        => 'main_bottom_sidebar_spacing',
						'label'     => esc_html__( 'Main Bottom Sidebar - Padding', 'mangazscans' ),
						'desc'      => esc_html__( 'Padding in Main Bottom Sidebar. Default value is 50 0 20 0 & unit is px', 'mangazscans' ),
						'std'       => '',
						'type'      => 'spacing',
						'condition' => 'custom_sidebar_settings:is(on)'
					),
					/*
					 * Other Settings
					 * */
					array(
						'label' => esc_html__( 'Other Settings', 'mangazscans' ),
						'id'    => 'page_settings',
						'type'  => 'tab'
					),
					array(
						'id'      => 'page_title',
						'label'   => esc_html__( 'Page Title', 'mangazscans' ),
						'desc'    => esc_html__('Turn on/off Page Title', 'mangazscans'),
						'std'     => 'on',
						'type'    => 'on-off'
						),
					array(
						'id'      => 'page_meta_tags',
						'label'   => esc_html__( 'Page Meta', 'mangazscans' ),
						'desc'    => esc_html__('Turn on/off Page Meta including published datetime', 'mangazscans'),
						'std'     => 'on',
						'type'    => 'on-off'
						)

				)
			);

			if ( function_exists( 'ot_register_meta_box' ) ) {
				ot_register_meta_box( $page_meta_boxes );
				ot_register_meta_box( $front_page_meta_boxes );
			}
		}
	}

	/**
	 * Return names of meta fields which may contain shortcodes, so they can be parsed in CT Shortcodes plugin to generate custom CSS
	 **/
	add_filter( 'ct_shortcodes_parse_shortcode_custom_css_in_metas', 'madara_meta_fields_contain_shortcodes' );
	function madara_meta_fields_contain_shortcodes( $metas ) {
		$metas = array_merge( $metas, array( 'page_header_content' ) );

		return $metas;
	}
