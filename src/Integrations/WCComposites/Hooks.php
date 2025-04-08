<?php
/**
 * Composite Products Support
 */
namespace Omom\PreOrders\Integrations\WCComposites; 

defined( 'ABSPATH' ) || die;

use Omom\PreOrders\Abstracts\Singleton;
use Omom\PreOrders\Helpers;
use Omom\PreOrders\Integrations\AtumProductLevels\Helpers as BoM;
use Omom\PreOrders\Handlers\ProductHandler;

class Hooks extends Singleton 
{
    /**
     * Load all necessary hooks
     */
    function __construct()
    {
        add_filter( 'woocommerce_composited_product_availability',            [ $this, 'compositeVariationArrivalDate' ], 10, 3 );
        add_filter( 'woocommerce_composite_component_options_query_args',     [ $this, 'compositedProductPreOrderData' ], 10, 3 );
        add_filter( 'woocommerce_composite_component_add_to_cart_validation', [ $this, 'addToCartComponentValidation' ], 11, 8 );
    }

    /**
     * Show pre-order arrival date in composite variations
     * 
     * @since  0.0.1
     * @param  array          $availability
     * @param  WC_Product     $product
	 * @param  WC_CP_Product  $composite_product
     * 
     * @return array
     */
    public function compositeVariationArrivalDate( $availability, $product, $composite_product ): array 
    {
        $stock = $product->get_stock_quantity();

        if ($stock > 0) return $availability;

        $id         = $product->get_id();
        $po_product = new ProductHandler( $id );
        $po_stock   = $po_product->getPreOrderStock();
        $bom_rs     = BoM::getClosestBoMArrival( $id );

        /* If the product is a bundle, loop through the bundle and retrieve the best 
        if( $product->is_type('bundle') && function_exists('wc_pb_get_bundled_product_map') ) {
            $bundle_map = wc_pb_get_bundled_product_map( $product, true );
        }*/

        // BOM
        if ($bom_rs) {
            $availability['availability'] = $bom_rs;
            $availability['class']        = 'pre-order-available';
        } // Pre-Order
        else if ($po_stock) {
            $arrival                      = $po_product->getClosestArrival();
            $availability['availability'] = $arrival !== '' ? $arrival : '';
            $availability['class']        = 'pre-order-available';
        }

        return $availability;
    }

    /**
     * Make sold out products visible again in composited products. 
     * This is necessary for the variable products. When a variable product is sold out, but one of its variations has a pre-order, 
     * it's not being shown correctly inside the composite product. 
     * 
     * This needs change
     * 
	 * @param  array  $wp_query_args
	 * @param  array  $cp_query_args
	 * @param  array  $component_data
     * 
     * @return array
     */
    public function compositedProductPreOrderData( $args, $query_args, $component_data ): array 
    {
        if (isset( $args[ 'tax_query' ] )) {
            foreach ($args[ 'tax_query' ] as $key => $tax_query) {
                if ($tax_query['taxonomy'] === 'product_visibility') {
                    unset( $args[ 'tax_query' ][$key] );
                }
            }
        }

        return $args;
    }

    /**
     * Validate add to cart stock
     * Validates: Composite components
     * 
     * @since 0.0.1
     * @param  boolean               $add
	 * @param  string                $composited_product_id
	 * @param  int                   $quantity
	 * @param  array                 $component_configuration
     * 
     * @return bool
     */
    public function addToCartComponentValidation( 
        $add,
        $product_id, 
        $component_id, 
        $component_product_id, 
        $quantity, 
        $cart_item_data, 
        $composite, 
        $component_configuration 
    ): bool {

        $is_variable = isset( $component_configuration['type'] ) && $component_configuration['type'] === 'variable';
        $id          = $is_variable ? $component_configuration['variation_id'] : $component_product_id;
        $product     = wc_get_product( $id );
        $backorders  = get_post_meta( $id, '_backorders', true );

        if (
            ! is_a( $product, 'WC_Product' ) || 
            ! $product->get_manage_stock() || 
            "yes" === $backorders
        ) return $add;

        $po_product = new ProductHandler( $id );
        $bom_items  = BoM::getLinkedBoMParts( $id ); // make one query for the total composite? 

        if (
            empty( $bom_items ) && 
            ! $po_product->hasPreOrderStock()
        ) return $add;
        
        $cmplt_stock = $po_product->getTotalStock();

        // Check if composite is updated
        if (
            isset( $_POST[ 'update-composite' ] ) && 
            ! empty( $po_stock ) && 
            ($updated_item = WC()->cart->cart_contents[ wc_clean( $_POST[ 'update-composite' ] ) ])
        ) {
            $updated_item_id = $updated_item['variation_id'] ?? $updated_item['product_id'];
            
            if (
                $updated_item_id === $id && 
                ($quantity - $updated_item['quantity']) > $cmplt_stock
            ) {
                wc_add_notice( 
                    __( 
                        'Updating failed. Not enough stock.', 
                        OMOM_PREORDERS()->text_domain 
                    ), 
                    'error' 
                );

                return false;
            } 
        }

        // No stock
        if ($cmplt_stock < 0) {
            wc_add_notice(
                __( 
                    'This product has not enough stock.', 
                    OMOM_PREORDERS()->text_domain 
                ), 
                'error' 
            );

            return false;
        }

        // Not enough stock
        if ($cmplt_stock < $quantity) {
            wc_add_notice( 
                __( 
                    'You are trying to add '. $quantity . ' but there are only ' . $cmplt_stock . ' in stock.', 
                    OMOM_PREORDERS()->text_domain 
                ), 
                'error' 
            );

            return false;
        }

        // Check other cart items
        return Helpers::compareWithCart( 
            $add, 
            $id, 
            $quantity, 
            $cmplt_stock, 
            $bom_items 
        );
    }
}