<?php
/**
 * Creates lists
 * 
 * @todo restock_list     -> preorder_list
 * @todo bom_restock_list -> bom_preorder_list
 * @todo restock_info     -> preorder_arrival_dates_html
 */
namespace Omom\PreOrders\Cart;

defined( 'ABSPATH' ) || exit;

use Omom\PreOrders\Dates;
use Omom\PreOrders\Handlers\ProductHandler as Product;
use Omom\PreOrders\Integrations\AtumProductLevels\Helpers as BoM;

class Lists 
{
    /**
     * 
     */
    private array $cart_contents = [];

    /**
     * 
     */
    private array $cart_items = [];

    /**
     * 
     */
    private array $bom_cart_items = [];

    /**
     * The constructor
     * 
     * @param WC_Cart $cart
     * @param array $cart_item_keys 
     */
    public function __construct( $cart = [], $cart_item_keys = [] )
    {
        $this->cart_contents = $cart_contents = [] === $cart ? WC()->cart->cart_contents : $cart->cart_contents;
        $cart_item_keys = [] === $cart_item_keys ? ([] === $cart ? WC()->cart->get_cart() : $cart->get_cart()) : $cart_item_keys;
        
        $bom_cart_items = $cart_items = [];

        // $item or quantity ? 
        foreach ($cart_item_keys as $cart_item_key => $value) {

            $cart_item = $cart_contents[$cart_item_key] ?? (is_array( $value ) ? $value : []);

            if ([] === $cart_item) continue;

            $id        = $cart_item['variation_id'] ?: $cart_item['product_id'];
            $bom_items = BoM::getProductBoMById( $id ) ?? false;

            if ($bom_items) {

                $bom_cart_items[$cart_item_key] = [
                    'bom_items' => $bom_items,
                    'quantity'  => is_array( $value ) ? 1 : $value
                ];

            } else if ((new Product( $id ))->hasPreOrderStock()) {

                $cart_items[$id] = $cart_item;

            }            
        }
        
        $this->bom_cart_items = $bom_cart_items;
        $this->cart_items     = $cart_items;
    }

    /**
     * ADD
     */

    /**
     * Add preorders list to a specific item 
     * 
     * @param array $item
     * @param int   $key
     * 
     * @return array
     */
    public function addTo( $item, $key ): array
    {
        $id      = $item['variation_id'] ?: $item['product_id'];
        $bom_ids = BoM::getProductBoMById( $id );
        
        // BoM
        if (! empty( $bom_ids )) {
            
            $item = $this->addPreOrderListToBoMItem( 
                $item, 
                $bom_ids 
            );

        } // pre-order
        else if((new Product( $id ))->hasPreOrderStock()) {
            
            $item = $this->addPreOrderListToCartItem( 
                $item, 
                $key, 
                $id 
            );

        }

        return $item;
    }

    /**
     * Add restock_list array to cart_item_data array if product is added with pre-order quantity.
     * This method is called before the item becomes a cart item itself
     * 
     * @param  array $item
     * @param  int/string $key
     * @param  int   $id product_id
     * 
     * @return array modified $item
     */
    public function addPreOrderListToCartItem( array $item, $key, int $id ): array 
    {
        $quantity   = $item[ 'quantity' ]; // what we add
        $product    = $item[ 'data' ];
        $in_stock   = $product->get_stock_quantity(); // what we have
        $quantities = WC()->cart->get_cart_item_quantities(); 
        $in_cart    = $quantities[$id] ?? 0; // what is in the cart
        $qty_all    = $in_cart + $quantity; // if in cart, combine quantities

        // when we take more than is in stock
        if ($qty_all <= $in_stock) return $item;

        $po_list = $this->generatePreOrderList( 
            $id, 
            $quantity, 
            $qty_all, 
            $in_cart > $in_stock ? ($in_stock >= 0 ? $in_cart - $in_stock : $in_cart) : 0, // how much pre-order is the cart already using, 
            ($in_stock < 0 || $in_cart > $in_stock )? 0 : $in_stock - $in_cart, // remove the in-stock the cart is already using  
        );
        
        if (! empty( $po_list )) {
            $item['restock_list']      = $po_list;
            $item['qty_all_instances'] = $po_list['qty_all_instances'];
        } else {
            error_log( "The product #" . $id . " returns an empty pre-order array, while having a pre-order quantity." );
        }
        
        return $item;
    }

