<?php
/**
 * Cart hooks
 */
namespace Omom\PreOrders\Cart;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Omom\PreOrders\Abstracts\Singleton;
use Omom\PreOrders\Dates;
use Omom\PreOrders\Integrations\AtumProductLevels\Helpers as BoM;
use Omom\PreOrders\Handlers\ProductHandler as Product;

class Hooks extends Singleton
{
    /**
     * Load hooks
     */
    protected function __construct() 
    {
        // While item is added to cart & before it is in the cart - add array of pre-order info to cart item data
        add_filter( 'woocommerce_add_cart_item', [ $this, 'addListToCartItem' ], 10, 2 );

        // When quantity is changed in cart - receives the new pre-order quantity
        add_action( 'woocommerce_after_cart_item_quantity_update', [ $this, 'updateList' ], 10, 4 );

        // Recalculate pre-order quantities when cart item removed or restored
        add_action( 'woocommerce_cart_item_removed', [ $this, 'recalculatePreorderOnRemoveOrRestore' ], 10, 2 );
        add_action( 'woocommerce_restore_cart_item', [ $this, 'recalculatePreorderOnRemoveOrRestore' ], 10, 2 );
        
        // show pre-order list on cart page
        add_filter( 'woocommerce_get_item_data', [ $this, 'addPreOrderDatesToItemData' ], 10, 2 );

        // validate pre-order quantity when changing qty on cart page - last validation in cart
        add_filter( 'woocommerce_update_cart_validation', [ $this, 'updateCartValidation' ], 10, 4 );

        // validation & update list
        add_action( 'woocommerce_store_api_cart_errors',                  [ $this, 'checkPreOrderStoreApiCartItems' ], 10, 2 );
        add_action( 'woocommerce_check_cart_items',                       [ $this, 'checkPreOrderCartItems' ], 10, 1 );
        add_filter( 'woocommerce_cart_item_required_stock_is_not_enough', [ $this, 'cartRequiredStockIsNotEnough' ], 10, 3 );
    }

    /**
     * Add restock_list array to cart_item_data array if product is added with preorder-stock
     * 
     * @since 1.5.0
     * @param array $item
     * @param int   $key
     * 
     * @return array
     */
    public function addListToCartItem( array $item, $key ): array 
    {
        $list = new Lists();
        return $list->addTo( $item, $key );
    }
    
    /**
     * Update restock list on cart item quantity change
     * 
     * @param int $cart_item_key
     * @param int $quantity
     * @param int $old_quantity
     * @param Object $cart WC_Cart
     */
    public function updateList( $cart_item_key, $quantity, $old_quantity, $cart ): void 
    {
        $list = new Lists( $cart, [$cart_item_key => $quantity] );
        $list->update();
    }

    /**
     * Recalculate cart items when product is removed from or restored to cart
     * 
     * @param int    $cart_item_key
     * @param object $cart
     */
    public function recalculatePreorderOnRemoveOrRestore( $cart_item_key, $cart ): void 
    {
        $removed_item = $cart->removed_cart_contents[ $cart_item_key ];
        $product      = wc_get_product( $removed_item['variation_id'] ?: $removed_item['product_id'] );

        // how to deal with composites?
        if (
            is_a( $product, 'WC_Product' ) && 
            $product->is_type( 'composite' )
        ) {
            foreach (wc_cp_get_composited_cart_items( $removed_item, $cart->cart_contents, true, true ) as $child_item_key) {
                $this->recalculatePreorderOnRemoveOrRestore( $child_item_key, $cart );
            }

            return;
        }

        $po_list     = $removed_item['restock_list'] ?? false;
        $bom_po_list = $removed_item['bom_restock_list'] ?? [];
        $dates_info  = $removed_item['restock_info'] ?? false;   

        if (
            ! $po_list && 
            ! $bom_po_list && 
            ! $dates_info
        ) return;

        (new Lists( $cart ))->onRemoveOrRestore( 
            $removed_item['variation_id'] ?: $removed_item['product_id'], 
            $bom_po_list 
        );
    }

    /**
     * Shows all dates on Cart and checkout 
     * 
     * @param array $item_data
     * @param array $cart_item Cart item object.
     * 
     * @return array
     */
    public function addPreOrderDatesToItemData( $item_data, $cart_item ): array 
    {
        $html = (new Dates( $cart_item ))->getHtml();

        if ($html) {
            $item_data[] = [
                'key'   => '<span class="restock-shipment-title">Shipment dates</span>',
                'value' => $html,
            ];
        }

        // DEBUG
        /*
        if (isset( $cart_item['bom_restock_list'] )) 
            print(
                "<pre>BOM_PO_LIST" .
                print_r( $cart_item['bom_restock_list'], true ) .
                "</pre>"
            );

        if (isset( $cart_item['restock_list'] )) 
            print(
                "<pre>PO_LIST" .
                print_r( $cart_item['restock_list'], true ) .
                "</pre>"
            );
        */
        
        return $item_data;
    }

