<?php
/**
 * Product Markup and Cost Management.
 * Tracks product costs and calculates markup profits.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * ProductMarkup class.
 */
class ProductMarkup {

	/**
	 * Add cost field to product meta.
	 */
	public function add_product_cost_fields() {
		global $post;

		echo '<div class="options_group">';

		// Product Cost
		woocommerce_wp_text_input(
			array(
				'id'          => '_product_cost',
				'label'       => __( 'Product Cost', 'fundraiser-pro' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'placeholder' => '0.00',
				'desc_tip'    => true,
				'description' => __( 'Your cost for this product (auto-filled from initial price)', 'fundraiser-pro' ),
				'type'        => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		// Display markup calculation if both cost and price are set
		$product = wc_get_product( $post->ID );
		if ( $product ) {
			$cost = get_post_meta( $post->ID, '_product_cost', true );
			$price = $product->get_regular_price();

			if ( $cost && $price && $price > $cost ) {
				$markup = $price - $cost;
				$markup_percent = ( $markup / $cost ) * 100;

				echo '<p class="form-field">';
				echo '<label>' . __( 'Markup Info', 'fundraiser-pro' ) . '</label>';
				echo '<span style="display: block; padding: 10px; background: #f0f6fc; border-left: 3px solid #2271b1; margin-top: 5px;">';
				echo '<strong>' . __( 'Cost:', 'fundraiser-pro' ) . '</strong> ' . wc_price( $cost ) . '<br>';
				echo '<strong>' . __( 'Price:', 'fundraiser-pro' ) . '</strong> ' . wc_price( $price ) . '<br>';
				echo '<strong>' . __( 'Markup:', 'fundraiser-pro' ) . '</strong> ' . wc_price( $markup ) . ' ';
				echo '(' . number_format( $markup_percent, 1 ) . '%)<br>';
				echo '<strong style="color: #28a745;">' . __( 'Your profit per sale:', 'fundraiser-pro' ) . '</strong> <strong style="color: #28a745;">' . wc_price( $markup ) . '</strong>';
				echo '</span>';
				echo '</p>';
			}
		}

		echo '</div>';
	}

	/**
	 * Save product cost fields and auto-fill from price if empty.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_product_cost_fields( $post_id ) {
		// Get the submitted cost
		$submitted_cost = isset( $_POST['_product_cost'] ) ? sanitize_text_field( $_POST['_product_cost'] ) : '';

		// Get existing cost
		$existing_cost = get_post_meta( $post_id, '_product_cost', true );

		// If cost field is empty or this is a new product, auto-fill from regular price
		if ( empty( $existing_cost ) && empty( $submitted_cost ) ) {
			// Get the regular price that was just saved
			$regular_price = isset( $_POST['_regular_price'] ) ? sanitize_text_field( $_POST['_regular_price'] ) : '';

			if ( ! empty( $regular_price ) && floatval( $regular_price ) > 0 ) {
				// Auto-fill cost with initial price
				update_post_meta( $post_id, '_product_cost', $regular_price );

				// Add a note that this was auto-filled
				update_post_meta( $post_id, '_product_cost_autofilled', 'yes' );
			}
		} else {
			// Manual update - save the provided cost
			if ( ! empty( $submitted_cost ) ) {
				update_post_meta( $post_id, '_product_cost', $submitted_cost );

				// Remove auto-fill flag since it's been manually set
				delete_post_meta( $post_id, '_product_cost_autofilled' );
			}
		}
	}

	/**
	 * Calculate markup for a product.
	 *
	 * @param int   $product_id Product ID.
	 * @param float $price      Sale price (optional, uses product price if not provided).
	 * @return array Markup details.
	 */
	public function calculate_markup( $product_id, $price = null ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array(
				'error' => 'Product not found',
			);
		}

		$cost = floatval( get_post_meta( $product_id, '_product_cost', true ) );

		if ( ! $cost || $cost <= 0 ) {
			return array(
				'has_cost' => false,
				'cost' => 0,
				'price' => 0,
				'markup' => 0,
				'markup_percent' => 0,
			);
		}

		if ( $price === null ) {
			$price = floatval( $product->get_price() );
		}

		$markup = $price - $cost;
		$markup_percent = $cost > 0 ? ( $markup / $cost ) * 100 : 0;

		return array(
			'has_cost' => true,
			'cost' => $cost,
			'price' => $price,
			'markup' => $markup,
			'markup_percent' => $markup_percent,
		);
	}

	/**
	 * Record markup profit from an order.
	 *
	 * @param int $order_id Order ID.
	 * @return float Total markup profit.
	 */
	public function record_order_markup( $order_id ) {
		global $wpdb;

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return 0;
		}

		$total_markup = 0;
		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'product_profits';

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$quantity = $item->get_quantity();
			$sale_price = $item->get_total() / $quantity; // Price per unit

			$markup_info = $this->calculate_markup( $product_id, $sale_price );

			if ( ! $markup_info['has_cost'] ) {
				continue;
			}

			$unit_markup = $markup_info['markup'];
			$total_item_markup = $unit_markup * $quantity;
			$total_markup += $total_item_markup;

			// Record in database
			$wpdb->insert(
				$table_name,
				array(
					'order_id' => $order_id,
					'product_id' => $product_id,
					'campaign_id' => get_post_meta( $product_id, '_fundraiser_campaign_id', true ),
					'quantity' => $quantity,
					'unit_cost' => $markup_info['cost'],
					'unit_price' => $sale_price,
					'unit_markup' => $unit_markup,
					'total_markup' => $total_item_markup,
					'supplier' => get_post_meta( $product_id, '_product_supplier', true ),
					'created_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s' )
			);
		}

		// Store total markup on order meta
		update_post_meta( $order_id, '_total_product_markup', $total_markup );

		return $total_markup;
	}

