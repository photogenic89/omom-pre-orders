<?php
/**
 * Helpers to get bom data
 */
namespace Omom\PreOrders\Integrations\AtumProductLevels;

defined( 'ABSPATH' ) || exit;

use Atum\Inc\Helpers as AtumOriginalHelpers;
use AtumLevels\Models\BOMModel;
use Omom\PreOrders\Handlers\ProductHandler as Product;

class Helpers 
{
    /**
     * A list of all BOM items in the cart
     */
    public static array $all_bom_items = [];

    /**
     * All the BOM ID's => multipliers in one array
     */
    public static array $all_bom_in_cart = [];

    /**
     * Get linked bom parts
     * 
     * @param int $product_id 
     * @return array
     */
    public static function getLinkedBoMParts( $product_id = 0 ): array 
    {
        $parts = class_exists( 'AtumLevels\Models\BOMModel' ) ? BOMModel::get_linked_bom( $product_id, 'product_part' ) : [];
        return is_array( $parts ) ? $parts : [];
    }

    /**
     * Find the deductable maximum by looking for the smallest purchasable amount
     * 
     * @param int $product_id 
     * @param bool  $combine
     * 
     * @return false|int|array false if no bom, int if combine is true, array if combine is false
     */
    public static function findMaxValue( int $product_id, bool $combine = true ): mixed 
    {
        $bom_items = self::getLinkedBoMParts( $product_id );

        if (empty( $bom_items )) return false;

        $max_values = [ 
            'stock'    => 0, 
            'po_stock' => 0 
        ];

        $max_value = $i = 0;

        foreach ($bom_items as $part) {

            $bom_id = $part->bom_id;

            if (! wc_get_product( $bom_id )) continue;

            $po_product = new Product( $bom_id );

            $bom_value = [
                'stock'    => $po_product->getStock( true ),
                'po_stock' => $po_product->getPreOrderStock(),
            ];

            $divider = $part->qty;

            // One value - Int
            if ($combine) {
                $value     = intval( $bom_value['stock'] / $divider ) + intval( $bom_value['po_stock'] / $divider );
                $max_value = ($i === 0 || $value < $max_value) ? $value : $max_value;

                $i++;

                continue;
            }

            // Separate values - Array
            foreach ($max_values as $type => $current_max) {
                
                switch ($type) {
                    case 'stock':
                        $value = intval( $bom_value['stock'] / $divider );
                        break;

                    case 'po_stock':
                        // Only count PO stock above what is covered by regular stock
                        $remaining_po = max( $bom_value['po_stock'] + $bom_value['stock'], 0 );
                        $value        = intval( $remaining_po / $divider );
                        break;
                }


                $max_values[ $type ] = ($i === 0 || $value < $current_max) ? $value : $current_max;
            }

            $i++;
        }

        // If no BOM items processed, return false
        if ($i === 0) return false;

        // after combining both values, remove what in-stock stock is purchasable 
        $max_values['po_stock'] = max( 0, $max_values['po_stock'] - $max_values['stock'] );

        return $combine ? $max_value : $max_values;
    }

    /**
     * It's an ATUM product and Mikkel set it to -1, then no one should touch it
     * 
     * @param int $id
     * @return bool
     */
    public static function atumProductIsAvailable( $id ): bool 
    {
        $product = class_exists( 'AtumOriginalHelpers' ) ? AtumOriginalHelpers::get_atum_product( $id ) : false;
        return ! $product ? true : ($product->get_available_to_purchase() === -1 ? false : true);       
    }

    /**
     * Check if whole BOM exceeds the minimum quantity which is needed for it
     * 
     * @param array $bom_items
     * @return bool
     */
    public static function boMExceedsMinimum( $bom_items = [] ): bool 
    {    
        $exceeds = true;

        foreach ($bom_items as $part) {
            $divider    = $part->qty;
            $po_product = new Product( $part->bom_id );
            $bom_value  = $po_product->getTotalStock();

            $exceeds = intval( $bom_value < $divider ) ? false : $exceeds;
            if (! $exceeds) break;
        }

        return $exceeds;
    }

