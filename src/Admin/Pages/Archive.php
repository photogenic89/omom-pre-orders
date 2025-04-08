<?php
/**
 * edit post
 */
namespace Omom\PreOrders\Admin\Pages;

defined( 'ABSPATH' ) || die;

use Omom\PreOrders\Abstracts\Page;
use Omom\PreOrders\Handlers\ShipmentHandler;

class Archive extends Page 
{
	/**
	 * The version of the scripts on this page
	 */
	protected static $script_version = '1.0.0';

	/**
	 * Call parent constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Enqueue admin scripts
	 */
	public static function enqueueAdminScripts( $hook )
	{
    }

    /**
     * 
     */
    public static function setHooks()
    {
        add_filter( 'the_title',                          [__CLASS__, 'singlePostArchiveTitle'], 10, 2 );
        add_filter( 'manage_restock_posts_columns',       [__CLASS__, 'postsColumns'] );
        add_action( 'manage_restock_posts_custom_column', [__CLASS__, 'postsCustomColumn'], 10, 2);
		add_filter( 'bulk_actions-edit-restock', 	      [__CLASS__, 'addDeleteAction'] );
        add_filter( 'post_row_actions',                   [__CLASS__, 'addRowDeleteAction'], 10, 2 );
    }

    /**
     * Add Archive Pre-Order Title
     * 
     * @since  0.0.1
     * @param  string $title
     * @param  num $post_id
     * 
     * @return string
     */
    public static function singlePostArchiveTitle( $title, $post_id ): string
    {
        if ('restock' !== get_post_type( $post_id )) return $title;

        return 'Auto Draft' === $title ? 'Pre-Order #' . $post_id : "#" . $post_id . ($title ? ": " . $title : "");
    }
    


        /**
     * Add metafield to columns
     * 
     * @since  0.0.1
     * @param  array $post_columns
     * 
     * @return array
     */
    public static function postsColumns( $post_columns ): array
    {
        unset( $post_columns['date'] );

        $post_columns['container']    = 'Container Name';
        $post_columns['arrival_date'] = 'Arrival Date';
        $post_columns['status']       = 'Status';

        return $post_columns;
    }

    /**
     * Add arrival date
     * 
     * @since 0.0.1
     * @param string $column_name
     * @param int    $post_id
     */
    public static function postsCustomColumn( $column_name, $post_id ): void 
    {
        $shipment = new ShipmentHandler( $post_id );

        switch ( $column_name ) {

            case 'container' :
                echo $shipment->getContainerID() ?: " — ";
                break;

            case 'status' :

                switch ($shipment->getStatus()) {
                    case 'draft':
                        $status = '<span style="color:darkorange">Draft</span>'; 
                        break;
                    case 'received':
                        $status = '<span style="color:pink">Received</span>';
                        break;
                    case 'released':
                        $status = '<span style="color:red">Released</span>';
                        break;
                    default:
                        $status = '<span style="color:green">Active</span>';
                }
                echo $status;
                break;

            case 'arrival_date' :
                echo $shipment->getArrival( 2 ) ?: 'No date specified yet';
                break;
        }
    }

    /**
     * Add a delete action and remove the trash action. 
     * because trashing an order is too much trouble
     * 
     * @param array $actions
     * 
     * @return array
     */
    public static function addDeleteAction( array $actions ): array
	{
        $actions['trash'] = 'Delete permanently';
		return $actions;
	}

    /**
     * Add a delete action and remove the trash action. 
     * because trashing an order is too much trouble
     * 
     * @param array $actions
     * 
     * @return array
     */
    public static function addRowDeleteAction( array $actions, $post ): array
	{
        if ("restock" === $post->post_type) {
            $actions['trash'] = sprintf(		
                '<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
                get_delete_post_link( $post->ID ),
                /* translators: %s: Post title. */
                esc_attr( 
                    sprintf( 
                        __( 'Delete &#8220;%s&#8221; permanently' ), 
                        _draft_or_post_title() 
                    ) 
                ),
                _x( 'Delete Permanently', 'verb' )
            );
        }

		return $actions;
	}
}