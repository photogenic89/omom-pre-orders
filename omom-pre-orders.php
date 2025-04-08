<?php
/**
 * Plugin Name: OMOM - Pre-Orders
 * Description: Create pre-orders and sell products before they arrive in in stock.
 * Author:      Studio Köpfchen
 * Version:     1.0.0
 * Author URI:  http://studiokoepfchen.com
 * Text Domain: omom-pre-orders
 *
 * WC requires at least: 8.0
 * WC tested up to: 8.7
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if (! class_exists( 'Omom_PreOrders' )) :

/**
 * Main plugin class.
 */
class OmomPreOrders 
{
	/**
	 * Plugin version
	 */
    public string $version  = '1.0.0';

	/**
	 * The text domain for string translations
	 */
	public string $text_domain = "omom-pre-orders";

    /**
	 * The single instance of the class.
	 * @var OmomPreOrders
	 */
    protected static $_instance = null;
    
    /**
	 * Main OmomPreOrders instance. 
	 * Ensures only one instance of OmomPreOrders is loaded or can be loaded
	 * @see 'OMOM_PREORDERS'.
	 *
	 * @return OmomPreOrders
	 */
	public static function instance(): object 
	{
		if (is_null( self::$_instance )) {
            self::$_instance = new self();
            self::$_instance->setupActions();
		}
		return self::$_instance;
    }
    
    /**
	 * Entry point
	 */
	protected function __construct() 
	{
		require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
		
        add_action( 'woocommerce_loaded', [$this, 'initializePlugin'], 9 );
		add_action( 'init', 		      [$this, 'removeHooks'], 10 );

		add_action( 'before_woocommerce_init', function() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		} );
    }

	/**
	 * Using this later on to remove called hooks
	 */
	public function removeHooks(): void 
	{
		if (class_exists( 'AtumLevels\Inc\Hooks' )) {
			$instance = AtumLevels\Inc\Hooks::get_instance();
			
			// Make product parts searchable in select 2 input
			remove_filter( 'woocommerce_json_search_found_products', [$instance, 'check_products_seller'], 10, 1 );

			// show order notes
            remove_filter( 'woocommerce_new_order_note_data', [$instance, 'maybe_remove_order_note'] );
		}
	}

    /**
	 * Plugin URL getter.
	 *
	 * @return string
	 */
	public function pluginUrl(): string 
	{
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin path getter.
	 *
	 * @return string
	 */
	public function pluginPath(): string 
	{
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Plugin base path name getter.
	 *
	 * @return string
	 */
	public function pluginBasename(): string 
	{
		return plugin_basename( __FILE__ );
	}
    
    /**
	 * Fire in the hole!
	 */
	public function initializePlugin(): void
	{
		if ( version_compare( wc()->version, '8.7.0', '<' ) ) return;

		// Admin includes.
		if (is_admin()) Omom\PreOrders\Admin\Bootstrap::getInstance();

		Omom\PreOrders\ProductHooks::getInstance();
		Omom\PreOrders\Cart\Hooks::getInstance();
		Omom\PreOrders\OrderHooks::getInstance();

		// 3rd party plugin support
		switch (true) {
			// check if Atum Stock Manager
			case class_exists( '\Atum\Bootstrap' ):
                Omom\PreOrders\Integrations\Atum\Hooks::getInstance();
            // check if Atum Product Levels
            case class_exists( '\AtumProductLevelsAddon' ):
                Omom\PreOrders\Integrations\AtumProductLevels\Hooks::getInstance();
            // check if WC Composites is loaded
            case class_exists( '\WC_Composite_Products' ):
                Omom\PreOrders\Integrations\WCComposites\Hooks::getInstance();
            // check if WC Bundles is loaded
            case class_exists( '\WC_Bundles' ):
                Omom\PreOrders\Integrations\WCBundles\Hooks::getInstance();
        }
    }

    /**
	 * Setup the default hooks and actions
	 */
	private function setupActions(): void 
	{
		Omom\PreOrders\Init::init();
		Omom\PreOrders\Cron::init(); // Cron events have to be loaded on each page load
    }   
}

/**
 * Returns the main instance of Omom_PreOrders to prevent the need to use globals.
 *
 * @return OmomPreOrders
 */
function OMOM_PREORDERS(): OmomPreOrders 
{
	return OmomPreOrders::instance();
}

OMOM_PREORDERS();

endif; // class_exists check
