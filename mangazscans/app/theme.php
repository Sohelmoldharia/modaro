<?php

	/**
	 * MangazScans theme bootstrap.
	 *
	 * Defines MangazScansStarter (the concrete theme class) on top of the
	 * App\MangazScans base in core.php, wires up sidebars, menus, and the
	 * front-end enqueue graph, and pulls in manga-specific glue when the
	 * bundled madara-core (WP Manga) plugin is active.
	 *
	 * @package mangazscans
	 * @license GNU/GPL v2 or later
	 */

	namespace App;

	// Prevent direct access to this file
	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	require( get_template_directory() . '/app/core.php' );

	// Silent installer for the bundled Madara-Core plugin. Registers an
	// after_switch_theme hook so the plugin auto-installs+activates on
	// theme activation without any user interaction.
	require( get_template_directory() . '/app/install-core.php' );

	require( 'lib/walker_mobile_menu.class.php' );

	if ( class_exists( 'WP_MANGA' ) ) {
		/*
		 * check plugin wp-manga active or not.
		 * */
		require( get_template_directory() . '/manga-functions.php' );

		// Theme-level chapter storage backends (ImgChest, Direct URLs).
		// Only loaded when Madara-Core is active, because they hook
		// Madara-Core filters and AJAX handlers.
		require( get_template_directory() . '/app/storage/bootstrap.php' );
	}

	/**
	 * Theme-level starter class. Extends the base in core.php with the
	 * concrete initialize() that registers sidebars, menus, enqueues,
	 * and template tags for the front-end.
	 */
	class MangazScansStarter extends MangazScans {

		private static $instance;

		public static function getInstance() {
			if ( null == self::$instance ) {
				self::$instance = new MangazScansStarter();
			}

			return self::$instance;
		}

		/**
		 * Initialize Madara Core.
		 *
		 * @return  void
		 */
		public function initialize() {
			add_action( 'template_redirect', array( $this, 'set_content_width' ), 0 );

			parent::initialize();

			/**
			 * Custom template tags and functions for this theme.
			 */
			require( get_template_directory() . '/inc/template-tags.php' );
			require( get_template_directory() . '/inc/extras.php' );
			require( get_template_directory() . '/inc/hooks.php' );

			add_action( 'after_setup_theme', array( $this, 'addThemeSupport' ) );
			add_action( 'widgets_init', array( $this, 'registerSidebar' ) );
			add_action( 'after_setup_theme', array( $this, 'registerNavMenus' ) );

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ) );

			add_filter( 'theme_page_templates', array( $this, 'makewp_exclude_page_templates' ) );
		}

		/**
		 * Set the content width in pixels, based on the theme's design and stylesheet.
		 *
		 * Priority 0 to make it available to lower priority callbacks.
		 *
		 * @global int $content_width
		 */
		function set_content_width() {

			$content_width = 980;

			$GLOBALS['content_width'] = apply_filters( 'madara_content_width', $content_width );
		}

		/**
		 * Hides the custom post template for pages on WordPress 4.6 and older
		 *
		 * @param array $post_templates Array of page templates. Keys are filenames, values are translated names.
		 *
		 * @return array Filtered array of page templates.
		 */
		function makewp_exclude_page_templates( $post_templates ) {
			if ( version_compare( $GLOBALS['wp_version'], '4.7', '<' ) ) {
				// unset( $post_templates['page-templates/my-full-width-post-template.php'] );
			}

			return $post_templates;
		}

		/**
		 * Add Theme Support
		 *
		 * @return void
		 */
		function addThemeSupport() {

			load_theme_textdomain( 'mangazscans', get_template_directory() . '/languages' );

			add_theme_support( 'automatic-feed-links' );

			add_theme_support( "title-tag" );

			add_theme_support( 'post-thumbnails' );

			add_theme_support( 'custom-background' );

			add_theme_support( 'custom-header' );

			add_theme_support( 'html5', array(
				'comment-form',
				'comment-list',
				'search-form',
				'gallery',
				'caption',
			) );
			
			add_theme_support( 'wp-block-styles' );
			add_theme_support( 'responsive-embeds' );
			add_theme_support( 'align-wide' );
			add_theme_support( 'align-full' );

			// register thumb sizes
			do_action( 'madara_reg_thumbnail' );
		}

		/**
		 * Madara Sidebar Init
		 *
		 * @since Madara Alpha 1.0
		 */
		function registerSidebar() {
			/*
			 * register WP Manga Main Top Sidebar & WP Manga Main Top Second Sidebar when plugin wp-manga activated.
			 * */
			do_action( 'madara_add_manga_sidebar' );

			$main_sidebar_before_widget = apply_filters( 'madara_main_sidebar_before_widget', '<div class="row"><div id="%1$s" class="widget %2$s"><div class="widget__inner %2$s__inner c-widget-wrap">' );
			$main_sidebar_after_widget  = apply_filters( 'madara_main_sidebar_after_widget', '</div></div></div>' );

			$before_widget = apply_filters( 'madara_sidebar_before_widget', '<div id="%1$s" class="widget %2$s"><div class="widget__inner %2$s__inner c-widget-wrap">' );
			$after_widget  = apply_filters( 'madara_sidebar_after_widget', '</div></div>' );

			$before_title = '<div class="widget-heading font-nav"><h5 class="heading">';
			$after_title  = '</h5></div>';

			register_sidebar( array(
				'name'          => esc_html__( 'Main Sidebar', 'mangazscans' ),
				'id'            => 'main_sidebar',
				'description'   => esc_html__( 'Main Sidebar used by all pages', 'mangazscans' ),
				'before_widget' => $main_sidebar_before_widget,
				'after_widget'  => $main_sidebar_after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Single Post Sidebar', 'mangazscans' ),
				'id'            => 'single_post_sidebar',
				'description'   => esc_html__( 'Appear in Single Post', 'mangazscans' ),
				'before_widget' => $main_sidebar_before_widget,
				'after_widget'  => $main_sidebar_after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Search Sidebar', 'mangazscans' ),
				'id'            => 'search_sidebar',
				'description'   => esc_html__( 'Search Sidebar in header', 'mangazscans' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Main Top Sidebar', 'mangazscans' ),
				'id'            => 'top_sidebar',
				'description'   => esc_html__( 'Appear before main content', 'mangazscans' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Main Top Second Sidebar', 'mangazscans' ),
				'id'            => 'top_second_sidebar',
				'description'   => esc_html__( 'Appear before main content', 'mangazscans' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Body Top Sidebar', 'mangazscans' ),
				'id'            => 'body_top_sidebar',
				'description'   => esc_html__( 'Appear before body content', 'mangazscans' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Body Bottom Sidebar', 'mangazscans' ),
				'id'            => 'body_bottom_sidebar',
				'description'   => esc_html__( 'Appear after body content', 'mangazscans' ),
				'before_widget' => '<div id="%1$s" class="widget %2$s"><div class="widget__inner %2$s__inner c-widget-wrap">',
				'after_widget'  => '</div></div>',
				'before_title'  => '<div class="widget-title"><div class="c-blog__heading style-2 font-heading"><h4>',
				'after_title'   => '</h4></div></div>',
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Main Bottom Sidebar', 'mangazscans' ),
				'id'            => 'bottom_sidebar',
				'description'   => esc_html__( 'Appear after main content', 'mangazscans' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );
			
			register_sidebar( array(
				'name'          => esc_html__( 'Footer Sidebar', 'mangazscans' ),
				'id'            => 'footer_sidebar',
				'description'   => esc_html__( 'Appear in Footer', 'mangazscans' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );
		}

		/**
		 * Register Menu Location
		 *
		 * @since Madara Alpha 1.0
		 */
		function registerNavMenus() {
			register_nav_menus( array(
				'primary_menu'   => esc_html__( 'Primary Menu', 'mangazscans' ),
				'secondary_menu' => esc_html__( 'Secondary Menu', 'mangazscans' ),
				'mobile_menu'    => esc_html__( 'Mobile Menu', 'mangazscans' ),
				'user_menu'      => esc_html__( 'User Menu', 'mangazscans' ),
				'footer_menu'    => esc_html__( 'Footer Menu', 'mangazscans' ),
			) );
		}

		/**
		 * Enqueue needed scripts
		 */
		function enqueueScripts() {
			if ( $this->getOption( 'loading_fontawesome', 'on' ) == 'on' ) {
				wp_enqueue_style( 'fontawesome', get_parent_theme_file_uri( '/app/lib/fontawesome/web-fonts-with-css/css/all.min.css' ), array(), '5.15.3' );
			}
			if ( $this->getOption( 'loading_ionicons', 'on' ) == 'on' ) {
				wp_enqueue_style( 'ionicons', get_parent_theme_file_uri( '/css/fonts/ionicons/css/ionicons.min.css' ), array(), '4.5.10' );
			}

			wp_enqueue_style( 'bootstrap', get_parent_theme_file_uri( '/css/bootstrap.min.css' ), array(), '4.3.1' );
			wp_enqueue_style( 'slick', get_parent_theme_file_uri( '/js/slick/slick.css' ), array(), '1.9.0' );
			wp_enqueue_style( 'slick-theme', get_parent_theme_file_uri( '/js/slick/slick-theme.css' ) );
            
            // currently on Oneshot Reading page is needed for lightbox
            $is_manga_oneshot = (defined('WP_MANGA_VER') && WP_MANGA_VER >= 1.66) ? is_manga_oneshot() : 0;
            
            if($is_manga_oneshot){
                wp_enqueue_style( 'lightbox', get_parent_theme_file_uri( '/css/lightbox.min.css' ), array(), '2.11.2' );
            }
			//Temporary
			wp_enqueue_style( 'loaders', get_parent_theme_file_uri( '/css/loaders.min.css' ) );

			wp_enqueue_style( 'madara-css', get_stylesheet_uri(), array(), '2.6.0' );

			// MangazScans overrides: content/body surface polish on top
			// of madara-css (manga grid, reader, etc.).
			wp_enqueue_style(
				'mangazscans-overrides',
				get_parent_theme_file_uri( '/css/mangazscans-overrides.css' ),
				array( 'madara-css' ),
				'2.6.0'
			);

			// Chrome: our own header + footer. Dedicated 'mz-*' namespace,
			// loaded last so nothing in madara-css or the overrides layer
			// can reach into our navigation/footer markup by accident.
			wp_enqueue_style(
				'mangazscans-chrome',
				get_parent_theme_file_uri( '/css/mangazscans-chrome.css' ),
				array( 'mangazscans-overrides' ),
				'2.6.0'
			);
			wp_enqueue_script(
				'mangazscans-chrome',
				get_parent_theme_file_uri( '/js/mangazscans-chrome.js' ),
				array(),
				'2.6.0',
				true
			);

			// Manga detail page stylesheet — only the single-manga view
			// needs this, and it ships the mz-manga__* rules that back
			// the rewritten madara-core/manga-single.php template.
			if ( function_exists( 'is_manga_single' ) && is_manga_single() ) {
				wp_enqueue_style(
					'mangazscans-manga',
					get_parent_theme_file_uri( '/css/mangazscans-manga.css' ),
					array( 'mangazscans-chrome' ),
					'2.6.0'
				);
			}

			// Homepage stylesheet — only the site root needs it.
			if ( is_front_page() || is_home() ) {
				wp_enqueue_style(
					'mangazscans-home',
					get_parent_theme_file_uri( '/css/mangazscans-home.css' ),
					array( 'mangazscans-chrome' ),
					'2.6.0'
				);
			}

			// Chapter reader stylesheet — only the reading page.
			if ( function_exists( 'is_manga_reading_page' ) && is_manga_reading_page() ) {
				wp_enqueue_style(
					'mangazscans-reader',
					get_parent_theme_file_uri( '/css/mangazscans-reader.css' ),
					array( 'mangazscans-chrome' ),
					'2.6.0'
				);
			}

			wp_enqueue_script( 'imagesloaded' );
			wp_enqueue_script( 'slick', get_parent_theme_file_uri( '/js/slick/slick.min.js' ), array( 'jquery' ), '1.9.0', true );
			// (aos.js was dropped in Phase A; the enqueue is gone with it.)
			
            wp_enqueue_script( 'madara-js', get_parent_theme_file_uri( '/js/template.js' ), array(
				'jquery',
				'bootstrap',
				'shuffle'
			), '1.7.3', true );
            
            if($is_manga_oneshot){
                wp_enqueue_script( 'lightbox', get_parent_theme_file_uri( '/js/lightbox.min.js' ), array( 'jquery' ), '2.11.2', true );
            }
            
            global $wp_manga_functions;
            
            if($wp_manga_functions && $wp_manga_functions->is_user_settings_page() && isset($_GET['tab']) && $_GET['tab'] == 'account-settings') {
                wp_enqueue_script( 'password-strength-meter' );
                wp_enqueue_script( 'madara-js-user-settings', get_parent_theme_file_uri( '/js/template-user-settings.js' ), array(
				'madara-js'), '1.7.1.1', true );
            }
            
			wp_enqueue_script( 'madara-ajax', get_parent_theme_file_uri( '/js/ajax.js' ), array( 'jquery' ), '', true );

			$js_params = array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) );

			global $wp_query, $wp;

			$js_params['query_vars']      = $wp_query->query_vars;
			$js_params['current_url']     = home_url( $wp->request );
			$js_params['load_more_nonce'] = wp_create_nonce( 'madara_load_more' );

			wp_localize_script( 'madara-js', 'mangazscans', apply_filters( 'madara_js_params', $js_params ) );

			/**
			 * Add Custom CSS
			 */
			require( get_template_directory() . '/css/custom.css.php' );
			$custom_css = mangazscans_custom_CSS();
			wp_add_inline_style( 'madara-css', $custom_css );
		}		

	}


	$mangazscans_theme = MangazScansStarter::getInstance();
	$mangazscans_theme->initialize();

	/**
	 * Back-compat: the previous identifier was App\MadaraStarter. Any
	 * caller still using that name keeps working via this alias.
	 */
	if ( ! class_exists( __NAMESPACE__ . '\\MadaraStarter', false ) ) {
		class_alias( __NAMESPACE__ . '\\MangazScansStarter', __NAMESPACE__ . '\\MadaraStarter' );
	}
