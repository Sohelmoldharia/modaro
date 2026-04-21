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
			 * Initialize the Welcome page
			 */
			Plugins\madara_Welcome\Welcome::initialize();

			/**
			 * Initialize Starter Content package
			 */
			Plugins\madara_Starter_Content\StarterContent::initialize();

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
			} else {
				if ( is_numeric( $args ) ) {
					$args = intval( $args );
				}
				if ( $args == 'false' ) {
					$args = false;
				}
				if ( $args == 'true' ) {
					$args = true;
				}

				$args = str_replace( '\"', '"', $args );
			}

			return $args;
		}

		/**
		 * Ajax call to load next page
		 *
		 * @return HTML
		 */
		function ajax_load_next_page() {

			// Get current page
			$page = intval( $_POST['page'] );

			// current query vars
			$vars = $_POST['vars'];
			if ( ! isset( $vars ) ) {
				$vars = array();
			}

			// convert string value into corresponding data types
			$vars = $this->__format_POST_args( $vars );

			// item template file
			$template = $_POST['template'];

			// Return next page
			$page = intval( $page ) + 1;

			$posts_per_page = isset( $vars['posts_per_page'] ) ? $vars['posts_per_page'] : get_option( 'posts_per_page' );

			if ( $page == 0 ) {
				$page = 1;
			}
			$offset = ( $page - 1 ) * $posts_per_page;
			/*
			 * This is confusing. Just leave it here to later reference
			 *

			 *
			 */


			// get more posts per page than necessary to detect if there are more posts
			$args = array( 'posts_per_page' => $posts_per_page + 1, 'offset' => $offset );
			$args = array_merge( $vars, $args );

			if ( ! isset( $args['post_status'] ) ) {
				$args['post_status'] = 'publish';
			}

			if ( isset( $args['sidebar'] ) ) {
				set_query_var( 'sidebar', $args['sidebar'] );
			}

			if ( isset( $args['archive_content_columns'] ) ) {
				set_query_var( 'archive_content_columns', $args['archive_content_columns'] );
			}

			// remove unnecessary variables
			unset( $args['paged'] );
			unset( $args['p'] );
			unset( $args['page'] );
			unset( $args['pagename'] ); // this is neccessary in case Posts Page is set to a static page
			unset( $args['sidebar'] );
			unset( $args['archive_content_columns'] );

			$query = new \WP_Query( $args );

			$idx = 0;
			set_query_var( 'madara_offset', $offset + 1 );
			set_query_var( 'madara_total', $posts_per_page );

			$manga_archives_item_layout = isset($args['manga_archives_item_layout']) ? $args['manga_archives_item_layout'] : self::getOption( 'manga_archives_item_layout', 'default' );
			
			set_query_var('manga_archives_item_layout', $manga_archives_item_layout);

			$madara_loop_index = '';

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$madara_loop_index ++;
					set_query_var( 'madara_loop_index', $madara_loop_index );

					if ( $madara_loop_index < $posts_per_page + 1 ) {
						if ( ( strpos( $template, 'plugins' ) !== false ) ) {
							include( $template );
						} else {
							//$post_format = get_post_format() ? get_post_format : '';
							get_template_part( $template, get_post_format() );
						}
					}
				}

				if ( $query->post_count <= $posts_per_page ) {
					// there are no more posts
					// print a flag to detect
					echo '<div class="invi no-posts"><!-- --></div>';
				}
			} else {
				// no posts found
			}

			/* Restore original Post Data */
			wp_reset_postdata();
			die( '' );
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
            if(is_admin()){
                $screen = \get_current_screen();
                if($screen){
                    if( 'toplevel_page_madara-welcome' == $screen->id ) {
                        wp_enqueue_style( 'bootstrap', get_parent_theme_file_uri( '/css/bootstrap.min.css' ), array(), '4.6.0' );
                    }
                }
            }
            
            wp_enqueue_style( 'fontawesome', get_parent_theme_file_uri( '/app/lib/fontawesome/web-fonts-with-css/css/all.min.css' ), array(), '5.2.0' );
			wp_enqueue_style( 'madara-admin-style', get_parent_theme_file_uri( '/admin/assets/css/style.css' ) );
            
            
		}

		/**
		 * Scripts for admin
		 */
		function __adminScripts() {
			wp_enqueue_media();

			wp_enqueue_script( 'wp-color-picker' );

			wp_enqueue_script( 'madara-admin', get_parent_theme_file_uri( '/admin/js/madara-admin.js' ), array( 'jquery' ), '1.7', true );

			wp_enqueue_style( 'madara-color-picker', get_parent_theme_file_uri( '/admin/assets/lib/colorpicker-master/jquery.colorpicker.css' ) );
			wp_enqueue_script( 'madara-color-picker', get_parent_theme_file_uri( '/admin/assets/lib/colorpicker-master/jquery.colorpicker.js' ), array( 'jquery' ), '1.2.13', true );
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

		}
        
        function theme_is_activated() {
            return ( 'yes' === get_site_option( 'madara_activated' ) );
        }

        function activate_theme( $code, $supported_until ) {
            update_site_option( 'madara_activated', 'yes' );
            update_site_option( 'madara_purchase_code', $code );
            update_site_option( 'madara_supported_until', $supported_until );
            
            do_action( 'madara_after_theme_activation' );
        }

        function deactivate_theme() {
            delete_site_option( 'madara_activated' );
            delete_site_option( 'madara_purchase_code' );
            
            do_action( 'madara_after_theme_deactivation' );
        }

        function delete_purchase_code() {
            delete_site_option( 'madara_purchase_code' );
        }

        function get_purchase_code() {
            return get_site_option( 'madara_purchase_code' );
        }
        
        /**
         * $rew - boolean - set false to return formated datetime
         **/
        function get_theme_support_date( $raw = true ) {
            $date = get_site_option( 'madara_supported_until' );
            if(!$raw){
                $date = date_i18n(get_option('date_format'), strtotime($date));
            }
            return $date;
        }

        function get_censored_purchase_code() {
            $madara = Madara::getInstance();
            
            $code = $madara->get_purchase_code();
            $hidden_part = substr( $code, 4, -4 );
            if ( $hidden_part ) {
                $code = str_replace( $hidden_part, str_repeat( '*', strlen( $hidden_part ) ), $code );
            }

            return $code;
        }
        
        /**
         * Register site to Mangabooth.com
         *
         * @return array ['error' => '', 'data' => mixed]
         *      $data - in case of unknown error, return WP_Error
                        - in case of success, return ['status' => [valid|error], 'data' => Envato Item Object]
         *
         **/
        function do_validate( $envato_code ){
            $result = array(
                            'error' => '',
                            'data' => ''
                        );

            $site_url = get_site_url();
            // Surrounding whitespace can cause a 404 error, so trim it first
            $envato_code = trim($envato_code);                       

            // Make sure the code looks valid before sending it to Envato
            if (!preg_match("/^([a-f0-9]{8})-(([a-f0-9]{4})-){3}([a-f0-9]{12})$/i", $envato_code)) {
                $result['error'] = esc_html__("Invalid Purchase Code", 'madara');
                
                return $result;
            }

            $args     = array(
                'timeout' => 30,
                'body'    => array(
                    'code' => urlencode( $envato_code ),
                    'site' => urldecode( $site_url ),
                    'action' => 'activate'
                ),
            );
            $response = wp_remote_post( 'https://mangabooth.com/envato', $args );
            
            if ( is_wp_error( $response ) ) {
                $result['error'] = esc_html__('An error occured.', 'madara');
                $result['data'] = $response;
                
                return $result;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            if ( '200' != $response_code ) {
                $result['error'] = $response_code . ': ' . esc_html__( 'Bad request.', 'madara' );
                
                return $result;
            }
            
            $result['data'] = json_decode( wp_remote_retrieve_body( $response ), true );            
            
            if($result['data']['status'] == 'error'){
                $result['error'] = $result['data']['message'];
            }
            
            return $result;
        }
        
        function do_deactivate(){
            $result = array(
                            'error' => '',
                            'data' => ''
                        );

            $site_url = get_site_url();
            $envato_code = $this->get_purchase_code();                       

            // Make sure the code looks valid before sending it to Envato
            if (!preg_match("/^([a-f0-9]{8})-(([a-f0-9]{4})-){3}([a-f0-9]{12})$/i", $envato_code)) {
                $result['error'] = esc_html__("Invalid Purchase Code", 'madara');
            }

            $args     = array(
                'timeout' => 30,
                'body'    => array(
                    'code' => urlencode( $envato_code ),
                    'site' => urldecode( $site_url ),
                    'action' => 'deactivate'                    
                ),
            );
            $response = wp_remote_post( 'https://mangabooth.com/envato', $args );
            
            if ( is_wp_error( $response ) ) {
                $result['error'] = esc_html__('An error occured.', 'madara');
                $result['data'] = $response;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            if ( '200' != $response_code ) {
                $result['error'] = $response_code . ': ' . esc_html__( 'Bad request.', 'madara' );
            }
            
            $result['data'] = json_decode( wp_remote_retrieve_body( $response ), true );
            
            if($result['data']['status'] == 'error'){
                $result['error'] = $result['data']['message'];
            }
            
            return $result;
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
