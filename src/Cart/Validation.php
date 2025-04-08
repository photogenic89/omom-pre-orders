<?php
namespace Omom\PreOrders\Cart;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Omom\PreOrders\Integrations\AtumProductLevels\Helpers as BoM;
use Omom\PreOrders\Handlers\ProductHandler as Product;

class Validation
{
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
    public function cart( 
        bool $passed, 
        string $cart_item_key, 
        array $cart_item, 
        int $quantity 
    ): bool {
        
        $id = $cart_item['variation_id'] ?: $cart_item['product_id'];

        // Check composite parent
        if (! empty( $cart_item[ 'composite_data' ] )) {

            if (! $this->composite( 
                $passed,
                $cart_item,
                $quantity
            )) return false;

        } // if is bundle, check all bundled items first. 
        // otherwise it might succeed changing stock with wrong child-values
        else if (isset( $cart_item['bundled_items'] )) {
            if (! $this->bundle(
                $passed,
                $cart_item['bundled_items'],
                $quantity
            )) return false;
        }

        if (! $this->boM(
            $passed,
            $cart_item_key,
            $quantity
        )) return false;

        return $this->cartItem( 
            $passed, 
            $id, 
            $cart_item['data'], 
            $quantity, 
            $cart_item_key 
        );
    }

    /**
     * Validate a composite cart item
     * 
     * @param bool $passed
     * @param array $cart_item
     * @param int $quantity
     * 
     * @return bool 
     */
    public function composite( $passed, $cart_item, $quantity ): bool
    {
        $child_items = wc_cp_get_composited_cart_items( $cart_item, WC()->cart->cart_contents, false, true );

        foreach ($child_items as $child_item_key => $child_item) {
            
            $child_id = $child_item['variation_id'] ?: $child_item['product_id'];

            // Bundles inside composite produts
            if (isset( $child_item['bundled_items'] )) {
                if (! $this->bundle( 
                    $passed,
                    $child_item['bundled_items'],
                    $quantity
                )) return false;
            }

            if (! $this->cartItem( 
                $passed, 
                $child_id, 
                $child_item['data'], 
                $quantity, 
                $child_item_key 
            )) return false;
        }

        return $passed;
    }

    /**
     * Validate the bundled items inside a bundle cart item
     * 
     * @param bool $passed
     * @param array $bundled_items
     * @param int $quantity
     * 
     * @eturn bool
     */
    public function bundle( bool $passed, array $bundled_items, int $quantity ): bool
    {
        foreach ($bundled_items as $child_item_key) {
            if (! $this->cart( 
                $passed, 
                $child_item_key, 
                WC()->cart->cart_contents[$child_item_key], 
                $quantity 
            )) return false;
        }

        return $passed;
    }

    /**
     * Check BOM - need to call it every cart item, to prevent errors while updating multiple products simultaneously
     */
    public function boM( $passed, $cart_item_key, $quantity ): bool
    {
        $all_bom_items = BoM::maybeGetAllBoMItemsInCart( $cart_item_key, $quantity, true );

        if (! empty( $all_bom_items )) {
            foreach ($all_bom_items as $bom_id => $data) {
                
                $po_product = new Product( $bom_id );
                
                if ($data['qty_all'] <= $po_product->getTotalStock()) continue;
                
                wc_add_notice( sprintf( __( 'Sorry, not enough parts, mate.', OMOM_PREORDERS()->text_domain ), '' ), 'error' );
                
                return false;
            }
        }

        return $passed;
    }

    /**
     * Check for BOM or Pre-Order stock + compare with other cart items and held stock
     * 
     * @param bool $passed
     * @param int  $id
     * @param \WC_Product  $product
     * @param int  $quantity
     * @param string $cart_item_key
     * 
     * @return bool
     */
    public function cartItem( $passed, $id, $product, $quantity, $cart_item_key ): bool 
    {    
        if (! $product) return false;

        $managed = $product->get_manage_stock();

        if (
            ! $managed || 
            'yes' === get_post_meta( $id, '_backorders', true )
        ) return $passed;
 
        $stock = $product->get_stock_quantity() ?? 0;
        $bom_max = BoM::findMaxValue( $id );

        // BOM
        if (false !== $bom_max) {
            $stock = $bom_max;
        } // Pre-Order
        else if ($pre_order = (new Product( $id ))->getPreOrderStock()) {
            $stock > 0 ? $stock += $pre_order : $stock = $pre_order;
        } // Not managing stock, we don't need to check it
        else {
            return $passed;
        }
        
        if ($stock <= 0) {
            wc_add_notice( sprintf( __( 'Sorry, "%s" is not in stock. Please edit your cart and try again. We apologize for any inconvenience caused.', OMOM_PREORDERS()->text_domain ), $product->get_name() ), 'error' );
            return false;
        }

        // Check stock based on all items in the cart
        $required_stock = 0;
        $search_id = $product->get_stock_managed_by_id();

		foreach (WC()->cart->get_cart() as $key => $values) {
            if ($cart_item_key === $key || $search_id !== $values['data']->get_stock_managed_by_id()) continue;
			$required_stock += $values['quantity'];
		}

        // Consider any held stock within pending orders
        $current_session_order_id = isset( WC()->session->order_awaiting_payment ) ? absint( WC()->session->order_awaiting_payment ) : 0;
        $held_stock               = wc_get_held_stock_quantity( $product, $current_session_order_id );

        $quantity = $required_stock + $held_stock + $quantity;

        if ($stock < $quantity) { 
            wc_add_notice( sprintf( __( 'Sorry, we do not have enough "%1$s" in stock to fulfill your order (%2$s available). We apologize for any inconvenience caused.', OMOM_PREORDERS()->text_domain ), $product->get_name(), wc_format_stock_quantity_for_display( $stock - $held_stock, $product ) ), 'error' );
            return false;
        }

        return $passed;
    }
}