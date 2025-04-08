<?php
/**
 * To be able to query products in Shipments
 *
 * taxonomy: restocked_products
 * Every term contains a product id as slug and name
 */
namespace Omom\PreOrders;

class Terms
{
    /**
     * Get all pre-ordered products by restock_products taxonomy
     */
    public static function getAll(): array
    {
        $terms = get_terms([
            'taxonomy'   => 'restocked_products',
            'hide_empty' => false,
        ]);
        return is_array( $terms ) ? $terms : [];
    }

    /**
     * Get all shipments which have our taxonomy
     * Ordered by product_id
     * 
     * @return array - [ product_id => [ shipment_id => empty_string ] ]
     */
    public static function getAllObjectIds(): array
    {
        $objects = [];
        
        // loop through all products in taxonomy
        foreach (self::getAll() as $product) {
            
            // remove empty terms
            if (0 === $product->count) {
                self::delete( $product->term_id );
                continue;
            }
 
            $id        = $product->name;
            $shipments = Queries::getAllShipmentIdsByProductId( $id );

            foreach($shipments as $shipment_id => $v)
                if (! isset( $objects[$id][$shipment_id] ))
                    $objects[$id][$shipment_id] = "";
        }

        return $objects;
    }

    /**
     * get term object by product id
     * 
     * @param int $product_id
     * @return WP_Term
     */
    public static function getTermByProductId( int $product_id )
    {
        return get_term_by( 'slug', $product_id, 'restocked_products' );
    }

    /**
     * Get an array with all product ids in a specific shipment
     * 
     * @return array only contains product ids
     */
    public static function getProductsInShipment( int $shipment_id ): array
    {
        return wp_get_post_terms( $shipment_id, 'restocked_products', [ 'fields' => 'slugs' ] );
    }

    /**
     * Add a product id to a shipment
     * 
     * @param int $shipment_id
     * @param int $product_id
     */
    public static function add( int $shipment_id, int $product_id ): void
    {
        wp_add_object_terms( $shipment_id, (string) $product_id, 'restocked_products' );
    }

    /**
     * Remove one product from query-taxonomy of a shipment
     * We want it gone, when it has a quantity of exactly 0 inside a shipment
     * Or if it is not in the shipment anymore
     * 
     * @param int $shipment_id
     * @param int $product_id
     */
    public static function remove( int $shipment_id, int $product_id ): void
    {
        wp_remove_object_terms( $shipment_id, (string) $product_id, 'restocked_products' );
    }

    /**
     * Delete a product from the taxonomy
     * 
     * @param int $term_id
     */
    public static function delete( int $term_id ): void
    {
        wp_delete_term( $term_id, 'restocked_products' );
    }

    /**
     * Delete the product from the taxonomy, so it does not distract anymore
     * 
     * @param int $term_id
     * @param int $product_id - if one does not have the term_id
     */
    public static function deleteByProductId( int $product_id = 0 ): void
    {
        global $wpdb;
        
        $term_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->prefix}terms WHERE slug = %d", $product_id ) );

        self::delete( $term_id );
    }
}