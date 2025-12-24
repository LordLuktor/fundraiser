<?php
/**
 * Weekly Payout Scheduler.
 * Handles scheduled batch payouts (weekly/monthly).
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * WeeklyPayoutScheduler class.
 */
class WeeklyPayoutScheduler {

	/**
	 * Initialize scheduler.
	 */
	public function __construct() {
		// Schedule hourly cron job to check for due payouts
		if ( ! wp_next_scheduled( 'fundraiser_pro_process_scheduled_payouts' ) ) {
			wp_schedule_event( time(), 'hourly', 'fundraiser_pro_process_scheduled_payouts' );
		}

		add_action( 'fundraiser_pro_process_scheduled_payouts', array( $this, 'process_scheduled_payouts' ) );
	}

	/**
	 * Set payout schedule for vendor.
	 *
	 * @param int    $vendor_id    Vendor user ID.
	 * @param string $frequency    Payout frequency (weekly, bi-weekly, monthly).
	 * @param int    $day_of_week  Day of week (1=Monday, 7=Sunday).
	 * @param int    $hour         Hour of day (0-23).
	 * @return bool Success status.
	 */
	public function set_payout_schedule( $vendor_id, $frequency, $day_of_week, $hour ) {
		update_user_meta( $vendor_id, '_payout_schedule_frequency', $frequency );
		update_user_meta( $vendor_id, '_payout_schedule_day', $day_of_week );
		update_user_meta( $vendor_id, '_payout_schedule_hour', $hour );
		update_user_meta( $vendor_id, '_payout_schedule_enabled', true );

		return true;
	}

	/**
	 * Process scheduled payouts.
	 * Called hourly via WP-Cron.
	 */
	public function process_scheduled_payouts() {
		$current_day = intval( date( 'N' ) ); // 1 (Monday) to 7 (Sunday)
		$current_hour = intval( date( 'G' ) ); // 0-23

		// Get all vendors with scheduled payouts
		$vendors = $this->get_vendors_due_for_payout( $current_day, $current_hour );

		foreach ( $vendors as $vendor_id ) {
			$this->process_vendor_payout( $vendor_id );
		}

		// Generate admin report if any payouts were processed
		if ( ! empty( $vendors ) ) {
			$this->send_admin_report( $vendors );
		}
	}

	/**
	 * Get vendors due for payout.
	 *
	 * @param int $day  Current day of week.
	 * @param int $hour Current hour.
	 * @return array Array of vendor IDs.
	 */
	private function get_vendors_due_for_payout( $day, $hour ) {
		global $wpdb;

		// Get all fundraiser/vendor users with scheduled payouts
		$users = get_users(
			array(
				'role'       => 'fundraiser',
				'meta_query' => array(
					array(
						'key'   => '_payout_schedule_enabled',
						'value' => true,
					),
					array(
						'key'   => '_payout_schedule_day',
						'value' => $day,
					),
					array(
						'key'   => '_payout_schedule_hour',
						'value' => $hour,
					),
				),
			)
		);

		$vendor_ids = array();

		foreach ( $users as $user ) {
			// Check if vendor has unpaid balance
			$balance = get_user_meta( $user->ID, '_wcv_unpaid_commission', true );

			if ( floatval( $balance ) > 0 ) {
				$vendor_ids[] = $user->ID;
			}
		}

		return $vendor_ids;
	}

	/**
	 * Process payout for a specific vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return bool Success status.
	 */
	private function process_vendor_payout( $vendor_id ) {
		// Get vendor balance
		$balance = get_user_meta( $vendor_id, '_wcv_unpaid_commission', true );

		if ( floatval( $balance ) <= 0 ) {
			return false;
		}

		// Get vendor's pending commissions (order IDs)
		$commissions = get_user_meta( $vendor_id, '_fundraiser_pending_commissions', true );
		$order_ids = array();

		if ( is_array( $commissions ) ) {
			foreach ( $commissions as $commission ) {
				if ( $commission['status'] === 'pending' ) {
					$order_ids[] = $commission['order_id'];
				}
			}
		}

		// Create payout via PayoutManager
		if ( class_exists( 'FundraiserPro\PayoutManager' ) ) {
			$payout_manager = new PayoutManager();

			// Get vendor's active payout account
			$vendor_integration = new VendorIntegration();
			$payout_method = get_user_meta( $vendor_id, '_preferred_payout_method', true );

			if ( empty( $payout_method ) ) {
				$payout_method = 'venmo'; // Default for scheduled
			}

			$payout_data = array(
				'vendor_id'     => $vendor_id,
				'amount'        => floatval( $balance ),
				'payout_method' => $payout_method,
				'order_ids'     => $order_ids,
				'currency'      => 'USD',
				'metadata'      => array(
					'scheduled_payout' => true,
					'schedule_date'    => current_time( 'mysql' ),
				),
			);

			$payout_id = $payout_manager->process_payout( $payout_data );

			return $payout_id !== false;
		}

		return false;
	}

	/**
	 * Send admin report of scheduled payouts.
	 *
	 * @param array $vendor_ids Array of vendor IDs that were processed.
	 */
	private function send_admin_report( $vendor_ids ) {
		$admin_email = get_option( 'admin_email' );

		$total_amount = 0;
		$payout_details = array();

		foreach ( $vendor_ids as $vendor_id ) {
			$vendor = get_userdata( $vendor_id );
			$balance = get_user_meta( $vendor_id, '_wcv_unpaid_commission', true );

			$payout_details[] = sprintf(
				'%s: $%s',
				$vendor->display_name,
				number_format( floatval( $balance ), 2 )
			);

			$total_amount += floatval( $balance );
		}

		$subject = sprintf(
			__( 'Scheduled Payouts Report - %s', 'fundraiser-pro' ),
			current_time( 'F j, Y' )
		);

		$message = sprintf(
			__( "Scheduled payouts have been processed for %d vendors:\n\n%s\n\nTotal Amount: $%s\n\nPlease review pending manual payments in the admin dashboard.\n\nBest regards,\nFundraiser Pro", 'fundraiser-pro' ),
			count( $vendor_ids ),
			implode( "\n", $payout_details ),
			number_format( $total_amount, 2 )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Get vendor payout schedule.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array Schedule details.
	 */
	public function get_vendor_schedule( $vendor_id ) {
		return array(
			'enabled'   => get_user_meta( $vendor_id, '_payout_schedule_enabled', true ),
			'frequency' => get_user_meta( $vendor_id, '_payout_schedule_frequency', true ),
			'day'       => get_user_meta( $vendor_id, '_payout_schedule_day', true ),
			'hour'      => get_user_meta( $vendor_id, '_payout_schedule_hour', true ),
		);
	}

	/**
	 * Disable payout schedule for vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return bool Success status.
	 */
	public function disable_schedule( $vendor_id ) {
		update_user_meta( $vendor_id, '_payout_schedule_enabled', false );
		return true;
	}
}
