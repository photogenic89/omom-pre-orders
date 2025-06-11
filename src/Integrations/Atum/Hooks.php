<?php
/**
 * Atum Stock Manager specific hooks
 * 
 * @todo: same work for Manufactor Central page
 */
namespace Omom\PreOrders\Integrations\Atum; 

defined( 'ABSPATH' ) || die;

use Omom\PreOrders\Abstracts\Singleton;
use Omom\PreOrders\Queries;
use Omom\PreOrders\Handlers\ShipmentHandler;

class Hooks extends Singleton 
{
    /**
     * Load all necessary hooks
     */
    function __construct()
    {
        add_filter( 'atum/stock_central_list/table_columns', [$this, 'reorderStockCentralColumns'], 100, 1 );
        add_filter( 'atum/list_table/column_stock',          [$this, 'makeCurrentStockUnselectable'], 100, 1 );
        add_filter( 'atum/list_table/column_inbound_stock',  [$this, 'addPreOrderStockToInboundStock'], 100, 2 );
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
     * Adds what is in shipments on top of what is in Atum purchase orders
     * 
     * @param string|int $inbound_stock
	 * @param \WP_Post $item The WooCommerce product post to use in calculations.
	 *
	 * @return int
     */
    public function addPreOrderStockToInboundStock( mixed $inbound_stock, mixed $item ): mixed
    {
        if ("integer" !== gettype( $inbound_stock )) return $inbound_stock;

        $id        = (int) (is_a( $item, '\WC_Product') ? $item->get_id() : $item->ID);
        $shipments = Queries::getActiveShipments( $id, true );
        $total_po  = 0;

        foreach ($shipments as $shipment_id) {
            $handler = new ShipmentHandler( $shipment_id );
            $total_po += $handler->getProduct( $id, 'original' ) ?: 0;
        }

        return $inbound_stock + $total_po;
    }
}