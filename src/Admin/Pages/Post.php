<?php
/**
 * edit post
 * 
 * @todo $_POST['arrival] can contain the wrong timezone
 */
namespace Omom\PreOrders\Admin\Pages;

defined( 'ABSPATH' ) || die;

use Omom\PreOrders\Abstracts\Page;
use Automattic\Jetpack\Constants;
use Omom\PreOrders\Handlers\ShipmentHandler;
use Omom\PreOrders\Helpers;

class Post extends Page 
{
	/**
	 * The version of the scripts on this page
	 */
	protected static $script_version = '1.1.0';

	/**
	 * Call parent constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Enqueue admin scripts
     * 
     * @param $hook
	 */
	public static function enqueueAdminScripts( $hook ): void
	{
        $url   = OMOM_PREORDERS()->pluginUrl();
		$asset = require_once(__DIR__ . '/../../../assets/js/dist/omom-post.asset.php');
        $v     = self::$script_version;

        wp_enqueue_style( 
            'single-pre-order', $url . '/assets/css/dist/omom-admin.css', 
            [], 
            $v 
        );

        wp_enqueue_script( 
            'edit-pre-order', $url . '/assets/js/dist/omom-post.js', 
            $asset['dependencies'] ?? [], 
            $asset['version'] ?? $v, 
            true 
        );

        // WC: Product search Ajax
        $suffix  = Constants::is_true( 'SCRIPT_DEBUG' ) ? '' : '.min';
        $version = Constants::get_constant( 'WC_VERSION' );

        wp_enqueue_script( 
            'wc-admin-order-meta-boxes', WC()->plugin_url() . '/assets/js/admin/meta-boxes-order' . $suffix . '.js', 
            ['wc-admin-meta-boxes'], 
            $version 
        );

        wp_enqueue_style( 
            'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', 
            [], 
            $version 
        );

		self::loadAjaxObject( 'edit-pre-order' );
    }

    /**
     * Hooks used on this page
     */
    public static function setHooks(): void
    {
        add_action( 'edit_form_top',  [__CLASS__, 'singlePostTitle'] );
        add_action( 'add_meta_boxes', [__CLASS__, 'addPreOrderMetabox'] );
    }

    /**
     * Add Post Pre-Order Title
     * 
     * @param object $post
     */
    public static function singlePostTitle( $post ): void
    {    
        if ('restock' !== get_post_type( $post )) return;

        global $pagenow;

        $post_id   = $post->ID;
        $handler   = new ShipmentHandler( $post_id );
        $is_active = $handler->isActive();
        $status    = "";

        switch (true) {
            case $handler->isStatus( 'released' ):

                $color = 'red';
                $status = 'Released';
                break;

            case $handler->isStatus( 'draft' ):

                $color = 'darkorange';
                $status = 'Draft';
                break;

            case $is_active && ! $handler->isStatus( 'draft' ):
                
                $color = "green";
                $status = 'Active';
                break;

            case ! $is_active && $pagenow === 'post-new.php':

                $color = 'lightblue';
                $status = 'New';
                break;

            default: 

                // case is past arrival date but still has stock
                $color = 'pink'; // 'yellow';
                $status = 'Received'; // 'Ready for release';
        }

        ?>
        <h1 style="color:<?= $color ?>">
            <?= $status ?> – Shipment #<?= $post_id ?>
        </h1>
        <?php
    }
        
    /**
     * Add metabox to CPT Restock
     */
    public static function addPreOrderMetabox(): void 
    {
        add_meta_box(
            'om_rs_actions',
            'Actions',
            [__CLASS__, 'outputActions'],
            'restock',
            'side',
            'core'
        );

        add_meta_box(
            'om_rs_data',
            'Info',
            [__CLASS__, 'outputInfo'],
            'restock',
            'side',
            'low'
        );

        add_meta_box(
            'om_rs_products',
            'Products in shipment',
            [__CLASS__, 'productsAppRoot'],
            'restock',
            'normal',
            'high'
        );

        add_meta_box(
            'om_rs_log',
            'Log',
            [__CLASS__, 'outputLog'],
            'restock',
            'normal',
            'core'
        );
    }

    /**
     * All actions 
     * 
     * @param $post
     */
    public static function outputActions( $post ): void
    {
        $post_id         = $post->ID;
        $shipment        = new ShipmentHandler( $post_id );
        $automatic       = $shipment->getAutomatic();
        $in_future_stock = $shipment->getShowInFutureStock();
 
        require_once OMOM_PREORDERS()->pluginPath()  . '/views/view-om-rs-actions.php';
    }

