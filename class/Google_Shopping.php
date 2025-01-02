<?php


namespace FS_Google_Shopping;

class Google_Shopping {
	public function __construct() {
		add_action( 'init', [ $this, 'add_feed' ] );
		add_filter( 'fs_plugin_settings', [ 'FS_Google_Shopping\Settings', 'plugin_settings_tab' ] );
		add_filter( 'fs_taxonomy_fields', [ 'FS_Google_Shopping\Settings', 'plugin_taxonomy_setting_fields' ] );

		add_filter( 'wp', [ $this, 'w3_super_cache_deactivate' ] );

		add_filter( 'fs_product_tabs_admin', [ $this, 'get_product_tabs' ] );
	}

	function get_product_tabs( $tabs ) {

		$tabs['basic']['fields']['fs_gs_product_condition'] = [
			'label'   => __( 'Condition', 'fs-google-shopping' ),
			'type'    => 'select',
			'max'     => 1,
			'options' => [
				'new'         => __( 'New', 'fs-google-shopping' ),
				'used'        => __( 'Used', 'fs-google-shopping' ),
				'refurbished' => __( 'Refurbished', 'fs-google-shopping' )
			]
		];

		return $tabs;
	}

	public static function w3_super_cache_deactivate() {
		if ( is_feed( FS_GS_FEED ) ) {
			add_filter( 'do_rocket_lazyload', '__return_false' );
		}
	}

	/**
	 * Register a feed google shopping
	 */
	public static function add_feed() {
		$feed = new Feed();
		add_feed( FS_GS_FEED, function () use ( $feed ) {
			return $feed->do_feed();
		} );
		if ( isset( $_GET['gs'] ) ) {
			$feed->do_feed();
		}
	}
}
