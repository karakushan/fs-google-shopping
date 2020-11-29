<?php


namespace FS_Google_Shopping;

use \domDocument;
use FS\FS_Config;

class Feed {
	public static function do_feed() {
		header( 'Content-type: text/xml' );

		// XML
		$xml                     = new domDocument( "1.0", "utf-8" );
		$xml->formatOutput       = true;
		$xml->preserveWhiteSpace = false;

		// rss
		$rss = $xml->createElement( "rss" );
		$rss->setAttribute( "version", "2.0" );
		$rss->setAttribute( "xmlns:g", "http://base.google.com/ns/1.0" );
		$xml->appendChild( $rss );

		// channel
		$channel = $xml->createElement( "channel" );
		$rss->appendChild( $channel );

		// title
		$title = $xml->createElement( "title", get_bloginfo( 'name' ) );
		$channel->appendChild( $title );

		// description
		$description = $xml->createElement( "description", get_bloginfo( 'description' ) );
		$channel->appendChild( $description );

		// link
		$link = $xml->createElement( "link", site_url( '/' ) );
		$channel->appendChild( $link );

		global $wpdb;
		$exclude_cats = $wpdb->get_col( "SELECT term_id FROM $wpdb->termmeta WHERE meta_key='_google_shopping_exclude' AND meta_value='1'" );
		$products     = new \WP_Query( array(
			'post_type'      => 'product',
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'tax_query'      => array(
				array(
					'taxonomy'         => FS_Config::get_data( 'product_taxonomy' ),
					'field'            => 'term_id',
					'operator'         => 'NOT IN',
					'terms'            => $exclude_cats,
					'include_children' => true
				)
			)
		) );

		global $post;

		if ( $products->have_posts() ) {
			while ( $products->have_posts() ) {
				$products->the_post();


				// item
				$item = $xml->createElement( "item" );
				$channel->appendChild( $item );

				// item g:id
				$item_id = $xml->createElement( "g:id", get_the_ID() );
				$item->appendChild( $item_id );

				// item title
				$item_title = $xml->createElement( "title", get_the_title() );
				$item->appendChild( $item_title );

				// item description
				$item_description = $xml->createElement( "description" );
				$item->appendChild( $item_description );
				$description = apply_filters( 'fs_product_description', $post->post_content, $post->ID );
				$description = apply_filters( 'the_content', sanitize_text_field( $description ) );
				$cdata       = $xml->createCDATASection( $description );
				$item_description->appendChild( $cdata );

				// item  link
				$item_link = $xml->createElement( "link", get_the_permalink() );
				$item->appendChild( $item_link );

				// item  g:image_link
				$item_image_link = $xml->createElement( "g:image_link", get_the_post_thumbnail_url( $post, 'full' ) );
				$item->appendChild( $item_image_link );

				// item g:price
				$item_price = $xml->createElement( "g:price", fs_get_product_id() . ' ' . fs_option( 'fs_gs_currency_code', 'USD' ) );
				$item->appendChild( $item_price );

				// item g:mpn
				$item_mpn = $xml->createElement( "g:mpn", fs_get_product_code() );
				$item->appendChild( $item_mpn );

				// item g:gtin
//				$item_gtin = $xml->createElement("g:gtin",fs_get_product_code());
//				$item->appendChild($item_gtin);

				// item g:availability
				$item_availability = $xml->createElement( "g:availability", fs_aviable_product() ? 'in_stock' : 'out_of_stock' );
				$item->appendChild( $item_availability );

				// item g:condition
				$item_condition = $xml->createElement( "g:condition", 'new' );
				$item->appendChild( $item_condition );

				// item g:google_product_category
				$product_terms = get_the_terms( $post->ID, FS_Config::get_data( 'product_taxonomy' ) );
				$cat_id_exists = false;
				if ( $product_terms ) {
					foreach ( $product_terms as $key => $product_term ) {
						$cat_id = get_term_meta( $product_term->term_id, '_google_shopping_id', 1 );
						if ( $cat_id ) {
							$item_product_category = $xml->createElement( "g:google_product_category", $cat_id );
							$item->appendChild( $item_product_category );
							$cat_id_exists = true;
							break;
						}
					}
				}

				$default_category_id = fs_option( 'fs_google_product_category_id' );
				if ( ! $cat_id_exists && is_numeric( $default_category_id ) ) {
					$item_product_category = $xml->createElement( "g:google_product_category", intval( $default_category_id ) );
					$item->appendChild( $item_product_category );
				}

				// item g:product_detail
				$attributes = get_the_terms( get_the_ID(), FS_Config::get_data( 'features_taxonomy' ) );
				foreach ( $attributes as $attribute ) {
					if ( ! $attribute->parent ) {
						continue;
					}

					$item_product_detail = $xml->createElement( "g:product_detail" );
					$item->appendChild( $item_product_detail );

					// g:product_detail g:section_name
					$item_section_name = $xml->createElement( "g:section_name", __( 'General information', 'fs-google-shopping' ) );
					$item_product_detail->appendChild( $item_section_name );

					// g:product_detail g:section_name g:attribute_name
					$item_attribute_name = $xml->createElement( "g:attribute_name", get_term_field( 'name', $attribute->parent, FS_Config::get_data( 'features_taxonomy' ) ) );
					$item_product_detail->appendChild( $item_attribute_name );

					// g:product_detail g:section_name g:attribute_value
					$item_attribute_value = $xml->createElement( "g:attribute_value", $attribute->name );
					$item_product_detail->appendChild( $item_attribute_value );
				}

			}
		}


		// OUTPUT
		echo $xml->saveXML();
		exit;
	}

}
