<?php
/**
 * Helper methods
 */
namespace Omom\PreOrders;

defined( 'ABSPATH' ) || exit;

use Omom\PreOrders\Handlers\ProductHandler;
use Omom\PreOrders\Handlers\ShipmentHandler;
use Omom\PreOrders\Integrations\AtumProductLevels\Helpers as BoM;

class Helpers 
{
    /**
     * Meta key in order item. value contains refNo of Kit Assembly
     * parent kit
     */
    public const TEXT_DOMAIN = '_vismanet_ka_ref_no';
    
    /**
     * Check the cart if the product is already in it
     * 
     * @param bool $valid
     * @param int  $product_id
     * @param int  $quantity
     * @param int  $max_stock
     * @param array $bom_items
     * @return bool
     */
    public static function compareWithCart( 
		bool $valid, 
		int $product_id, 
		int $quantity, 
		int $max_stock, 
		array $bom_items = [] 
	): bool {

        // BOM
        if (! empty( $bom_items )) return BoM::validateBoMCart( $valid, $quantity, $bom_items );

        $quantities    = WC()->cart->get_cart_item_quantities();
        $all_instances = $quantities[$product_id] ?? 0;

        if (0 === $all_instances) return $valid;
        
        $quantity += $all_instances; // the product is not yet in the cart, thus adding the quantity is fine

        if ($max_stock < $quantity) {
            wc_add_notice( 
                __( 
                    'There is not enough stock to add this quantity. #' . $product_id, 
                    OMOM_PREORDERS()->text_domain 
                ), 
                'error' 
            );

            return false;
        }

        return $valid;
    }

    /**
     * Update product stock of preorder posts
     * 
     * @todo compare between bom and normal po_list
     * @todo if shipment has changed in meantime, recalculate so qty is taken from somewhere else
     * 
     * @param int    $order_item_id
     * @param int    $product_id
     * @param array  $preorder_list = [
     *      quantity => int,
     *      instock  => int,
     *      restock  => int,
     *      restocks => [
     *          shipment_id (int) => [
     *              (arrival => string) ?
     *              stock => int 
     *          ]
     *      ]
     * ]
     * @param 'decrease' | 'increase' | 'set' $operation 
     * @param string $note
     * 
     * @return int - new pre-order stock of product
     */
    public static function updateOrderItem( 
        int $order_item_id,
        int $product_id, 
        array $po_list, 
        string $operation, 
        string $note 
    ): int {

        $new_po_list = $po_list;
        $new_po_list['restocks'] = [];

        foreach ($po_list['restocks'] as $shipment_id => $info) {
            
            $handler = new ShipmentHandler( $shipment_id );
            $qty     = $info['stock'];

            // don't change product in released, draft or removed (no products) shipments
            if (
                empty( $handler->getProducts() ) ||
                $handler->isStatus( 'released' ) || 
                $handler->isStatus( 'draft' )
            ) {
                isset( $new_po_list['instock'] ) ? $new_po_list['instock'] += $qty : $new_po_list['instock'] = $qty;
                $new_po_list['restock'] -= $qty;
                continue;
            }

            $new_po_list['restocks'][$shipment_id] = $info;

		    $handler->updateProductPreOrderQty( 
                $product_id, 
                $qty, 
                $operation, 
                $note 
            );
        }

        if ($po_list === $new_po_list) return (new ProductHandler( $product_id ))->getPreOrderStock();

        // update the shipments list if has changed
        $item = new \WC_Order_Item_Product( $order_item_id );

        // remove any order item meta, if no shipments are processable anymore
        if (
            ! empty( $po_list ) && 
            empty( $new_po_list['restocks'] )
        ) {

            $item->delete_meta_data( '_reduced_restock' );
            $item->delete_meta_data( '_restock_list' );
            $item->delete_meta_data( '_bom_restock_list' );
            $item->delete_meta_data( 'Shipment dates' );

        } else {

            // BoM
            if (isset( $new_po_list['multiplied_by'] )) {
                $old_po_list = $item->get_meta_data( '_bom_restock_list' );
                $old_po_list[$new_po_list['id']] = $new_po_list;
                $new_po_list = $old_po_list;
            }

            $item->update_meta_data( 
                isset( $new_po_list['multiplied_by'] ) ? '_bom_restock_list' : '_restock_list',
                $new_po_list
            );

            $item->update_meta_data( 
                'Shipment dates',
                (new Dates( $item ))->getHtml()
            );

        }

        $item->save();

        return (new ProductHandler( $product_id ))->getPreOrderStock();
    }

    /**
     * A customized version of the WP function map_deep
     * So it does not remove the important bits from Wordpress blocks
     * 
     * @param mixed $value
     * @param string $callback
     * @®eturn mixed
     */
    public static function mapDeep( mixed $value, string $callback = 'sanitize_text_field' ): mixed
    {
        if (is_array( $value )) {

            foreach ($value as $index => $item) {
                $callback = 'post_content' === $index ? 'wp_kses_post' : $callback; // change the callback for post_content
                $value[ $index ] = self::mapDeep( $item, $callback );
            }

        } elseif (is_object( $value )) {

            $object_vars = get_object_vars( $value );
            foreach ($object_vars as $property_name => $property_value) {
                $value->$property_name = self::mapDeep( $property_value, $callback );
            }
            
        } else {
            $value = call_user_func( $callback, $value );
        }
    
        return $value;
    }
}