<?php
/**
 * Platform Fees Management.
 * Handles commission calculations for fundraising campaigns.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * PlatformFees class.
 */
class PlatformFees {

	/**
	 * Fee types.
	 */
	const FEE_TYPE_PERCENTAGE = 'percentage';
	const FEE_TYPE_FLAT = 'flat';
	const FEE_TYPE_PACKAGE = 'package';

	/**
	 * Calculate platform fee for a transaction.
	 *
	 * @param float $amount       Transaction amount.
	 * @param int   $campaign_id  Campaign ID.
	 * @param int   $fundraiser_id User ID of fundraiser.
	 * @return array Fee details array.
	 */
	public function calculate_fee( $amount, $campaign_id = 0, $fundraiser_id = 0 ) {
		// Get fee configuration
		$fee_type = $this->get_fee_type( $fundraiser_id );

		switch ( $fee_type ) {
			case self::FEE_TYPE_PACKAGE:
				$fee_details = $this->calculate_package_fee( $amount, $fundraiser_id );
				break;

			case self::FEE_TYPE_FLAT:
				$fee_details = $this->calculate_flat_fee( $amount, $fundraiser_id );
				break;

			case self::FEE_TYPE_PERCENTAGE:
			default:
				$fee_details = $this->calculate_percentage_fee( $amount, $fundraiser_id );
				break;
		}

		$fee_details['campaign_id'] = $campaign_id;
		$fee_details['fundraiser_id'] = $fundraiser_id;
		$fee_details['gross_amount'] = $amount;

		return $fee_details;
	}

	/**
	 * Calculate percentage-based fee.
	 *
	 * @param float $amount       Transaction amount.
	 * @param int   $fundraiser_id User ID.
	 * @return array Fee details.
	 */
	private function calculate_percentage_fee( $amount, $fundraiser_id = 0 ) {
		// Check for user-specific percentage
		$percentage = $this->get_user_fee_percentage( $fundraiser_id );

		// Fall back to default percentage
		if ( $percentage === false ) {
			$percentage = get_option( 'fundraiser_pro_default_fee_percentage', 5.0 );
		}

		$fee_amount = ( $amount * $percentage ) / 100;
		$net_amount = $amount - $fee_amount;

		return array(
			'type' => self::FEE_TYPE_PERCENTAGE,
			'percentage' => $percentage,
			'fee_amount' => round( $fee_amount, 2 ),
			'net_amount' => round( $net_amount, 2 ),
			'description' => sprintf( __( '%s%% platform fee', 'fundraiser-pro' ), $percentage ),
		);
	}

	/**
	 * Calculate flat fee.
	 *
	 * @param float $amount       Transaction amount.
	 * @param int   $fundraiser_id User ID.
	 * @return array Fee details.
	 */
	private function calculate_flat_fee( $amount, $fundraiser_id = 0 ) {
		// Check for user-specific flat fee
		$flat_fee = $this->get_user_flat_fee( $fundraiser_id );

		// Fall back to default flat fee
		if ( $flat_fee === false ) {
			$flat_fee = get_option( 'fundraiser_pro_default_flat_fee', 0 );
		}

		$fee_amount = $flat_fee;
		$net_amount = $amount - $fee_amount;

		// Don't charge more than the transaction amount
		if ( $net_amount < 0 ) {
			$fee_amount = $amount;
			$net_amount = 0;
		}

		return array(
			'type' => self::FEE_TYPE_FLAT,
			'fee_amount' => round( $fee_amount, 2 ),
			'net_amount' => round( $net_amount, 2 ),
			'description' => sprintf( __( '$%s flat fee', 'fundraiser-pro' ), number_format( $flat_fee, 2 ) ),
		);
	}

