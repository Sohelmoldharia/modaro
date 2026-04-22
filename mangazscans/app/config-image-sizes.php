<?php
	use App\MangazScans;
	
	$mz_image_sizes = array(
		'madara_misc_thumb_1' => array(
			254,
			140,
			true,
			esc_html__( 'Thumb 254x140px', 'mangazscans' ),
			esc_html__( 'This thumb size is used for post thumbnail in Widget Posts & Post Navigation', 'mangazscans' )
		),
		'madara_misc_thumb_2' => array(
			360,
			206,
			true,
			esc_html__( 'Thumb 360x206px', 'mangazscans' ),
			esc_html__( 'This thumb size is used for post thumbnail in Blog Listing', 'mangazscans' )
		),
		'madara_misc_thumb_3' => array(
			125,
			180,
			true,
			esc_html__( 'Thumb 125x180px', 'mangazscans' ),
			esc_html__( 'This thumb size is used for post thumbnail in Popular Slider', 'mangazscans' )
		),
		'madara_misc_thumb_4' => array(
			1200,
			630,
			true,
			esc_html__( 'Thumb 1200x630px', 'mangazscans' ),
			esc_html__( 'This thumb size is used for meta tag og:image', 'mangazscans' )
		),
		'madara_manga_big_thumb' => array(
			175,
			238,
			true,
			esc_html__( 'Thumb 175x238px', 'mangazscans' ),
			esc_html__( 'This thumb size is used for Manga\'s Big Thumbnail item layout in Manga Archives page', 'mangazscans' )
		),
		'madara_manga_big_thumb_full' => array(
			350,
			476,
			true,
			esc_html__( 'Thumb 350x476px', 'mangazscans' ),
			esc_html__( 'This thumb size is used for Manga\'s Big Thumbnail item layout in Manga Archives page when using full-width mode for mobile', 'mangazscans' )
		)
	);

	$mz_image_size_mapping = array(
		'254x140'  => 'madara_misc_thumb_1',
		'360x206'  => 'madara_misc_thumb_2',
		'125x180'  => 'madara_misc_thumb_3',
		'1200x630' => 'madara_misc_thumb_4',
	);