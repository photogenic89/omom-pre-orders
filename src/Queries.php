<?php
/**
 * Get and set products
 */
namespace Omom\PreOrders;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Omom\PreOrders\Handlers\ShipmentHandler;
class Queries
{
    /**
	 * Find all products with pre-order stock
	 * 
	 * @return array post_id as key
	 */
	public static function getAllProductIdsWithPOStock(): array 
	{
		global $wpdb;

		$request = $wpdb->get_results( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key LIKE '_omom_po_stock'", OBJECT_K );

		return array_flip( array_keys($request ?? []) );
	}
    
    /**
     * Gets all restock post ids
     * If only the id is needed
     * More performant than getAllShipmentsWithProduct
     * 
     * @param int $product_id
     * 
     * @return array
     */
    public static function getAllShipmentIdsByProductId( int $product_id ): array
    {
		global $wpdb;
        
        // Find out term taxonomy id
        $term = Terms::getTermByProductId( $product_id );
        $term_id = $term ? $term->term_id : 0; // $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->prefix}terms WHERE slug = %d", $product_id ) );

		// Find object ids in wp_term_relationships
        return $term_id ? $wpdb->get_results( $wpdb->prepare( "SELECT object_id FROM {$wpdb->prefix}term_relationships WHERE term_taxonomy_id = %d", $term_id ), OBJECT_K ) : [];
    }

    /**
     * Get all shipments which contain a certain product
     * 
     * @param int $product_id
     * 
     * @return array
     */
    public static function getAllShipmentsWithProduct( int $product_id ): array
    {
        $all_shipments = [];

        $shipments = get_posts([
            'posts_per_page' => -1, 
            'post_type'      => Init::$post_type,
            'post_status'    => 'publish',
            'tax_query'      =>             [
                'taxonomy' => Init::$taxonomy,
                'field'    => 'slug',
                'terms'    => $product_id,
            ],
        ]);
            
        foreach ($shipments as $shipment) {

            $id      = (int) $shipment->ID;
            $handler = new Handlers\ShipmentHandler( $id );
            $product = $handler->getProduct( $product_id );

            // only if the shipment can't find inside
            if (false === $product) continue;

            $all_shipments[] = [
                "id"        => $id,
                "original"  => (int) ($product['Original'] ?? 0),
                "available" => (int) ($product['Restock'] ?? 0),
                "arrival"   => $handler->getArrival()
            ];
        }

        return $all_shipments;
    }

    /**
     * Get all shipments which are not yet released
     * and sort them by date
     * 
     * @param int   $product_id - if want to filter for a certain product
     * @param bool  $ids_only - return only ids
     * @param bool  $shortcode_filter - if filtered in shipment
     * 
     * @return array
     */
    public static function getActiveShipments( int $product_id = 0, bool $ids_only = false, bool $filter = false ): array 
    {   
        $args = [
            'post_type'      => Init::$post_type,
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'rs_arrival',
            'order'          => 'ASC',
            'post_status'    => 'publish', // what about is_draft?
            'posts_per_page' => -1,
            'meta_query'     => [
                [       
                    'relation' => 'OR',         
                    [
                        // all the not yet released shipments
                        'key'     => 'omom_shipment_status',
                        'value'   => 'released',
                        'compare' => '!=',
                        'type'    => 'string'
                    ],
                    [
                        // or which do not have the status meta
                        'key'     => 'omom_shipment_status',
                        'compare' => 'NOT EXISTS',
                    ]  
                ]  
            ],
        ];

        if ($product_id) $args['tax_query'] = [
            [
                'taxonomy' => Init::$taxonomy,
                'field'    => 'slug',
                'terms'    => $product_id,
            ],
        ];

        if ($filter) {
            $args['meta_query']['relation'] = 'AND';
            $args['meta_query'][] = [
                'key'   => 'rs_show_in_future_stock',
                'value' => 'on'
            ];
        }

        if ($ids_only) $args['fields'] = 'ids';

        $shipments = get_posts( $args );

        // when we only go by shipment ids
        if ($ids_only) return $shipments;

        $active_shipments = [];

        foreach ($shipments as $shipment) {

            $id      = (int) $shipment->ID;
            $handler = new Handlers\ShipmentHandler( $id );
            $arrival = $handler->getArrival();

            // when we look for all products
            if (! $product_id) {
                $active_shipments[$id] = $handler;
                continue;
            }

            // when we look for one product
            $product = $handler->getProduct( $product_id );
            
            if ([] == $product) continue;
            
            $active_shipments[$arrival] = [
                $id => $product['quantity']
            ];
        }

        return $active_shipments;
    }

    /**
     * Get container name based on arrival date
     * better if we had the restock id, so we wouldn't need to check 
     * 
     * @param int $arrival unix timestampe
     * 
     * @return string
     */
    public static function getContainerIdByArrival( int $arrival ): string
    {
        if (0 === $arrival) return "";

        $post = get_posts([
            'post_type'      => Init::$post_type,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND', 
                [
                    'key'     => 'rs_container',
                    'compare' => 'EXISTS',
                ],
                [
                    'key'       => 'rs_arrival',
                    'value'     => $arrival,
                ]
            ]
        ]);

        return isset($post[0]) ? (new Handlers\ShipmentHandler( $post[0] ))->getContainerID() : "";
    }

    /**
     * Get the next closest shipment by id
     * 
     * shipment arrival date can be passed but 
     * should contain an available quantity
     * 
     * @param  int  $current_post_id
     * 
     * @return int  $post_id    pre-order post id
     */
    public static function getClosestShipmentId( int $product_id ): int 
    {
        $shipments = get_posts([
            'post_type'      => Init::$post_type,
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'rs_arrival',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => Init::$taxonomy,
                    'field'    => 'slug',
                    'terms'    => $product_id,
                ],
            ],
            'meta_query'    => [
                [       
                    'relation' => 'OR',         
                    [
                        // all the not yet released shipments
                        'key'     => 'omom_shipment_status',
                        'value'   => 'released',
                        'compare' => '!=',
                        'type'    => 'string'
                    ],
                    [
                        // or which do not have the status meta
                        'key'     => 'omom_shipment_status',
                        'compare' => 'NOT EXISTS',
                    ]  
                ]  
            ],
        ]);

        $id = 0;

        foreach ($shipments as $shipment_id) {
            $handler = new ShipmentHandler( $shipment_id );
            $qty = $handler->getProduct( $product_id, 'quantity' );
            
            if ($qty <= 0) continue;

            $id = (int) $shipment_id;
            break;
        }

        return $id;
    }
}