    /**
     * Info box on the side
     * 
     * @param WP_Post object $post
     */
    public static function outputInfo( $post ): void
    {
        $post_id   = $post->ID;
        $shipment  = new ShipmentHandler( $post_id );
        $products  = $shipment->getProducts();
        $time      = $shipment->getArrival( 0 ) ?: date( 'Y-m-d H:i' );
        $comment   = $shipment->getComment();
        $container = $shipment->getContainerID();

        // find non existent products and add them to the comments.
        foreach ($products as $product) {
            $id      = $product['ID'] ?? '';
            $product = $id ? wc_get_product( $product['ID'] ) : '';

            if ($product) continue;

            $$po_stock = $product['Restock'] ?? '';
            $string = $id . " and a pre-order stock of " . $$po_stock;

            if (false === stripos( $comment, $string )) continue;
            
            $comment .= "&#13;&#10;Found non-existent product with ID " . $string;
        }

        // render pre-order post page
        require_once OMOM_PREORDERS()->pluginPath() . '/views/view-om-rs-info.php';
    }

    /**
     * Show what the stock was, when it was released
     * + and how stock moved
     * 
     * @param $post
     */
    public static function outputLog( $post ): void
    {
        $post_id    = $post->ID;
		$upload_dir = wp_upload_dir();
		$upload_dir = $upload_dir['basedir'];
		$file       = $upload_dir . '/restock-logs/' . $post_id . '.log';
        $lines      = file_exists( $file ) ? file( $file ) : false;
        $i          = true;

        if (! $lines) return;

        krsort( $lines );

        foreach ($lines as $line) {

            $line   = "string" === gettype( $line ) ? json_decode( $line, true ) : $lines;
            $data   = $line['context'] ?? '';
            $action = $line['message'] ?? '';
            $user   = $line['user'] ?? '';
            $date   = $line['datetime']['date'] ?? $line['datetime'] ?? "";
            $time   = (string) date( "Y.n.j, g:ia", strtotime( (string) $date ) );

            if (empty( $data )) continue;

            if ($i) { 
            ?>
            <table class="wp-list-table widefat fixed striped table-view-list posts">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Old available quantity</th>
                        <th>New available quantity</th>
                    </tr>
                </thead>
                <tbody>
            <?php 
            $i = true === $i ? false : $i;
            } 
            ?>
                <tr>
                    <th colspan="3"><strong><?= $action ?></strong> — by <?= $user . " — on " . $time ?></th>
                </tr>
            <?php

            foreach ($data as $product_id => $stock) {
                $product = wc_get_product( $product_id );
                $name    = ! $product ? "" : $product->get_name();
                
                ?>
                <tr>
                    <td><?= $name ?> (<?= $product_id ?>)</td>
                    <td><?= $stock['post_old'] ?? "" ?></td>
                    <td><?= $stock['post_new'] ?? "" ?></td>
                </tr>
                <?php
            }  
        }    

        if (! $i) ?></tbody></table><?php
    }

    /**
     * Post.php Markup 
     * 
     * @param WP_Post object $post
     */
    public static function productsAppRoot( $post ): void
    {
        // Add a nonce field to check on save
        wp_nonce_field( basename( __FILE__ ), 'om-rs-data');

		?>
		<div id="products-root"></div>
		<?php
    }

    /**
     * Save Post 
     * 
     * @param int $post_id
     * @param $post
     */
    public static function savePost( int $post_id, $post ): void
    {
        /***********************************
         *  Safety checks
         ***********************************/ 
        // Verify the nonce before proceeding | Bail if user doesn't have permission to edit the post | Bail if this is an Ajax request, autosave or revision
        if (
            ! isset( $_POST['om-rs-data'] ) || 
            ! wp_verify_nonce( $_POST['om-rs-data'], basename( __FILE__ ) ) ||
            ! current_user_can( 'edit_post', $post_id ) ||
            wp_doing_ajax() || 
            wp_is_post_autosave( $post_id) || 
            wp_is_post_revision( $post_id ) ||
            ! isset($_POST['omom_products_loaded'])
        ) return;

        // If stock in pre-order has changed
        /* from WC : Handle stock changes on past save
		if ( isset( $_POST['_stock'] ) ) {
			if ( isset( $_POST['_original_stock'] ) && wc_stock_amount( $product->get_stock_quantity( 'edit' ) ) !== wc_stock_amount( wp_unslash( $_POST['_original_stock'] ) ) ) {
				// translators: 1: product ID 2: quantity in stock
				WC_Admin_Meta_Boxes::add_error( 
                    sprintf( 
                        __( 
                            'The stock has not been updated because the value has changed since editing. Product %1$d has %2$d units in stock.', 
                            'woocommerce' 
                        ), 
                        $product->get_id(), 
                        $product->get_stock_quantity( 'edit' ) 
                    ) 
                );
			} else {
				$stock = wc_stock_amount( wp_unslash( $_POST['_stock'] ) );
			}
        } */
        // Check if there is a pre-order with the same date

        $new_values = Helpers::mapDeep( $_POST );

        // fix: last product is an empty entry
        if (
            ! empty( $new_values['rs_products'] ) && 
            empty( $new_values['rs_products'][array_key_last( $new_values['rs_products'] )]['ID'] )
        ) {
            array_pop( $new_values['rs_products'] );
        }

        (new ShipmentHandler( $post_id ))->save( $new_values );
    }
}