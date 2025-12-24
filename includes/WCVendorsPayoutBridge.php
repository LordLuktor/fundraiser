<?php
/**
 * WC Vendors Payout Bridge.
 * Integrates custom payout automation with WC Vendors withdrawal system.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * WCVendorsPayoutBridge class.
 */
class WCVendorsPayoutBridge {

	/**
	 * Initialize bridge.
	 */
	public function __construct() {
		// Hook into WC Vendors commission payment
		add_action( 'wcvendors_commission_paid', array( $this, 'process_commission_payment' ), 10, 2 );

		// Hook into admin withdrawal approval (if available)
		add_action( 'wcvendors_vendor_ship', array( $this, 'maybe_automate_payout' ), 10, 1 );

		// Add Stripe Connect option to vendor settings
		add_action( 'wcvendors_settings_after_paypal', array( $this, 'add_stripe_connect_option' ) );
		add_action( 'wcvendors_shop_settings_saved', array( $this, 'save_stripe_settings' ) );
	}

	/**
	 * Process commission payment via our custom system.
	 *
	 * @param int   $commission_id Commission ID.
	 * @param array $commission_data Commission data.
	 */
	public function process_commission_payment( $commission_id, $commission_data ) {
		if ( ! isset( $commission_data['vendor_id'] ) || ! isset( $commission_data['total_due'] ) ) {
			return;
		}

		$vendor_id = $commission_data['vendor_id'];
		$amount = floatval( $commission_data['total_due'] );

		// Check if vendor has Stripe Connect enabled
		$stripe_enabled = get_user_meta( $vendor_id, '_wcv_stripe_connect_enabled', true );

		if ( $stripe_enabled && class_exists( 'FundraiserPro\StripeConnectPayout' ) ) {
			// Automatic Stripe payout
			$this->process_stripe_payout( $vendor_id, $amount, $commission_id );
		} else {
			// Mark for manual payment
			update_post_meta( $commission_id, '_requires_manual_payment', true );
			update_post_meta( $commission_id, '_manual_payment_method', get_user_meta( $vendor_id, '_wcv_payment_method', true ) );

			// Notify admin
			$this->notify_admin_manual_payment( $vendor_id, $amount );
		}
	}

	/**
	 * Process Stripe Connect payout.
	 *
	 * @param int    $vendor_id     Vendor user ID.
	 * @param float  $amount        Amount to pay.
	 * @param int    $commission_id Commission ID.
	 * @return bool Success status.
	 */
	private function process_stripe_payout( $vendor_id, $amount, $commission_id ) {
		if ( ! class_exists( 'FundraiserPro\PayoutManager' ) ) {
			return false;
		}

		$payout_manager = new PayoutManager();

		// Check if vendor has Stripe account
		$stripe_account = $payout_manager->get_payout_account( $vendor_id, 'stripe' );

		if ( ! $stripe_account || $stripe_account->account_status !== 'verified' ) {
			// Stripe not set up, fall back to manual
			return false;
		}

		// Create payout
		$payout_data = array(
			'vendor_id'         => $vendor_id,
			'amount'            => $amount,
			'payout_method'     => 'stripe',
			'payout_account_id' => $stripe_account->id,
			'metadata'          => array(
				'commission_id' => $commission_id,
				'source'        => 'wcvendors_auto',
			),
		);

		$payout_id = $payout_manager->process_payout( $payout_data );

		if ( $payout_id ) {
			// Mark commission as paid via automation
			update_post_meta( $commission_id, '_automated_payout_id', $payout_id );
			update_post_meta( $commission_id, '_payout_method', 'stripe_connect' );
			return true;
		}

		return false;
	}

