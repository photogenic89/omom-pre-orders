<?php
/**
 * Create, update or delete product data
 */
namespace Omom\PreOrders\Handlers;

use Atum\Inc\Helpers as AtumHelpers;
use Omom\PreOrders\Integrations\AtumProductLevels\Helpers as BoM;
use Omom\PreOrders\Queries;

class ProductHandler
{
    /**
     * Constructor
     * 
     * @param int $product_id
     */
    public function __construct( private int $product_id )
    {
    }

    /**
     * unfiltered actual getter for the product stock
     * 
     * @param bool $ignore_negative - when we do not want to show what was oversold
     * 
     * @return int
     */
    public function getStock( $ignore_negative = false ): int
    {
        $stock = (int) get_post_meta( $this->product_id, '_stock', true );
        return ($ignore_negative && $stock < 0) ? 0 : $stock;
    }

    /**
     * Get the product pre-order stock
     * 
     * @param bool $ignore_bom - check parts in product
     * 
     * @return int
     */
    public function getPreOrderStock( $ignore_bom = true ): int 
    {
        $po_stock = (int) get_post_meta( $this->product_id, '_omom_po_stock', true );
        
        if (! empty( $po_stock ) && $po_stock > 0) return $po_stock;

        $bom_max = $ignore_bom ? false : BoM::findMaxValue( $this->product_id, false );

        return false === $bom_max ? 0 : $bom_max['po_stock'];
    }

    /**
     * Get the combined stock of pre-order stock and stock 
     * checks also form BoM
     * 
     * @return int
     */
    public function getTotalStock(): int
    {            
        $stock     = $this->getStock();
        $bom_stock = BoM::findMaxValue( $this->product_id );

        // BOM
        if (false !== $bom_stock) {
            $stock += $bom_stock;
        } // Pre-Order
        else if ($po_stock = $this->getPreOrderStock()) {
            $stock > 0 ? $stock += $po_stock : $stock = $po_stock;
        }

        return $stock;
    }

    /**
     * Another way to check if has preOrder
     * 
     * @todo check BoM if has preOrder stock 
     * 
     * @return bool
     */
    public function hasPreOrderStock(): bool
    {
        return $this->getPreOrderStock() !== 0;
    }

    /**
     * Update the pre-order stock
     * When the value is lower than 0, remove meta
     * 
     * @param int $new_po_stock
     */
    public function updatePreOrderStock( int $new_po_stock ): void
    {
        $new_po_stock >= 0 ? update_post_meta( $this->product_id, '_omom_po_stock', $new_po_stock ) : $this->deleteMeta();
    }

    /**
     * remove the preOrder stock
     */
    public function deletePreOrderStock(): void
    {
        delete_post_meta( $this->product_id, '_omom_po_stock' );
    }

    /**
     * Check if product has BOM or pre-order and if there is enough of it
     * 
     * @param bool $default - default bool value
     * @return bool
     */
    public function canBePreOrdered( bool $default = false ): bool 
    {
        $product_id = $this->product_id;
        $bom_items  = BoM::getLinkedBoMParts( $product_id );

        // BOM - if it can't reach the bare minimum
        if (! empty( $bom_items )) {
            return BoM::atumProductIsAvailable( $product_id ) ? BoM::boMExceedsMinimum( $bom_items ) : false;
        } 

        $product  = wc_get_product( $product_id ); 
        $managed  = $product !== NULL ? $product->managing_stock() : true;
        
        // Pre-Order - ! $this->productHasStock( $product_id ) &&  remove because it blocks validation
        return ($managed && $this->hasPreOrderStock()) ? true : $default;
    }

    /**
     * When a product has no stock, and needs to take from preOrder
     * It is in pre-order
     * 
     * @return bool
     */
    public function isInPreOrder(): bool
    {
        return ! $this->hasStock() && $this->hasPreOrderStock();
    }

    /**
     * Check if product has stock
     * 
     * @return int
     */
    public function hasStock(): int 
    {
        $id = $this->product_id;

        if ('onbackorder' === get_post_meta( $id, '_stock_status', true ) || 'yes' === get_post_meta( $id, '_backorders', true )) return true;

        $bom_items = BoM::getLinkedBoMParts( $id );
        $stock     = get_post_meta( $id, '_stock', true );

        // what about negative stock?
        if (empty( $bom_items )) return (! empty( $stock ) &&  $stock > 0) ? true : false;
            
        return BoM::boMExceedsMinimum( $bom_items ); 
    }

