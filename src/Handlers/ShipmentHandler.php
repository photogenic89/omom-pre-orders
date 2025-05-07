<?php
/**
 * Create, update or delete a restock-post
 * we call them Shipments
 */
namespace Omom\PreOrders\Handlers;

use Omom\PreOrders\Cron;
use Omom\PreOrders\Log;
use Omom\PreOrders\Terms;

class ShipmentHandler
{
    /**
     * Constructor
     * 
     * @param int $post_id
     */
    public function __construct( private int $post_id )
    {
    }

    /**
     * Get the arrival date of the shipment
     * 
     * @param int $format - choose the array key of the format you want to use. if none selected, dont format
     * @return string - unix timestamp
     */
    public function getArrival( int $format = 999 ): string|int
    {
        $arrival = esc_attr( get_post_meta( $this->post_id, 'rs_arrival', true ) );

        if (empty( $arrival )) return "";

        $formats = [
            "Y-m-d\TH:i", 
            "Y m d H i", 
            "jS \of F Y - H:i",
            "jS \of F Y",
            "F j Y"
        ];

        return isset($formats[$format]) ? (new \DateTime( "@" . $arrival, wp_timezone() ))->format( $formats[$format] ) : (int) $arrival; 
    }

    /**
     * An active shipment is one that has its arrival in the future
     * 
     * @return bool
     */
    public function isActive(): bool 
    {
        // empty( $rs_arrival ) || time() > $rs_arrival
        return $this->getArrival() > ((int) date( "U" ));
    }

    /**
     * Change the arrival date of a post
     * 
     * @param string $new_timestamp - unix timestamp
     * @return string - formatted timestring
     */
    public function setArrival( int $new_timestamp ): bool
    {
        if (! $new_timestamp) return false;
        
        return (bool) update_post_meta( $this->post_id, 'rs_arrival', $new_timestamp );
    }

    /**
     * Get all the products inside a shipment
     * 
     * @return array
     */
    public function getProducts(): array
    {
        return (array) get_post_meta( $this->post_id, 'rs_products', false );
    }

    /**
     * Get all data for one specific product inside a shipment
     * 
     * @param int $product_id
     * @param string $filter_by
     * @return false|int|array
     */
    public function getProduct( int $product_id, string $filter_by = "" ): false|int|array
    {
        $found = false;

        foreach ($this->getProducts() as $product) {

            if (((int) $product['ID']) !== $product_id) continue;

            $found = true;
            break;
        }

        if (! $found) return false;

        switch ($filter_by) {
            // how much originally was in the shipment
            case 'original':
                return intval( $product['Original'] ?? 0 );
                break;
            // how much is still available
            case 'quantity':
                return intval( $product['Restock'] ?? 0 );
        }

        return $product;
    }

