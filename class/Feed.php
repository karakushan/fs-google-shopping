<?php

namespace FS_Google_Shopping;

use FS\FS_Config;

class Feed
{
    public $xml;
    public $channel;
    public $products;

    public function __construct()
    {
        // XML
        $this->xml = new \DOMDocument('1.0', 'UTF-8');
        $this->xml->formatOutput = true;
        $this->xml->preserveWhiteSpace = false;
    }

    public function do_feed()
    {
        header('Content-type: text/xml');

        // rss
        $rss = $this->xml->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $this->xml->appendChild($rss);

        // channel
        $this->channel = $this->xml->createElement('channel');
        $rss->appendChild($this->channel);

        // title
        $title = $this->xml->createElement('title', get_bloginfo('name'));
        $this->channel->appendChild($title);

        // description
        $description = $this->xml->createElement('description', get_bloginfo('description'));
        $this->channel->appendChild($description);

        // link
        $link = $this->xml->createElement('link', site_url('/'));
        $this->channel->appendChild($link);

        global $wpdb;
        $exclude_cats = $wpdb->get_col("SELECT term_id FROM $wpdb->termmeta WHERE meta_key='_google_shopping_exclude' AND meta_value='1'");

        // Отримуємо ID товарів, які потрібно виключити
        $excluded_product_ids = get_posts([
            'post_type' => FS_Config::get_data('post_type'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'fs_gs_exclude_from_feed',
                    'value' => 'yes',
                    'compare' => '=',
                ],
            ],
        ]);

        $this->products = new \WP_Query([
            'post_type' => FS_Config::get_data('post_type'),
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'post__not_in' => $excluded_product_ids,
            'tax_query' => [
                [
                    'taxonomy' => FS_Config::get_data('product_taxonomy'),
                    'field' => 'term_id',
                    'operator' => 'NOT IN',
                    'terms' => $exclude_cats,
                    'include_children' => true,
                ],
            ],
        ]);

        global $post;

        if ($this->products->have_posts()) {
            $this->gtag_items();
        }

