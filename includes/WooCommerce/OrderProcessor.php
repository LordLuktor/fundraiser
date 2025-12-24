<?php
/**
 * WooCommerce Order Processor.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro\WooCommerce;

/**
 * OrderProcessor class.
 */
class OrderProcessor {

	/**
	 * Save custom checkout fields.
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_custom_checkout_fields( $order_id ) {
		if ( isset( $_POST['fundraiser_anonymous_donation'] ) ) {
			update_post_meta( $order_id, '_fundraiser_anonymous_donation', sanitize_text_field( $_POST['fundraiser_anonymous_donation'] ) );
		}

		if ( isset( $_POST['fundraiser_recurring_donation'] ) ) {
			update_post_meta( $order_id, '_fundraiser_recurring_donation', sanitize_text_field( $_POST['fundraiser_recurring_donation'] ) );
		}

		if ( isset( $_POST['fundraiser_recurring_frequency'] ) ) {
			update_post_meta( $order_id, '_fundraiser_recurring_frequency', sanitize_text_field( $_POST['fundraiser_recurring_frequency'] ) );
		}
	}

	/**
	 * Process order completion.
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_order_completion( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$product_type = $product->get_type();

			// Handle raffle ticket purchase
			if ( 'raffle_ticket' === $product_type ) {
				$this->process_raffle_ticket_purchase( $order, $item );
			}

			// Handle donation
			if ( 'donation' === $product_type ) {
				$this->process_donation( $order, $item );
			}
		}

		// Record product markup profits
		require_once FUNDRAISER_PRO_PATH . 'includes/ProductMarkup.php';
		$product_markup = new \FundraiserPro\ProductMarkup();
		$product_markup->record_order_markup( $order_id );

		// Send thank you email
		$this->send_thank_you_email( $order );
	}

	/**
	 * Update campaign totals when order is completed.
	 *
	 * @param int $order_id Order ID.
	 */
	public function update_campaign_totals( $order_id ) {
		global $wpdb;

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Check if platform fees are enabled
		$fees_enabled = get_option( 'fundraiser_pro_enable_platform_fees', false );

		// Load PlatformFees class if fees are enabled
		if ( $fees_enabled ) {
			require_once FUNDRAISER_PRO_PATH . 'includes/PlatformFees.php';
			$platform_fees = new \FundraiserPro\PlatformFees();
		}

		$campaign_updates = array();

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$campaign_id = get_post_meta( $product_id, '_fundraiser_campaign_id', true );

			if ( ! $campaign_id ) {
				continue;
			}

			$gross_amount = $item->get_total();
			$net_amount = $gross_amount;

			// Calculate and deduct platform fees
			if ( $fees_enabled && isset( $platform_fees ) ) {
				// Get campaign owner (fundraiser)
				$campaign_table = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';
				$campaign = $wpdb->get_row(
					$wpdb->prepare( "SELECT * FROM {$campaign_table} WHERE id = %d", $campaign_id )
				);

				$fundraiser_id = $campaign->user_id ?? 0;

				// Calculate fee
				$fee_details = $platform_fees->calculate_fee( $gross_amount, $campaign_id, $fundraiser_id );
				$net_amount = $fee_details['net_amount'];

				// Record fee transaction
				$platform_fees->record_fee_transaction( $order_id, $fee_details );

				// Log fee deduction
				do_action( 'fundraiser_pro_platform_fee_collected', $order_id, $fee_details );
			}

			if ( ! isset( $campaign_updates[ $campaign_id ] ) ) {
				$campaign_updates[ $campaign_id ] = 0;
			}

			// Add NET amount (after fees) to campaign
			$campaign_updates[ $campaign_id ] += $net_amount;
		}

		// Update campaign amounts in database with NET amounts
		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';