    /**
     * Get the product purchasable stock 
     * How much a customer can actually buy
     * 
     * Is this also for product-parts?
     * 
     * @todo how to make this independent from ATUM?
     * @todo check if ATUM is active
     * 
     * @param null|\WC_Product $product
     * 
     * @return int
     */
    public function getPurchasableStock( mixed $product = null ): int
    {
        $product_id    = $this->product_id;
        $po_stock      = $this->getPreOrderStock( $product_id );
        $product       = ! $product ?  AtumHelpers::get_atum_product( $product_id ) : $product;
        $stock         = ! $product ? 0 : (int) $product->get_stock_quantity(); 
        $stock_on_hold = ! $product ? 0 : (int) AtumHelpers::get_product_stock_on_hold( $product );
        $inbound_stock = ! $product ? 0 : (int) AtumHelpers::get_product_inbound_stock( $product );

        return $stock + $po_stock + $inbound_stock - $stock_on_hold;
    }

    /**
     * Get the ID of the next closest shipment in line
     * 
     * @return int - the id
     */
    public function getNextShipmentID(): int
    {
        $id = (int) get_post_meta( $this->product_id, '_omom_closest_shipment', true );
        return empty( $id ) ? 0 : $id;
    }

    /**
     * Update the ID of the next closes shipment in line
     * 
     * @param int $new_id - if 0, let the system find the next shipment id
     * 
     * @return int the new id or 0 if none found
     */
    public function updateNextShipmentID( int $new_id = 0 ): int
    {
        $product_id = $this->product_id;
        $new_id     = 0 === $new_id ? Queries::getClosestShipmentId( $product_id ) : $new_id;

        0 !== $new_id ? update_post_meta( $product_id, '_omom_closest_shipment', $new_id ) : $this->deleteMeta();

        return $new_id;
    }

    /**
     * Delete the ID of the next closes shipment in line
     */
    public function deleteNextShipmentID(): void
    {
        delete_post_meta( $this->product_id, '_omom_closest_shipment' );
    }

    /**
     * List of all pre-order posts with that product, 
     * sorted by arrival date in ascending order
     * 
     * Returns:        
     *  array ( 
     *    arrival date => array ( 
     *          id => shipment_id
     *          stock => product pre-order stock
     *    ) 
     *  ) 
     * 
     * @return array
     */
    public function getAllShipments(): array 
    {
        $product_id = $this->product_id;
        $posts = $product_id ? Queries::getAllShipmentIdsByProductId( $product_id ) : [];

        if ([] === $posts) return [];

        $shipments = [];

        // 3. Generate the list
        foreach ($posts as $post_id => $obj) {
            
            $shipment = new ShipmentHandler( $post_id );
            $qty      = $shipment->getProduct( $product_id, 'quantity' );

            if (false === $qty) continue;

            $arrival = $shipment->getArrival();

            $shipments[$arrival] = [
                'id'    => $post_id,
                'stock' => $qty
            ]; 
        }

        ksort( $shipments ); // sort in ascending order

        return $shipments;
    }

    /**
     * Get the timestamp for the closest arrival
     * 
     * @param bool $formatted 
     * 
     * @return int|string - int is unix timestamp, string formatted date
     */
    public function getClosestArrival( $formatted = false, $ignore_bom = true ): string|int
    {
        $bom_arrival = $ignore_bom ? false : BoM::getClosestBoMArrival( $this->product_id );

        if (false !== $bom_arrival) return $bom_arrival;

        $id = $this->getNextShipmentID();
        return $id ? (new ShipmentHandler( $id ))->getArrival( $formatted ? 3 : 999 ) : "";
    }

    /**
	 * Get current pre-order stock, closest arrival time and closest shipment
     * 
	 * @return array
	 */
	public function getData(): array 
	{
        $product_id = $this->product_id;

		if (! $product_id) return [];

		$data = [
			'restock' => 0,
			'arrival' => 0,
			'post_id' => 0,
		];
		$i = 0;

		$shipments = Queries::getAllShipmentIdsByProductId( $product_id );

		if ([] === $shipments) return [];

		// 3. Generate the list
		foreach ($shipments as $shipment_id => $obj) {

            $shipment = new ShipmentHandler( $shipment_id );
			$arrival  = $shipment->getArrival();

            $data['restock'] += $shipment->getProduct( $product_id, 'quantity' );
			$data['arrival'] = ($i === 0 || $data['arrival'] > $arrival) ? $arrival : $data['arrival'];
			$data['post_id'] = ($i === 0 || $data['arrival'] > $arrival) ? $shipment_id : $data['post_id'];

			$i++;
		}

		return $data;
	}

    /**
     * Delete all preOrders data from a product
     */
    public function deleteMeta(): void
    {
        $this->deleteNextShipmentID();
        $this->deletePreOrderStock();
    }

    /**
     * Update the meta of a product
     * pre-order stock, closest shipment, closest shipment arrival time
     * 
     * @param int $product_id - the product to update
     * @return bool - true on success
     */
    public function updatePOMeta(): bool
    {
        $id   = $this->product_id;
		$data = $this->getData();
				
		if ([] === $data) return false;

        $this->updatePreOrderStock( $data['restock'] );
        $this->updateNextShipmentID( $data['post_id'] );

        return true;
    }
}