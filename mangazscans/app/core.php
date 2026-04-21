<?php
	/**
	 * 1.0
	 * @package    Madara
	 * @author     Madara Team <hi@mangabooth.com>
	 * @copyright  Copyright (C) 2014 mangabooth.com. All Rights Reserved.
	 * @license    GNU/GPL v2 or later http://www.gnu.org/licenses/gpl-2.0.html
	 *
	 * Websites: https://www.mangabooth.com
	 */

	namespace App;

	// Prevent direct access to this file
	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	/**
	 * Core class.
	 *
	 * @package  Madara
	 * @since    1.0
	 */
	class Madara {
		/**
		 * Define theme version.
		 *
		 * @var  string
		 */
		const VERSION = '1.6.5';

		private static $instance;

		public static function getInstance() {
			if ( null == self::$instance ) {
				self::$instance = new Madara();
			}

			return self::$instance;
		}

		/**
		 * Initialize Madara Core.
		 *
		 * @return  void
		 */
		public function initialize() {
			/**
			 * Require Autoload Class
			 *
			 * @since Madara Alpha 1.0
			 *
			 * PLEASE DO NOT DELETE
			 */
			require( 'lib/autoload.php' );

			/**
			 * load theme options, using Option Tree
			 */
			Config\OptionTree::setup();

			/**
			 * Make sure theme has required plugins
			 */
			Plugins\TGM_Plugin_Activation\ThemeRequired::initialize();

			/**
			 * Initialize the Social Accounts Entity
			 */
			Models\Entity\Social::initialize();

			/**
			 * Initialize the Ads Entity
			 */
			Models\Entity\Ads::initialize();

			new Config\ThemeConfig();

			/**
			 * Global setup
			 */
			$this->setup();

			require ( get_template_directory() . '/app/hooks/actions.php');

        }

		/**
		 * Get option from Theme Options. Currently it uses Option Tree plugin for Theme Options
		 *
		 * @param $option string - Option name
		 * @param $default_value - Default value if not set
		 *
		 * @return mixed - Value of option
		 */
		public static function getOption( $option, $default_value = null ) {
			// check if current page or post has its own metadata
			global $wp_query;

			if ( $wp_query->is_singular( ) ) {
				
				$setting = get_post_meta( get_the_ID(), $option, true );

				$is_empty = false;
				if ( ! isset( $setting ) || $setting == '' || $setting == 'default' ) {
					$is_empty = true;
				} elseif(is_array($setting)){
					$is_empty = true;
					foreach($setting as $key => $value){
						if(!empty($value)){
							$is_empty = false;
							break;
						}
					}
				}
				
				if($is_empty){
					// if current page/post setting is empty, then check in Theme Options
					// this requires same names in metadata and theme options
					$setting = \App\Config\OptionTree::getOption( $option, $default_value );
				}

			} else {

				$setting = \App\Config\OptionTree::getOption( $option, $default_value );

			}

			return apply_filters( 'madara_get_option', $setting, $option, $default_value );
		}

		/**
		 * Setup theme
		 */
		private function setup() {
			/**
			 * Hooks to alter template
			 */
			require( 'hooks/template-hooks.php' );

			$this->__registerWidgets();

			/**
			 * Base template tags and functions for this theme.
			 */
			require( 'inc/template-tags.php' );

			require( 'inc/extras.php' );

			/**
			 * Import all Metadatas
			 */
			require( 'metadatas/metadatas.php' );

			add_action( 'wp_enqueue_scripts', array( $this, '__enqueueScripts' ) );

			add_action( 'admin_init', array( $this, '__admin_init' ) );
			add_action( 'admin_print_styles', array( $this, '__adminStyles' ) );
			add_action( 'admin_enqueue_scripts', array( $this, '__adminScripts' ) );

			add_action( 'wp_enqueue_scripts', array( $this, '__enqueueGoogleFonts' ) );

			add_action( 'wp_head', array( $this, '__wp_head' ), 999 );
			add_action( 'wp_footer', array( $this, '__wp_footer' ), 100 );
			add_action( 'login_enqueue_scripts', array( $this, '__admin_login_page' ) );

			/**
			 * Ajax page navigation
			 */
			// when the request action is 'madara_load_more', the ajax_load_next_page() will be called
			add_action( 'wp_ajax_madara_load_more', array( $this, 'ajax_load_next_page' ) );
			add_action( 'wp_ajax_nopriv_madara_load_more', array( $this, 'ajax_load_next_page' ) );
		}
        
        function mangas_for_current_author($query) {
            if(is_admin()){
                $screen = \get_current_screen();
                if($screen){
                    if( 'edit-wp-manga' != $screen->id || !$query->is_admin )
                        return $query;
                  
                    if( !(current_user_can('editor') || current_user_can('administrator')) ) {
                       global $user_ID;
                       $query->set('author', $user_ID );
                    }
                }
            }
            return $query;
        }

		function __admin_init() {
			add_editor_style( 'editor.css' );
            add_filter('pre_get_posts', array($this, 'mangas_for_current_author'));
		}

		/**
		 * fortmat value of WP_Query $args submitted via POST
		 */
		private function __format_POST_args( $args ) {
			if ( is_array( $args ) ) {
				foreach ( $args as $key => $val ) {
					$val          = $this->__format_POST_args( $val );
					$args[ $key ] = $val;
				}
			} elseif ( is_string( $args ) ) {
				if ( is_numeric( $args ) ) {
					$args = intval( $args );
				} elseif ( $args === 'false' ) {
					$args = false;
				} elseif ( $args === 'true' ) {
					$args = true;
				} else {
					$args = sanitize_text_field( $args );
				}
			}

			return $args;
		}

		/**
		 * Ajax call to load next page.
		 *
		 * Hardened: requires the 'madara_load_more' nonce sent in $_POST['nonce']
		 * and only loads templates whose slug resolves inside the active theme.
		 * The legacy code path that include()'d an arbitrary $_POST['template']
		 * (when its name contained "plugins") was a Local File Inclusion sink
		 * and has been removed.
		 */
		function ajax_load_next_page() {

			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'madara_load_more' ) ) {
				wp_send_json_error( 'invalid_nonce', 403 );
			}

			$page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 0;
			$page = $page + 1;
			if ( $page < 1 ) {
				$page = 1;
			}

			$vars = isset( $_POST['vars'] ) && is_array( $_POST['vars'] ) ? wp_unslash( $_POST['vars'] ) : array();
			$vars = $this->__format_POST_args( $vars );

			$template_slug = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
			$template_slug = $this->sanitize_template_slug( $template_slug );
			if ( $template_slug === '' ) {
				wp_send_json_error( 'invalid_template', 400 );
			}

			$posts_per_page = isset( $vars['posts_per_page'] ) ? intval( $vars['posts_per_page'] ) : intval( get_option( 'posts_per_page' ) );
			if ( $posts_per_page < 1 ) {
				$posts_per_page = 10;
			}
			$offset = ( $page - 1 ) * $posts_per_page;

			$args = array_merge( $vars, array(
				'posts_per_page' => $posts_per_page + 1,
				'offset'         => $offset,
			) );

			if ( ! isset( $args['post_status'] ) ) {
				$args['post_status'] = 'publish';
			}

			if ( isset( $args['sidebar'] ) ) {
				set_query_var( 'sidebar', $args['sidebar'] );
			}

			if ( isset( $args['archive_content_columns'] ) ) {
				set_query_var( 'archive_content_columns', $args['archive_content_columns'] );
			}

			unset( $args['paged'], $args['p'], $args['page'], $args['pagename'], $args['sidebar'], $args['archive_content_columns'] );

			$query = new \WP_Query( $args );

			set_query_var( 'madara_offset', $offset + 1 );
			set_query_var( 'madara_total', $posts_per_page );

			$manga_archives_item_layout = isset( $args['manga_archives_item_layout'] )
				? $args['manga_archives_item_layout']
				: self::getOption( 'manga_archives_item_layout', 'default' );
			set_query_var( 'manga_archives_item_layout', $manga_archives_item_layout );

			$madara_loop_index = 0;

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$madara_loop_index++;
					set_query_var( 'madara_loop_index', $madara_loop_index );

					if ( $madara_loop_index < $posts_per_page + 1 ) {
						get_template_part( $template_slug, get_post_format() );
					}
				}

				if ( $query->post_count <= $posts_per_page ) {
					echo '<div class="invi no-posts"><!-- --></div>';
				}
			}

			wp_reset_postdata();
			wp_die();
		}

		/**
		 * Reduce a caller-supplied template slug to a safe relative path that
		 * get_template_part() can resolve inside the (parent or child) theme.
		 *
		 * Strips any leading slash, drops the .php suffix, blocks path
		 * traversal, and rejects anything that isn't [a-zA-Z0-9_/-].
		 */
		private function sanitize_template_slug( $slug ) {
			if ( ! is_string( $slug ) || $slug === '' ) {
				return '';
			}
			$slug = ltrim( $slug, '/' );
			$slug = preg_replace( '/\.php$/i', '', $slug );
			if ( strpos( $slug, '..' ) !== false ) {
				return '';
			}
			if ( ! preg_match( '#^[a-zA-Z0-9_\-/]+$#', $slug ) ) {
				return '';
			}
			return $slug;
		}


		/**
		 * Register widgets
		 */
		protected function __registerWidgets() {

			/**
			 * Extend widgets behavior
			 */
			$widgetExtension = new \App\Plugins\Widgets\WidgetExtension();
		}

		/**
		 * Parse Google Fonts
		 *
		 * @since Madara Alpha 1.0
		 */
		function __enqueueGoogleFonts() {
			$getGoogleFonts = new Views\ParseGoogleFonts();

			$googleFonts = $getGoogleFonts->render();

			if ( $googleFonts ) {
				wp_enqueue_style( 'google-fonts', '//fonts.googleapis.com/css?family=' . implode( '|', $googleFonts ) );
			}
		}

		/**
		 * Enqueue LESS styles
		 */
		function __enqueue_less_styles( $style_tag, $handle ) {

			global $wp_styles;
			$obj = $wp_styles->query( $handle );
			if ( $obj === false ) {
				return $style_tag;
			}
			if ( ! preg_match( '/\.less$/U', $obj->src ) ) {
				return $style_tag;
			}
			/*
			 * The current stylesheet is a LESS stylesheet, so make according changes
			 * */
			$rel       = isset( $obj->extra['alt'] ) && $obj->extra['alt'] ? 'alternate stylesheet' : 'stylesheet';
			$style_tag = str_replace( "rel='" . $rel . "'", "rel='stylesheet/less'", $style_tag );
			$style_tag = str_replace( "id='" . $handle . "-css'", "id='" . $handle . "-less'", $style_tag );

			return $style_tag;
		}

		/**
		 * Enqueue scripts and styles.
		 */
		function __enqueueScripts() {
			if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
				wp_enqueue_script( 'comment-reply' );
			}

			wp_enqueue_script( 'madara-core', get_parent_theme_file_uri( '/js/core.js' ), array( 'jquery' ), '', true );

			if ( $this->getOption( 'scroll_effect', 'off' ) == 'on' ) {
				wp_enqueue_script( 'smoothscroll', get_parent_theme_file_uri( '/js/smoothscroll.js' ), array(), '1.4.10', true );
			}

			if ( $this->getOption( 'lazyload', 'off' ) == 'on' ) {
				wp_enqueue_script( 'lazysizes', get_parent_theme_file_uri( '/js/lazysizes/lazysizes.min.js' ), array(), '5.3.2', true );
			}


			wp_enqueue_script( 'bootstrap', get_parent_theme_file_uri( '/js/bootstrap.min.js' ), array(), '4.6.0', true );
			wp_enqueue_script( 'shuffle', get_parent_theme_file_uri( '/js/shuffle.min.js' ), array(), '5.3.0', true );
		}

		/**
		 * Style for admin
		 */
		function __adminStyles() {
			wp_enqueue_style( 'fontawesome', get_parent_theme_file_uri( '/app/lib/fontawesome/web-fonts-with-css/css/all.min.css' ), array(), '5.15.3' );
			wp_enqueue_style( 'madara-admin-style', get_parent_theme_file_uri( '/admin/assets/css/style.css' ) );
		}

		/**
		 * Scripts for admin
		 */
		function __adminScripts() {
			wp_enqueue_media();

			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );

			wp_enqueue_script( 'madara-admin', get_parent_theme_file_uri( '/admin/js/madara-admin.js' ), array( 'jquery', 'wp-color-picker' ), '1.7', true );
		}

		/**
		 * add extra meta tags in HEAD
		 */
		function __wp_head() {
			/**
			 * pre-built meta tags
			 */
			if ( Madara::getOption( 'echo_meta_tags', 'on' ) == 'on' ) {
				if ( function_exists( 'madara_meta_tags' ) ) {
					madara_meta_tags();
				}
			}

			$mobile_header_color = Madara::getOption('mobile_browser_header_color','');
			if($mobile_header_color != ''){
				?>
				<meta name="theme-color" content="<?php echo esc_html($mobile_header_color);?>"/>
				<?php
			}

			// Default favicon: only inject if the user hasn't configured a Site Icon
			// in the WP Customizer. WP emits its own <link rel> for site_icon.
			if ( ! has_site_icon() ) {
				$base = get_parent_theme_file_uri();
				echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( $base . '/images/favicon.svg' ) . '">' . "\n";
				echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( $base . '/images/favicon.png' ) . '">' . "\n";
				echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( $base . '/images/favicon-180.png' ) . '">' . "\n";
			}
		}
        
		/**
		 * add custom code to footer
		 */
		function __wp_footer() {
			do_action( 'madara-footer' );
		}

		/**
		 * custom login page
		 */
		function __admin_login_page() {
			if ( $img = Madara::getOption( 'login_logo_image' ) ) {
				?>
                <style type="text/css">
                    body.login div#login h1 a {
                        background-image: url(<?php echo esc_url($img); ?>);
                        width: 320px;
                        height: 120px;
                        background-size: auto;
                        background-position: center;
                    }
                </style>
			<?php }
		}

		/**
		 * Get current theme name
		 */
		public static function getThemeName() {
			$theme = wp_get_theme();

			return $theme->get( 'Name' );
		}

		/**
		 * Get current theme vesion
		 */
		public static function getThemeVersion() {
			$theme = wp_get_theme();

			return $theme->get( 'Version' );
		}
	}