		foreach ( $campaign_updates as $campaign_id => $amount ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET current_amount = current_amount + %f WHERE id = %d",
					$amount,
					$campaign_id
				)
			);

			// Check for milestones
			$this->check_campaign_milestone( $campaign_id );
		}
	}

	/**
	 * Handle order refund.
	 *
	 * @param int $order_id Order ID.
	 */
	public function handle_refund( $order_id ) {
		global $wpdb;

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$campaign_updates = array();

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$campaign_id = get_post_meta( $product_id, '_fundraiser_campaign_id', true );

			if ( ! $campaign_id ) {
				continue;
			}

			if ( ! isset( $campaign_updates[ $campaign_id ] ) ) {
				$campaign_updates[ $campaign_id ] = 0;
			}

			$campaign_updates[ $campaign_id ] += $item->get_total();
		}

		// Deduct refunded amounts from campaigns
		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';

		foreach ( $campaign_updates as $campaign_id => $amount ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET current_amount = GREATEST(0, current_amount - %f) WHERE id = %d",
					$amount,
					$campaign_id
				)
			);
		}
	}

	/**
	 * Process raffle ticket purchase.
	 *
	 * @param \WC_Order      $order Order object.
	 * @param \WC_Order_Item $item  Order item.
	 */
	private function process_raffle_ticket_purchase( $order, $item ) {
		global $wpdb;

		$product_id = $item->get_product_id();
		$raffle_id  = get_post_meta( $product_id, '_fundraiser_raffle_id', true );

		if ( ! $raffle_id ) {
			return;
		}

		$quantity = $item->get_quantity();
		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'raffle_tickets';

		// Generate ticket numbers
		for ( $i = 0; $i < $quantity; $i++ ) {
			$ticket_number = $this->generate_ticket_number( $raffle_id );

			$wpdb->insert(
				$table_name,
				array(
					'raffle_id'      => $raffle_id,
					'ticket_number'  => $ticket_number,
					'order_id'       => $order->get_id(),
					'customer_id'    => $order->get_customer_id(),
					'customer_email' => $order->get_billing_email(),
					'purchase_date'  => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%d', '%d', '%s', '%s' )
			);
		}

		// Update raffle tickets sold count
		$raffles_table = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'raffles';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$raffles_table} SET tickets_sold = tickets_sold + %d WHERE id = %d",
				$quantity,
				$raffle_id
			)
		);
	}

	/**
	 * Process donation.
	 *
	 * @param \WC_Order      $order Order object.
	 * @param \WC_Order_Item $item  Order item.
	 */
	private function process_donation( $order, $item ) {
		// Record in analytics
		$this->record_donation_analytics( $order, $item );

		// Handle recurring donation setup if applicable
		$is_recurring = get_post_meta( $order->get_id(), '_fundraiser_recurring_donation', true );

		if ( 'yes' === $is_recurring ) {
			$this->setup_recurring_donation( $order, $item );
		}
	}

	/**
	 * Generate unique ticket number.
	 *
	 * @param int $raffle_id Raffle ID.
	 * @return string Ticket number.
	 */
	private function generate_ticket_number( $raffle_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'raffle_tickets';

		do {
			$ticket_number = sprintf( 'R%d-%06d', $raffle_id, wp_rand( 1, 999999 ) );

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE raffle_id = %d AND ticket_number = %s",
					$raffle_id,
					$ticket_number
				)
			);
		} while ( $exists > 0 );

		return $ticket_number;
	}

	/**
	 * Record donation in analytics.
	 *
	 * @param \WC_Order      $order Order object.
	 * @param \WC_Order_Item $item  Order item.
	 */
	private function record_donation_analytics( $order, $item ) {
		global $wpdb;

		$product_id  = $item->get_product_id();
		$campaign_id = get_post_meta( $product_id, '_fundraiser_campaign_id', true );

		if ( ! $campaign_id ) {
			return;
		}

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaign_analytics';
		$date       = current_time( 'Y-m-d' );

		// Update or insert analytics record
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table_name} (campaign_id, date, donations_count, donations_total, unique_donors)
				VALUES (%d, %s, 1, %f, 1)
				ON DUPLICATE KEY UPDATE
					donations_count = donations_count + 1,
					donations_total = donations_total + %f",
				$campaign_id,
				$date,
				$item->get_total(),
				$item->get_total()
			)
		);
	}

	/**
	 * Setup recurring donation.
	 *
	 * @param \WC_Order      $order Order object.
	 * @param \WC_Order_Item $item  Order item.
	 */
	private function setup_recurring_donation( $order, $item ) {
		// This would integrate with WooCommerce Subscriptions if available
		// For now, we'll just log the intent
		$frequency = get_post_meta( $order->get_id(), '_fundraiser_recurring_frequency', true );

		update_post_meta( $order->get_id(), '_fundraiser_recurring_setup', array(
			'frequency' => $frequency,
			'amount'    => $item->get_total(),
			'status'    => 'active',
		) );
	}

	/**
	 * Check campaign milestone and trigger emails.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	private function check_campaign_milestone( $campaign_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';

		$campaign = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT goal_amount, current_amount FROM {$table_name} WHERE id = %d",
				$campaign_id
			)
		);

		if ( ! $campaign || $campaign->goal_amount <= 0 ) {
			return;
		}

		$percentage = ( $campaign->current_amount / $campaign->goal_amount ) * 100;

		$milestones = array( 25, 50, 75, 100 );

		foreach ( $milestones as $milestone ) {
			$meta_key = "_fundraiser_milestone_{$milestone}_sent";
			$sent = get_post_meta( $campaign_id, $meta_key, true );

			if ( ! $sent && $percentage >= $milestone ) {
				// Trigger milestone email
				do_action( 'fundraiser_pro_campaign_milestone', $campaign_id, $milestone );

				// Mark as sent
				update_post_meta( $campaign_id, $meta_key, true );
			}
		}
	}

	/**
	 * Send thank you email.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function send_thank_you_email( $order ) {
		$to      = $order->get_billing_email();
		$subject = sprintf( __( 'Thank you for your contribution!', 'fundraiser-pro' ) );

		$message = sprintf(
			__( 'Dear %s,\n\nThank you for your generous contribution! Your support makes a real difference.\n\nOrder #%s\nTotal: %s\n\nBest regards,\nThe Fundraising Team', 'fundraiser-pro' ),
			$order->get_billing_first_name(),
			$order->get_order_number(),
			$order->get_formatted_order_total()
		);

		wp_mail( $to, $subject, $message );
	}
}