	/**
	 * Calculate package-based fee.
	 *
	 * @param float $amount       Transaction amount.
	 * @param int   $fundraiser_id User ID.
	 * @return array Fee details.
	 */
	private function calculate_package_fee( $amount, $fundraiser_id = 0 ) {
		// Get user's package
		$package = $this->get_user_package( $fundraiser_id );

		if ( ! $package ) {
			// Fall back to percentage if no package assigned
			return $this->calculate_percentage_fee( $amount, $fundraiser_id );
		}

		$fee_amount = 0;
		$description = '';

		// Calculate based on package type
		if ( isset( $package['type'] ) && $package['type'] === 'percentage' ) {
			$percentage = $package['value'];
			$fee_amount = ( $amount * $percentage ) / 100;
			$description = sprintf( __( '%s%% (%s package)', 'fundraiser-pro' ), $percentage, $package['name'] );
		} elseif ( isset( $package['type'] ) && $package['type'] === 'flat' ) {
			$fee_amount = $package['value'];
			$description = sprintf( __( '$%s (%s package)', 'fundraiser-pro' ), number_format( $fee_amount, 2 ), $package['name'] );
		}

		$net_amount = $amount - $fee_amount;

		// Don't charge more than the transaction amount
		if ( $net_amount < 0 ) {
			$fee_amount = $amount;
			$net_amount = 0;
		}

		return array(
			'type' => self::FEE_TYPE_PACKAGE,
			'package' => $package['name'],
			'fee_amount' => round( $fee_amount, 2 ),
			'net_amount' => round( $net_amount, 2 ),
			'description' => $description,
		);
	}