    /**
     * Changes out the products in the Shipment
     * handles how to deal with the old product information as well
     * 
     * @param array $new_products
     * @param bool $is_published - if new post status is publish
     * @param bool $draft_to_publish - was the Shipment in draft status before this update?
     * @param string $note
     */
    public function updateProducts( 
        array $new_products,
        bool $is_published = true, 
        bool $was_draft = false,
        string $note = "" 
    ): void {

        // when the status of the shipment has changed
        $draft_to_publish   = $was_draft && $is_published;
        $draft_to_draft     = $was_draft && ! $is_published;
        $publish_to_publish = ! $was_draft && $is_published;
        $publish_to_draft   = ! $was_draft && ! $is_published;

        $post_id      = $this->post_id;
        $old_products = $this->getProducts();
        $post_terms   = Terms::getProductsInShipment( $post_id );
        $new_ids      = array_column( $new_products, 'ID' );
        $old_ids      = array_column( $old_products, 'ID' ); 
        $log_data     = [];

        /*------------------------------*/
        /*  Remove: Old Products        */
        /*------------------------------*/

        foreach ($old_products as $old_product) {
            $old_id = (int) $old_product['ID'];

            // don't remove if is still in post update
            if (in_array( $old_id, $new_ids )) continue;

            $handler   = new ProductHandler( $old_id );
            $old_stock = $old_product['Restock'];

            if (! $was_draft) 
                $handler->updatePreOrderStock( $handler->getPreOrderStock() - $old_stock );

            // Log data -> removed product
            $log_data[$old_id] = [
                'post_old'  => $old_stock,
                'post_new'  => 0,
            ];
        }

        /*------------------------------*/
        /*  Add or Update: New Products */
        /*------------------------------*/

        foreach ($new_products as $new_product) {

            // ignore empty entries
            if (empty( $new_product['ID'] )) continue;
            
            $new_id         = (int) $new_product['ID'];
            $new_stock      = (int) $new_product['Restock'];
            $handler        = new ProductHandler( $new_id );
            $original_stock = $handler->getPreOrderStock();  
            $updated_stock  = $new_stock + $original_stock;

            // Add post terms
            if (
                (! in_array( $new_id, $post_terms ) || $draft_to_publish) && 
                $updated_stock > 0 &&
                ! $publish_to_draft &&
                ! $draft_to_draft
            ) {
                Terms::add( $post_id, $new_id );
            }

            // remove term if stock is empty
            if (
                $publish_to_draft ||
                (0 === $new_stock && in_array( $new_id, $post_terms ))
            ) Terms::remove( $post_id, $new_id ); 

            // Add - if product did not exist before
            if (
                wc_get_product( $new_id ) &&
                (! in_array( $new_id, $old_ids ) || $draft_to_publish)
            ) {
                if (
                    $updated_stock > 0 && 
                    ! $publish_to_draft && 
                    ! $draft_to_draft
                ) $handler->updatePreOrderStock( $updated_stock );
                
                // Log data -> newly added product
                $log_data[$new_id] = [
                    'post_old'  => 0,
                    'post_new'  => $new_stock,
                ];

                continue;
            }

            // Update - if product existed before
            foreach ($old_products as $old_product) {

                $old_id    = (int) $old_product['ID'];
                $old_stock = (int) $old_product['Restock'];
            
                if (
                    $new_id !== $old_id || 
                    ($old_stock === $new_stock && ($draft_to_draft || $publish_to_publish))
                ) continue;

                // Log data -> updated product
                $log_data[$new_id] = [
                    'post_old'  => $old_stock,
                    'post_new'  => $new_stock,
                ];

                if ($draft_to_draft) break;

                switch (true) {
                    case $publish_to_draft:
                        $updated_stock = $original_stock - $old_stock;
                        break;
                    case $draft_to_publish:
                        $updated_stock = $original_stock + $new_stock;
                        break;
                    case $new_stock > $old_stock:
                        $updated_stock = $original_stock + $new_stock - $old_stock;
                        break;
                    default:
                        $updated_stock = $original_stock - ( $old_stock - $new_stock );
                }

                $handler->updatePreOrderStock( $updated_stock );

                break;
            }           
        }

        // remove old terms from Pre-Order Products taxonomy
        foreach ($post_terms as $post_term)
            if (! in_array( $post_term, $new_ids ))
                Terms::remove( $post_id, $post_term );

        // Log changes
        if ([] === $log_data) return;

        (new Log)->logStockChange( 
            $post_id, 
            $log_data, 
            $note ?: 'Updated this shipment' 
        );

        // remove all old meta
        delete_post_meta( $this->post_id, 'rs_products' );

        foreach ($new_products as $new_product) {
            if (empty( $new_product['ID'] )) continue;
            
            // create for each product a new meta entry
            add_post_meta( $this->post_id, 'rs_products', $new_product, false );
            
            // needs to be executed after updateStockChanges
            if ($is_published || (! $is_published && ! $was_draft)) $this->maybeUpdateProductArrival( $new_product['ID'] );
        }
    }