    /**
     * Add restock_list to products with bom
     * 
     * @param array $item
     * @param array $bom_ids
     * 
     * @return array
     */
    public function addPreOrderListToBoMItem( $item, $bom_ids ): array 
    {
        $quantities = WC()->cart->get_cart_item_quantities();

        // check every bom item for pre-orders 
        foreach ($bom_ids as $bom_item) {

            $bom_id      = $bom_item->bom_id;
            $multiplier  = $bom_item->qty;
            $bom_qty     = $multiplier * $item['quantity'];
            $po_product  = new Product( $bom_id );
            $bom_stock   = $po_product->getStock( true );

            if (
                ! $po_product->hasPreOrderStock()|| 
                $bom_qty <= $bom_stock
            ) continue;

            // Prepare args for restock_list
            $what_in_cart = BoM::getBoMCartItemQuantity( $bom_id, $quantities );

            // Generate the pre-order list
            $po_list = $this->generatePreOrderList( 
                $bom_id, 
                $bom_qty, 
                $bom_qty + $what_in_cart, 
                $what_in_cart > $bom_stock ? $what_in_cart - $bom_stock : 0, 
                $bom_stock 
            ); 
            
            if (empty( $po_list )) {
                error_log("The BOM product #" . $bom_id . " returns an empty pre-order array, while having a pre-order quantity.");
                continue;
            }

            $item['bom_restock_list'][$bom_id]                  = $po_list;
            $item['bom_restock_list'][$bom_id]['multiplied_by'] = $multiplier;
        }

        return $item;
    }

    /**
     * UPDATE
     */

    /**
     * Update all preorders needs updating 
     * 
     * @return bool if data has changed
     */
    public function update(): bool
    {
        $cart_contents = $this->cart_contents;
        $has_changed = false;

        // bom
        if ($this->bom_cart_items) 
            $has_changed = $this->updateBoMPreOrderLists( $cart_contents, $this->bom_cart_items );
        
        // pre-order
        if ($this->cart_items) 
            $has_changed = $this->updatePreOrderLists( $cart_contents, $this->cart_items );

        /**
         * update dates as well
         * This updates the shipment dates visible to the client, not the restock_list for stock managment 
         */
        $cart_contents = WC()->cart->get_cart();

        foreach ($cart_contents as $cart_item_key => $cart_item) {

            $dates = new Dates( $cart_item );
            $html  = $dates->getHtml();

            if ("" === $html) {
                unset( $cart_contents[ $cart_item_key ][ 'restock_info' ] );
                continue;
            }

            $cart_contents[ $cart_item_key ]['restock_info'] = $html;
        }
  
        WC()->cart->set_cart_contents( $cart_contents );

        return $has_changed;
    }