	/**
	 * Get total markup profits.
	 *
	 * @param array $args Query arguments.
	 * @return float Total markup.
	 */
	public function get_total_markup( $args = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'product_profits';

		$where = array( '1=1' );

		if ( ! empty( $args['start_date'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['start_date'] );
		}

		if ( ! empty( $args['end_date'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['end_date'] );
		}

		if ( ! empty( $args['product_id'] ) ) {
			$where[] = $wpdb->prepare( 'product_id = %d', $args['product_id'] );
		}

		if ( ! empty( $args['campaign_id'] ) ) {
			$where[] = $wpdb->prepare( 'campaign_id = %d', $args['campaign_id'] );
		}

		$where_clause = implode( ' AND ', $where );

		$total = $wpdb->get_var( "SELECT SUM(total_markup) FROM {$table_name} WHERE {$where_clause}" );

		return floatval( $total );
	}

	/**
	 * Get markup transactions.
	 *
	 * @param array $args Query arguments.
	 * @return array Transactions.
	 */
	public function get_markup_transactions( $args = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'product_profits';

		$where = array( '1=1' );
		$limit = '';
		$order = 'ORDER BY created_at DESC';

		if ( ! empty( $args['start_date'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['start_date'] );
		}

		if ( ! empty( $args['end_date'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['end_date'] );
		}

		if ( ! empty( $args['limit'] ) ) {
			$limit = $wpdb->prepare( 'LIMIT %d', $args['limit'] );
		}

		$where_clause = implode( ' AND ', $where );

		$results = $wpdb->get_results( "SELECT * FROM {$table_name} WHERE {$where_clause} {$order} {$limit}" );

		return $results;
	}

	/**
	 * Display product profit summary in order details.
	 *
	 * @param int $order_id Order ID.
	 */
	public function display_order_profit_summary( $order_id ) {
		$markup = get_post_meta( $order_id, '_total_product_markup', true );

		if ( ! $markup || $markup <= 0 ) {
			return;
		}

		echo '<div class="order_data_column" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 3px solid #28a745;">';
		echo '<h3>' . __( 'Product Profit Summary', 'fundraiser-pro' ) . '</h3>';
		echo '<p><strong>' . __( 'Total Product Markup:', 'fundraiser-pro' ) . '</strong> ' . wc_price( $markup ) . '</p>';

		// Get platform fee
		global $wpdb;
		$fee_table = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'platform_fees';
		$platform_fee = $wpdb->get_var(
			$wpdb->prepare( "SELECT fee_amount FROM {$fee_table} WHERE order_id = %d", $order_id )
		);

		if ( $platform_fee ) {
			echo '<p><strong>' . __( 'Platform Fee (5%):', 'fundraiser-pro' ) . '</strong> ' . wc_price( $platform_fee ) . '</p>';
			$total_profit = $markup + $platform_fee;
			echo '<p style="font-size: 1.2em; border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px;"><strong>' . __( 'Total Your Profit:', 'fundraiser-pro' ) . '</strong> <strong style="color: #28a745;">' . wc_price( $total_profit ) . '</strong></p>';
		}

		echo '</div>';
	}
}
