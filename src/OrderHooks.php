<?php
/**
 * Orders
 *
 * @author   Studio Koepfchen <info@studiokoepfchen.com>
 * @package  Omom Pre-Orders
 * @since    0.0.1
 */
namespace Omom\PreOrders;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die;

use Omom\PreOrders\Abstracts\Singleton;
use Omom\PreOrders\Handlers\ProductHandler as Product;
use Omom\PreOrders\Helpers;

/**
 * Adjust WC Orders
 *
 * @class    Orders
 * @version  0.0.1
 */
class OrderHooks extends Singleton
{
    /**
     * Load all hooks
     */
    protected function __construct() 
    {
        // Add pre-order meta to order items 
        add_action( 'woocommerce_checkout_create_order_line_item', [$this, 'checkoutCreateOrderLineItemMeta'], 10, 3 );

        // Stock levels
        add_filter( 'woocommerce_order_item_quantity', [$this, 'decreaseOrderItemQuantity'], 100, 3 );
        add_action( 'woocommerce_restore_order_stock', [$this, 'increaseOrderStockLevels'], 10 );

        // Item meta
        add_filter( 'woocommerce_hidden_order_itemmeta', [$this, 'hidePreOrderMeta'], 10, 1 );
    }

    /**
     * Action hook to adjust item before save.
     * 
     * @param WC_Order_Item_Product $item
     * @param string                $cart_item_key
     * @param array                 $values -> same as $cart_item in cart object
     * @param WC_Order              $order
     */
    public function checkoutCreateOrderLineItemMeta( $item, $cart_item_key, $cart_item ): void 
    {
        if (isset( $cart_item['bom_restock_list'] )) $item->add_meta_data( '_bom_restock_list', $cart_item['bom_restock_list'], true );
        if (isset( $cart_item['restock_list'] ))     $item->add_meta_data( '_restock_list',     $cart_item['restock_list'],     true );
        if (isset( $cart_item['restock_info'] ))     $item->add_meta_data( 'Shipment dates',    $cart_item['restock_info'],     true );
    }

    /**
     * Log were pre-order stock was taken from
     * 
     * @param  int    $item_quantity
     * @param  object $order
     * @param  object $item
     * 
     * @return int    $item_quantity
     */
    public function decreaseOrderItemQuantity( $item_quantity, $order, $item ): int 
    {
        $po_list = $item->get_meta( '_restock_list', true );
        $reduced = $item->get_meta( '_reduced_restock', true );

        if (
            empty( $po_list ) || 
            '' !== $reduced
        ) return $item_quantity;

        $product = $item->get_product();
        $id      = $product->get_id();

        // Save
		$item->add_meta_data( 
            '_reduced_restock', 
            true, 
            true 
        );
		$item->save();

        // Order note displaying pre-order changes
        $old_po_stock = (new Product( $id ))->getPreOrderStock();
        $new_po_stock = Helpers::updateOrderItem( 
            $item->get_id(),
            $id, 
            $po_list, 
            'decrease',
            'Decreased stock (order #' . $order->get_id() . ")"
        );

        $order->add_order_note( 
            'PRE-ORDER levels used: ' . $product->get_formatted_name() . ' ' . $old_po_stock . '&rarr;' . $new_po_stock 
        );

        return $item_quantity;
    }

    /**
     * Increase pre-order stock again. When orders are cancelled for example
     * 
     * @param int|WC_Order $order_id Order ID or order instance.
     */
    public function increaseOrderStockLevels( $order ): void 
    {
        $changes = [];

        foreach ($order->get_items() as $item_id => $item) {

            $product = $item->get_product();
            $po_list = $item->get_meta( '_restock_list', true );
            $reduced = $item->get_meta( '_reduced_restock', true );

            if (
                ! $product || 
                ! $po_list || 
                '' === $reduced  || 
                ! $product->managing_stock()
            ) continue;

            $product_id = $product->get_id();

            // update product pre-order stock
            $old_po_stock = (new Product( $product_id ))->getPreOrderStock(); // @todo does this work correctly with BOM?
            $new_po_stock = Helpers::updateOrderItem(
                $item_id,
                $product_id,
                $po_list,
                'increase',
                'Increased stock (order #'. $order->get_id() . ')' 
            );

            // tell it to the world
            $item_name = $product->get_formatted_name();
            $changes[] = $item_name . ' ' . $old_po_stock . '&rarr;' . $new_po_stock;

            $item->delete_meta_data( '_reduced_restock' );
		    $item->save();
        }

        if ([] === $changes) return;
        
        $order->add_order_note( 
            __( 
                'PRE-ORDER levels increased:', 
                OMOM_PREORDERS()->text_domain 
            ) . ' ' . implode( ', ', $changes ) 
        );
    }

    /**
     * Hide for clients unnecessary meta
     * 
     * @param array $blacklist
     * 
     * @return array
     */
    public function hidePreOrderMeta( array $blacklist ): array 
    {
        $blacklist[] = '_reduced_restock';
        $blacklist[] = '_restock_list';
        return $blacklist;
    }  
}