    /**
     * Update one product entry in the meta
     * 
     * @param array $new_value in set arrival
     * @param array|string $old_value
     */
    public function updateProduct( array $new_value, array|string $old_value = "" ): void
    {
        update_post_meta( $this->post_id, 'rs_products', $new_value, $old_value );
    }

    /**
	 * Update a product's pre-order quantity in a shipment
     * And respectively in the product
	 *
	 * @param  int		 $product_id    Product ID or product instance.
	 * @param  int       $new_qty       Pre-Order quantity.
	 * @param  string    $operation     Type of opertion, allows 'set', 'increase' and 'decrease'.
     * 
     * @return int - new pre-order quantity for this shipment
	 */
	public function updateProductPreOrderQty(
		int $product_id, 
		int $new_qty, 
		string $operation = 'decrease', 
        string $note = 'Changed stock'
	): int {
		$product = $product_id ? $this->getProduct( $product_id ) : false;

		if (false === $product) return 0;

		$handler      = new ProductHandler( $product_id );
        $old_po_stock = $handler->getPreOrderStock();
        $old_qty      = $product['Restock'];

        switch ($operation) {
            case 'increase':
                $new_po_stock = $old_po_stock + $new_qty;
                $new_qty = $old_qty + $new_qty;
                break;
            case 'decrease':
                $new_po_stock = $old_po_stock - $new_qty;
                $new_qty = $old_qty - $new_qty;
                break;
            case 'set':
            default:
                $new_po_stock = $old_qty > $new_qty ? ($old_po_stock - ($old_qty - $new_qty)) : ($old_po_stock + ($new_qty - $old_qty));
                $new_qty = $new_qty;
        }
        
        // update product pre-order stock
        $handler->updatePreOrderStock( $new_po_stock );

		// If pre-order stock reached 0
        if (0 >= $new_qty) {
            // remove from query-taxonomy
            Terms::remove( $this->post_id, $product_id );
			// switch to next order date
			if ($new_po_stock > 0) $handler->updateNextShipmentID();
        } else {
            Terms::add( $this->post_id, $product_id );
        }

		// finally update it
        $this->updateProduct(
            [
                'ID'       => $product['ID'],
                'Original' => $product['Original'],
                'Restock'  => $new_qty
            ], 
            $product 
        );

        $log = new Log;
        $log->logStockChange( 
            $this->post_id, 
            [
                $product_id => [
                    'post_old'  => $old_qty,
                    'post_new'  => $new_qty,
                ]
            ], 
            $note
        );

        return $new_qty;
	}

    /**
     * Returns a comment if someone wrote one
     */
    public function getComment(): string
    {
        return esc_attr( get_post_meta( $this->post_id, 'rs_comment', true ) );
    }

    /**
     * Returns the container ID
     */
    public function getContainerID(): string
    {
        return esc_attr( get_post_meta( $this->post_id, 'rs_container', true ) );
    }

    /**
     * If the shipment is automatically added to stock
     */
    public function getAutomatic()
    {
        return esc_attr( get_post_meta( $this->post_id, 'rs_automatic', true ) );
    }

    /**
     * If the products of this shipment should be shown in the future stock
     */
    public function getShowInFutureStock()
    {
        return esc_attr( get_post_meta( $this->post_id, 'rs_show_in_future_stock', true ) );
    }

    /**
     * A shipment in draft status does not add stock 
     * 
     * @return bool
     */
    public function getIsDraft(): bool
    {
        return "yes" === get_post_meta( $this->post_id, 'rs_is_a_draft', true );
    }

    /**
     * delete the draft status
     */
    public function deleteIsDraft(): void
    {
        delete_post_meta( $this->post_id, "rs_is_a_draft" );
    }

