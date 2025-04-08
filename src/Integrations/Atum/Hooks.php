<?php
/**
 * Atum Stock Manager specific hooks
 * 
 * @todo: same work for Manufactor Central page
 */
namespace Omom\PreOrders\Integrations\Atum; 

defined( 'ABSPATH' ) || die;

use Omom\PreOrders\Abstracts\Singleton;
use Atum\Inc\Helpers as AtumHelpers;
use Omom\PreOrders\Handlers\ProductHandler as Product;

class Hooks extends Singleton 
{
    /**
     * Load all necessary hooks
     */
    function __construct()
    {
        add_filter( 'atum/stock_central_list/table_columns',                       [$this, 'reorderStockCentralColumns'], 100, 1 );
        add_filter( 'atum/product_levels/manufacturing_list_table/table_columns',  [$this, 'reorderStockCentralColumns'], 100, 1 );
        add_filter( 'atum/list_table/column_stock',                                [$this, 'makeCurrentStockUnselectable'], 100, 1 );
        add_filter( 'atum/product_levels/list_table/column_available_to_purchase', [$this, 'makeCurrentStockUnselectable'], 100, 1 );
        add_filter( 'atum/list_table/column_inbound_stock',                        [$this, 'addPreOrderStockToInboundStock'], 100, 2 );
        add_filter( 'atum/product_levels/get_available_to_purchase_column_value',  [$this, 'calculateAvailableToPurchase'], 100, 3 ); 
    }

    /**
     * Reorder the stock specific columns
     * 
     * 1. Available to purchase -> stock + inbound stock - stock on hold
     * 2. stock -> what is in stock
     * 3. Inbound stock -> what is in pre-order posts and purchase orders
     * 4. Stock on hold -> in non-completed orders
     * 
     * @param array $t_c table columns
     * 
     * @return array
     */
    public function reorderStockCentralColumns( array $t_c ): array
    {   
        // only do this if current stock is in there
        if (! isset( $t_c['_stock'] )) return $t_c;

        $stock_columns = [];

        if (isset( $t_c['_available_to_purchase'] )) $stock_columns['_available_to_purchase'] = $t_c['_available_to_purchase'];
        
        $stock_columns['_stock'] = $t_c['_stock'];
        
        if (isset( $t_c['_inbound_stock'] )) $stock_columns['_inbound_stock'] = $t_c['_inbound_stock'];
        if (isset( $t_c['_stock_on_hold'] )) $stock_columns['_stock_on_hold'] = $t_c['_stock_on_hold'];

        unset( $t_c['_available_to_purchase'], $t_c['_inbound_stock'], $t_c['_stock_on_hold'] );

        $offset = array_flip( array_keys( $t_c ) )['_stock'];

        return array_slice( $t_c, 0, $offset, TRUE ) + $stock_columns + array_slice( $t_c, $offset + 1, NULL, TRUE );
    }

    /**
     * Get the stock quantity value from the stock html string
     * 
     * @param string $stock_html
     * 
     * @return string
     */
    public function makeCurrentStockUnselectable( string $stock_html ): string
    {
        preg_match( '/<span[^>]*>\s*(-?\s*(?:\&\#45\;)?\s*\d+(?:\.\d+)?)\s*<\/span>/', $stock_html, $matches );
        return $matches[1] ?? $stock_html;
    }

    /**
     * Adds pre-order stock on top of what is in Atum purchase orders
     * 
     * @param string|int $inbound_stock
	 * @param \WP_Post $item The WooCommerce product post to use in calculations.
	 *
	 * @return int
     */
    public function addPreOrderStockToInboundStock( mixed $inbound_stock, mixed $item ): mixed
    {
        return "integer" !== gettype( $inbound_stock ) ? $inbound_stock : $inbound_stock + (new Product( (int) $item->ID ))->getPreOrderStock();
    }

    /**
     * Show a correct number in 
     * Could calculate this in Javascript
     * We ignore the initial value? But should we?
     * 
     * @todo: bundles
     * @todo: items with inventory
     */
    public function calculateAvailableToPurchase( $available_stock, $item, $list_table )
    {
        // current stock + inbound stock - stock on hold
        $list_item = AtumHelpers::get_atum_product( $item );

        if (! $list_item->get_manage_stock()) return $available_stock;

        $current_stock = (int) apply_filters( 'atum/list_table/column_stock_value', wc_stock_amount( $list_item->get_stock_quantity() ), $list_item ); // check  AtumListTable->column__stock
        $inbound_stock = $list_item->get_inbound_stock() + (new Product( (int) $item->ID ))->getPreOrderStock();
        $stock_on_hold = (int) $list_item->get_stock_on_hold();

        return $current_stock + $inbound_stock - $stock_on_hold;
    }
}