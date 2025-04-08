<?php
/**
 * Check for wrong 
 * - shipment status
 * - missing terms
 * - orphaned meta 
 * - wrong pre-order stock
 */
namespace Omom\PreOrders;

defined( 'ABSPATH' ) || exit;

use Omom\PreOrders\Handlers\ShipmentHandler;
use Omom\PreOrders\Handlers\ProductHandler;

class FixErrors 
{
    /**
     * Constructor loads all methods
     */
    public function __construct()
    {
        $products = $this->checkActiveShipments();
        $this->checkProducts( $products );
    }

    /**
     * Loop through all not yet released shipment
     */
    private function checkActiveShipments(): array
    {
        $shipments       = Queries::getActiveShipments();
        $term_objects    = Terms::getAllObjectIds();
        $active_products = [];

        foreach ($shipments as $shipment_id => $shipment) {

            $handler  = new ShipmentHandler( $shipment_id );
            $terms    = Terms::getProductsInShipment( $shipment_id );
            $released = ! $handler->isActive();
            
            // check the shipment, if it is active
            // or if it still has products with a stock
            foreach ($shipment['products'] as $product) {

                $id  = $product['ID'];
                $qty = $product['Original'];
                $av  = $product['Restock'];

                // check if term is missing in shipment
                $key = array_search( $id, $terms );

                // add if yes, remove from array if no
                if (false === $key) {
                    if (0 !== $qty) Terms::add( $shipment_id, $id );
                } else {
                    unset( $terms[$key] );
                }

                // remove from all
                unset( $term_objects[$id][$shipment_id] );

                // release - when all items are at 0
                if ($qty && $released) $released = false;

                // collect po_stock
                if (isset( $active_products[$id] )) {
                    $active_products[$id]['qty'] += $qty;
                    $active_products[$id]['av'] += $av;
                    continue;
                }

                $active_products[$id]['qty'] = $qty;
                $active_products[$id]['av'] = $av;
            }

            // remove leftover terms
            foreach ($terms as $term)
                Terms::remove( $shipment_id, $term );

            $this->maybeSetStatus( $handler, $released );
        }

        // check if Terms are in any other shipment posts
        foreach ($term_objects as $product_id => $s_ids)
            foreach ($s_ids as $s_id => $v)
                Terms::remove( $s_id, $product_id );


        return $active_products;
    }

    /**
     * Check if the shipment has the correct status
     * Update if not
     * 
     * @param ShipmentHandler $handler
     * @param bool $released
     */
    private function maybeSetStatus( ShipmentHandler $handler, bool $released ): void
    {
        $status     = $handler->getStatus();
        $new_status = "";

        // check status 
        switch (true) {

            // should be draft
            case 'draft' !== $status && $handler->getIsDraft():

                $handler->deleteIsDraft(); // switch to the new status system
                $new_status = "draft"; 
                break;

            // should be release
            case $released && 'released' !== $status:

                $new_status = 'released';
                break;
        }

        // update if status is not matching
        if ($new_status) $handler->setStatus( $new_status );
    }

    /**
     * Update all active products with the correct new stock &
     * remove all meta of all inactive products
     */
    private function checkProducts( array $active_products ): void
    {
        // update po stock of all items in active shipments
        foreach ($active_products as $product_id => $stocks) {

            $original  = $stocks['qty'];
            $available = $stocks['av'];

            $handler = new ProductHandler( $product_id );
            $stock   = $handler->getStock();

            // remove what is already purchased, if stock has been taken
            $po_stock = $stock < 0 ? $original + $stock : $original;
            
            // if it does not match available stock in shipments, take the calculated value there
            if ($available !== $po_stock) {
                wp_mail( 
                    "kin@omniumcargo.com",
                    "Pre-order stock mismatch",
                    "Product ID " . $product_id . " has a mismach. Available in shipments is " . $available . " and calculated pre-order stock is " . $po_stock 
                );
            }

            // remove empty
            if (0 >= $po_stock) {
                $handler->deleteMeta();
                continue;
            }

            $handler->updatePreOrderStock( $po_stock );
            $handler->updateNextShipmentID();
        }

        $all_po_products      = Queries::getAllProductIdsWithPOStock();
        $inactive_po_products = array_diff_key( $all_po_products, $active_products );

        // remove inactive products
        foreach ($inactive_po_products as $id => $value) 
            (new Handlers\ProductHandler( $id ))->deleteMeta();
    }
}