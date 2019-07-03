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

ini_set( 'memory_limit', '320M' );

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
	if ( isset( $_GET['gs'] ) ) {
		fs_googleshopping_do_feed();
	}
}

add_action( 'init', 'fs_googleshopping_add_feed' );

function fs_googleshopping_settings_tab( $settings ) {
	$settings['google_shopping'] = array(
		'name'        => __( 'Google Shopping', 'fs-google-shopping' ),
		'description' => sprintf( 'Адрес вашего фида: <a href="%1$s" target="_blank">%1$s</a>. </br> На мультиязычном сайте необходимо добавить приставку языка, например для ua : %2$s и т.д.. <br> <b>ВНИМАНИЕ!</b> Если ссылка не работает, попробуйте пересохранить <a href="%3$s">настройки постоянных ссылок</a>.<br> Если пермалинки выключены можно использовать ссылку типа <a href="%4$s" target="_blank">%4$s</a>', esc_url( home_url( 'feed/' . FS_GOOGLE_SHOPING_FEED . '/' ) ), esc_url( home_url( 'ua/feed/' . FS_GOOGLE_SHOPING_FEED . '/' ) ), esc_url( admin_url( 'options-permalink.php' ) ), esc_url( add_query_arg( array( 'feed' => FS_GOOGLE_SHOPING_FEED ), home_url() ) ) ),
		'fields'      => array(
			array(
				'type'  => 'text',
				'name'  => 'fs_gs_currency_code',
				'label' => __( 'Item Currency Code', 'fs-google-shopping' ),
				'help'  => __( 'For example: USD, UAH', 'fs-google-shopping' ),
				'value' => fs_option( 'fs_gs_currency_code', 'USD' )
			),
			array(
				'type'  => 'checkbox',
				'name'  => 'fs_gs_description',
				'label' => __( 'Use the meta field to get product description', 'fs-google-shopping' ),
				'help'  => __( 'If you want to display a description of excellent from the main content', 'fs-google-shopping' ),
				'value' => fs_option( 'fs_gs_description' )
			),
			array(
				'type'  => 'text',
				'name'  => 'fs_gs_description_meta',
				'label' => __( 'The name of the metafield of the product description', 'fs-google-shopping' ),
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
	global $wpdb;
	$exclude_cats = $wpdb->get_col( "SELECT term_id FROM $wpdb->termmeta WHERE meta_key='_google_shopping_exclude' AND meta_value='1'" );
	$products     = new \WP_Query( array(
		'post_type'      => 'product',
		'posts_per_page' => - 1,
		'post_status'    => 'publish',
		'tax_query'      => array(
			array(
				'taxonomy'         => 'catalog',
				'field'            => 'term_id',
				'operator'         => 'NOT IN',
				'terms'            => $exclude_cats,
				'include_children' => true
			)
		)
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
			$product_terms = get_the_terms( $post->ID, 'catalog' );
			if ( $product_terms ) {
				foreach ( $product_terms as $key => $product_term ) {
					$cat_id = get_term_meta( $product_term->term_id, '_google_shopping_id', 1 );
					if ( $cat_id ) {
						$item->google_product_category( $cat_id );
						break;
					}
				}
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

// Дополнительные настройки категорий товаров
add_filter( 'fs_taxonomy_fields', 'fs_gs_taxonomy_fields' );
function fs_gs_taxonomy_fields( $fields ) {


	$fields['catalog']=array_merge($fields['catalog'],array(
		'_google_shopping_exclude' => array(
			'name' => __( 'Exclude Google Shopping feed', 'fs-google-shopping' ),
			'help' => __( 'Products will also be excluded from child categories.', 'fs-google-shopping' ),
			'type' => 'checkbox',
			'args' => array()
		),
		'_google_shopping_id'      => array(
			'name' => __( 'Google Shopping category ID', 'fs-google-shopping' ),
			'type' => 'text',
			'help' => __( 'You can specify multiple categories, separated by commas. <a href="http://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.xls" download="taxonomy-with-ids.en-US.xls">Скачать список категорий</a>', 'fs-google-shopping' ),
			'args' => array()
		)
	));

	return $fields;

}

add_action( 'plugins_loaded', 'fs_gs_load_plugin_textdomain' );
function fs_gs_load_plugin_textdomain() {
	load_plugin_textdomain( 'fs-google-shopping', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}