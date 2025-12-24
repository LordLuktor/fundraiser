<?php
/**
 * Fired during plugin deactivation.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * Deactivator class.
 */
class Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		// Clear scheduled cron jobs
		$cron_hooks = array(
			'fundraiser_pro_daily_analytics',
			'fundraiser_pro_raffle_draws',
			'fundraiser_pro_abandoned_cart_emails',
			'fundraiser_pro_milestone_check',
		);

		foreach ( $cron_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}

		// Flush rewrite rules
		flush_rewrite_rules();

		// Note: We don't remove roles, capabilities, or database tables
		// This allows for reactivation without data loss
		// Uninstall.php will handle complete removal if needed
	}
}
