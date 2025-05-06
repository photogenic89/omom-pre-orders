<?php
/**
 * Settings
 */
namespace Omom\PreOrders\Admin\Pages;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Omom\PreOrders\Abstracts\Page;

class Settings extends Page
{
	/**
	 * Slug of the admin page
	 */
	private static string $slug = "settings";

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
		$asset   = require_once(__DIR__ . '/../../../assets/js/dist/omom-settings.asset.php');
		$script  = 'preorder_settings_scripts';

		wp_enqueue_style( 
			'preorder_admin_styles', 
			$dir_url . '/assets/css/dist/omom-admin.css', 
			[], 
			self::$script_version 
		);

		wp_register_script( 
			$script, 
			$dir_url . '/assets/js/dist/omom-settings.js', 
            $asset['dependencies'] ?? [], 
            $asset['version'] ?? self::$script_version, 
			true 
		);

		// Force script to load in footer
		wp_scripts()->add_data( $script, 'group', 1 );
		wp_enqueue_script( $script );

		self::loadAjaxObject( $script );
	}

	/**
     * Add the settings menu to WP Admin
   	 */
 	public static function addSubMenu(): void 
	{
    	add_submenu_page( 
			'edit.php?post_type=restock', 
			'Settings', 
			'Settings', 
			'edit_posts', 
			self::$slug,
			[__CLASS__, 'settingsPageApp'] 
		);
	}

	/**
	 * The root of the settings page app
	 */
	public static function settingsPageApp(): void
	{
		?>
		<div id="settings-root"></div>
		<?php
	}
}