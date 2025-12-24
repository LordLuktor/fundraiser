<?php
/**
 * WC Vendors Integration for Fundraiser Pro.
 * Maps fundraisers to WC Vendors and handles commission tracking.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * VendorIntegration class.
 */
class VendorIntegration {

	/**
	 * Initialize integration.
	 */
	public function __construct() {
		// Hook into fundraiser creation to make them vendors
		add_action( 'user_register', array( $this, 'maybe_convert_to_vendor' ), 20 );
		add_action( 'set_user_role', array( $this, 'on_role_change' ), 10, 3 );

		// Force 7% commission on campaign products
		add_filter( 'wcvendors_commission_rate', array( $this, 'force_campaign_commission' ), 10, 3 );

		// Track commissions in our system when WC Vendors records them
		add_action( 'wcvendors_commission_insert', array( $this, 'sync_commission_to_platform_fees' ), 10, 1 );

		// Customize vendor capabilities for fundraisers
		add_filter( 'wcvendors_vendor_caps', array( $this, 'customize_vendor_capabilities' ), 10, 2 );
	}

	/**
	 * Maybe convert user to vendor when they register.
	 *
	 * @param int $user_id User ID.
	 */
	public function maybe_convert_to_vendor( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		// Check if user has fundraiser role
		if ( in_array( 'fundraiser', $user->roles, true ) ) {
			$this->convert_fundraiser_to_vendor( $user_id );
		}
	}

	/**
	 * Handle role changes.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $new_role  New role.
	 * @param array  $old_roles Old roles.
	 */
	public function on_role_change( $user_id, $new_role, $old_roles ) {
		// If user is now a fundraiser, make them a vendor
		if ( 'fundraiser' === $new_role ) {
			$this->convert_fundraiser_to_vendor( $user_id );
		}

		// If user is no longer a fundraiser, remove vendor status
		if ( in_array( 'fundraiser', $old_roles, true ) && 'fundraiser' !== $new_role ) {
			$this->remove_vendor_status( $user_id );
		}
	}

	/**
	 * Convert fundraiser user to WC Vendor.
	 *
	 * @param int $user_id User ID.
	 * @return bool Success status.
	 */
	public function convert_fundraiser_to_vendor( $user_id ) {
		// Check if user is already a vendor
		if ( $this->is_vendor( $user_id ) ) {
			return true;
		}

		// Add vendor role
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$user->add_role( 'vendor' );
		$user->add_role( 'wc_product_vendors_admin_vendor' );

		// Set vendor meta
		update_user_meta( $user_id, '_wcv_vendor_id', $user_id );
		update_user_meta( $user_id, '_wcv_commission', '' ); // Use default 7%
		update_user_meta( $user_id, '_wcv_custom_commission', '' );

		// Create vendor shop settings
		$shop_name = get_user_meta( $user_id, 'fundraiser_organization_name', true );
		if ( empty( $shop_name ) ) {
			$shop_name = $user->display_name . "'s Fundraiser";
		}

		update_user_meta( $user_id, 'pv_shop_name', sanitize_text_field( $shop_name ) );
		update_user_meta( $user_id, 'pv_shop_slug', sanitize_title( $shop_name ) );
		update_user_meta( $user_id, 'pv_shop_description', '' );

		// Set payment preferences (we'll handle payouts custom)
		update_user_meta( $user_id, '_wcv_payment_method', 'manual' );

		// Log the conversion
		if ( class_exists( 'FundraiserPro\ActivityLogger' ) ) {
			$logger = new ActivityLogger();
			$logger->log_activity( array(
				'user_id'     => $user_id,
				'action'      => 'fundraiser_converted_to_vendor',
				'description' => 'Fundraiser user converted to WC Vendor',
				'object_type' => 'user',
				'object_id'   => $user_id,
			) );
		}

		do_action( 'fundraiser_pro_vendor_conversion', $user_id );

		return true;
	}

	/**
	 * Remove vendor status from user.
	 *
	 * @param int $user_id User ID.
	 */
	public function remove_vendor_status( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$user->remove_role( 'vendor' );
		$user->remove_role( 'wc_product_vendors_admin_vendor' );

		delete_user_meta( $user_id, '_wcv_vendor_id' );
	}

	/**
	 * Check if user is a vendor.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if vendor.
	 */
	public function is_vendor( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		return in_array( 'vendor', $user->roles, true ) ||
		       in_array( 'wc_product_vendors_admin_vendor', $user->roles, true );
	}

	/**
	 * Force 7% commission on campaign products.
	 *
	 * @param float $commission Commission rate.
	 * @param int   $product_id Product ID.
	 * @param int   $vendor_id  Vendor ID.
	 * @return float Modified commission rate.
	 */
	public function force_campaign_commission( $commission, $product_id, $vendor_id ) {
		// Check if this product belongs to a campaign
		$campaign_id = get_post_meta( $product_id, '_fundraiser_campaign_id', true );

		if ( $campaign_id ) {
			// Return 7% commission for campaign products
			// Note: WC Vendors calculates vendor commission, not platform fee
			// So we return 93% to vendor (platform keeps 7%)
			return 93.0;
		}

		return $commission;
	}