    /**
     * Get the shipment status
     * 
     * @todo how to handle when no meta saved
     * 
     * status definitions 
     * draft    - when post status is set to draft
     * released - all products are at 0 po_stock. Arrival date is not important, because, someone could decide to release it even before.
     * active   - arrival date is in the future 
     * received - arrival date has passed, still has products with stock
     * 
     * @return draft|released|active|string 
     */
    public function getStatus(): string
    {
        $status = (string) get_post_meta( $this->post_id, "omom_shipment_status", true );

        switch (true) {
            case $status:
                return $status;
            case $this->getIsDraft():
                return 'draft';
            case $this->isActive():
                return "active";
            default:
                return "received";
        };
    }

    /**
     * Is a certain shipment status
     * 
     * @param draft|released|string 
     * 
     * @return bool
     */
    public function isStatus( $status ): bool
    {
        return $status === $this->getStatus();
    }

    /**
     * Update the status to be one of four options
     * 
     * @param draft|released| $new_status
     */
    public function setStatus( string $new_status ): void
    {
        if (! in_array( $new_status, ['draft', 'released'])) return;
        update_post_meta( $this->post_id, "omom_shipment_status", $new_status );
    }

    /**
     * Delete the shipment status
     */
    public function deleteStatus(): void
    {
        delete_post_meta( $this->post_id, "omom_shipment_status" );
    }

    /**
     * Save a Shipment restock-post
     */
    public function save( array $new_values ): void
    {
        $post_id = $this->post_id;

        /***********************************
         *  Check post status
         ***********************************/
        $is_published = "publish" === ($new_values['post_status'] ?? 'draft');
        $was_draft    = $this->isStatus( "draft" );

        ! $is_published ? $this->setStatus( "draft" ) : $this->deleteStatus();
        
        /***********************************
         *  Save meta
         ***********************************/ 
        $arrival = 0;

        $meta = [
            'rs_show_in_future_stock' => $this->getShowInFutureStock(),
            'rs_automatic'            => $this->getAutomatic(),
            'rs_comment'              => $this->getComment(),
            'rs_arrival'              => $this->getArrival(),
            'rs_container'            => $this->getContainerID(),
            'rs_products'             => $this->getProducts(),
        ];
        
        foreach ($meta as $name => $old_value) {

            $multiple  = 'rs_products' === $name ? false : true; // looks counter-intuitive, just means if false, then meta can have several entries
            $new_value = $new_values[$name] ?? (true === $multiple ? "" : []);

            // no new but an old value, delete
            if (! $new_value && $old_value) delete_post_meta( $post_id, $name );

            switch ($name) {

                case 'rs_arrival':

                    $date    = new \DateTime( $new_value ); // wp_timezone()
                    $arrival = (int) $date->format( 'U' );

                    $this->setArrival( $arrival );

                    break;

                case 'rs_products':
                    
                    $this->updateProducts( 
                        $new_value,
                        $is_published, 
                        $was_draft 
                    );

                    break;
                    
                default:

                    // don't update if is same value as before
                    if ($new_value === $old_value) break;

                    update_post_meta( $post_id, $name, $new_value );
            }
        }

        /***********************************
         *  When the shipment is public
         ***********************************/ 
        $release_automatic = 'on' === ($new_values['rs_automatic'] ?? "");

        if ($is_published) {
            $this->deleteIsDraft();
            $this->setStatus( 'active' );

            // Cron
            if ($release_automatic) {
                Cron::scheduleEvent( 'release', $arrival, [ $post_id ] ); // what about posts which are already finished when posted?
            }
        }

        if (! $is_published || ! $release_automatic) {
            Cron::removeEvent( 'release', [ $post_id ] );
        }

        /***********************************
         *  Add shipment to stock
         ***********************************/ 
        if (isset( $new_values['rs_release_po_stock'] )) {
            $this->release();
        }
    }

