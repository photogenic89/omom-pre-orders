<?php
/**
 * Log
 *
 * @author   Studio Koepfchen <info@studiokoepfchen.com>
 * @package  Omom Pre-Orders
 * @since    3.4.0
 */
namespace Omom\PreOrders;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

/**
 * One consistent interface for the Monolog API
 *
 * @class    Log
 * @version  3.4.0
 */
class Log
{
	/**
	 * The directory
	 */
	protected $dir = '';

	/**
	 * When class is instantiated
	 */
    public function __construct() 
    {
		// Get WordPress uploads directory.
		$upload_dir = wp_upload_dir();
		$upload_dir = $upload_dir['basedir'] . '/restock-logs/';
		
		// restock-logs sub dir exists
		if( file_exists($upload_dir) ) {
			$this->dir = $upload_dir;
			return;
		}

		// make restock-logs sub dir
		$make = mkdir($upload_dir, 0777, true);
	}

	/**
	 * Log when stock has changed for a pre-order post
	 * 
	 * @param string $pre_order_id
	 * @param array  $data
	 * @param string $message
	 */
	public function logStockChange( $pre_order_id = '', $data = [], $message = '' ): void
	{
		// Add handler and formatter
		$dir 	= $this->dir . $pre_order_id . '.log';
		$stream = new StreamHandler( $dir, Logger::INFO );
		$stream->setFormatter( new JsonFormatter() );
		
		// Create the logger
		$logger = new Logger( (string) $pre_order_id );
		$logger->pushHandler( $stream );
		$logger->pushProcessor(function ( $record ) {
			$user = wp_get_current_user() ?? get_current_user();
			$record['user'] = $user->display_name ?? "system";
			return $record;
		});

		// Add data
		$logger->info( $message, $data );
	}
}