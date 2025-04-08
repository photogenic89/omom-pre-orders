<?php
/**
 * All Ajax calls for the schedule page
 */
namespace Omom\PreOrders\Admin\Ajax; 

defined( 'ABSPATH' ) || die;

use Omom\PreOrders\Queries;
use Omom\PreOrders\Terms;
use Omom\PreOrders\Handlers\ProductHandler;

class Schedule 
{
    /**
     * Get all products which can be found in shipments
     * Can include products which are not being shipped anymore 
     * and which have a pre-order stock of 0
     */
    public function getProducts(): void
    {
        $in_shipments = [];

        foreach (Terms::getAll() as $product) {

            // remove orphaned terms if not active in any pre-orders
            if (0 === $product->count) {
                Terms::delete( $product->term_id );
                continue;
            }
        
            $id         = $product->name;
            $wc_product = wc_get_product( $id );
            $handler    = new ProductHandler( $id );
            $po_stock   = $handler->getPreOrderStock();
            $shipments  = Queries::getAllShipmentsWithProduct( $id );
            $total      = array_sum( array_column( $shipments, 'original' ) );
            $available  = array_sum( array_column( $shipments, 'available' ) );

            // Errors
            $errors = [];
            $checks = [
                [
                    'condition' => ! $po_stock && $available > 0,
                    'note'      => 'Product has NO pre-order meta saved while being available in active shipments.',
                ],
                [
                    'condition' => $available !== $po_stock,
                    'note'      => "Mismatch between total available and pre-order stock.",
                    'option'    => ''
                ],
            ];

            foreach ($checks as $check) {
                if (! $check['condition']) continue;
                $errors[] = [
                    'id'     => $id, // maybe this will point to a post and not a product at some point, so keep it in
                    'option' => $check['option'] ?? 'updateMeta',
                    'note'   => $check['note']
                ];
            }   

            // add to array
            $in_shipments[] = [
                "name"                 => is_a( $wc_product, 'WC_Product' ) ? $wc_product->get_formatted_name() : "!Product has been deleted!",
                "id"                   => (int) $id,
                "shipments"            => $shipments,
                "errors"               => $errors,
                "inStock"              => $handler->getStock(),
                "totalPreOrderStock"   => $total,
                "totalAvPreOrderStock" => $available,
                "preOrderStock"        => $po_stock
            ];

            // remove orphaned terms if not active in any pre-orders
            if (0 === count( $shipments )) Terms::delete( $product->term_id );
        }

        wp_send_json_success([
            'products' => $in_shipments
        ]);
    }

    /**
     * Remove all pre-order meta from a product, 
     * if is not in any active Shipments anymore
     * 
     * @param array $data
     */
    public function removeOrphanPreOrderMeta( array $data ): void
    {
		// wp_send_json_success( Queries::removeOrphanedProducts() ? 'removed' : 'failure' );
    }

	/**
	 * A product is missing pre-order meta or has incorrect pre-order stock
	 * 
	 * @param array $data
	 */
	public function updateMeta( array $data ): void
	{
		$id = $data['id'] ?? 0;
		wp_send_json_success( (new ProductHandler( $id ))->updatePOMeta() ? 'meta_added' : 'failure' );
	}
}