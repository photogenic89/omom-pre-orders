<?php
/**
 * Atum ProductLevels specific hooks
 */
namespace Omom\PreOrders\Integrations\AtumProductLevels; 

defined( 'ABSPATH' ) || die;

use Omom\PreOrders\Abstracts\Singleton;
use Atum\Inc\Helpers as AtumHelpers;
use Omom\PreOrders\Handlers\ProductHandler as Product;
use Omom\PreOrders\Helpers as POHelpers;

class Hooks extends Singleton 
{
    /**
     * Load all necessary hooks
     */
    function __construct()
    {   
        add_filter( 'atum/product_levels/bom_stock_control_fields_args',        [$this, 'controlFieldsArgs'], 10 );
        add_filter( 'atum/product_levels/allow_removing_order_comments',        [$this, 'disallowRemovingOrderComments'], 10, 2 );
        add_filter( "atum/product_levels/maybe_decrease_bom_stock_order_items", [$this, "maybeDecreaseBoMOrderItemInventories"], 2, 6 );
        add_filter( "atum/product_levels/maybe_increase_bom_stock_order_items", [$this, "maybeIncreaseBoMOrderItemInventories"], 2, 6 );
    }

    /**
     * Show the calculated max value which includes all pre-order stock
     * 
     * @param array $args
     * 
     * @return array
     */
    public function controlFieldsArgs( $args ): array
    {
        $product = $args['item'];
        $max = Helpers::findMaxValue( $product->get_id() );

        if (false !== $max) $args['calc_stock_quantity'] = $max;

        return $args;
    }

    /**
     * ATUM is hiding all order comments and we don't want that
     * 
     * @param bool $allow
     * @param int $id
     * 
     * @return bool
     */
    public function disallowRemovingOrderComments( $allow, $id ): bool 
    {
        return false;
    }

    /**
     * Decreases re/stock from bom products
     * @author: Nikolay
     * 
     * @param bool          $decrease
     * @param WC_Order_Item $order_item
     * @param               $bom_id
     * @param int           $qty - this already includes the multiplication per parent item
     * @param int           $changed_qty
     * @param               $order_type
     * 
     * @return bool
     */
    public function maybeDecreaseBoMOrderItemInventories( $decrease, $order_item, $bom_id, $qty, $changed_qty, $order_type ): bool 
    {
        $pre_order_list = $order_item->get_meta( '_bom_restock_list', true );

        if (
            empty( $pre_order_list ) || 
            ! array_key_exists( $bom_id, $pre_order_list )
        ) return $decrease;

        $order_id = $order_item->get_order_id();

        // 1. Stock
        $bom_product = AtumHelpers::get_atum_product( $bom_id );
        $po_product  = new Product( $bom_id );
        $old_stock   = $bom_product ? $bom_product->get_stock_quantity() : $po_product->getStock();

        // 2. Pre-Orders 
        if ($qty > $old_stock) {
            $old_pre_order  = $po_product->getPreOrderStock();
            $new_pre_order  = $old_pre_order - ($qty - $old_stock);

            POHelpers::updateOrderItem( 
                $order_item->get_id(),
                $bom_id, 
                $pre_order_list[$bom_id],
                'decrease', 
                'Decreased stock (order #' . $order_id . ")"
            ); 
        }

        // 3. Add note
        $order = wc_get_order( $order_id );
        $name  = $bom_product->get_formatted_name();
        $order->add_order_note( 'BOM PRE-ORDER levels decreased: ' . $name . ' ' . $old_pre_order . '&rarr;' . $new_pre_order );

        // 4. Add meta if order is cancelled
        $order_item->add_meta_data( '_reduced_restock', true, true );
		$order_item->save();
        
        return $decrease;
    }

    /**
     * Increase pre-order/stock from bom products
     * 
     * @param bool $increase
     * @param      $order_item
     * @param      $bom_id
     * @param int  $qty - this already includes the multiplication per parent item
     * @param int  $changed_qty
     * @param      $order_type
     * 
     * @return bool
     */
    public function maybeIncreaseBoMOrderItemInventories( 
        $increase, 
        $order_item, 
        $bom_id, 
        $qty, 
        $changed_qty, 
        $order_type 
    ): bool {

        $po_list = $order_item->get_meta( '_bom_restock_list', true );
        $reduced = $order_item->get_meta( '_reduced_restock', true );

        if (
            empty( $po_list ) || 
            ! array_key_exists( $bom_id, $po_list ) || 
            '' === $reduced
        ) return $increase;

        $old_po_stock = (new Product( $bom_id ))->getPreOrderStock();
        $new_po_stock = POHelpers::updateOrderItem(
            $order_item->get_id(),
            $bom_id,
            $po_list[$bom_id],
            'increase',
            'Increased BOM stock (order #'. $order_item->get_order_id() . ')' 
        );

        $order_item->get_order()->add_order_note( 
            "BOM PRE-ORDER levels increased: " . 
            AtumHelpers::get_atum_product( $bom_id )->get_formatted_name() . 
            " " . 
            $old_po_stock . 
            "&rarr;" . 
            $new_po_stock 
        );

        // Remove reduce pre-order  
        $order_item->delete_meta_data( '_reduced_restock' );
        $order_item->save();

        return $increase;
    }
}