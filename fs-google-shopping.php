<?php
/**
 * Plugin Name:     F-SHOP Google Shopping
 * Plugin URI:      https://f-shop.top/
 * Description:     Creates a feed Google Shopping. Integration with F-SHOP.
 * Author:          Vitaliy Karakushan
 * Author URI:      https://f-shop.top/
 * Text Domain:     fs-google-shopping
 * Domain Path:     /languages
 * Version:         0.1.0
 * Описание атрибутов: https://support.google.com/merchants/topic/6324338?hl=ru&ref_topic=7294998
 *
 * @package         FS_Google_Shopping
 */


if (!defined('FS_PLUGIN_VER')) {
	add_action('admin_notices', function () {
		echo '<div class="notice notice-warning is-dismissible"><p>Для работы плагина <b>F-SHOP Google Merchant</b> необходимо установить плагин <b>F-SHOP</b>. <a href="https://f-shop.top" target="_blank">Скачать F-SHOP</a></p></div>';
	});

	return;
}

require_once "vendor/autoload.php";

define("FS_GS_FEED", 'googleshopping');
define("FS_GS_PLUGIN_DIR", plugin_dir_path(__FILE__));

new FS_Google_Shopping\Google_Shopping();

register_activation_hook(__FILE__, 'fs_gs_activate');
function fs_gs_activate()
{
	FS_Google_Shopping\Google_Shopping::add_feed();
	flush_rewrite_rules();
}

// Подключаем локализацию
add_action('plugins_loaded', 'fs_gs_load_plugin_textdomain');
function fs_gs_load_plugin_textdomain()
{
	load_plugin_textdomain('fs-google-shopping', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
