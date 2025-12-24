<?php
/**
 * WooCommerce Integration.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro\WooCommerce;

/**
 * Integration class.
 */
class Integration {

	/**
	 * Add custom product types to WooCommerce.
	 *
	 * @param array $types Existing product types.
	 * @return array Modified product types.
	 */
	public function add_product_types( $types ) {
		$types['raffle_ticket'] = __( 'Raffle Ticket', 'fundraiser-pro' );
		$types['donation']      = __( 'Donation', 'fundraiser-pro' );

		return $types;
	}

	/**
	 * Add custom product data fields.
	 */
	public function add_product_data_fields() {
		global $post;

		$product = wc_get_product( $post->ID );

		if ( ! $product ) {
			return;
		}

		$product_type = $product->get_type();

		echo '<div class="options_group show_if_raffle_ticket show_if_donation">';

		// Campaign ID field
		woocommerce_wp_select(
			array(
				'id'          => '_fundraiser_campaign_id',
				'label'       => __( 'Linked Campaign', 'fundraiser-pro' ),
				'options'     => $this->get_campaigns_for_select(),
				'desc_tip'    => true,
				'description' => __( 'Select the campaign this product is linked to.', 'fundraiser-pro' ),
			)
		);

		echo '</div>';

		// Raffle-specific fields
		echo '<div class="options_group show_if_raffle_ticket">';

		woocommerce_wp_text_input(
			array(
				'id'          => '_fundraiser_raffle_id',
				'label'       => __( 'Raffle ID', 'fundraiser-pro' ),
				'type'        => 'number',
				'desc_tip'    => true,
				'description' => __( 'The raffle this ticket is for.', 'fundraiser-pro' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_fundraiser_max_tickets_per_customer',
				'label'       => __( 'Max Tickets Per Customer', 'fundraiser-pro' ),
				'type'        => 'number',
				'desc_tip'    => true,
				'description' => __( 'Maximum number of tickets a customer can purchase (leave blank for unlimited).', 'fundraiser-pro' ),
			)
		);

		echo '</div>';

		// Donation-specific fields
		echo '<div class="options_group show_if_donation">';

		woocommerce_wp_checkbox(
			array(
				'id'          => '_fundraiser_allow_custom_amount',
				'label'       => __( 'Allow Custom Amount', 'fundraiser-pro' ),
				'description' => __( 'Allow donors to enter a custom donation amount.', 'fundraiser-pro' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_fundraiser_min_donation',
				'label'       => __( 'Minimum Donation', 'fundraiser-pro' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'type'        => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_checkbox(
			array(
				'id'          => '_fundraiser_allow_recurring',
				'label'       => __( 'Allow Recurring Donations', 'fundraiser-pro' ),
				'description' => __( 'Enable recurring donation option for this product.', 'fundraiser-pro' ),
			)
		);

		echo '</div>';
	}

	/**
	 * Save custom product data fields.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_product_data_fields( $post_id ) {
		// Campaign ID
		if ( isset( $_POST['_fundraiser_campaign_id'] ) ) {
			update_post_meta( $post_id, '_fundraiser_campaign_id', absint( $_POST['_fundraiser_campaign_id'] ) );
		}

		// Raffle ID
		if ( isset( $_POST['_fundraiser_raffle_id'] ) ) {
			update_post_meta( $post_id, '_fundraiser_raffle_id', absint( $_POST['_fundraiser_raffle_id'] ) );
		}

		// Max tickets per customer
		if ( isset( $_POST['_fundraiser_max_tickets_per_customer'] ) ) {
			update_post_meta( $post_id, '_fundraiser_max_tickets_per_customer', absint( $_POST['_fundraiser_max_tickets_per_customer'] ) );
		}

		// Allow custom amount
		$allow_custom = isset( $_POST['_fundraiser_allow_custom_amount'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_fundraiser_allow_custom_amount', $allow_custom );

		// Minimum donation
		if ( isset( $_POST['_fundraiser_min_donation'] ) ) {
			update_post_meta( $post_id, '_fundraiser_min_donation', sanitize_text_field( $_POST['_fundraiser_min_donation'] ) );
		}

		// Allow recurring
		$allow_recurring = isset( $_POST['_fundraiser_allow_recurring'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_fundraiser_allow_recurring', $allow_recurring );
	}

	/**
	 * Get campaigns for select dropdown.
	 *
	 * @return array Campaign options.
	 */
	private function get_campaigns_for_select() {
		$campaigns = get_posts(
			array(
				'post_type'      => 'fundraiser_campaign',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array( '' => __( 'Select a campaign', 'fundraiser-pro' ) );

		foreach ( $campaigns as $campaign ) {
			$options[ $campaign->ID ] = $campaign->post_title;
		}

		return $options;
	}
}