    /**
     * Update Arrival Date for products if necessary
     * 
     * @param int $product_id
     */
    public function maybeUpdateProductArrival( int $product_id ): void 
    {
        if (! wc_get_product( $product_id )) return;

        $handler  = new ProductHandler( $product_id );
        $po_stock = $handler->getPreOrderStock(); 

        // if product has no pre-order stock
        if (0 === $po_stock) {
            $handler->deleteMeta();
            return;
        }

        $post_id     = $this->post_id; 
        $old_post_id = $handler->getNextShipmentID();
        $old_value   = $handler->getClosestArrival();
        $arrival     = $this->getArrival();

        // if is new entry or if is new pre-order with closer arrival date
        if (
            (empty( $old_post_id ) && empty( $old_value )) || 
            ($post_id != $old_post_id && $arrival < $old_value) 
        ) {
            $handler->updateNextShipmentID( $post_id );
        }

        // check if is different pre-order posts with same date, choose oldest post
    }

    /**
     * Remove all post meta and reset product pre-order stock
     * 
     * @see when the method is called on delete_post, it'll start an infinite loop
     */
    public function delete(): void 
    {
        $post_id  = $this->post_id;
        $products = ($this->getIsDraft() || $this->isStatus( 'draft' )) ? [] : $this->getProducts();
        
        foreach ($products as $product) {

            // reset product pre-order stock or remove product meta
            $product_id    = $product['ID'];
            $handler       = new ProductHandler( $product_id );
            $product_stock = $handler->getPreOrderStock();
            $product_stock = $product_stock - $product['Original']; // failure: non-numeric value

            if ($product_stock > 0) {
                $handler->updatePreOrderStock( $product_stock );
            } else {
                $handler->deletePreOrderStock();
            }

            // change to next closest arrival date or delete meta otherwise
            $handler->updateNextShipmentID();

            // Remove empty terms
            $term = Terms::getTermByProductId( $product_id );
            
            // 1 is the current post
            if ($term->count <= 1) {    
                Terms::delete( $term->term_id );
            }
        }

        $this->deleteIsDraft();
        $this->deleteStatus();
        delete_post_meta( $post_id, "rs_comment" );
        delete_post_meta( $post_id, "rs_arrival" );
        delete_post_meta( $post_id, "rs_amount_products" );
        delete_post_meta( $post_id, 'rs_products' );

        // completely remove the pre-order post
        wp_delete_post( $post_id, true );

        // Clear scheduled event
        Cron::removeEvent( 'release', [ $post_id ] );
    }

    /**
     * Release the pre-order quantities of 
     * all products in one shipment to stock
     * 
     * @param string $log_title
     */
    public function release( $log_title = "Released to stock" ): void
    {
        $post_id  = $this->post_id;
        $log_data = [];

        foreach ($this->getProducts() as $product) {

            $po_stock = $product['Restock'];
            $to_add   = $product['Original'];
            $id       = $product['ID'];
            $handler  = new ProductHandler( $id );

            // remove from query-taxonomy
            Terms::remove( $post_id, $id );

            // if oversold, remove from what to add
            if (0 > $po_stock) $to_add += $po_stock;

            // Remove stock from pre-order post
            $this->updateProduct(
                [
                    'ID'       => $id,
                    'Original' => $to_add,
                    'Restock'  => 0
                ], 
                $product 
            );

            // Remove stock from product pre-order meta
            $old_pre_order = $handler->getPreOrderStock();
            $handler->updatePreOrderStock( $old_pre_order - $po_stock );

            // Add pre-order stock to product stock
            wc_update_product_stock( $id, $to_add, 'increase' );
            if (0 < $handler->getStock()) wc_update_product_stock_status( $id, 'instock');
            wc_delete_product_transients( $id );
            
            // log
            $log_data[ $id ] = [
                'post_old'  => $po_stock,
                'post_new'  => 0,
            ];

            // change to next closest arrival date
            if ($handler->hasPreOrderStock()) $handler->updateNextShipmentID();
        }

        // Write to pre-order log
        (new Log)->logStockChange( 
            $post_id, 
            $log_data, 
            $log_title 
        );

        // Change shipment status
        $this->setStatus( 'released' );
    }
}