    /**
     * Update preorder list on cart item quantity change
     * 
     * @todo add the pre-order check in here
     * 
     * @param array $cart_contents
     * @param array $id => $cart_items
     * 
     * @return bool
     */
    public function updatePreOrderLists( array $cart_contents, array $cart_items ): bool 
    {   
        $has_changed  = false;
        $qtys_all = [];
        $sequence = [];

        // What is in the client's cart - already includes the new quantity
        foreach ($cart_contents as $item_key => $_item) {
            
            $item_id = $_item['variation_id'] ?: $_item['product_id'];
            $qtys_all[$item_id] = $qtys_all[$item_id] ?? 0;
            
            $sequence[$item_id][$item_key]['quantity'] = $_item['quantity'];
            $sequence[$item_id][$item_key]['ignore']   = $qtys_all[$item_id];
            
            $qtys_all[$item_id] += $_item['quantity'];
        }

        foreach ($cart_items as $id => $cart_item) {      

            $product  = $cart_item['data'];
            $in_stock = $product->get_stock_quantity();
            $qty_all  = $qtys_all[$id] ?? 0;

            // Adjust pre-order list of every instance - and add correct qty_all to each list
            foreach ($sequence[$id] ?? [] as $key => $value) {

                $item   = $cart_contents[ $key ];
                $qty    = $value['quantity'];
                $ignore = $value['ignore'];

                // how much in-stock is available for this item
                $available_in_stock = $ignore > $in_stock ? 0 : $in_stock - $ignore; 

                // client is adding more than is in stock 
                if ($qty > $available_in_stock) {
                    
                    $po_list = $this->generatePreOrderList( 
                        $id, 
                        $qty, 
                        $qty_all, 
                        $ignore > $in_stock ? ($in_stock > 0 ? $ignore - $in_stock : $ignore) : 0, // how much pre-order to ignore, 
                        $available_in_stock 
                    );
                    
                    if (! empty( $po_list )) {
                        
                        // the list has changed 
                        $has_changed = isset( $item['restock_list'] ) && $item['restock_list'] !== $po_list;

                        $item['restock_list']      = $po_list;
                        $item['qty_all_instances'] = $po_list['qty_all_instances'];

                    } else {
                        error_log( "The product #" . $id . " returns an empty pre-order array, while having a pre-order quantity." );
                    }

                } else if (
                    isset( $item['restock_list'] ) && 
                    $qty <= $available_in_stock
                ) {
                    $has_changed = true;
                    unset( $item['restock_list'] );
                    unset( $item['qty_all_instances'] );
                }

                // add altered cart item
                $cart_contents[$key] = $item;
            }
        }

        // Update the cart
        WC()->cart->set_cart_contents( $cart_contents );

        return $has_changed;
    }   
    
    /**
     * update BOM pre-order quantity of multiple items
     * 
     * @param array $cart_contents
     * @param array $items = [
     *      $cart_item_key => [
     *          quanity => int, 
     *          bom_items => []
     *      ]
     * ]
     * 
     * @return bool - if something has changed
     */
    public function updateBoMPreOrderLists( array $cart_contents, array $items ): bool 
    {    
        $has_changed = false;

        foreach ($items as $cart_item_key => $item) {      
            
            $quantity  = $item['quantity'];
            $bom_items = $item['bom_items'];
            
            $bom_sequence = [];
            $all_boms     = BoM::maybeGetAllBoMItemsInCart( $cart_item_key, $quantity, false );

            foreach ($bom_items as $bom_id => $bom) {
                foreach ($all_boms[$bom_id]['links'] as $key => $value) {
                    $bom_sequence[$key][$bom_id]['quantity'] = $value['quantity'];
                    $bom_sequence[$key][$bom_id]['multiply'] = $value['multiply'];
                    $bom_sequence[$key][$bom_id]['offset']   = $value['offset'];
                }
            }

            // Adjust pre-order list of every instance - and add correct qty_all to each list
            foreach ($bom_sequence as $key => $incl_bom) {

                $item = $cart_contents[$key];

                foreach ($incl_bom as $bom_id => $value) {
        
                    $in_stock = (new Product( $bom_id ))->getStock( true );
        
                    // client is adding more than is in stock 
                    if ($value['quantity'] > ( $in_stock - $value['offset'] )) {
        
                        $po_list = $this->generatePreOrderList( 
                            $bom_id, 
                            $value['quantity'], 
                            $all_boms[$bom_id]['qty_all'], 
                            $value['offset'], 
                            $in_stock 
                        );
        
                        if (! empty( $po_list )) {

                            // if has changed
                            $has_changed = isset($item['bom_restock_list'][$bom_id]) && $item['bom_restock_list'][$bom_id] === $po_list;

                            $item['bom_restock_list'][$bom_id] = $po_list;
                            $item['bom_restock_list'][$bom_id]['multiplied_by'] = $value['multiply'];

                        } else {
                            error_log( "The BOM product # " . $bom_id . " returns an empty pre-order array, while having a pre-order quantity." );
                        }
        
                    } // Client removed so much stock, we don't need to use pre-orders anymore
                    else if (
                        isset( $item['bom_restock_list'][$bom_id] ) && 
                        $value['quantity'] <= ( $in_stock - $value['offset'] )
                    ) {
                        $has_changed = true;
                        unset( $item['bom_restock_list'][$bom_id] );
                        if( empty( $item['bom_restock_list']) ) unset ($item['bom_restock_list'] );

                    }
                }

                $cart_contents[$key] = $item;
            }
        }

        // Update the cart
        WC()->cart->set_cart_contents($cart_contents);

        return $has_changed;
    }