        // OUTPUT
        echo $this->xml->saveXML();
        exit;
    }

    public function gtag_items()
    {
        global $post;
        while ($this->products->have_posts()) {
            $this->products->the_post();

            // Переконуємося, що у фід потрапляють тільки опубліковані товари
            if (get_post_status($post->ID) !== 'publish') {
                continue;
            }

            // Спочатку перевіряємо наявність зображення
            $image_url = get_the_post_thumbnail_url($post, 'full');
            if (empty($image_url)) {
                $gallery_ids = \FS\FS_Images_Class::get_gallery($post->ID, false);
                if (!empty($gallery_ids)) {
                    $first_gallery_image_id = reset($gallery_ids);
                    $image_url = wp_get_attachment_image_url($first_gallery_image_id, 'full');
                }
            }

            // Якщо зображення не знайдено, пропускаємо товар
            if (empty($image_url)) {
                continue;
            }

            // item
            $item = $this->xml->createElement('item');
            $this->channel->appendChild($item);

            // item g:id
            $item_id = $this->xml->createElement('g:id', get_the_ID());
            $item->appendChild($item_id);

            // item title
            $item_title = $this->xml->createElement('title', get_the_title());
            $item->appendChild($item_title);

            // item description
            $item_description = $this->xml->createElement('description');
            $item->appendChild($item_description);
            $description = apply_filters('fs_product_description', $post->post_content, $post->ID);
            $description = apply_filters('the_content', sanitize_text_field($description));
            $cdata = $this->xml->createCDATASection(self::clean_html($description));
            $item_description->appendChild($cdata);

            // item  link
            $item_link = $this->xml->createElement('link', get_the_permalink());
            $item->appendChild($item_link);

            // item  g:image_link - використовуємо вже отриманий URL
            $item_image_link = $this->xml->createElement('g:image_link', $image_url);
            $item->appendChild($item_image_link);

            // item g:price
            $price = fs_get_product_min_qty($post->ID) > 1 ? fs_get_price($post->ID) * fs_get_product_min_qty($post->ID) : fs_get_price($post->ID);
            $item_price = $this->xml->createElement('g:price', $price.' '.fs_option('fs_gs_currency_code', 'USD'));
            $item->appendChild($item_price);

            // item g:mpn
            $item_mpn = $this->xml->createElement('g:mpn', fs_get_product_code());
            $item->appendChild($item_mpn);

            // item g:gtin
            //				$item_gtin = $this->xml->createElement("g:gtin",fs_get_product_code());
            //				$item->appendChild($item_gtin);

            // item g:availability
            $item_availability = $this->xml->createElement('g:availability', fs_in_stock() ? 'in_stock' : 'out_of_stock');
            $item->appendChild($item_availability);

            // item g:condition
            $condition = get_post_meta($post->ID, 'fs_gs_product_condition', true) ? get_post_meta($post->ID, 'fs_gs_product_condition', true) : 'new';
            $item_condition = $this->xml->createElement('g:condition', apply_filters('fs_gs_product_condition', $condition, $post->ID));
            $item->appendChild($item_condition);

            // item g:brand
            $brand_attributes = apply_filters('fs_gs_brand_attributes', []);
            $brand = '';
            $product_terms = !empty($brand_attributes) ? get_the_terms($post, FS_Config::get_data('features_taxonomy')) : [];
            if (!empty($product_terms)) {
                foreach ($product_terms as $term) {
                    if (in_array($term->parent, $brand_attributes)) {
                        $brand = $term->name;
                        break;
                    }
                }
            }

            if ($brand) {
                $item_brand = $this->xml->createElement('g:brand', $brand);
                $item->appendChild($item_brand);
            }

            // item g:google_product_category
            $product_terms = get_the_terms($post->ID, FS_Config::get_data('product_taxonomy'));
            $cat_id_exists = false;
            if ($product_terms) {
                foreach ($product_terms as $key => $product_term) {
                    $cat_id = get_term_meta($product_term->term_id, '_google_shopping_id', 1);
                    if ($cat_id) {
                        $item_product_category = $this->xml->createElement('g:google_product_category', $cat_id);
                        $item->appendChild($item_product_category);
                        $cat_id_exists = true;
                        break;
                    }
                }
            }

            $default_category_id = fs_option('fs_google_product_category_id');
            if (!$cat_id_exists && is_numeric($default_category_id)) {
                $item_product_category = $this->xml->createElement('g:google_product_category', intval($default_category_id));
                $item->appendChild($item_product_category);
            }

            // g:product_type
            $categories = apply_filters('fs_gs_product_type_start_categories', [
                __('Home', 'fs-google-shopping'),
                __('Products', 'fs-google-shopping'),
            ]);
            if ($product_terms) {
                foreach ($product_terms as $key => $product_term) {
                    $categories[] = $product_term->name;
                }
            }
            $item_product_type = $this->xml->createElement('g:product_type', implode(' > ', $categories));
            $item->appendChild($item_product_type);

            // item g:unit_pricing_measure
            if (fs_get_product_min_qty($post->ID) > 1) {
                $item_unit_pricing_measure = $this->xml->createElement('g:unit_pricing_measure', '500 шт.');
                $item_unit_pricing_base_measure = $this->xml->createElement('g:unit_pricing_base_measure', '1 шт.');
                $item->appendChild($item_unit_pricing_measure);
                $item->appendChild($item_unit_pricing_base_measure);
            }

            // item g:product_detail
            $attributes = get_the_terms(get_the_ID(), FS_Config::get_data('features_taxonomy'));
            foreach ($attributes as $attribute) {
                if (!$attribute->parent || $attribute->name == '') {
                    continue;
                }

                $item_product_detail = $this->xml->createElement('g:product_detail');
                $item->appendChild($item_product_detail);

                // g:product_detail g:section_name
                $item_section_name = $this->xml->createElement('g:section_name', __('General information', 'fs-google-shopping'));
                $item_product_detail->appendChild($item_section_name);

                // g:product_detail g:section_name g:attribute_name
                $item_attribute_name = $this->xml->createElement('g:attribute_name', get_term_field('name', $attribute->parent, FS_Config::get_data('features_taxonomy')));
                $item_product_detail->appendChild($item_attribute_name);

                // g:product_detail g:section_name g:attribute_value
                $item_attribute_value = $this->xml->createElement('g:attribute_value', $attribute->name);
                $item_product_detail->appendChild($item_attribute_value);
            }
        }
    }

    public static function clean_html($text)
    {
        $text = html_entity_decode($text);
        $text = strip_tags($text);
        $text = preg_replace("/(\n(\s*)\n)/", "\n\n", $text);
        $text = preg_replace("/\n\n+/", "\n", $text);
        $text = str_replace(PHP_EOL, ' ', $text);

        return $text;
    }
}
