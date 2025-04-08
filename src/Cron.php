<?php
/**
 * Cron Jobs
 */
namespace Omom\PreOrders;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Omom\PreOrders\Handlers\ShipmentHandler;

class Cron
{
    /**
     * The cron hooks
     */
    private static array $hooks = [
        // hook_key => hook_filter
        "release" => 'omnium_cron_restock_arrives', // release shipment
        "errors" => 'omom_cron_check_errors' // check for any errors
    ];

    /**
     * All the hooks
     * 
     * @since 0.0.1
     */
    public static function init(): void
    {
        foreach (self::$hooks as $hook) {
            add_action( $hook, [__CLASS__, 'doCronEvent'], 10, 1 );
        }
    }

    /**
     * Schedule Cron Job
     * 
     * @param string   $hook_key - like release or orphans
     * @param int      $arrival
     * @param array    $args[ $post_id ]
     * 
     * @return bool
     */
    public static function scheduleEvent( string $hook_key, int $arrival, array $args ): bool 
    {
        $hook = self::$hooks[$hook_key] ?? false;

        if (! $hook) return false;

        // check if scheduled event already exists for this post
        if (wp_next_scheduled( $hook, $args )) {
            wp_clear_scheduled_hook( $hook, $args );
        }

        $scheduled = wp_schedule_single_event( $arrival, $hook, $args );

        return is_wp_error( $scheduled ) ? false : $scheduled;
    }

    /**
     * Cron event – remove stock from pre-order post meta and update product stock
     * 
     * @since 0.0.1
     * @param int $post_id
     * @param bool $auto
     */
    public static function doCronEvent( $post_id, $auto = false ): void 
    {
        switch (current_filter()) {
            case 'omnium_cron_restock_arrives':
                (new ShipmentHandler( $post_id ))->release( 'Automatically added to stock' );
                break;
            case 'omom_cron_check_errors':
                new FixErrors();
                break;
        }
    }

    /**
     * Remove cron task
     * 
     * @since 0.0.1
     * @param array  $args
     */
    public static function removeEvent( $hook_key, $args ): void
    {
        $hook = self::$hooks[$hook_key] ?? false;

        if ($hook) wp_clear_scheduled_hook( $hook, $args );
    }  
}