	/**
	 * Sync WC Vendors commission data to our platform fees system.
	 *
	 * @param array $commission_data Commission data from WC Vendors.
	 */
	public function sync_commission_to_platform_fees( $commission_data ) {
		if ( ! isset( $commission_data['order_id'] ) ) {
			return;
		}

		$order_id = $commission_data['order_id'];
		$product_id = $commission_data['product_id'] ?? 0;
		$vendor_id = $commission_data['vendor_id'] ?? 0;

		// Check if this is a campaign product
		$campaign_id = get_post_meta( $product_id, '_fundraiser_campaign_id', true );

		if ( ! $campaign_id ) {
			return;
		}

		// Get the commission amounts
		$total_due = $commission_data['total_due'] ?? 0; // Amount to vendor
		$total_shipping = $commission_data['total_shipping'] ?? 0;
		$tax = $commission_data['tax'] ?? 0;
		$product_amount = $commission_data['product_amount'] ?? 0;

		// Calculate platform fee (7% of gross)
		$gross_amount = $product_amount;
		$platform_fee = $gross_amount * 0.07;

		// Record in our platform fees table
		if ( class_exists( 'FundraiserPro\PlatformFees' ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'fundraiser_platform_fees';

			$wpdb->insert(
				$table_name,
				array(
					'order_id'      => $order_id,
					'campaign_id'   => $campaign_id,
					'fundraiser_id' => $vendor_id,
					'gross_amount'  => $gross_amount,
					'fee_amount'    => $platform_fee,
					'net_amount'    => $total_due,
					'fee_type'      => 'percentage',
					'fee_percentage' => 7.0,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%f', '%s' )
			);
		}

		// Track payout owed to vendor
		$this->track_payout_balance( $vendor_id, $total_due, $order_id );
	}

	/**
	 * Track payout balance for vendor.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param float $amount    Amount owed.
	 * @param int   $order_id  Order ID.
	 */
	private function track_payout_balance( $vendor_id, $amount, $order_id ) {
		// Get current balance
		$current_balance = get_user_meta( $vendor_id, '_wcv_unpaid_commission', true );
		if ( empty( $current_balance ) ) {
			$current_balance = 0;
		}

		// Add new amount
		$new_balance = floatval( $current_balance ) + floatval( $amount );
		update_user_meta( $vendor_id, '_wcv_unpaid_commission', $new_balance );

		// Track individual commission for payout history
		$commissions = get_user_meta( $vendor_id, '_fundraiser_pending_commissions', true );
		if ( ! is_array( $commissions ) ) {
			$commissions = array();
		}

		$commissions[] = array(
			'order_id' => $order_id,
			'amount'   => $amount,
			'date'     => current_time( 'mysql' ),
			'status'   => 'pending',
		);

		update_user_meta( $vendor_id, '_fundraiser_pending_commissions', $commissions );
	}

	/**
	 * Customize vendor capabilities for fundraisers.
	 *
	 * @param array $caps    Capabilities array.
	 * @param int   $user_id User ID.
	 * @return array Modified capabilities.
	 */
	public function customize_vendor_capabilities( $caps, $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user || ! in_array( 'fundraiser', $user->roles, true ) ) {
			return $caps;
		}

		// Fundraisers can manage their products
		$caps['edit_products'] = true;
		$caps['edit_published_products'] = true;
		$caps['delete_products'] = false; // Prevent deletion
		$caps['publish_products'] = true;
		$caps['upload_files'] = true;

		// Fundraisers cannot see certain vendor features
		$caps['view_woocommerce_reports'] = false;
		$caps['manage_woocommerce'] = false;

		return $caps;
	}

	/**
	 * Bulk convert existing fundraisers to vendors.
	 * Run this once during setup or via admin action.
	 */
	public function bulk_convert_fundraisers() {
		$fundraisers = get_users( array( 'role' => 'fundraiser' ) );
		$converted = 0;

		foreach ( $fundraisers as $fundraiser ) {
			if ( $this->convert_fundraiser_to_vendor( $fundraiser->ID ) ) {
				$converted++;
			}
		}

		return array(
			'total'     => count( $fundraisers ),
			'converted' => $converted,
			'message'   => sprintf( 'Converted %d of %d fundraisers to vendors', $converted, count( $fundraisers ) ),
		);
	}

	/**
	 * Get vendor's unpaid balance.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return float Unpaid balance.
	 */
	public function get_vendor_balance( $vendor_id ) {
		$balance = get_user_meta( $vendor_id, '_wcv_unpaid_commission', true );
		return floatval( $balance );
	}

	/**
	 * Clear vendor's unpaid balance (after payout).
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param float $amount    Amount paid out.
	 */
	public function clear_vendor_balance( $vendor_id, $amount ) {
		$current_balance = $this->get_vendor_balance( $vendor_id );
		$new_balance = max( 0, $current_balance - $amount );

		update_user_meta( $vendor_id, '_wcv_unpaid_commission', $new_balance );

		// Mark commissions as paid
		$commissions = get_user_meta( $vendor_id, '_fundraiser_pending_commissions', true );
		if ( is_array( $commissions ) ) {
			$remaining_amount = $amount;

			foreach ( $commissions as $key => $commission ) {
				if ( $remaining_amount <= 0 ) {
					break;
				}

				if ( $commission['status'] === 'pending' ) {
					$commission_amount = floatval( $commission['amount'] );

					if ( $remaining_amount >= $commission_amount ) {
						$commissions[ $key ]['status'] = 'paid';
						$commissions[ $key ]['paid_date'] = current_time( 'mysql' );
						$remaining_amount -= $commission_amount;
					}
				}
			}

			update_user_meta( $vendor_id, '_fundraiser_pending_commissions', $commissions );
		}
	}
}
