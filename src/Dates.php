<?php
/**
 * Creates a list of dates for either cart or order
 */
namespace Omom\PreOrders;

defined( 'ABSPATH' ) || exit;

class Dates 
{
    /**
     * the list of dates
     */
    private array $dates = [];

    /**
     * Constructor
     * Add the item for which to generate the list
     */
    public function __construct( 
        array|\WC_Order_Item_Product $item
    ) {   
        $is_order = is_a( $item, 'WC_Order_Item_Product');

        $dates = $this->setList( 
            [], 
            $is_order ? $item : WC()->cart->cart_contents[ $item['key'] ]
        );

        $product = $is_order ? $item->get_product() : $item['data'];

        // Composite and Bundle support
        if (
            is_a( $product, 'WC_Product' ) && 
            $product->is_type( 'composite' )
        ) {

            $dates = $this->compositeDatesList( 
                $dates, 
                $item 
            );
        
        } else if (
            is_a( $product, 'WC_Product' ) && 
            $product->is_type( 'bundle' )
        ) {
            
            $dates = $this->bundleDatesList( 
                $dates, 
                $item 
            );

        }

        $this->dates = $dates;
    }

    /**
     * Alter list and generate html 
     * 
     * @return string
     */
    public function getHtml(): string
    {
        $value = '';
        $items = $this->getSequence();

        foreach ($items as $arrival => $qty) {
            $str       = $arrival === 0 ? "x ready to ship" : "x pre-order for the " . date_i18n( "jS \of F Y", $arrival );
            $name      = Queries::getContainerIdByArrival( $arrival );
            $container = ! $name ? '' : '<span class="omom-shipment-name"> (' . $name . ')</span>';
            
            $value .= '<p class="omom-shipment-date">' . $qty . $str . $container . '</p>';
        }

        return $value;
    }

    /**
     * Get the current sequence 
     * 
     * @return array
     */
    public function getSequence(): array
    {
        $dates = $this->dates;
        return empty( $dates ) ? [] : $this->generateSequence( $dates );       
    }

    /**
     * Loop through all composited items
     * 
     * @param array $dates
     * @param array|\WC_Order_Item_Product $item
     * 
     * @return array $dates
     */
    private function compositeDatesList( array $dates, array|\WC_Order_Item_Product $item ): array 
    {
        if (! function_exists( 'wc_cp_get_composited_cart_items' )) return $dates;

        $is_order   = is_a( $item, 'WC_Order_Item_Product');
        $composited = $is_order ? wc_cp_get_composited_order_items( $item, $item->get_order(), true, true ) : wc_cp_get_composited_cart_items( $item, WC()->cart->cart_contents, true, true );

        foreach ($composited as $child_item_key) {
            
            $child_item = $is_order ? new \WC_Order_Item_Product( $child_item_key ) : WC()->cart->cart_contents[ $child_item_key ];
            $product    = $is_order ? $child_item->get_product() : $child_item['data'];

            if (! $product) continue;

            if ($product->is_type( 'bundle' )) {
                $dates = $this->bundleDatesList( 
                    $dates, 
                    $child_item 
                );
                continue;
            }
            
            $dates = $this->setList( 
                $dates, 
                $child_item
            );
        }

        return $dates;
    }
        
    /**
     * Loop through all bundled items
     * 
     * @param array $dates
     * @param array|\WC_Order_Item_Product $item
     * 
     * @return array $dates
     */
    private function bundleDatesList( array $dates, array|\WC_Order_Item_Product $item  ): array 
    {
        if (! function_exists( 'wc_pb_get_bundled_cart_items' )) return $dates;

        $is_order = is_a( $item, 'WC_Order_Item_Product');
        $bundled  = $is_order ? wc_pb_get_bundled_order_items( $item, $item->get_order(), true ) : wc_pb_get_bundled_cart_items( $item, WC()->cart->cart_contents, true );

        foreach ($bundled as $child_item_key)
            $dates = $this->setList( 
                $dates, 
                $is_order ? new \WC_Order_Item_Product( $child_item_key ) : WC()->cart->cart_contents[ $child_item_key ]
            );

        return $dates;
    }

