<?php

namespace FS_Google_Shopping;

class Settings
{
    /**
     * Создает таб "Google Shopping" в настройках F-SHOP.
     */
    public static function plugin_settings_tab($settings)
    {
        $settings['google_shopping'] = [
            'name' => __('Google Shopping', 'fs-google-shopping'),
            'description' => sprintf('Адрес вашего фида: <a href="%1$s" target="_blank">%1$s</a>. </br> На мультиязычном сайте необходимо добавить приставку языка, например для ua : %2$s и т.д.. <br> <b>ВНИМАНИЕ!</b> Если ссылка не работает, попробуйте пересохранить <a href="%3$s">настройки постоянных ссылок</a>.<br> Если пермалинки выключены можно использовать ссылку типа <a href="%4$s" target="_blank">%4$s</a>', esc_url(home_url('feed/'.FS_GS_FEED.'/')), esc_url(home_url('ua/feed/'.FS_GS_FEED.'/')), esc_url(admin_url('options-permalink.php')), esc_url(add_query_arg(['feed' => FS_GS_FEED], home_url()))),
            'fields' => [
                [
                    'type' => 'text',
                    'name' => 'fs_gs_currency_code',
                    'label' => __('Item Currency Code', 'fs-google-shopping'),
                    'help' => __('For example: USD, UAH', 'fs-google-shopping'),
                    'value' => fs_option('fs_gs_currency_code', 'USD'),
                ],
                [
                    'type' => 'checkbox',
                    'name' => 'fs_gs_description',
                    'label' => __('Use the meta field to get product description', 'fs-google-shopping'),
                    'help' => __('If you want to display a description of excellent from the main content', 'fs-google-shopping'),
                    'value' => fs_option('fs_gs_description'),
                ],
                [
                    'type' => 'text',
                    'name' => 'fs_gs_description_meta',
                    'label' => __('The name of the metafield of the product description', 'fs-google-shopping'),
                    'value' => fs_option('fs_gs_description_meta'),
                ],
                [
                    'type' => 'select',
                    'name' => 'fs_google_product_category_id',
                    'label' => __('Default Google Category ID', 'fs-google-shopping'),
                    'value' => fs_option('fs_google_product_category_id'),
                    'values' => self::parse_taxonomies(),
                ],
                [
                    'type' => 'select',
                    'name' => 'fs_gs_brand_taxonomy_id',
                    'label' => __('Brand attribute', 'fs-google-shopping'),
                    'help' => __('Select the product attribute that is responsible for the brand.', 'fs-google-shopping'),
                    'value' => fs_option('fs_gs_brand_taxonomy_id'),
                    'values' => self::get_brand_terms(),
                ],
                [
                    'type' => 'checkbox',
                    'name' => 'fs_gs_multilang_single',
                    'label' => __('Multilingual feed', 'fs-google-shopping'),
                    'help' => __('Products in different languages will be displayed in one feed', 'fs-google-shopping'),
                    'value' => fs_option('fs_gs_multilang_single'),
                ],
            ],
        ];

        return $settings;
    }

    /**
     * Дополнительные настройки категорий товаров.
     */
    public static function plugin_taxonomy_setting_fields($fields)
    {
        $fields['catalog'] = array_merge($fields['catalog'], [
            '_google_shopping_exclude' => [
                'name' => __('Exclude Google Shopping feed', 'fs-google-shopping'),
                'help' => __('Products will also be excluded from child categories.', 'fs-google-shopping'),
                'type' => 'checkbox',
                'args' => [],
            ],
            '_google_shopping_id' => [
                'name' => __('Google Shopping category ID', 'fs-google-shopping'),
                'type' => 'text',
                'help' => __('You can specify multiple categories, separated by commas. <a href="http://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.xls" download="taxonomy-with-ids.en-US.xls">Скачать список категорий</a>', 'fs-google-shopping'),
                'args' => [],
            ],
        ]);

        return $fields;
    }

    /**
     * Gets a list of attribute terms for selecting a brand.
     *
     * @return array
     */
    private static function get_brand_terms()
    {
        $brand_terms = [];
        $taxonomy = \FS\FS_Config::get_data('features_taxonomy');
        if (!$taxonomy) {
            return $brand_terms;
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'parent' => 0,
        ]);

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $brand_terms[$term->term_id] = $term->name;
            }
        }

        return $brand_terms;
    }

    /**
     * Парсим кетегории из текстовых файлов расположенных в "/taxonomies/".
     *
     * @return array
     */
    public static function parse_taxonomies()
    {
        $locale = get_locale();
        if ($locale == 'uk') {
            $filename = 'taxonomies/taxonomy-with-ids.uk-UA.txt';
        } elseif ($locale == 'ru_RU') {
            $filename = 'taxonomies/taxonomy-with-ids.ru-RU.txt';
        } else {
            $filename = 'taxonomies/taxonomy-with-ids.en-US.txt';
        }

        $lines = file(FS_GS_PLUGIN_DIR.$filename);

        $taxes_parse = [];
        if (count($lines)) {
            foreach ($lines as $line) {
                if (preg_match('/^([\d]+)/i', $line, $output_array)) {
                    $taxes_parse[$output_array[0]] = $line;
                }
            }
        }

        return $taxes_parse;
    }
}
