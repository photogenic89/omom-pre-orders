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
     * 
     * @return array - all products in active shipments
     */
    private function checkActiveShipments(): array
    {
        $shipments       = Queries::getActiveShipments();
        $term_objects    = Terms::getAllObjectIds();
        $active_products = [];

        foreach ($shipments as $shipment_id => $shipment) {

            $terms    = Terms::getProductsInShipment( $shipment_id );
            $released = ! $shipment->isActive();
            
            // check the shipment, if it is active
            // or if it still has products with a stock
            foreach ($shipment->getProducts() as $product) {

                $id  = $product['ID'];
                $qty = $product['Original']; // how much is expected to come
                $av  = $product['Restock']; // how much of it is still available

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

            $this->maybeSetStatus( $shipment, $released );
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
     * 
     * @todo change email
     * 
     * @param array $active_products
     */
    private function checkProducts( array $active_products ): void
    {
        // update po stock of all items in active shipments
        foreach ($active_products as $product_id => $stocks) {

            $original  = $stocks['qty'];
            $available = $stocks['av'];
            $handler   = new ProductHandler( $product_id );
            $stock     = $handler->getStock();
            $po_stock  = $stock < 0 ? $original + $stock : $available; // remove what is already purchased, if stock has been taken
            $recipient = get_option( 'woocommerce_stock_email_recipient' ); // should this be somewhere else or better documented?

            // if it does not match available stock in shipments, take the calculated value there
            if ($available !== $po_stock && false !== $recipient) {

                $product = wc_get_product( $product_id );
                $sku = $product ? $product->get_sku() : "";

                wp_mail( 
                    $recipient,
                    "Found pre-order stock mismatch when checking for errors",
                    "Product: " . $product->get_name() .
                    "<br>ID: " . $product_id .  
                    "<br>SKU: " . $sku .
                    "<br>has a mismatch." .
                    "<br><br>In stock: " . $stock . 
                    "<br>Available pre-order stock: " . $available . 
                    "<br>Total pre-order stock in all active shipments: " . $original .
                    "<br><br>To fix either adjust stock in product or pre-order stock in shipments.",
                    ['Content-Type: text/html; charset=UTF-8'] 
                );
            }

            // remove empty
            if (0 >= $po_stock) {
                $handler->deleteMeta();
                continue;
            }

            // blanket update
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