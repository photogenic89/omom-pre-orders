<?php
/**
 * All hooks connected to the products
 */
namespace Omom\PreOrders;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Omom\PreOrders\Abstracts\Singleton;
use Omom\PreOrders\Handlers\ProductHandler as Product;
use Omom\PreOrders\Integrations\AtumProductLevels\Helpers as BoM;
use Omom\PreOrders\Helpers;

class ProductHooks extends Singleton
{
    /**
     * Load hooks
     */
    protected function __construct() 
    {
        add_action( 'wp_enqueue_scripts', [$this, 'bikeBuilderArrivalTimesScript'], 999 );
        
        add_filter( 'woocommerce_quantity_input_args',                [$this, 'quantityInputMax'], 10, 2 );
        add_filter( 'woocommerce_product_get_stock_status',           [$this, 'productGetStockStatus'], 10, 2 );
        add_filter( 'woocommerce_product_variation_get_stock_status', [$this, 'productGetStockStatus'], 10, 2 );
        add_filter( 'woocommerce_product_backorders_allowed',         [$this, 'preOrderAndBoMCheck'], 100, 3 );
        add_filter( 'woocommerce_product_is_in_stock',                [$this, 'productIsInStock'], 999, 2 );
        add_filter( 'woocommerce_get_stock_html',                     [$this, 'getStockHtml'], 10, 2 );
        add_filter( 'woocommerce_get_availability_class',             [$this, 'getAvailabilityClass'], 10, 2 );
        add_filter( 'woocommerce_available_variation',                [$this, 'adjustVariationMaxQuantity'], 10, 1 );
        add_filter( 'woocommerce_add_to_cart_validation',             [$this, 'addToCartValidation'], 10, 5 );
    }

    /**
     * Enqueue script for product pages
     */
    public function bikeBuilderArrivalTimesScript(): void 
    {
        wp_enqueue_style( 
            'pre_order_styles', 
            OMOM_PREORDERS()->pluginUrl() . '/assets/css/dist/pre-order-styles.css', 
            [], 
            OMOM_PREORDERS()->version 
        );
        
        if (! is_product()) return;

        wp_register_script( 
            'pre_order_script', 
            OMOM_PREORDERS()->pluginUrl() . '/assets/js/dist/omom-arrival.js', 
            [ 'jquery' ], 
            OMOM_PREORDERS()->version, 
            true 
        );

        if (has_term( 'bbv2', 'product_tag', get_the_ID() )) wp_enqueue_script( 'pre_order_script' );
    }

    /**
     * Adjust input max
     * 
     * @since 0.0.1
     * @param array      $defaults
     * @param WC_Product $product Product Object.
     * 
     * @return array 
     */
    public function quantityInputMax( array $defaults, $product ): array 
    {
        if ($product->managing_stock()) {
            $id = $product->get_id();
            $defaults['max_value'] = (new Product( $id ))->getTotalStock();
        }

        return $defaults;
    }

    /**
     * All user roles
     * Get product stock status - instock or outofstock
     * 
     * @param string $status
     * @param object $product
     * 
     * @return string
     */
    public function productGetStockStatus( $status, $product ): string
    {
        return 'onbackorder' === $status ? $status : ((new Product( $product->get_id() ))->canBePreOrdered( 'instock' === $status ) ? 'instock' : 'outofstock');
    }

    /**
     * Check if product has BOM or pre-order and if there is enough of it
     * 
     * @param bool        $status
     * @param int         $product_id
     * @param \WC_Product $product
     * 
     * @return bool
     */
    public function preOrderAndBoMCheck( bool $status, int $product_id, \WC_Product $product ): bool 
    {
        return (new Product( $product->get_id() ))->canBePreOrdered( $status );
    }