	/**
	 * Notify admin about manual payment needed.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param float $amount    Amount to pay.
	 */
	private function notify_admin_manual_payment( $vendor_id, $amount ) {
		$vendor = get_userdata( $vendor_id );
		$payment_method = get_user_meta( $vendor_id, '_wcv_payment_method', true );

		$admin_email = get_option( 'admin_email' );
		$subject = sprintf( __( 'Manual Payment Required: $%s for %s', 'fundraiser-pro' ), number_format( $amount, 2 ), $vendor->display_name );

		$message = sprintf(
			__( "A manual payment is required:\n\nVendor: %s\nAmount: $%s\nPayment Method: %s\n\nPlease process this payment manually.\n\nView vendor details: %s", 'fundraiser-pro' ),
			$vendor->display_name,
			number_format( $amount, 2 ),
			ucfirst( str_replace( '_', ' ', $payment_method ) ),
			admin_url( 'user-edit.php?user_id=' . $vendor_id )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Add Stripe Connect option to vendor settings page.
	 */
	public function add_stripe_connect_option() {
		$vendor_id = get_current_user_id();
		$stripe_enabled = get_user_meta( $vendor_id, '_wcv_stripe_connect_enabled', true );
		$stripe_account = null;

		if ( $stripe_enabled && class_exists( 'FundraiserPro\PayoutManager' ) ) {
			$payout_manager = new PayoutManager();
			$stripe_account = $payout_manager->get_payout_account( $vendor_id, 'stripe' );
		}

		?>
		<h2><?php esc_html_e( 'Stripe Connect (Instant Payouts)', 'fundraiser-pro' ); ?></h2>
		<p><?php esc_html_e( 'Connect your bank account via Stripe for instant automatic payouts when commissions are due.', 'fundraiser-pro' ); ?></p>

		<?php if ( $stripe_account && $stripe_account->account_status === 'verified' ) : ?>
			<div class="notice notice-success inline">
				<p><?php esc_html_e( 'Your Stripe account is connected and verified!', 'fundraiser-pro' ); ?></p>
			</div>
			<p>
				<label>
					<input type="checkbox" name="_wcv_stripe_connect_enabled" value="1" <?php checked( $stripe_enabled, true ); ?>>
					<?php esc_html_e( 'Enable automatic Stripe payouts', 'fundraiser-pro' ); ?>
				</label>
			</p>
		<?php elseif ( $stripe_account ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'Your Stripe account is pending verification. Please complete the setup process.', 'fundraiser-pro' ); ?></p>
			</div>
			<p>
				<a href="<?php echo esc_url( $this->get_stripe_onboarding_url( $vendor_id ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Complete Stripe Setup', 'fundraiser-pro' ); ?>
				</a>
			</p>
		<?php else : ?>
			<p>
				<a href="<?php echo esc_url( $this->get_stripe_onboarding_url( $vendor_id ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Connect Stripe Account', 'fundraiser-pro' ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Get Stripe Connect onboarding URL.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return string Onboarding URL.
	 */
	private function get_stripe_onboarding_url( $vendor_id ) {
		if ( ! class_exists( 'FundraiserPro\StripeConnectPayout' ) ) {
			return '#';
		}

		$stripe_connect = new StripeConnectPayout();

		// Check if account exists
		$payout_manager = new PayoutManager();
		$account = $payout_manager->get_payout_account( $vendor_id, 'stripe' );

		if ( ! $account ) {
			// Create Stripe Connect account
			$stripe_account_id = $stripe_connect->create_connected_account( $vendor_id );

			if ( ! $stripe_account_id ) {
				return '#';
			}

			// Save to database
			$payout_manager->create_payout_account(
				$vendor_id,
				'stripe',
				array( 'stripe_account_id' => $stripe_account_id )
			);

			$account = $payout_manager->get_payout_account( $vendor_id, 'stripe' );
		}

		if ( $account && $account->stripe_account_id ) {
			$return_url = add_query_arg( 'stripe_setup', 'complete', wc_get_account_endpoint_url( 'dashboard' ) );
			$refresh_url = add_query_arg( 'stripe_setup', 'refresh', wc_get_account_endpoint_url( 'dashboard' ) );

			return $stripe_connect->get_account_link( $account->stripe_account_id, $return_url, $refresh_url );
		}

		return '#';
	}

	/**
	 * Save Stripe settings.
	 *
	 * @param int $vendor_id Vendor user ID.
	 */
	public function save_stripe_settings( $vendor_id ) {
		if ( isset( $_POST['_wcv_stripe_connect_enabled'] ) ) {
			update_user_meta( $vendor_id, '_wcv_stripe_connect_enabled', true );
		} else {
			delete_user_meta( $vendor_id, '_wcv_stripe_connect_enabled' );
		}
	}

	/**
	 * Maybe automate payout on specific WC Vendors actions.
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_automate_payout( $order_id ) {
		// Hook for future automation triggers
		do_action( 'fundraiser_pro_wcv_payout_trigger', $order_id );
	}
}