	/**
	 * Record platform fee transaction.
	 *
	 * @param int   $order_id     WooCommerce order ID.
	 * @param array $fee_details  Fee calculation details.
	 * @return int|false Transaction ID or false on failure.
	 */
	public function record_fee_transaction( $order_id, $fee_details ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'platform_fees';

		$result = $wpdb->insert(
			$table_name,
			array(
				'order_id' => $order_id,
				'campaign_id' => $fee_details['campaign_id'],
				'fundraiser_id' => $fee_details['fundraiser_id'],
				'gross_amount' => $fee_details['gross_amount'],
				'fee_amount' => $fee_details['fee_amount'],
				'net_amount' => $fee_details['net_amount'],
				'fee_type' => $fee_details['type'],
				'fee_rate' => $fee_details['percentage'] ?? 0,
				'description' => $fee_details['description'],
				'status' => 'pending',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%f', '%s', '%s', '%s' )
		);

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Get fee type for a fundraiser.
	 *
	 * @param int $fundraiser_id User ID.
	 * @return string Fee type.
	 */
	private function get_fee_type( $fundraiser_id = 0 ) {
		if ( $fundraiser_id ) {
			$user_fee_type = get_user_meta( $fundraiser_id, 'fundraiser_fee_type', true );
			if ( $user_fee_type ) {
				return $user_fee_type;
			}
		}

		return get_option( 'fundraiser_pro_default_fee_type', self::FEE_TYPE_PERCENTAGE );
	}

	/**
	 * Get user-specific fee percentage.
	 *
	 * @param int $fundraiser_id User ID.
	 * @return float|false Percentage or false if not set.
	 */
	private function get_user_fee_percentage( $fundraiser_id = 0 ) {
		if ( ! $fundraiser_id ) {
			return false;
		}

		$percentage = get_user_meta( $fundraiser_id, 'fundraiser_fee_percentage', true );

		return $percentage !== '' ? floatval( $percentage ) : false;
	}

	/**
	 * Get user-specific flat fee.
	 *
	 * @param int $fundraiser_id User ID.
	 * @return float|false Flat fee or false if not set.
	 */
	private function get_user_flat_fee( $fundraiser_id = 0 ) {
		if ( ! $fundraiser_id ) {
			return false;
		}

		$flat_fee = get_user_meta( $fundraiser_id, 'fundraiser_flat_fee', true );

		return $flat_fee !== '' ? floatval( $flat_fee ) : false;
	}

	/**
	 * Get user's package configuration.
	 *
	 * @param int $fundraiser_id User ID.
	 * @return array|false Package details or false if not set.
	 */
	private function get_user_package( $fundraiser_id = 0 ) {
		if ( ! $fundraiser_id ) {
			return false;
		}

		$package_id = get_user_meta( $fundraiser_id, 'fundraiser_package', true );

		if ( ! $package_id ) {
			return false;
		}

		// Get package details from options
		$packages = get_option( 'fundraiser_pro_packages', array() );

		if ( isset( $packages[ $package_id ] ) ) {
			return $packages[ $package_id ];
		}

		return false;
	}

	/**
	 * Get total platform fees collected.
	 *
	 * @param array $args Query arguments.
	 * @return float Total fees.
	 */
	public function get_total_fees( $args = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'platform_fees';

		$where = array( '1=1' );

		if ( ! empty( $args['start_date'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['start_date'] );
		}

		if ( ! empty( $args['end_date'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['end_date'] );
		}

		if ( ! empty( $args['fundraiser_id'] ) ) {
			$where[] = $wpdb->prepare( 'fundraiser_id = %d', $args['fundraiser_id'] );
		}

		if ( ! empty( $args['campaign_id'] ) ) {
			$where[] = $wpdb->prepare( 'campaign_id = %d', $args['campaign_id'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		$total = $wpdb->get_var( "SELECT SUM(fee_amount) FROM {$table_name} WHERE {$where_clause}" );

		return floatval( $total );
	}

	/**
	 * Get fee transactions.
	 *
	 * @param array $args Query arguments.
	 * @return array Fee transactions.
	 */
	public function get_fee_transactions( $args = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'platform_fees';

		$where = array( '1=1' );
		$limit = '';
		$order = 'ORDER BY created_at DESC';

		if ( ! empty( $args['start_date'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['start_date'] );
		}

		if ( ! empty( $args['end_date'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['end_date'] );
		}

		if ( ! empty( $args['fundraiser_id'] ) ) {
			$where[] = $wpdb->prepare( 'fundraiser_id = %d', $args['fundraiser_id'] );
		}

		if ( ! empty( $args['limit'] ) ) {
			$limit = $wpdb->prepare( 'LIMIT %d', $args['limit'] );
		}

		$where_clause = implode( ' AND ', $where );

		$results = $wpdb->get_results( "SELECT * FROM {$table_name} WHERE {$where_clause} {$order} {$limit}" );

		return $results;
	}

	/**
	 * Update fee status (pending, collected, refunded).
	 *
	 * @param int    $fee_id Fee transaction ID.
	 * @param string $status New status.
	 * @return bool Success.
	 */
	public function update_fee_status( $fee_id, $status ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'platform_fees';

		$result = $wpdb->update(
			$table_name,
			array(
				'status' => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $fee_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Set user's fee configuration.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $fee_type    Fee type (percentage, flat, package).
	 * @param mixed  $fee_value   Fee value or package ID.
	 * @return bool Success.
	 */
	public function set_user_fee_config( $user_id, $fee_type, $fee_value ) {
		update_user_meta( $user_id, 'fundraiser_fee_type', $fee_type );

		switch ( $fee_type ) {
			case self::FEE_TYPE_PERCENTAGE:
				update_user_meta( $user_id, 'fundraiser_fee_percentage', floatval( $fee_value ) );
				delete_user_meta( $user_id, 'fundraiser_flat_fee' );
				delete_user_meta( $user_id, 'fundraiser_package' );
				break;

			case self::FEE_TYPE_FLAT:
				update_user_meta( $user_id, 'fundraiser_flat_fee', floatval( $fee_value ) );
				delete_user_meta( $user_id, 'fundraiser_fee_percentage' );
				delete_user_meta( $user_id, 'fundraiser_package' );
				break;

			case self::FEE_TYPE_PACKAGE:
				update_user_meta( $user_id, 'fundraiser_package', sanitize_text_field( $fee_value ) );
				delete_user_meta( $user_id, 'fundraiser_fee_percentage' );
				delete_user_meta( $user_id, 'fundraiser_flat_fee' );
				break;
		}

		return true;
	}

	/**
	 * Get available packages.
	 *
	 * @return array Packages.
	 */
	public function get_packages() {
		return get_option( 'fundraiser_pro_packages', array() );
	}

	/**
	 * Save packages configuration.
	 *
	 * @param array $packages Packages array.
	 * @return bool Success.
	 */
	public function save_packages( $packages ) {
		return update_option( 'fundraiser_pro_packages', $packages );
	}
}
