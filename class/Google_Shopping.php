<?php


namespace FS_Google_Shopping;

class Google_Shopping
{
	public function __construct()
	{
		add_action('init', [$this, 'add_feed']);
		add_filter('fs_plugin_settings', ['FS_Google_Shopping\Settings', 'plugin_settings_tab']);
		add_filter('fs_taxonomy_fields', ['FS_Google_Shopping\Settings', 'plugin_taxonomy_setting_fields']);

		add_filter('wp', [$this, 'w3_super_cache_deactivate']);
	}

	public static  function w3_super_cache_deactivate()
	{
		if (is_feed(FS_GS_FEED)) {
			add_filter('do_rocket_lazyload', '__return_false');
		}
	}

	/**
	 * Register a feed google shopping
	 */
	public static function add_feed()
	{
		add_feed(FS_GS_FEED, function () {
			return Feed::do_feed();
		});
		if (isset($_GET['gs'])) {
			Feed::do_feed();

		}
	}
}