    /**
     * Find closest shipment arrival date in BOM
     * 
     * @param int $product_id
     * @return mixed
     */
    public static function getClosestBoMArrival( int $product_id ): mixed 
    {
        $unix_date = 0;
        $bom_items = self::getLinkedBoMParts( $product_id );

        if (empty( $bom_items )) return false;

        foreach ($bom_items as $part) {

            $po_product = new Product( $part->bom_id );
            
            if ($po_product->hasStock()) continue;
            
            $current_rs_date = $po_product->getClosestArrival();
            $unix_date = (0 < $current_rs_date && ( 0 === $unix_date || $unix_date > $current_rs_date )) ? $current_rs_date : $unix_date;
        }

        return $unix_date !== 0 ? $unix_date : false;
    }

    /**
     * @author: Nikolay
     * Database query to receive all linked bom items
     * 
     * @param int $id
     */
    public static function getProductBoMById( $product_id ) 
    {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT bom_id, qty FROM {$wpdb->prefix}atum_linked_boms WHERE product_id = %d", strval($product_id) ), OBJECT_K );
    }

    /**
     * @author: Nikolay
     * Database query to receive all linked bom items
     * 
     * @param int $id
     */
    public static function getAllLinkedBoMProducts( $bom_id ) 
    {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT product_id, qty FROM {$wpdb->prefix}atum_linked_boms WHERE bom_id = %d", strval($bom_id) ), OBJECT_K );
    }

    /**
     * Compares cart items and linked boms of a product part
     * Returns the bom_cart_quantity - used for offset
     * 
     * @param int   $bom_id
     * @param array $quantities
     * 
     * @return int
     */
    public static function getBoMCartItemQuantity( $bom_id, $quantities ): int 
    {    
        $linked_products   = self::getAllLinkedBoMProducts( $bom_id );
        $bom_cart_quantity = 0;

        foreach (array_intersect_key( $linked_products, $quantities ) as $link_id => $link_data) {
            $link_multiplier = $link_data->qty; 
            $link_in_cart    = $quantities[$link_id];
            
            $bom_cart_quantity += $link_multiplier * $link_in_cart;
        }

        return $bom_cart_quantity;
    }

    /**
     * Maybe get all bom items with one call, instead of using several sql queries
     * 
     * Returns:
     * $all_bom_items =array(
     *   bom_id => array(
     *       'links' => array (
     *           cart_item_key => array (
     *               quantity => int,
     *               multiply => int,
     *               offset   => int,
     *          )
     *       ),
     *       'qty_all' => int,
     *   )
     * )
     * 
     * @param int  $cart_item_key
     * @param int  $quantity
     * @param bool $validate
     * 
     * @return array $all_bom_items 
     */
    public static function maybeGetAllBoMItemsInCart( $cart_item_key, $quantity, $validate = false ): array 
    {
        $all_bom_items = self::$all_bom_items;
        $list = $ids = [];
        $incorrect = false;

        // safety check if all_bom_items needs updating
        foreach ($all_bom_items as $id => $data) {
            foreach ($data['links'] as $key => $values) {
                $divider = empty( $values['multiply'] ) ? 1 : $values['multiply'];
                
                if ($key !== $cart_item_key && ( $values['quantity'] / $divider ) === $quantity) continue;
                
                $incorrect = true;
                
                break;
            }

            if ($incorrect) break;
        }

        if (
            ! empty( $all_bom_items ) && 
            ! $incorrect
        ) return $all_bom_items;

        $all_bom_items = $validate_composites = [];

        // 1. list of products
        foreach (WC()->cart->cart_contents as $item_key => $item) {
            $product_id = $item['variation_id'] ?: $item['product_id'];

            if (! array_key_exists( $product_id, $list )) array_push( $ids, $product_id );

            $list[$item_key] = array(
                'product_id' => $product_id,
                'quantity'   => ($validate && $cart_item_key === $item_key) ? $quantity : $item['quantity'],
            );
            
            // check on validation for composites
            if ($validate && $cart_item_key === $item_key && isset( $item['composite_children']) ) {
                $validate_composites = $item[ 'composite_children' ];
            }

            // check on validation for bundles ?
        }

        // check on validation for composites
        if ($validate_composites) {
            foreach ($validate_composites as $key => $item_key) {
                $list[$item_key]['quantity'] = $quantity;
                // do I need to multiply this quantity? 
            }
        }

        // 2. SQL query
        global $wpdb;

        $query     = '';
        $first     = true;
        $bom_items = [];

        foreach ($ids as $id) {
            if (! $first) $query .=' OR';
            $query .= ' product_id = %d';
            $first  = false;
        }

        $results = ! empty( $ids ) ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}atum_linked_boms WHERE $query", $ids ), OBJECT_K ) : false;

