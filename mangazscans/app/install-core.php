<?php
	/**
	 * Silent bundled-plugin installer.
	 *
	 * When MangazScans is activated, extract the bundled madara-core plugin
	 * into wp-content/plugins/madara-core/ (if not already there) and
	 * activate it. Users never have to touch TGM. The standard TGM prompt
	 * ({@see App\Plugins\TGM_Plugin_Activation\ThemeRequired}) is left in
	 * place as a fallback in case the filesystem isn't writable directly
	 * (e.g. shared hosts that require FTP credentials).
	 *
	 * @package mangazscans
	 */

	namespace App;

	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	class CoreInstaller {

		const PLUGIN_SLUG    = 'madara-core';
		const PLUGIN_FILE    = 'madara-core/wp-manga.php';
		const BUNDLED_SOURCE = '/app/plugins/packages/madara-core.zip';

		public static function register() {
			add_action( 'after_switch_theme', array( __CLASS__, 'install_and_activate' ) );
			add_action( 'admin_notices',      array( __CLASS__, 'maybe_render_notice' ) );
		}

		/**
		 * Install (if missing) and activate (if inactive) the bundled plugin.
		 * Silent on success; stores a transient on failure so we can surface
		 * a notice in admin.
		 */
		public static function install_and_activate() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$plugin_dir = WP_PLUGIN_DIR . '/' . self::PLUGIN_SLUG;

			if ( ! is_dir( $plugin_dir ) ) {
				$extracted = self::extract_bundled_plugin();
				if ( is_wp_error( $extracted ) ) {
					set_transient(
						'mangazscans_core_install_error',
						$extracted->get_error_message(),
						MINUTE_IN_SECONDS * 5
					);
					return;
				}
			}

			if ( ! is_plugin_active( self::PLUGIN_FILE ) ) {
				$result = activate_plugin( self::PLUGIN_FILE, '', false, true );
				if ( is_wp_error( $result ) ) {
					set_transient(
						'mangazscans_core_install_error',
						$result->get_error_message(),
						MINUTE_IN_SECONDS * 5
					);
				}
			}
		}

		/**
		 * Extract the bundled plugin zip into wp-content/plugins/.
		 *
		 * @return true|\WP_Error
		 */
		private static function extract_bundled_plugin() {
			$zip = get_template_directory() . self::BUNDLED_SOURCE;
			if ( ! file_exists( $zip ) ) {
				return new \WP_Error(
					'mangazscans_core_missing',
					sprintf( 'Bundled plugin not found at %s', $zip )
				);
			}

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! function_exists( 'unzip_file' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			// Use direct filesystem access; fall back to WP_Filesystem if not allowed.
			if ( ! WP_Filesystem() ) {
				return new \WP_Error(
					'mangazscans_fs_unavailable',
					'WordPress filesystem is not directly writable. Install the "Madara - Core" plugin via the TGM prompt instead.'
				);
			}

			$result = unzip_file( $zip, WP_PLUGIN_DIR );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return true;
		}

		/**
		 * Render an admin notice if the silent install failed.
		 * Success case is silent on purpose — the plugin just works.
		 */
		public static function maybe_render_notice() {
			$err = get_transient( 'mangazscans_core_install_error' );
			if ( ! $err ) {
				return;
			}
			delete_transient( 'mangazscans_core_install_error' );

			echo '<div class="notice notice-error"><p>';
			echo '<strong>MangazScans:</strong> bundled Madara-Core could not be installed automatically. ';
			echo esc_html( $err );
			echo ' You can still install it manually via the standard prompt under <em>Appearance &rarr; Install Plugins</em>.';
			echo '</p></div>';
		}
	}

	CoreInstaller::register();
