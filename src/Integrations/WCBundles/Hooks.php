<?php
/**
 * Product Bundles Support
 */
namespace Omom\PreOrders\Integrations\WCBundles; 

defined( 'ABSPATH' ) || die;

use Omom\PreOrders\Abstracts\Singleton;
use Omom\PreOrders\Handlers\ProductHandler as Product;
use Omom\PreOrders\Helpers;
use Omom\PreOrders\ProductHooks;
use Omom\PreOrders\Integrations\AtumProductLevels\Helpers as BoM;

class Hooks extends Singleton 
{
    /**
     * Load all necessary hooks
     */
    function __construct()
    {
        add_filter( 'woocommerce_synced_bundled_items_stock_status',   [ $this, 'bundledItemsPreOrderStatus' ], 10, 2);
        add_filter( 'woocommerce_bundled_item_description',            [ $this, 'bundledItemPreOrderHtml' ], 10, 2 );
        add_action( 'woocommerce_bundles_add_to_cart_button',          [ $this, 'bundlePreOrderAvailabilityHtml' ], 10 );
        add_filter( 'woocommerce_bundled_item_add_to_cart_validation', [ $this, 'validatePreOrderBundleItems' ], 10, 6 );
        add_filter( 'woocommerce_bundled_item_quantity_max',           [ $this, 'bundleItemQuantityMax' ], 10, 3 );
    }

    /**
     * Check for preorder stock in bundle
     * This produces a lot of overhead, what can I do?
     * Used during add to cart as well
     * 
     * @param   string              $stock_status
     * @param   WC_Product_Bundle   $bundle
     * @return string
     */
    public function bundledItemsPreOrderStatus( $stock_status, $bundle ): string 
    {
        if ('instock' === $stock_status) return $stock_status;

        foreach ($bundle->get_bundled_data_items() as $bundled_data_item) {

            $bundled_item    = $bundle->get_bundled_item( $bundled_data_item );
            $bundled_product = $bundled_item->product;
            $id              = $bundled_item->get_product_id();
            $bi_stock_status = $bundled_data_item->get_meta( 'stock_status' );

            if (
                ! is_a( $bundled_product, 'WC_Product' ) || 
                'in_stock' === $bi_stock_status
            ) continue;

            // Support for variations
            // bug which showed bundles as outofstock when variable product was sold out with variation in restock
            if ('variable' === $bundled_product->get_type()) {
                foreach ($bundled_item->get_children() as $variation_id) {
                    $po_product = new Product( $variation_id );
                    
                    if ($po_product->hasStock()) continue;
                    
                    $stock_status = $po_product->hasPreOrderStock() ? 'instock' : 'outofstock';
                    
                    if ('instock' === $stock_status) break; // It only needs one variation in re/stock
                }
            } 
            
            // don't need to check optional items further
            if ('yes' === $bundled_data_item->get_meta( 'optional' )) continue;

            // standard check
            if ((new Product( $id ))->hasStock()) {
                $stock_status = 'instock';
                continue;
            } 

            // BOM & Restock
            $stock_status = ProductHooks::getInstance()->preOrderAndBoMCheck( false, $id, $bundled_product ) ? 'instock' : 'outofstock';
            
            if ('outofstock' === $stock_status) break;
        }

        return $stock_status;
    }

    /**
     * Show Restock dates as bundled item description
     * 
     * @param  string           $title
	 * @param  WC_Bundled_Item  $this
     * @return string
     */
    public function bundledItemPreOrderHtml( $description, $bundled_item ): string 
    {
        $id = $bundled_item->get_product_id();
        $po_product = new Product( $id );
       
        if ($po_product->isInPreOrder()) {
            $description .= "<p>Product ready to order. Closest pre-order date " . $po_product->getClosestArrival( true ) . ".</p>";
        }
        
        return $description;
    }

    /**
     * Show avaibility text on the single product page of a product bundle
     * 
     * @param object $product
     */
    public function bundlePreOrderAvailabilityHtml( $product ): void 
    {
        $arrival = [];

        foreach ($product->get_bundled_items() as $bundled_item) {
            $po_product = new Product( $bundled_item->get_product_id() );

            if ($po_product->isInPreOrder()) {
                $arrival[] = $po_product->getClosestArrival();
            }
        }

        if (! empty( $arrival )) {
            $arrival  = date_i18n( "jS \of F Y", max( $arrival ) );
            ?>
            <p class="stock restock">Product ready to order. Closest pre-order date <?= $arrival ?></p>
            <?php
        }        
    }  

    /**
     * Validate bundled items 
     * 
	 * @param  boolean          $is_valid
	 * @param  WC_Product       $product
	 * @param  WC_Bundled_Item  $bundled_item
	 * @param  int              $quantity
	 * @param  mixed            $bundled_variation_id
	 * @param  array            $configuration
     * 
     * @return bool
     */
    public function validatePreOrderBundleItems( 
        bool $is_valid, 
        $product, 
        $bundled_item, 
        $quantity, 
        $bundled_variation_id, 
        $configuration 
    ): bool {
        if (! $product->get_manage_stock()) return $is_valid;

        $id         = $bundled_variation_id ?: $bundled_item->get_product_id();
        $bom_items  = BoM::getLinkedBoMParts( $id ); // make one query for the total bundle?
        $po_product = new Product( $id );

        if (
            empty( $bom_items ) && 
            ! $po_product->hasPreOrderStock()
        ) return $is_valid;
        
        $stock = $po_product->getTotalStock();
        
        if ($quantity > $stock) {
            
            wc_add_notice( 
                __( 
                    'Not enough stock.', 
                    OMOM_PREORDERS()->text_domain 
                ), 
                'error' 
            );

            return false;
        }

        // this does not check the correct quantity if bundle is inside composite 
        return Helpers::compareWithCart( 
            $is_valid, 
            $id, 
            $quantity, 
            $stock, 
            $bom_items 
        );
    }

    /**
     * 'woocommerce_bundled_item_quantity_max' filter.
     *
     * @param  mixed            $qty_max
     * @param  WC_Bundled_Item  $this
     * @param  array            $args
     */
    public function bundleItemQuantityMax( $qty_max, $bundled_item, $args  ): mixed 
    {
        $product_id = $bundled_item->item_data['product_id'] ?? false;

        if (! $product_id) return $qty_max;

        $bom_max = BoM::findMaxValue( $product_id );

        return false === $bom_max ? $qty_max : $bom_max;
    }
}