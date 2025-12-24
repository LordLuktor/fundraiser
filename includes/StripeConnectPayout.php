<?php
/**
 * Stripe Connect Payout Handler.
 * Processes instant payouts via Stripe Connect.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * StripeConnectPayout class.
 */
class StripeConnectPayout {

	/**
	 * Process payout via Stripe Connect.
	 *
	 * @param object $payout Payout object from database.
	 * @return bool Success status.
	 */
	public function process_payout( $payout ) {
		global $wpdb;

		// Get payout account
		$account_table = $wpdb->prefix . 'fundraiser_payout_accounts';
		$account = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$account_table} WHERE id = %d",
				$payout->payout_account_id
			)
		);

		if ( ! $account || ! $account->stripe_account_id ) {
			return false;
		}

		// Initialize Stripe (requires Stripe PHP library)
		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			// Stripe library not loaded
			return $this->fallback_to_manual_processing( $payout );
		}

		try {
			// Get Stripe secret key from settings
			$stripe_secret_key = get_option( 'fundraiser_pro_stripe_secret_key' );

			if ( empty( $stripe_secret_key ) ) {
				return $this->fallback_to_manual_processing( $payout );
			}

			\Stripe\Stripe::setApiKey( $stripe_secret_key );

			// Create transfer to connected account
			$transfer = \Stripe\Transfer::create(
				array(
					'amount'      => intval( $payout->amount * 100 ), // Convert to cents
					'currency'    => strtolower( $payout->currency ),
					'destination' => $account->stripe_account_id,
					'metadata'    => array(
						'payout_id'  => $payout->id,
						'vendor_id'  => $payout->vendor_id,
						'order_ids'  => $payout->order_ids,
					),
				)
			);

			// Update payout record with transaction ID
			$payout_table = $wpdb->prefix . 'fundraiser_payouts';
			$wpdb->update(
				$payout_table,
				array(
					'status'         => 'completed',
					'transaction_id' => $transfer->id,
					'processed_at'   => current_time( 'mysql' ),
					'metadata'       => wp_json_encode(
						array(
							'stripe_transfer_id' => $transfer->id,
							'transfer_amount'    => $transfer->amount,
							'destination'        => $transfer->destination,
						)
					),
				),
				array( 'id' => $payout->id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return true;

		} catch ( \Exception $e ) {
			// Log error
			$payout_table = $wpdb->prefix . 'fundraiser_payouts';
			$wpdb->update(
				$payout_table,
				array(
					'status'        => 'failed',
					'error_message' => $e->getMessage(),
				),
				array( 'id' => $payout->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			return false;
		}
	}

	/**
	 * Create Stripe Connect account for vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return string|false Stripe account ID on success, false on failure.
	 */
	public function create_connected_account( $vendor_id ) {
		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			return false;
		}

		$stripe_secret_key = get_option( 'fundraiser_pro_stripe_secret_key' );
		if ( empty( $stripe_secret_key ) ) {
			return false;
		}

		\Stripe\Stripe::setApiKey( $stripe_secret_key );

		$vendor = get_userdata( $vendor_id );
		if ( ! $vendor ) {
			return false;
		}

		try {
			// Create Express account
			$account = \Stripe\Account::create(
				array(
					'type'         => 'express',
					'country'      => 'US',
					'email'        => $vendor->user_email,
					'capabilities' => array(
						'transfers' => array( 'requested' => true ),
					),
					'metadata'     => array(
						'vendor_id' => $vendor_id,
					),
				)
			);

			// Save account ID to payout account record
			global $wpdb;
			$table_name = $wpdb->prefix . 'fundraiser_payout_accounts';

			$wpdb->update(
				$table_name,
				array(
					'stripe_account_id' => $account->id,
					'account_status'    => 'pending_verification',
				),
				array(
					'vendor_id'     => $vendor_id,
					'payout_method' => 'stripe',
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			);

			return $account->id;

		} catch ( \Exception $e ) {
			error_log( 'Stripe Connect account creation failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get Stripe Connect account link for onboarding.
	 *
	 * @param string $account_id Stripe account ID.
	 * @param string $return_url URL to return to after onboarding.
	 * @param string $refresh_url URL to return to if link expires.
	 * @return string|false Account link URL on success, false on failure.
	 */
	public function get_account_link( $account_id, $return_url, $refresh_url ) {
		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			return false;
		}

		$stripe_secret_key = get_option( 'fundraiser_pro_stripe_secret_key' );
		if ( empty( $stripe_secret_key ) ) {
			return false;
		}

		\Stripe\Stripe::setApiKey( $stripe_secret_key );

		try {
			$account_link = \Stripe\AccountLink::create(
				array(
					'account'     => $account_id,
					'refresh_url' => $refresh_url,
					'return_url'  => $return_url,
					'type'        => 'account_onboarding',
				)
			);

			return $account_link->url;

		} catch ( \Exception $e ) {
			error_log( 'Stripe account link creation failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Fallback to manual processing if Stripe fails.
	 *
	 * @param object $payout Payout object.
	 * @return bool Always returns true to mark as pending manual processing.
	 */
	private function fallback_to_manual_processing( $payout ) {
		global $wpdb;

		$payout_table = $wpdb->prefix . 'fundraiser_payouts';
		$wpdb->update(
			$payout_table,
			array(
				'status'   => 'pending_approval',
				'metadata' => wp_json_encode(
					array(
						'requires_manual_processing' => true,
						'reason'                     => 'Stripe not configured or unavailable',
					)
				),
			),
			array( 'id' => $payout->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}
}