    /**
     * Check if product has metadata and stock
     * 
     * @param  bool   $is_in_stock
     * @param  object $product
     * 
     * @return bool
     */
    public function productIsInStock( $is_in_stock, $product ): bool 
    {
        if ($is_in_stock) return $is_in_stock;

        $is_in_stock = (new Product( $product->get_id() ))->canBePreOrdered( $is_in_stock );

        if (! $product->is_type( 'variable' )) return $is_in_stock;

        // in case variable product is sold out but has pre-ordered variations
        $variations = $product->get_children();

        // for later refactoring: maybe give variable product a boolean post_meta "child_has_po_stock", so we don't have to loop through all variations
        // this also might make the bundle sync method obsolete, since we only sync it because of the variations
        foreach ($variations as $var_id) {
            if (0 < (new Product( $var_id) )->getPreOrderStock( false )) return true;
        }

        return $is_in_stock;
    }

    /**
     * Get HTML to show product stock.
     *
     * @since  0.0.1
     * @param  WC_Product $product Product Object.
     * 
     * @return string
     */
    public function getStockHtml( $html, $product ): string 
    {
        $stock = $product->get_stock_quantity();

        if (! $product->managing_stock() || $stock > 0) return $html;

        $html = "";

        $id         = $product->get_id();
        $po_product = new Product( $id );
        $max_value  = BoM::findMaxValue( $id );
        
        // BOM
        if (false !== $max_value) {
            if (! BoM::atumProductIsAvailable( $id )) return $html;

            $bom_date = BoM::getClosestBoMArrival( $id );

            if ($max_value <= 5) $html .= '<p class="pre-order-available pre-order-blue">Last ' . $max_value . ' left for purchase</p>';
            if ($bom_date) $html .= '<p class="pre-order-available pre-order-blue">Pre-order now. In stock: ' . date_i18n( "jS \of F Y", $bom_date ) . '</p>';

            return $html;
        } 
        
        $po_stock = $po_product->getPreOrderStock();

        //Pre-Order
        if ($po_stock) {

            $arrival = $po_product->getClosestArrival( true );

            if ($po_stock <= 5) $html .= '<p class="stock in-stock pre-order-blue">Last ' . $po_stock . ' left for purchase</p>';
            $html .= '<span class="pre-order-blue">Pre-order now. In stock ' . $arrival . "</span>";
        }

        return $html;
    }

    /**
     * Get availability class
     * 
     * @param string $class
     * @param WC_Product $product
     * 
     * @return string 
     */
    public function getAvailabilityClass( string $class, $product ): string 
    {
        return (new Product( $product->get_id() ))->isInPreOrder() ? "in-stock" : $class;
    }
    
    /**
     * Change max input for Variation with pre-order
     * 
     * @param array $data
     * 
     * @return array
     */
    public function adjustVariationMaxQuantity( array $data ): array 
    {
        $id = $data['variation_id'] ?? '';
        $data['max_qty'] = (new Product( $id ))->getTotalStock();

        return $data;
    }

    /**
     * Validate add to cart stock
     * Validates: Simple, Variable, Variation, Composite and Bundle products
     * 
     * @since 0.0.1
     * @param bool  $valid
     * @param int   $product_id
     * @param int   $quantity
     * 
     * @return bool
     */
    public function addToCartValidation( 
        $valid, 
        $product_id, 
        $quantity, 
        $variation_id = 0, 
        $variations = [] 
    ): bool {

        $id      = $variation_id ?: $product_id;
        $product = wc_get_product( $id );

        if (
            ! $product || 
            ! $product->get_manage_stock()
        ) return $valid;

        $bom_items  = BoM::getLinkedBoMParts( $id );
        $po_product = new Product( $id );

        // only continue if 
        if (
            empty( $bom_items ) && 
            ! $po_product->hasPreOrderStock()
        ) return $valid;

        $max_value = $po_product->getTotalStock();

        if ($max_value < $quantity) {
            wc_add_notice( 
                __( 
                    'There is not enough stock to add this quantity.', 
                    OMOM_PREORDERS()->text_domain 
                ), 
                'error' 
            );

            return false;
        }

        return Helpers::compareWithCart( 
            $valid, 
            $id, 
            $quantity, 
            $max_value, 
            $bom_items 
        );
    }
}