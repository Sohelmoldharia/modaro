<?php

	/**
	 * Plugins MangazScans expects to find. Madara - Core (a.k.a. WP Manga)
	 * provides the manga post type, chapter reader and the wp_manga_* glue
	 * that the theme leans on; Option Tree powers Theme Options.
	 *
	 * The bundled madara-shortcodes plugin was dropped to keep the theme
	 * lighter; install it manually if you use [manga_listing] etc.
	 */
	$mz_required_plugins = array(
		array(
			'name'     => 'Option Tree',
			'slug'     => 'option-tree',
			'required' => true,
			'version'  => '2.7.3.2',
		),

		array(
			'name'     => 'Madara - Core',
			'slug'     => 'madara-core',
			'source'   => get_template_directory() . '/app/plugins/packages/madara-core.zip',
			'required' => true,
			'version'  => '1.7.3.1',
		),

		array(
			'name'     => 'Widget Logic',
			'slug'     => 'widget-logic',
			'required' => false,
		),
	);