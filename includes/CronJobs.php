<?php
/**
 * Cron job handlers.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * CronJobs class.
 */
class CronJobs {

	/**
	 * Process daily analytics.
	 */
	public function process_daily_analytics() {
		global $wpdb;

		$table_campaigns = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';
		$table_analytics = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaign_analytics';

		// Get all active campaigns
		$campaigns = $wpdb->get_results(
			"SELECT id FROM {$table_campaigns} WHERE status = 'active'"
		);

		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		foreach ( $campaigns as $campaign ) {
			// Calculate metrics for yesterday
			$donations_count = 0; // Would query WooCommerce orders
			$donations_total = 0; // Would calculate from orders
			$unique_donors = 0; // Would count unique customers

			// Insert/update analytics record
			$wpdb->replace(
				$table_analytics,
				array(
					'campaign_id'       => $campaign->id,
					'date'              => $yesterday,
					'donations_count'   => $donations_count,
					'donations_total'   => $donations_total,
					'unique_donors'     => $unique_donors,
					'raffle_sales_count' => 0,
					'raffle_sales_total' => 0,
				),
				array( '%d', '%s', '%d', '%f', '%d', '%d', '%f' )
			);
		}
	}

	/**
	 * Process scheduled raffle draws.
	 */
	public function process_raffle_draws() {
		global $wpdb;

		$table_raffles = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'raffles';
		$table_tickets = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'raffle_tickets';

		// Get raffles that are ready to be drawn
		$current_time = current_time( 'mysql' );

		$raffles = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$table_raffles}
				WHERE status = 'active'
				AND draw_date <= %s
				AND winner_id IS NULL",
				$current_time
			)
		);

		foreach ( $raffles as $raffle ) {
			// Get all tickets for this raffle
			$tickets = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, ticket_number, customer_id, customer_email
					FROM {$table_tickets}
					WHERE raffle_id = %d",
					$raffle->id
				)
			);

			if ( empty( $tickets ) ) {
				continue;
			}

			// Randomly select a winner
			$winning_ticket = $tickets[ array_rand( $tickets ) ];

			// Update raffle with winner
			$wpdb->update(
				$table_raffles,
				array(
					'winner_id' => $winning_ticket->customer_id,
					'status'    => 'completed',
				),
				array( 'id' => $raffle->id ),
				array( '%d', '%s' ),
				array( '%d' )
			);

			// Mark winning ticket
			$wpdb->update(
				$table_tickets,
				array( 'is_winner' => 1 ),
				array( 'id' => $winning_ticket->id ),
				array( '%d' ),
				array( '%d' )
			);

			// Send winner notification email
			$this->send_winner_notification( $raffle->id, $winning_ticket );
		}
	}

	/**
	 * Send abandoned cart emails.
	 */
	public function send_abandoned_cart_emails() {
		// Implementation would track carts abandoned for 24+ hours
		// and send reminder emails with donation links
	}

	/**
	 * Check campaign milestones and send notifications.
	 */
	public function check_campaign_milestones() {
		global $wpdb;

		$table_campaigns = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';

		$campaigns = $wpdb->get_results(
			"SELECT id, goal_amount, current_amount
			FROM {$table_campaigns}
			WHERE status = 'active'"
		);

		$milestones = array( 25, 50, 75, 100 );

		foreach ( $campaigns as $campaign ) {
			if ( $campaign->goal_amount <= 0 ) {
				continue;
			}

			$percentage = ( $campaign->current_amount / $campaign->goal_amount ) * 100;

			foreach ( $milestones as $milestone ) {
				$meta_key = "_fundraiser_milestone_{$milestone}_sent";
				$sent = get_post_meta( $campaign->id, $meta_key, true );

				if ( ! $sent && $percentage >= $milestone ) {
					// Trigger milestone email
					do_action( 'fundraiser_pro_campaign_milestone', $campaign->id, $milestone );

					// Send to EmailManager
					require_once FUNDRAISER_PRO_PATH . 'includes/EmailManager.php';
					$email_manager = new EmailManager();
					$email_manager->send_milestone_notification( $campaign->id, $milestone );

					// Mark as sent
					update_post_meta( $campaign->id, $meta_key, true );
				}
			}
		}
	}

	/**
	 * Send winner notification.
	 *
	 * @param int    $raffle_id Raffle ID.
	 * @param object $ticket    Winning ticket.
	 */
	private function send_winner_notification( $raffle_id, $ticket ) {
		global $wpdb;

		$table_raffles = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'raffles';

		$raffle = $wpdb->get_row(
			$wpdb->prepare( "SELECT title, prize_details FROM {$table_raffles} WHERE id = %d", $raffle_id )
		);

		if ( ! $raffle ) {
			return;
		}

		$to = $ticket->customer_email;
		$subject = sprintf(
			/* translators: %s: Raffle title */
			__( 'Congratulations! You won: %s', 'fundraiser-pro' ),
			$raffle->title
		);

		$message = sprintf(
			__( "Congratulations!\n\nYou are the winner of %s!\n\nYour winning ticket number is: %s\n\nPrize details:\n%s\n\nWe will contact you shortly with instructions on how to claim your prize.\n\nThank you for supporting our cause!", 'fundraiser-pro' ),
			$raffle->title,
			$ticket->ticket_number,
			$raffle->prize_details
		);

		wp_mail( $to, $subject, $message );
	}
}
