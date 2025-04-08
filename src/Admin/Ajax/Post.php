<?php
/**
 * All Ajax calls for a single post page
 */
namespace Omom\PreOrders\Admin\Ajax; 

defined( 'ABSPATH' ) || die;

use Omom\PreOrders\Handlers\ShipmentHandler;

class Post 
{
    /**
     * Get all products to display on 
     */
    public function getProducts( $data ): void
    {	
        $post_id = $data['postId'] ?? 0;

        if (! $post_id) return;

        $products  = $post_id ? (new ShipmentHandler( $post_id))->getProducts() : [];
        $_products = [];

        foreach ($products as $product) {

            $id         = $product['ID'] ?? '';
            $is_product = $id ? wc_get_product($id) : '';
            
            if (! $is_product) continue;
            
            $_products[] = [
                'id'                => $id,
                'OriginalQty'       => $product['Original'] ?? 0,
                'AvailableQty'      => $product['Restock'] ?? "",
                'onFutureStockPage' => $product['On_FS'] ?? "", // on_FS missing!
                'name'              => $is_product->get_formatted_name() ?? $id,
                "state"             => "old"
            ];
        }

        wp_send_json_success([
            'products' => $_products
        ]);
    }

    /**
     * When searching for a product, show these suggestions
     *
     */
    public function searchForSuggestions( $data ): void
    {
        $term = $data['term'] ?? false;

        if (! $term) wp_send_json_success( [] );

		// incorporated parts from WC_AJAX::json_search_products	
		$data_store = \WC_Data_Store::load( 'product' );
		$ids        = $data_store->search_products( $term, '', false, false, 10, [], [] );

		$products = [];
        
		foreach ($ids as $id) {
			$product_object = wc_get_product( $id );

			if (! wc_products_array_filter_readable( $product_object )) continue;

			$formatted_name = $product_object->get_formatted_name();

			$products[] = [
				'id'    => $product_object->get_id(),
				'title' => rawurldecode( wp_strip_all_tags( $formatted_name ) ),
			];
		}
		
		wp_send_json_success( $products );
    }
}