    /**
     * Check cart items when cart is loaded
     * 
     * @todo check if actually works
     * 
	 * @param \WP_Error $errors  WP_Error object.
	 * @param \WC_Cart  $cart    Cart object.
     */
    public function checkPreOrderStoreApiCartItems( $cart_errors, \WC_Cart $cart ): void
    {
        remove_action( 
            'woocommerce_check_cart_items',                       
            [$this, 'checkPreOrderCartItems'], 
            10
        );

        // here we will need to check items like we did in checkPreOrderCartItems
        $this->checkCart( $cart_errors );
    }

    /**
     * Validate cart items in cart and on checkout
     * This is the last time items get validated before creating an order!
     * This is also called in cart 
     * 
     * @todo if list different from before, inform customer
     * @deprecated
     */
    public function checkPreOrderCartItems(): void 
    {       
        // need to update restock_list, because pre-order-/stock levels can have changed in the meantime
        $has_changed = (new Lists( WC()->cart ))->update();
 
        // only on checkout
        if (! empty( $_REQUEST['woocommerce-process-checkout-nonce'] ) && $has_changed) {
            add_action( 'woocommerce_before_thankyou', [$this, 'maybeShowPOUpdateInfo'] );
        }

        $this->checkCart();
    }

    /**
     * 
     */
    public function checkCart( $cart_errors = null ): void
    {
        $product_qty_in_cart      = WC()->cart->get_cart_item_quantities();
        $current_session_order_id = absint( WC()->session->order_awaiting_payment ?? 0 );

        // validate all cart items now
        foreach (WC()->cart->get_cart() as $cart_item) {

            $product = $cart_item['data'];
            $id      = $cart_item['variation_id'] ?: $cart_item['product_id'];

            if (
                ! $product->managing_stock() || 
                'yes' === get_post_meta( $id, '_backorders', true )
            ) continue;

            $handler = new Product( $id );
            $stock   = $handler->getTotalStock();

            if ($stock <= 0) {

                $notice = sprintf( 
                    __( 
                        'Sorry, "%s" is not in stock. Please edit your cart and try again. We apologize for any inconvenience caused.', 
                        OMOM_PREORDERS()->text_domain 
                    ), 
                    $product->get_name() 
                );

                null !== $cart_errors ? 
                $cart_errors->add(
                    'woocommerce_rest_product_out_of_stock',
                    $notice
                )
                : wc_add_notice( 
                    $notice,
                    'error'
                );

            } 

            // Check stock based on all items in the cart and consider any held stock within pending orders.
            $held_stock     = wc_get_held_stock_quantity( $product, $current_session_order_id );
            $required_stock = $product_qty_in_cart[ $product->get_stock_managed_by_id() ];

            if ($stock >= ($held_stock + $required_stock)) continue;

            $notice = sprintf( 
                __( 
                    'Sorry, we do not have enough "%1$s" in stock to fulfill your order (%2$s available). We apologize for any inconvenience caused.', 
                    OMOM_PREORDERS()->text_domain 
                ), 
                $product->get_name(), 
                wc_format_stock_quantity_for_display( $stock - $held_stock, $product ) 
            );

            null !== $cart_errors ?
            $cart_errors->add(
                'woocommerce_rest_product_partially_out_of_stock',
                $notice
            )
            : wc_add_notice(
                $notice,
                'error' 
            );
        }
    }

	/**
	 * Validates in-cart component quantity changes.
	 *
	 * @param  bool    $passed
	 * @param  string  $cart_item_key
	 * @param  array   $cart_item
	 * @param  int     $quantity
     * 
	 * @return bool
	 */
    public function updateCartValidation( $passed, $cart_item_key, $cart_item, $quantity ): bool 
    {
        return (new Validation())->cart(
            $passed,
            $cart_item_key,
            $cart_item,
            $quantity
        );
    }

    /**
     * A copy of the previous method
     * It  will be obsolete, if stock quantity is used
     * 
     * @param bool       $enough     If have enough stock.
     * @param WC_Product $product   Product instance.
     * @param array      $cart_item Cart item values.
     *
     * @return bool
     */
    public function cartRequiredStockIsNotEnough( $enough, $product, $cart_item ): bool
    {
        if (! $product) return true;

        $managed   = $product->get_manage_stock();
        $stock     = $product->get_stock_quantity() ?? 0;
        $quantity  = $cart_item['quantity'];
        $id        = $product->get_id();

        if (
            ! $managed || 
            ($stock >= $quantity || 'yes' === get_post_meta( $id, '_backorders', true ) )
        ) return $enough;
 
        $bom_items = BoM::getLinkedBoMParts( $id );

        if (
            ! empty($bom_items) || 
            (new Product( $id ))->getPreOrderStock()
        ) return false;
        
        return $enough;
    }

    /**
     * When the po-info has changed, tell that to the customer
     */
    public function maybeShowPOUpdateInfo(): void
    {   
        wc_add_notice( 
            __( 
                'Shipment dates were recalculated due to stock changes since checkout. Please review if all looks good.', 
                OMOM_PREORDERS()->text_domain 
            ), 
            'notice' 
        );
    }
}