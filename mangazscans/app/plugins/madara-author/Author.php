<?php
	/**
	 * Author extension for the theme
	 *
	 * @package mangazscans
	 */

	namespace App\Plugins\madara_Author;

	class Author {
		private static $instance;

		public static function getInstance() {
			if ( null == self::$instance ) {
				self::$instance = new Author();
			}

			return self::$instance;
		}

		public static function initialize() {

			$author = Author::getInstance();

		}

	}