    /**
     * Generate list of all items sorted by date
     * 
     *  $dates = { 
     *      unix_timestamp => {
     *          item_id => qty,
     *      },
     *  };
     * 
     * @param array $dates
     * @param array|\WC_Order_Item_Product $item
     * @return array
     */
    private function setList( 
        array $dates, 
        array|\WC_Order_Item_Product $item
    ): array {
        
        // checking for unique keys, otherwise this might lead to complications if the same product appears several times inside the cart/order
        $is_order  = is_a( $item, 'WC_Order_Item_Product');
        $shipments = $is_order ? $item->get_meta( '_restock_list' ) : ($item['restock_list'] ?? []);

        // 1. If has pre-order, generate the list
        if (! empty( $shipments )) {
            $product_id   = $is_order ? $item->get_product_id() : $item['product_id'];
            $variation_id = $is_order ? $item->get_variation_id() : $item['variation_id'];
            $product_id   = $variation_id ?: $product_id;
            
            if (isset( $shipments['instock'] )) $dates[0][$product_id] = $shipments['instock'];

            foreach ($shipments['restocks'] as $value) 
                $dates[$value['arrival']][$product_id] = $value['stock'];
        }
        
        $bom_shipments = $is_order ? $item->get_meta( '_bom_restock_list' ) : ($item['bom_restock_list'] ?? []);
        
        // 2. if has BOM with pre-order
        if (empty( $bom_shipments )) return $dates;

        foreach ($bom_shipments as $bom_id => $part) {

            $divider  = $part['multiplied_by'] ?? 1;
            $instock  = $part['instock'] ?? false;
            $leftover = 0;

            if ($instock) {
                // correct in_stock, until it has an integer value
                while ($instock % $divider !== 0) {
                    $leftover++;
                    if ($instock < $divider) break;
                    $instock--;
                }

                if ($instock > 0) $dates[0][$bom_id] = intval( $instock / $divider );
            }

            if (! isset( $part['restocks'] )) continue;

            foreach ($part['restocks'] as $value) {
                $bom_stock = $value['stock'] + $leftover;
                $leftover  = 0;

                // correct pre-order, until it has an integer value
                while ($bom_stock % $divider !== 0) {
                    $leftover++;
                    if( $bom_stock < $divider ) break;
                    $bom_stock--;
                }

                if ($bom_stock < $divider) continue;

                $dates[$value['arrival']][$bom_id] = intval( $bom_stock / $divider );
            }
        }
        
        return $dates;
    }

    /**
     * Change pre-orders to a list of the most logical shipment order
     * 
     * @param array $dates
     * 
     * @return array
     */
    protected function generateSequence( array $dates ): array 
    {
        $all_ids   = [];
        $prev_date = null;
        $sequence  = [];

        // go through the dates in ascending order
        ksort( $dates );

        // list all products
        foreach ($dates as $date => $values) {
            foreach ($values as $id => $qty) {
                if (! in_array( $id, $all_ids, true )) array_push( $all_ids, $id   );
            }
        }

        // find the sequence
        foreach ($dates as $date => $values) {
            
            if (! $prev_date) {
                // if you have all the products
                if (count( $all_ids ) === count( $values ) ) {
                    $sequence[$date] = min( $values );
                    foreach ($all_ids as $id) {
                        $values[$id] -= $sequence[$date];
                    }
                } 
        
                // build a first prev_date array for the next round
                foreach ($values as $id => $qty) {
                    $prev_date[$id] = $qty; 
                } 

                continue;
            }

            // add stocks from previous date
            foreach ($all_ids as $id) {
                if (isset( $prev_date[$id] )) {
                    if (! isset( $values[$id] )) $values[$id] = 0;
                    $prev_date[$id] = $values[$id] += $prev_date[$id];
                } else if (! isset( $prev_date[$id] ) && isset( $values[$id] )) {
                    $prev_date[$id] = $values[$id];
                }
            }

            // this is probably ignoring bundled quantities e.g. 5 spacers in 1 bike
    
            $min      = min( $values );
            $godt_nok = true;
    
            // check if products are missing
            foreach ($all_ids as $id) {
                if ((isset( $values[$id] ) && $values[$id] < $min ) || ! array_key_exists( $id, $values )) {
                    $godt_nok = false; 
                }
            }

            if (! $godt_nok) continue;

            if ($min > 0) $sequence[$date] = $min;

            foreach ($values as $id => $qty) $prev_date[$id] = $qty - $min;
        };

        return $sequence;
    }
}