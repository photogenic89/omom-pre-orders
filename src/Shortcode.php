<?php
/**
 * shortcode that can be called wherever a shortcode can be called
 * currently shows all products which are selected in Shipments to be shown in this shortcode
 */
namespace Omom\PreOrders;

use Omom\PreOrders\Abstracts\Singleton;
use Omom\PreOrders\Handlers\ShipmentHandler;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class Shortcode extends Singleton 
{   
    /**
     * Nothing to construct 
     */ 
    public function __construct()
    {
    }

    /**
     * The callback for the shortcode, returning html tables
     * 
     * @param array $atts
     * 
     * @return string - html
     */
    public function getCallback( $atts ): string 
    {
        if (is_admin()) return "";

        ob_start();

        $shipments = $this->generateList();

        foreach ($shipments as $container_id => $shipment) {
        ?>
            <h2 class="has-text-align-center">
                <?= 'PL' . $container_id . ' | ' . $shipment['date'] ?>
            </h2>

            <figure class="wp-block-table">
                <table class="has-fixed-layout">
                    <tbody>
                        <tr>
                            <td class="has-text-align-center" data-align="center" data-col="1">
                                <strong>MODEL</strong>
                            </td>
                            <td class="has-text-align-center" data-align="center" data-col="2">
                                <strong>AMOUNT</strong>
                            </td>
                        </tr>

                        <?php 
                        foreach ($shipment['products'] as $product) { 
                            
                            $variations = $product['variations'] ?? false;

                            if (false === $variations) {

                                $this->getTableRow(
                                    $product["name"],
                                    (is_main_site() && 0 == $product["po_stock"]) ? 'SOLD OUT' : $product["original"]
                                );

                                continue; 
                            }

                            foreach ($variations as $var_name => $var_po_stock)
                                $this->getTableRow(
                                    $product["name"] . ' - ' . $var_name,
                                    (is_main_site() && 0 == $product['rs_vars'][$var_name]) ? 'SOLD OUT' : $var_po_stock
                                );
                        } 
                        ?>

                    </tbody>
                </table>
            </figure>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Add a table row to the table
     * 
     * @param string $name
     * @param $amount
     */
    private function getTableRow( string $name, $amount ): void
    {
        ?>
        <tr>
            <td 
                class="has-text-align-center" 
                data-align="center" 
                data-col="1"
            >
                <?= $name ?>
            </td>
            <td 
                class="has-text-align-center" 
                data-align="center" 
                data-col="2"
            >
                <?= $amount ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Generate a curated list of the future stock 
     * 
     * @todo $blog should be a shortcode atts defined by the client
     * 
     * @return array
     */
    public function generateList(): array
    {
        $data = [];
        $blog = is_main_site() ? 2 : 1; // get info from ws site   
        $i    = 0;
    
        if (2 === $blog) switch_to_blog( $blog );

        foreach (Queries::getActiveShipments( 0, false, true ) as $shipment) {
            
            // containers tend to have 'PL' prepended to a four digit number
            $key = (int) str_replace( 'PL', '', $shipment->getContainerID() );
            $key = empty( $key ) ? ++$i : $key;

            $data[$key] = [
                'date'     => $shipment->getArrival( 4 ),
                'products' => $this->createProductsList( $shipment->getProducts() ),
            ];
        }

        ksort( $data );

        if (2 === $blog) restore_current_blog();

        return $data;
    }

    /**
     * Create a list of products in this shipment
     * 
     * @param array $list_items
     * 
     * @return array
     */
    private function createProductsList( array $list_items ): array
    {
        $products = [];

        foreach ($list_items as $list_item) {

            if ('on' !== ($list_item['On_FS'] ?? 'off')) continue;
            
            $product_id = $list_item['ID'];
            $product    = wc_get_product( $product_id );

            if (! $product) continue;

            $name = $product->get_name();
            $type = $product->get_type();

            // don't forget variable-product-part
            if ('variation' === $type || 'variable-product-part' === $type) {
                $product_id = $product->get_parent_id();
                $name       = wc_get_product( $product_id )->get_name();
            }

            if (
                // two different types of dashes
                false === stripos( $name, '– Frame Type' ) && 
                false === stripos( $name, '- Frame Type' )  
            ) { 
                $products[$product_id] = [
                    'name'     => $name,
                    'original' => $list_item['Original'] + ($_products[$product_id]['original'] ?? 0),
                    'po_stock' => $list_item['Restock'] + ($_products[$product_id]['po_stock'] ?? 0),
                ];

                continue;
            }

            $attr_name = $this->getAttributeName( $product->get_attributes() );

            // combine variations by colour, not size
            $products[$product_id]['name']                   = str_replace( ['- Frame Type', ' – Frame Type'], '', $name );
            $products[$product_id]['variations'][$attr_name] = $list_item['Original'] + ($products[$product_id]['variations'][$attr_name] ?? 0);
            // Used on retail to display SOLD OUT if po_stock is 0
            $attr_po_stock                                = (int) ($products[$product_id]['rs_vars'][$attr_name] ?? 0);
            $products[$product_id]['rs_vars'][$attr_name] = $attr_po_stock + ((int) $list_item['Restock']);
        }

        return $products;
    }

    /**
     * Get an attribute name
     * 
     * @param array $attributes
     * 
     * @return string
     */
    private function getAttributeName( $attributes ): string
    {
        $attr_name = "";

        foreach ($attributes as $taxonomy => $attr_slug) {
                    
            if (false === stripos( $taxonomy, 'colour' )) continue;
            
            $term      = get_term_by( 'slug', $attr_slug, $taxonomy ); // Do i need to retrieve a whole object?
            $attr_name = $term->name ?? '';

            break;
        }

        return $attr_name;
    }
}