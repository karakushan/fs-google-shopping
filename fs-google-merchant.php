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
 *
 * @package         Fs_Google_Merchant
 */

/*
 *  Описание атрибутов https://support.google.com/merchants/topic/6324338?hl=ru&ref_topic=7294998
 */


require_once "vendor/autoload.php";

define( "FS_GOOGLE_SHOPING_FEED", 'googleshopping' );

use LukeSnowden\GoogleShoppingFeed\Containers\GoogleShopping;

register_activation_hook( __FILE__, 'fs_googleshopping_activate' );
function fs_googleshopping_activate() {
	fs_googleshopping_add_feed();
	flush_rewrite_rules();
}

// Register a feed googleshopping
function fs_googleshopping_add_feed() {
	if ( ! defined( 'FS_PLUGIN_VER' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-warning is-dismissible"><p>Для работы плагина <b>F-SHOP Google Merchant</b> необходимо установить плагин <b>F-SHOP</b>. <a href="https://f-shop.top" target="_blank">Скачать F-SHOP</a></p></div>';
		} );

		return;
	}
	add_feed( FS_GOOGLE_SHOPING_FEED, 'fs_googleshopping_do_feed' );
}

add_action( 'init', 'fs_googleshopping_add_feed' );

function fs_googleshopping_settings_tab( $settings ) {
	$settings['google_shopping'] = array(
		'name'        => __( 'Google Merchant', 'fast-shop' ),
		'description' => sprintf( 'Адрес вашего фида: <a href="%1$s" target="_blank">%1$s</a>. </br> На мультиязычном сайте необходимо добавить приставку языка, например для ua : %2$s и т.д.. <br> <b>ВНИМАНИЕ!</b> Если ссылка не работает, попробуйте пересохранить <a href="%3$s">настройки постоянных ссылок</a>.<br> Если пермалинки выключены можно использовать ссылку типа <a href="%4$s" target="_blank">%4$s</a>', esc_url( home_url( 'feed/' . FS_GOOGLE_SHOPING_FEED . '/' ) ), esc_url( home_url( 'ua/feed/' . FS_GOOGLE_SHOPING_FEED . '/' ) ), esc_url( admin_url( 'options-permalink.php' ) ), esc_url( add_query_arg( array( 'feed' => FS_GOOGLE_SHOPING_FEED ), home_url() ) ) ),
		'fields'      => array(
			array(
				'type'  => 'text',
				'name'  => 'fs_gs_categories',
				'label' => 'Категории товара Google',
				'help'  => 'Можно указать id категорий через запятую: 2345, 34444. Также можно использовать названия:Apparel & Accessories > Clothing > Dresses [Предметы одежды и принадлежности > Одежда > Платья. Подробнее: https://support.google.com/merchants/answer/6324436',
				'value' => fs_option( 'fs_gs_categories' )
			),
			array(
				'type'  => 'checkbox',
				'name'  => 'fs_gs_description',
				'label' => 'Использовать метаполе для получения описания товара',
				'help'  => 'Если вы хотите выводить описание отлчиное от основного контента',
				'value' => fs_option( 'fs_gs_description' )
			),
			array(
				'type'  => 'text',
				'name'  => 'fs_gs_description_meta',
				'label' => 'Название метаполя  описания товара',
//				'help'  => 'Если вы хотите выводить описание отлчиное от основного контента',
				'value' => fs_option( 'fs_gs_description_meta' )
			)

		)
	);

	return $settings;
}

add_filter( 'fs_plugin_settings', 'fs_googleshopping_settings_tab' );

function fs_googleshopping_do_feed() {


	GoogleShopping::title( get_bloginfo( 'name' ) );
	GoogleShopping::link( site_url() );
	GoogleShopping::description( get_bloginfo( 'description' ) );
	GoogleShopping::setIso4217CountryCode( 'UAH' );

	$products = new \WP_Query( array(
		'post_type'      => 'product',
		'posts_per_page' => - 1,
		'post_status'    => 'publish'
	) );
	if ( $products->have_posts() ) {
		while ( $products->have_posts() ) {
			$products->the_post();
			global $post;

			$item = GoogleShopping::createItem();
			$item->id( $post->ID );
			$item->title( apply_filters( 'the_title', $post->post_title ) );
			$item->price( fs_get_price( $post->ID ) );
			if ( fs_option( 'fs_gs_description' ) && fs_option( 'fs_gs_description_meta' ) ) {
				$item->description( apply_filters( 'the_content', get_post_meta( $post->ID, fs_option( 'fs_gs_description_meta' ), 1 ) ) );
			} else {
				$item->description( apply_filters( 'the_content', get_the_content() ) );
			}
			$item->mpn( fs_product_code( $post->ID ) );
			$available = get_post_meta( $post->ID, 'fs_remaining_amount', 0 ) != 0 ? $item::INSTOCK : $item::OUTOFSTOCK;
			$item->availability( $available );
//		$item->sale_price( $salePrice );
			$item->link( get_the_permalink( $post->ID ) );
			$item->image_link( get_the_post_thumbnail_url( $post->ID, 'full' ) );
			if ( fs_option( 'fs_gs_categories' ) ) {
				$item->google_product_category( htmlspecialchars( fs_option( 'fs_gs_categories' ) ) );
			}


			/** create a variant */
			$variant = $item->variant();
			/*$variant->size( $variant::LARGE );
			$variant->color( 'Red' );*/

			$item->delete();

		}
	}

// boolean value indicates output to browser
	GoogleShopping::asRss( true );
}
