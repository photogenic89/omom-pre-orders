<?php
/**
 * Init
 * Register custom post type and hidden Taxonomy
 */
namespace Omom\PreOrders;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class Init 
{
    /**
     * Load hooks
     */
    public static function init(): void
    { 
        add_action( 
            'init', 
            [__CLASS__, 'register'], 
            999 
        );
    }

    /**
     * Register post-type taxonomy and post status
     */
    public static function register(): void
    {
        self::registerPostType();
        self::registerPreOrderTaxonomy();
        // self::registerPostStatus();
        StoreApi::registerEndpoint();
    }
    
    /**
     * Register the Pre-Orders CPT
     * 
     * @todo rename to omom_shipment - this is what they are used as, so shipment
     */
    public static function registerPostType(): void 
    {
        $text_domain = OMOM_PREORDERS()->text_domain;
        $labels = [
            'name'                  => _x( 'Pre-Order Shipments', 'Post Type General Name', $text_domain ),
            'singular_name'         => _x( 'Pre-Order', 'Post Type Singular Name', $text_domain ),
            'menu_name'             => __( 'Pre-Orders', $text_domain ),
            'name_admin_bar'        => __( 'Pre-Orders', $text_domain ),
            'archives'              => __( 'Pre-Order Archives', $text_domain ),
            'attributes'            => __( 'Pre-Order Attributes', $text_domain ),
            'parent_item_colon'     => __( 'Parent Item:', $text_domain ),
            'all_items'             => __( 'All Shipments', $text_domain ),
            'add_new_item'          => __( 'Add New Shipment', $text_domain ),
            'add_new'               => __( 'Add New', $text_domain ),
            'new_item'              => __( 'New Shipment', $text_domain ),
            'edit_item'             => __( 'Edit Shipment', $text_domain ),
            'update_item'           => __( 'Update Shipment', $text_domain ),
            'search_items'          => __( 'Search Shipment', $text_domain ),
            'not_found'             => __( 'Not found', $text_domain ),
            'not_found_in_trash'    => __( 'Not found in Trash', $text_domain ),
            'insert_into_item'      => __( 'Insert into item', $text_domain ),
            'uploaded_to_this_item' => __( 'Uploaded to this item', $text_domain ),
            'items_list'            => __( 'Items list', $text_domain ),
            'items_list_navigation' => __( 'Items list navigation', $text_domain ),
            'filter_items_list'     => __( 'Filter items list', $text_domain ),
        ];
        $args = [
            'label'                 => __( 'Pre-Orders', $text_domain ),
            'description'           => __( 'Add new stock', $text_domain ),
            'labels'                => $labels,
            'supports'              => [ 'title' ],
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'show_in_admin_bar'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'publicly_queryable'    => true,
            'menu_icon'             => 'dashicons-update',
            'capability_type'       => 'page',
        ];
        
        register_post_type( 'restock', $args );
        flush_rewrite_rules();
    }

    /**
     * Register custom taxonomy Restocked Products
     * 
     * @todo rename to omom_shipping_products
     */
    public static function registerPreOrderTaxonomy(): void
    {
        // Taxonomy arguments
        $args = [
            'public'            => true,
            'show_ui'           => false,
            'show_in_nav_menus' => false,
            'show_admin_column' => false,
            'meta_box_cb'       => false,
            'query_var'         => 'restocked_products',

            // The rewrite handles the URL structure
            'rewrite' => [
                'slug'         => 'restocked_products',
                'with_front'   => false,
                'hierarchical' => false,
                'ep_mask'      => \EP_NONE
            ],

            // Text labels
            'labels' => [
                'name'                  => 'Products in Shipments',
                'singular_name'         => 'Product in Shipments',
                'menu_name'             => 'Products in Shipments',
                'name_admin_bar'        => 'Product in Shipments',
                'search_items'          => 'Search Products in Shipments',
                'popular_items'         => 'Popular Products in Shipments',
                'all_items'             => 'All Products in Shipments',
                'edit_item'             => 'Edit Product in Shipments',
                'view_item'             => 'View Product in Shipments',
                'update_item'           => 'Update Product in Shipments',
                'add_new_item'          => 'Add New Product in Shipments',
                'new_item_name'         => 'New Product in Shipments',
                'not_found'             => 'No products in shipments found',
                'no_terms'              => 'No products in shipments',
                'items_list_navigation' => 'Products in Shipments list navigation',
                'items_list'            => 'Products list'
            ]
        ];

        register_taxonomy( 'restocked_products', 'restock', $args );
    }

    /**
     * Register post status internal
     * 
     * @todo figure out if necessary
     */
    public static function registerPostStatus(): void
    {
        register_post_status( 'internal', [
            'label'                     => _x( 'Internal', 'post' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Internal <span class="count">(%s)</span>', 'Internal <span class="count">(%s)</span>' ),
        ]);
    }  
}