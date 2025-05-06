<?php
/**
 * Extending the WC StoreApi
 */
namespace Omom\PreOrders;

if ( ! defined( 'ABSPATH' ) ) exit;

use Omom\PreOrders\Handlers\ProductHandler;
use Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema;

class StoreApi
{
    /**
     * Register the custom endpoint in the WC StoreApi
     */
    public static function registerEndpoint(): void
    {
        if (! function_exists('woocommerce_store_api_register_endpoint_data')) return;
        
        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => ProductSchema::IDENTIFIER,
            'namespace'       => 'omomPreOrders',
            'data_callback'   => [__CLASS__, 'dataCallback'],
            'schema_callback' => [__CLASS__, 'schemaCallback'],
            'schema_type'     => ARRAY_A,
        ]);
    }

    /**
     * Registers custom product meta into product response endpoint.
     *
     * @param \WC_Product $product Current product.
     *
     * @return array Array with the custom meta value.
     */
    public static function dataCallback( \WC_Product $product ): array 
    {
        $handler = new ProductHandler( $product->get_id() );

        return [
            'preOrderStock'       => 1 * $handler->getPreOrderStock( false ),
            'closestPreOrderDate' => 1 * ((int) $handler->getClosestArrival( false, false )), // a unix timestamp, for when the product is available 
        ];
    }

    /**
     * Registers custom product schema into schema endpoint.
     *
     * @return array
     *   Registered schema.
     */
    public static function schemaCallback(): array 
    {
        return [
            'preOrderStock' => [
                'description' => __('the pre-order stock quantity', OMOM_PREORDERS()->text_domain ),
                'type'        => 'integer',
                'readonly'    => TRUE,
            ],
            'closestPreOrderDate' => [
                'description' => __('the closest date to pre-order', OMOM_PREORDERS()->text_domain ),
                'type'        => 'integer',
                'readonly'    => TRUE,
            ],
        ];
    }
}