        if (! $results) return $all_bom_items;

        foreach ($results as $rt) {
            $bom_items[$rt->product_id][$rt->bom_id] = $rt->qty;
        }

        // 3. Generate list
        foreach ($list as $item_key => $product) {
            $id  = $product['product_id'];
            $qty = $product['quantity'];

            if (! array_key_exists( $id, $bom_items )) continue;

            $bom_item = $bom_items[$id];

            foreach ($bom_item as $bom_id => $multiplier) {

                $bom_quantity = $qty * $multiplier;
                $all_bom_items[$bom_id]['qty_all'] = $all_bom_items[$bom_id]['qty_all'] ?? 0;

                $all_bom_items[$bom_id]['links'][$item_key] = array(
                    'quantity' => $bom_quantity,
                    'multiply' => $multiplier,
                    'offset'   => $all_bom_items[$bom_id]['links'][$item_key]['offset'] ?? $all_bom_items[$bom_id]['qty_all'],
                );

                $all_bom_items[$bom_id]['qty_all'] += $bom_quantity;
            }  
        }

        self::$all_bom_items = $all_bom_items;

        return $all_bom_items;
    }

    /**
     * Check all BOM cart items
     * It does not search by product_id, because product parts can be in differing products
     * 
     * @param bool  $valid
     * @param int   $quantity
     * @param array $bom_items
     * @return bool
     */
    public static function validateBoMCart( bool $valid, int $quantity, array $bom_items = [] ): bool 
    {
        $bom_in_cart = self::$all_bom_in_cart;

        // 1. Find all BOM items in cart 
        if (empty( $bom_in_cart )) {
            $bom_in_cart = self::$all_bom_in_cart = self::maybeGetAllBoMItemsInCart( 0, $quantity );
            
            if (empty( $bom_in_cart )) return $valid;
        }

        // 2. Find intersecting BOM parts
        $intersect = [];
        $ids = wp_list_pluck( $bom_items, 'bom_id' );
        
        foreach ($bom_in_cart as $id => $data) {
            if (! in_array( $id, $ids )) continue;

            $intersect[$id] = $intersect[$id] ?? $data['qty_all'];
        }

        if (empty( $intersect )) return $valid;

        $multiplier = function (int $id) use ($bom_items) {
            $multiplier = 1;

            foreach ($bom_items as $bom_item) {
                if ($id !== ((int) $bom_item->bom_id)) continue;
                $multiplier = $bom_item->qty;
                break;
            }

            return $multiplier;
        };

        // 3. Check intersecting BOM parts
        foreach ($intersect as $id => $qty_all) {
            $po_product     = new Product( $id );
            $total_in_stock = $po_product->getTotalStock();

            if ($total_in_stock >= ( $qty_all + $multiplier($id) * $quantity )) continue;

            wc_add_notice( 
                __( 
                    'The product has not enough product parts. #' . $id, 
                    OMOM_PREORDERS()->text_domain 
                ), 
                'error' 
            );

            return false;
        }

        return $valid;
    }
}