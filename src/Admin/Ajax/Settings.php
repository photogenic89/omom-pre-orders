<?php
/**
 * All Ajax calls for the settings page
 */

namespace Omom\PreOrders\Admin\Ajax;

defined( 'ABSPATH' ) || die;

use Omom\PreOrders\FixErrors;
use Automattic\WooCommerce\Utilities\LoggingUtil;

class Settings 
{
	/**
	 * 
	 */
	public function getSettings(): void
	{
		$settings = [
			[
				"type"      => "checkbox",
				"id" 		=> "omom_pre_order_only_dates",
				"isChecked" => "true" === get_option( 'omom_pre_order_only_dates' ),
				"label"     => "Show only pre-order dates and don't use for stock validation. (saves on click)"
			]
		];

		// Check if WooCommerce's logging feature is enabled.
		if (class_exists( '\Automattic\WooCommerce\Utilities\LoggingUtil' ) && LoggingUtil::logging_is_enabled()) {
			// Add the logging option to the form fields.
			$settings[] = [
				"type"      => "checkbox",
				"id" 		=> "omom_pre_order_enable_logging",
				"isChecked" => "true" === get_option( 'omom_pre_order_enable_logging' ),
				"label"     => "Enable logging"
			];
		}

		$settings[] = [
			"type"   => "button",
			"method" => "fixErrors",
			"label"  => "Fix errors"
		];

		wp_send_json_success([
			'settings' => $settings
		]);
	}

	/**
	 * Checkbox clicked
	 * 
	 * @param array $data
	 */
	public function checkboxChanged( array $data ): void
	{
		$id = 0;
		$checked = sanitize_text_field( $_POST['isChecked'] ?? false );
		$checked ? update_option( $id, $checked ) : delete_option( $id );
		wp_send_json_success( 'checkbox_saved' );
	}

	/**
	 * 
	 */
	public function fixErrors()
	{
		new FixErrors();
		wp_send_json_success( 'checked for errors' );
	}
}