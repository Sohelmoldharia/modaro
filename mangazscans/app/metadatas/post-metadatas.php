<?php

	/**
	 * Initialize the Post Metaboxes. See /option-tree/assets/theme-mode/demo-meta-boxes.php for reference
	 *
	 * @package mangazscans
	 */

	add_action( 'admin_init', 'madara_post_MetaBoxes' );

	if ( ! function_exists( 'madara_post_MetaBoxes' ) ) {
		function madara_post_MetaBoxes() {
			$manga_badges = madara_get_badge_choices();
			
			$badge_choices = array(
							array(
								'value' => 'no',
								'label' => esc_html__( 'No', 'mangazscans' )
							)
						);
			foreach($manga_badges as $badge){
				array_push($badge_choices, array(
									'value' => sanitize_title($badge),
									'label' => $badge
								));
			}
			
			array_push($badge_choices,
							array(
								'value' => 'custom',
								'label' => esc_html__( 'Custom', 'mangazscans' )
							));
			
			$post_meta_boxes = array(
				'id'       => 'manga_other_settings',
				'title'    => esc_html__( 'Other Settings', 'mangazscans' ),
				'desc'     => '',
				'pages'    => array( 'wp-manga' ),
				'context'  => 'normal',
				'priority' => 'high',
				'fields'   => array(

					array(
						'id'       => 'manga_reading_style',
						'label'    => esc_html__( 'Default Reading Style', 'mangazscans' ),
						'desc'     => esc_html__( 'Reading Style specified for Manga', 'mangazscans' ),
						'std'      => 'default',
						'type'    => 'select',
						'choices' => array(
							array(
								'value' => 'default',
								'label' => esc_html__( 'Default', 'mangazscans' )
							),
							array(
								'value' => 'paged',
								'label' => esc_html__( 'Paged', 'mangazscans' )
							),
							array(
								'value' => 'list',
								'label' => esc_html__( 'List', 'mangazscans' )
							),
						)
					),
                    
                    array(
						'id'       => 'manga_reading_oneshot',
						'label'    => esc_html__( 'One Shot Manga', 'mangazscans' ),
						'desc'     => esc_html__( 'Is One Shot Manga? (specified for Manga type)', 'mangazscans' ),
						'std'      => '',
						'type'    => 'select',
						'choices' => array(
							array(
								'value' => '',
								'label' => esc_html__( 'Use Default Setting in Theme Options', 'mangazscans' )
							),
                            array(
								'value' => 'manga',
								'label' => esc_html__( 'Manga', 'mangazscans' )
							),
							array(
								'value' => 'oneshot',
								'label' => esc_html__( 'One Shot', 'mangazscans' )
							)
						)
					),
                    
                    array(
						'id'        => 'manga_oneshot_image_height',
						'label'     => esc_html__( 'Height for Images in this OneShot', 'mangazscans' ),
						'desc'      => esc_html__( 'Enter height value in pixel', 'mangazscans' ),
						'std'       => '200',
						'type'      => 'text',
						'condition' => 'manga_reading_oneshot:is(oneshot)',
					),
					
					array(
						'id'       => 'manga_reading_content_gaps',
						'label'    => esc_html__( 'Gaps between images', 'mangazscans' ),
						'desc'     => esc_html__( 'Enable gaps between images in chapter (used for Manga Chapter Type', 'mangazscans' ),
						'type'     => 'select',
						'choices' => array(
							array(
								'value' => 'default',
								'label' => esc_html__( 'Default', 'mangazscans' )
							),
							array(
								'value' => 'on',
								'label' => esc_html__( 'Yes', 'mangazscans' )
							),
							array(
								'value' => 'off',
								'label' => esc_html__( 'No', 'mangazscans' )
							),
						)
					),

					array(
						'id'       => 'manga_adult_content',
						'label'    => esc_html__( 'Adult Content', 'mangazscans' ),
						'desc'     => esc_html__( 'Mark this manga is Adult Content', 'mangazscans' ),
						'std'      => '',
						'type'     => 'checkbox',
						'operator' => 'and',
						'choices'  => array(
							array(
								'value' => 'yes',
								'label' => esc_html__( 'Yes', 'mangazscans' ),
								'src'   => ''
							)
						)
					),
                    
                    array(
						'id'       => 'manga_expected_total',
						'label'    => esc_html__( 'Expected Total Chapters', 'mangazscans' ),
						'desc'     => esc_html__( 'If you know how many chapters are there in this series, enter it here, even if it is just expectation', 'mangazscans' ),
						'std'      => '',
						'type'     => 'text'
					),

					array(
						'id'      => 'manga_title_badges',
						'label'   => esc_html__( 'Title Badges', 'mangazscans' ),
						'desc'    => esc_html__( 'Choose Manga Title Badges', 'mangazscans' ),
						'std'     => '',
						'type'    => 'select',
						'choices' => $badge_choices
					),
					array(
						'id'        => 'manga_custom_badges',
						'label'     => esc_html__( 'Custom Badge', 'mangazscans' ),
						'desc'      => esc_html__( 'Enter a custom text for Badge', 'mangazscans' ),
						'std'       => '',
						'type'      => 'text',
						'condition' => 'manga_title_badges:is(custom)',
					),
					array(
						'id'        => 'manga_custom_badge_link',
						'label'     => esc_html__( 'Custom Badge URL', 'mangazscans' ),
						'desc'      => esc_html__( 'Set link for badge. Leave empty to not use link', 'mangazscans' ),
						'std'       => '',
						'type'      => 'text'
					),
					
					array(
						'id'        => 'manga_custom_badge_link_target',
						'label'     => esc_html__( 'Badge URL target', 'mangazscans' ),
						'desc'      => esc_html__( 'Open link in new tab or current tab', 'mangazscans' ),
						'std'       => '_self',
						'type'    => 'select',
						'choices' => array(
							array(
								'value' => '_self',
								'label' => esc_html__( 'Current Tab', 'mangazscans' )
							),
							array(
								'value' => '_blank',
								'label' => esc_html__( 'New Tab', 'mangazscans' )
							)
						)
					),
                    
                    array(
						'id'        => 'manga_profile_summary_layout',
						'label'     => esc_html__( 'Summary Layout', 'mangazscans' ),
						'desc'      => esc_html__( 'Layout of Manga Summary Info section', 'mangazscans' ),
						'std'       => '',
						'type'    => 'select',
						'choices' => array(
							array(
								'value' => '',
								'label' => esc_html__( 'Use Default Setting in Theme Options', 'mangazscans' )
							),
                            array(
                                'value' => 1,
                                'label' => esc_html__( 'Layout 1 - Small Featured Image', 'mangazscans' ),
                            ),
                            array(
                                'value' => 2,
                                'label' => esc_html__( 'Layout 2 - Fullsize Featured Image', 'mangazscans' ),
                            )
						)
					),

					array(
						'id'    => 'manga_profile_background',
						'label' => esc_html__( 'Manga Single - Background', 'mangazscans' ),
						'desc'  => esc_html__( 'Upload background image used in Manga detail page', 'mangazscans' ),
						'std'   => '',
						'type'  => 'background',
					),
					
					array(
						'id'    => 'manga_banner',
						'label' => esc_html__( 'Manga Banner Image', 'mangazscans' ),
						'desc'  => esc_html__( 'Upload banner image (horizontal/wide ratio) used in Manga Slider and other places', 'mangazscans' ),
						'std'   => '',
						'type'  => 'upload',
					),

					// SEO customization option
					array(
						'id'    => 'manga_meta_title',
						'label' => esc_html__( 'SEO - Manga Meta Title', 'mangazscans' ),
						'desc'  => esc_html__( 'Custom Meta Title for Manga Post', 'mangazscans' ),
						'std'   => '',
						'type'  => 'text',
					),
					array(
						'id'    => 'manga_meta_desc',
						'label' => esc_html__( 'SEO - Manga Meta Description', 'mangazscans' ),
						'desc'  => esc_html__( 'Custom Meta Description for Manga Post', 'mangazscans' ),
						'std'   => '',
						'type'  => 'text',
					),

				)
			);


			if ( function_exists( 'ot_register_meta_box' ) ) {
				ot_register_meta_box( $post_meta_boxes );
			}

		}
	}
