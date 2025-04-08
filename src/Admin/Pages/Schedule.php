<?php
/**
 * The schedule of when products are to arrive 
 * and how many are shipped in the Shipments
 * Can fix pre-order stock issues here
 */
namespace Omom\PreOrders\Admin\Pages;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Omom\PreOrders\Abstracts\Page;

class Schedule extends Page
{
	/**
	 * Slug of the admin page
	 */
	private static string $slug = "schedule";

	/**
	 * Call parent constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Load hooks
	 */
	public static function setHooks(): void
	{
	}

	/**
	 * Enqueue a script in the WordPress admin on edit.php.
	 *
	 * @param int $hook Hook suffix for the current admin page.
	 */
	public static function enqueueAdminScripts( $hook ): void 
	{
		$dir_url = OMOM_PREORDERS()->pluginUrl();
		$asset   = require_once(__DIR__ . '/../../../assets/js/dist/omom-schedule.asset.php');

		wp_enqueue_style( 
			'preorder_admin_styles', 
			$dir_url . '/assets/css/dist/omom-admin.css', 
			[], 
			self::$script_version 
		);

		wp_enqueue_script( 
			'preorder_schedule_scripts', 
			$dir_url . '/assets/js/dist/omom-schedule.js', 
            $asset['dependencies'] ?? [], 
            $asset['version'] ?? self::$script_version, 
			true 
		);

		self::loadAjaxObject( 'preorder_schedule_scripts' );
	}

	/**
     * Add the product info sub menu to WP Admin
	 * 
     * @since 0.0.1
   	 */
 	public static function addSubMenu(): void 
	{
    	add_submenu_page( 
			'edit.php?post_type=restock', 
			'Schedule', 
			'Schedule', 
			'edit_posts', 
			self::$slug,
			[__CLASS__, 'scheduleAppRoot'] 
		);
	}

	/**
	 * Debug: show product-info
	 * 
	 * @since 0.0.1
	 */
	public static function scheduleAppRoot(): void
	{
		?>
		<div id="schedule-root"></div>
		<?php
	}
}