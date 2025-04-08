<?php
/**
 * Admin class
 */
namespace Omom\PreOrders\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Omom\PreOrders\Abstracts\Singleton;
use Omom\PreOrders\Handlers\ProductHandler as Product;
use Omom\PreOrders\Helpers;
use Omom\PreOrders\Handlers\ShipmentHandler as Shipment;

class Bootstrap extends Singleton
{	
    /**
     * Constructor
     */
    function __construct() 
    {
        self::setHooks();
    }

    /**
     * Load hooks
     */
    public static function setHooks(): void
    {
        add_action( 'current_screen', [__CLASS__, 'maybeInitPage'], 10 );
		add_action( 'admin_menu', 	  [Pages\Schedule::class, 'addSubMenu'] );
		add_action( 'admin_menu', 	  [Pages\Settings::class, 'addSubMenu'] );

		add_action( 'wp_ajax_nopriv_omom_preorders_request', [ __CLASS__, 'processAdminAjax' ], 10 );
		add_action( 'wp_ajax_omom_preorders_request',   	 [ __CLASS__, 'processAdminAjax' ], 10 );

        // non-page related admin hooks
        add_filter( 'woocommerce_admin_stock_html', [__CLASS__, 'productsStockColumn'], 10, 2 );
        add_action( 'trashed_post',                 [__CLASS__, 'deletePost'], 10 );
        add_filter( 'bulk_post_updated_messages',   [__CLASS__, 'changeTrashToDeletedMessage'], 10, 2 );

        // here for when planning to publish a post in the future
        add_action( 'save_post_restock', [Pages\Post::class, 'savePost'], 10, 2); 
    }

    /**
	 * Initializes the current page if exists
     * page based directory system, because each page has very different scripts and functions
	 */
	public static function maybeInitPage(): void 
	{
        $screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		if (wp_doing_ajax()) return;

		switch ($screen_id) {
            case 'edit-restock':
                new Pages\Archive();
                break;
            case 'restock':
                new Pages\Post();
                break;
            case 'restock_page_schedule':
                new Pages\Schedule();
                break;
            case 'restock_page_settings':
                new Pages\Settings();
                break;
		}
	}

    /**
     * All admin ajax request go through here
     */
    public static function processAdminAjax(): void
    {
		if (! check_ajax_referer( '535vviwrmKI', 'ajax_nonce' )) die( 'Nonce value cannot be verified.' );

		$method  = sanitize_text_field($_REQUEST['method'] ?? '');
		$post_id = sanitize_text_field($_REQUEST['post_id'] ?? 0);
		$page    = sanitize_text_field($_REQUEST['page'] ?? '');
		$object  = (object) [];

		// the data send with the request
		$input            = @file_get_contents( 'php://input' ); 
		$unsanitized_data = json_decode( $input, true );
		$data             = (is_array($unsanitized_data) || is_object($unsanitized_data)) ? Helpers::mapDeep( $unsanitized_data ) : sanitize_text_field($unsanitized_data);
		$data             = empty($data) ? Helpers::mapDeep( $_REQUEST ) : $data;

		switch ($page) {
			case 'Schedule':
				$object = new Ajax\Schedule();
                break;
            case 'Settings':
                $object = new Ajax\Settings();
				break;
            case 'Post':
                $object = new Ajax\Post();
                break;
		}

		if (method_exists( $object, $method )) $object->$method( $data );

		die( 'No matching method found.' );
    }
    
    /**
     * Change stocks colum from on backorder to on pre-order
     * 
     * @param string $stock_html
     * @param \WC_Product $product
     * 
     * @return string
     */
    public static function productsStockColumn( string $stock_html, \WC_Product $product ): string
    { 
        // not having translations in mind
        return (false === strpos( $stock_html, 'On backorder' ) || ! (new Product( $product->get_id() ))->canBePreOrdered()) ? $stock_html : str_replace( 'On backorder', 'In pre-order', $stock_html );
    }

    /**
     * Remove all post meta and reset product pre-order stock
     * 
     * @param int   $post_id
     */
    public static function deletePost( $post_id ): void 
    {
        if ('restock' === get_post_type( $post_id )) (new Shipment( $post_id ))->delete();
    }

    /**
     * Filters the bulk action updated messages.
     * 
     * @param array[] $bulk_messages
     * @param array $bulk_counts
     * 
     * @return array
     */
    public static function changeTrashToDeletedMessage( array $bulk_messages, array $bulk_counts ): array
    {
        $bulk_messages['post']['trashed'] = _n( '%s post permanently deleted.', '%s posts permanently deleted.', $bulk_counts['deleted'] );
        return $bulk_messages;
    }
}