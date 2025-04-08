<?php
/**
 * The things all admin pages have in common
 * abstract base for pages
 */
namespace Omom\PreOrders\Abstracts;

defined( 'ABSPATH' ) || die;

abstract class Page 
{
	/** 
	 * The version of the scripts on this page
	 */
	protected static $script_version = '1.0.0';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_action( 
			'admin_enqueue_scripts', 
			[static::class, 'enqueueAdminScripts'], 
			PHP_INT_MAX 
		);

		static::class::setHooks();
	}

	/**
	 * Call all hooks connected to this page
	 */
	abstract public static function setHooks();

	/**
	 * Enqueue all styles and scripts needed on that page
	 */
	abstract public static function enqueueAdminScripts( $hook );

	/**
	 * makes it possible to use ajax on the page
	 */
	public static function loadAjaxObject( string $handle ): void
	{
		wp_localize_script( 
			$handle, 
			'omomPreOrdersObject', 
			[ 
				'ajaxUrl' => admin_url( 'admin-ajax.php' ), 
				'nonce'   => wp_create_nonce( '535vviwrmKI' ),
			]
		);
	}
}