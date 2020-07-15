<?php


namespace FS_Google_Shopping;


class Settings
{
	/**
	 * Создает таб "Google Shopping" в настройках F-SHOP
	 *
	 * @param $settings
	 * @return mixed
	 */
	public static function plugin_settings_tab($settings)
	{
		$settings['google_shopping'] = array(
			'name' => __('Google Shopping', 'fs-google-shopping'),
			'description' => sprintf('Адрес вашего фида: <a href="%1$s" target="_blank">%1$s</a>. </br> На мультиязычном сайте необходимо добавить приставку языка, например для ua : %2$s и т.д.. <br> <b>ВНИМАНИЕ!</b> Если ссылка не работает, попробуйте пересохранить <a href="%3$s">настройки постоянных ссылок</a>.<br> Если пермалинки выключены можно использовать ссылку типа <a href="%4$s" target="_blank">%4$s</a>', esc_url(home_url('feed/' . FS_GS_FEED . '/')), esc_url(home_url('ua/feed/' . FS_GS_FEED . '/')), esc_url(admin_url('options-permalink.php')), esc_url(add_query_arg(array('feed' => FS_GS_FEED), home_url()))),
			'fields' => array(
				array(
					'type' => 'text',
					'name' => 'fs_gs_currency_code',
					'label' => __('Item Currency Code', 'fs-google-shopping'),
					'help' => __('For example: USD, UAH', 'fs-google-shopping'),
					'value' => fs_option('fs_gs_currency_code', 'USD')
				),
				array(
					'type' => 'checkbox',
					'name' => 'fs_gs_description',
					'label' => __('Use the meta field to get product description', 'fs-google-shopping'),
					'help' => __('If you want to display a description of excellent from the main content', 'fs-google-shopping'),
					'value' => fs_option('fs_gs_description')
				),
				array(
					'type' => 'text',
					'name' => 'fs_gs_description_meta',
					'label' => __('The name of the metafield of the product description', 'fs-google-shopping'),
					'value' => fs_option('fs_gs_description_meta')
				),
				array(
					'type' => 'select',
					'name' => 'fs_google_product_category_id',
					'label' => __('Default Google Category ID', 'fs-google-shopping'),
					'value' => fs_option('fs_google_product_category_id'),
					'values' => self::parse_taxonomies()
				)

			)
		);

		return $settings;
	}

	/**
	 * Дополнительные настройки категорий товаров
	 *
	 * @param $fields
	 * @return mixed
	 */
	public static function plugin_taxonomy_setting_fields($fields)
	{
		$fields['catalog'] = array_merge($fields['catalog'], array(
			'_google_shopping_exclude' => array(
				'name' => __('Exclude Google Shopping feed', 'fs-google-shopping'),
				'help' => __('Products will also be excluded from child categories.', 'fs-google-shopping'),
				'type' => 'checkbox',
				'args' => array()
			),
			'_google_shopping_id' => array(
				'name' => __('Google Shopping category ID', 'fs-google-shopping'),
				'type' => 'text',
				'help' => __('You can specify multiple categories, separated by commas. <a href="http://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.xls" download="taxonomy-with-ids.en-US.xls">Скачать список категорий</a>', 'fs-google-shopping'),
				'args' => array()
			)
		));

		return $fields;

	}

	/**
	 * Парсим кетегории из текстовых файлов расположенных в "/taxonomies/"
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

		$lines = file(FS_GS_PLUGIN_DIR . $filename);

		$taxes_parse = [];
		if (count($lines))
			foreach ($lines as $line) {
				if (preg_match('/^([\d]+)/i', $line, $output_array)) {
					$taxes_parse[$output_array[0]] = $line;
				}
			}

		return $taxes_parse;
	}
}