    /**
     * REMOVE OR RESTORE
     */

    /**
     * When removing or restoring a cart item 
     * 
     * @param int $product_id
     * @param array $bom_po_list
     */
    public function onRemoveOrRestore( int $product_id, array $bom_po_list ): void
    {
        $cart_contents = $this->cart_contents;

        // do we have a better way to loop through this? The methods below will loop through the cart again
        foreach ($cart_contents as $cart_item_key => $cart_item) {

            $id       = $cart_item['variation_id'] ?: $cart_item['product_id'];
            $this_bom = $cart_item['bom_restock_list'] ?? [];
        
            // BOM
            if (
                $this_bom && 
                array_intersect_key( $bom_po_list, $this_bom )
            ) {

                $this->updateBoMPreOrderLists( 
                    $cart_contents, 
                    [
                        $cart_item_key => [
                            'bom_items' => $this_bom,
                            'quantity'  => $cart_item['quantity']
                        ]
                    ]
                );

            } // pre-order
            else if (
                $product_id === $id && 
                isset( $cart_item['restock_list'] )
            ) {

                $this->updatePreOrderLists( 
                    $cart_contents, 
                    [
                        $id => $cart_item
                    ] 
                );

            }
        }
    }

    /**
     * GENERATORS
     */

    /**
     * Adds a list of all shipments to the product's cart_item_data.
     *
     * Returns:
     * restock_list => array {
     *      quantity    => int,
     *      instock     => int,
     *      restock     => int,
     *      restocks    => array {
     *          restock_id => array {
     *              arrival => int unix_timestamp,
     *              stock   => int,
     *          }
     *      }
     *  }
     * 
     * @param int   $id - product id
     * @param int   $quantity - of the cart item
     * @param int   $qty_all_instances - always include the current item here as well
     * @param int   $offset - says how far in the pre-order, we start taking. applies for pre-order stock, not in-stock 
     * @param int   $in_stock - how much of the stock in_stock to use, remove what is used somewhere else
     * 
     * @return array $items
     */
    public function generatePreOrderList( 
        int $id, 
        int $quantity, 
        int $qty_all_instances, 
        int $offset, 
        int $in_stock 
    ): array {

        $list      = [];
        $shipments = (new Product( $id ))->getAllShipments();

        if (empty( $shipments )) return [];

        // all pre-order data
        $list['id']                = $id;
        $list['quantity']          = $quantity;
        $list['qty_all_instances'] = $qty_all_instances;
        $list['offset']            = $offset;

        if ($in_stock > 0) {
            $list['instock'] = $in_stock;
        } else {
            $in_stock = 0;
        }

        $list['restock']  = $exc_stock = $quantity - $in_stock;
        $list['restocks'] = [];

        // loop through all the shipments
        foreach ($shipments as $arrival => $shipment) {

            if ((int) $exc_stock === 0) break;

            $shipment_id = $shipment['id'];
            $po_stock    = $shipment['stock'];

            // offset. this pre-order is being ignored
            if ($offset >= $po_stock) {
                $offset -= $po_stock;
                continue;
            }

            $po_stock -= $offset;
            $offset = 0;

            $list['restocks'][$shipment_id]['arrival'] = $arrival;

            if ($po_stock < $exc_stock) {
                $exc_stock -= $po_stock;
                $list['restocks'][$shipment_id]['stock'] = $po_stock;
                continue;
            }

            $list['restocks'][$shipment_id]['stock'] = $exc_stock;
            $exc_stock = 0;
            
            break;
        }
        
        return $list;
    }
}