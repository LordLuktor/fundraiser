<?php
/**
 * Payout Manager for Fundraiser Pro.
 * Handles payout account management and payout processing.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * PayoutManager class.
 */
class PayoutManager {

	/**
	 * Payout methods.
	 */
	const METHOD_STRIPE = 'stripe';
	const METHOD_PAYPAL = 'paypal';
	const METHOD_BANK = 'bank_transfer';
	const METHOD_VENMO = 'venmo';
	const METHOD_CASHAPP = 'cashapp';

	/**
	 * Payout status.
	 */
	const STATUS_PENDING = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED = 'failed';
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Create or update payout account for vendor.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param string $method    Payout method.
	 * @param array  $account_data Account details (encrypted).
	 * @return int|false Account ID on success, false on failure.
	 */
	public function create_payout_account( $vendor_id, $method, $account_data ) {
		global $wpdb;

		// Validate payout method
		$valid_methods = array( self::METHOD_STRIPE, self::METHOD_PAYPAL, self::METHOD_BANK, self::METHOD_VENMO, self::METHOD_CASHAPP );
		if ( ! in_array( $method, $valid_methods, true ) ) {
			return false;
		}

		// Encrypt account data
		$encrypted_data = $this->encrypt_account_data( $account_data );

		$table_name = $wpdb->prefix . 'fundraiser_payout_accounts';

		// Check if account already exists
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE vendor_id = %d AND payout_method = %s AND is_active = 1",
				$vendor_id,
				$method
			)
		);

		if ( $existing ) {
			// Update existing account
			$wpdb->update(
				$table_name,
				array(
					'account_data'   => $encrypted_data,
					'account_status' => 'pending',
					'updated_at'     => current_time( 'mysql' ),
				),
				array(
					'id' => $existing->id,
				),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			return $existing->id;
		}

		// Create new account
		$result = $wpdb->insert(
			$table_name,
			array(
				'vendor_id'      => $vendor_id,
				'payout_method'  => $method,
				'account_data'   => $encrypted_data,
				'account_status' => 'pending',
				'is_active'      => 1,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( $result === false ) {
			return false;
		}

		$account_id = $wpdb->insert_id;

		// Log account creation
		do_action( 'fundraiser_pro_payout_account_created', $account_id, $vendor_id, $method );

		return $account_id;
	}

	/**
	 * Get payout account for vendor.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param string $method    Optional payout method filter.
	 * @return object|null Account object or null.
	 */
	public function get_payout_account( $vendor_id, $method = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fundraiser_payout_accounts';

		if ( $method ) {
			$account = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE vendor_id = %d AND payout_method = %s AND is_active = 1 ORDER BY created_at DESC LIMIT 1",
					$vendor_id,
					$method
				)
			);
		} else {
			$account = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE vendor_id = %d AND is_active = 1 ORDER BY created_at DESC LIMIT 1",
					$vendor_id
				)
			);
		}

		if ( ! $account ) {
			return null;
		}

		// Decrypt account data
		$account->account_data_decrypted = $this->decrypt_account_data( $account->account_data );

		return $account;
	}

	/**
	 * Get all payout accounts for vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array Array of account objects.
	 */
	public function get_all_payout_accounts( $vendor_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fundraiser_payout_accounts';

		$accounts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE vendor_id = %d AND is_active = 1 ORDER BY created_at DESC",
				$vendor_id
			)
		);

		// Decrypt account data for each
		foreach ( $accounts as $account ) {
			$account->account_data_decrypted = $this->decrypt_account_data( $account->account_data );
		}

		return $accounts;
	}

	/**
	 * Process payout for vendor.
	 *
	 * @param array $payout_data {
	 *     Payout data array.
	 *     @type int    $vendor_id         Vendor user ID.
	 *     @type float  $amount            Payout amount.
	 *     @type string $payout_method     Payout method.
	 *     @type int    $payout_account_id Payout account ID.
	 *     @type array  $order_ids         Array of order IDs included in payout.
	 * }
	 * @return int|false Payout ID on success, false on failure.
	 */
	public function process_payout( $payout_data ) {
		global $wpdb;

		// Validate required fields
		if ( empty( $payout_data['vendor_id'] ) || empty( $payout_data['amount'] ) ) {
			return false;
		}

		$vendor_id = intval( $payout_data['vendor_id'] );
		$amount = floatval( $payout_data['amount'] );

		// Check if vendor has sufficient balance
		if ( class_exists( 'FundraiserPro\VendorIntegration' ) ) {
			$vendor_integration = new VendorIntegration();
			$balance = $vendor_integration->get_vendor_balance( $vendor_id );

			if ( $balance < $amount ) {
				return false; // Insufficient balance
			}
		}

		// Get payout account
		$account = $this->get_payout_account( $vendor_id, $payout_data['payout_method'] ?? '' );
		if ( ! $account ) {
			return false;
		}

		// Create payout record
		$table_name = $wpdb->prefix . 'fundraiser_payouts';

		$result = $wpdb->insert(
			$table_name,
			array(
				'vendor_id'         => $vendor_id,
				'payout_account_id' => $account->id,
				'amount'            => $amount,
				'currency'          => $payout_data['currency'] ?? 'USD',
				'payout_method'     => $account->payout_method,
				'status'            => self::STATUS_PENDING,
				'order_ids'         => isset( $payout_data['order_ids'] ) ? wp_json_encode( $payout_data['order_ids'] ) : null,
				'metadata'          => isset( $payout_data['metadata'] ) ? wp_json_encode( $payout_data['metadata'] ) : null,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return false;
		}

		$payout_id = $wpdb->insert_id;

		// Process payout based on method
		$this->execute_payout( $payout_id );

		return $payout_id;
	}

	/**
	 * Execute payout based on method.
	 *
	 * @param int $payout_id Payout ID.
	 * @return bool Success status.
	 */
	private function execute_payout( $payout_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fundraiser_payouts';

		$payout = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$payout_id
			)
		);

		if ( ! $payout ) {
			return false;
		}

		// Update status to processing
		$wpdb->update(
			$table_name,
			array( 'status' => self::STATUS_PROCESSING ),
			array( 'id' => $payout_id ),
			array( '%s' ),
			array( '%d' )
		);

		$success = false;

		try {
			switch ( $payout->payout_method ) {
				case self::METHOD_STRIPE:
					if ( class_exists( 'FundraiserPro\StripeConnectPayout' ) ) {
						$stripe_payout = new StripeConnectPayout();
						$success = $stripe_payout->process_payout( $payout );
					}
					break;

				case self::METHOD_PAYPAL:
					// PayPal integration (future implementation)
					$success = $this->process_paypal_payout( $payout );
					break;

				case self::METHOD_BANK:
				case self::METHOD_VENMO:
				case self::METHOD_CASHAPP:
					// Manual payment methods - mark as pending admin approval
					$wpdb->update(
						$table_name,
						array(
							'status' => 'pending_approval',
							'metadata' => wp_json_encode( array( 'requires_manual_processing' => true ) ),
						),
						array( 'id' => $payout_id ),
						array( '%s', '%s' ),
						array( '%d' )
					);
					$success = true;
					break;
			}

			if ( $success ) {
				// Update vendor balance
				if ( class_exists( 'FundraiserPro\VendorIntegration' ) ) {
					$vendor_integration = new VendorIntegration();
					$vendor_integration->clear_vendor_balance( $payout->vendor_id, $payout->amount );
				}

				// Send notification
				$this->send_payout_notification( $payout_id, 'completed' );

				do_action( 'fundraiser_pro_payout_completed', $payout_id, $payout );
			} else {
				// Mark as failed
				$wpdb->update(
					$table_name,
					array(
						'status'        => self::STATUS_FAILED,
						'error_message' => 'Payout processing failed',
					),
					array( 'id' => $payout_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);

				$this->send_payout_notification( $payout_id, 'failed' );

				do_action( 'fundraiser_pro_payout_failed', $payout_id, $payout );
			}
		} catch ( \Exception $e ) {
			// Log error
			$wpdb->update(
				$table_name,
				array(
					'status'        => self::STATUS_FAILED,
					'error_message' => $e->getMessage(),
				),
				array( 'id' => $payout_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$this->send_payout_notification( $payout_id, 'failed' );

			return false;
		}

		return $success;
	}

	/**
	 * Process PayPal payout (placeholder).
	 *
	 * @param object $payout Payout object.
	 * @return bool Success status.
	 */
	private function process_paypal_payout( $payout ) {
		// PayPal API integration would go here
		// For now, mark as pending manual processing
		global $wpdb;

		$table_name = $wpdb->prefix . 'fundraiser_payouts';

		$wpdb->update(
			$table_name,
			array(
				'status'   => 'pending_approval',
				'metadata' => wp_json_encode( array( 'requires_manual_processing' => true ) ),
			),
			array( 'id' => $payout->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Get payout history for vendor.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param string $status    Optional status filter.
	 * @param int    $limit     Number of results.
	 * @return array Array of payout objects.
	 */
	public function get_payout_history( $vendor_id, $status = '', $limit = 50 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fundraiser_payouts';

		if ( $status ) {
			$payouts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE vendor_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d",
					$vendor_id,
					$status,
					$limit
				)
			);
		} else {
			$payouts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE vendor_id = %d ORDER BY created_at DESC LIMIT %d",
					$vendor_id,
					$limit
				)
			);
		}

		return $payouts;
	}

	/**
	 * Encrypt account data.
	 *
	 * @param array $data Account data to encrypt.
	 * @return string Encrypted data.
	 */
	private function encrypt_account_data( $data ) {
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'SECURE_AUTH_KEY' ) ) {
			return wp_json_encode( $data ); // Fallback if keys not defined
		}

		$json = wp_json_encode( $data );

		$encrypted = openssl_encrypt(
			$json,
			'AES-256-CBC',
			AUTH_KEY,
			0,
			substr( SECURE_AUTH_KEY, 0, 16 )
		);

		return base64_encode( $encrypted );
	}

	/**
	 * Decrypt account data.
	 *
	 * @param string $encrypted_data Encrypted data.
	 * @return array Decrypted data array.
	 */
	private function decrypt_account_data( $encrypted_data ) {
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'SECURE_AUTH_KEY' ) ) {
			return json_decode( $encrypted_data, true ); // Fallback
		}

		$encrypted = base64_decode( $encrypted_data );

		$decrypted = openssl_decrypt(
			$encrypted,
			'AES-256-CBC',
			AUTH_KEY,
			0,
			substr( SECURE_AUTH_KEY, 0, 16 )
		);

		return json_decode( $decrypted, true );
	}

	/**
	 * Send payout notification email.
	 *
	 * @param int    $payout_id Payout ID.
	 * @param string $status    Payout status.
	 */
	private function send_payout_notification( $payout_id, $status ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fundraiser_payouts';

		$payout = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$payout_id
			)
		);

		if ( ! $payout ) {
			return;
		}

		$vendor = get_userdata( $payout->vendor_id );
		if ( ! $vendor ) {
			return;
		}

		$to = $vendor->user_email;

		if ( 'completed' === $status ) {
			$subject = sprintf( __( 'Payment of $%s sent', 'fundraiser-pro' ), number_format( $payout->amount, 2 ) );
			$message = sprintf(
				__( "Hi %s,\n\nYour payout of $%s has been successfully processed via %s.\n\nTransaction ID: %s\n\nThank you for using our platform!\n\nBest regards,\nThe Fundraiser Team", 'fundraiser-pro' ),
				$vendor->display_name,
				number_format( $payout->amount, 2 ),
				ucfirst( str_replace( '_', ' ', $payout->payout_method ) ),
				$payout->transaction_id ?? 'N/A'
			);
		} else {
			$subject = sprintf( __( 'Payout of $%s failed', 'fundraiser-pro' ), number_format( $payout->amount, 2 ) );
			$message = sprintf(
				__( "Hi %s,\n\nUnfortunately, your payout of $%s could not be processed.\n\nReason: %s\n\nPlease contact support or update your payout account details.\n\nBest regards,\nThe Fundraiser Team", 'fundraiser-pro' ),
				$vendor->display_name,
				number_format( $payout->amount, 2 ),
				$payout->error_message ?? 'Unknown error'
			);
		}

		wp_mail( $to, $subject, $message );
	}
}
