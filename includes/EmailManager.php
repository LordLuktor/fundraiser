<?php
/**
 * Email Manager.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * EmailManager class.
 */
class EmailManager {

	/**
	 * Set from email.
	 *
	 * @param string $email Email address.
	 * @return string Modified email address.
	 */
	public function set_from_email( $email ) {
		$from_email = get_option( 'fundraiser_pro_email_from_email' );
		return $from_email ? $from_email : $email;
	}

	/**
	 * Set from name.
	 *
	 * @param string $name From name.
	 * @return string Modified from name.
	 */
	public function set_from_name( $name ) {
		$from_name = get_option( 'fundraiser_pro_email_from_name' );
		return $from_name ? $from_name : $name;
	}

	/**
	 * Send donation thank you email.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $email    Email address.
	 * @param array  $data     Email data.
	 * @return bool Success status.
	 */
	public function send_donation_thank_you( $order_id, $email, $data ) {
		$subject = __( 'Thank you for your donation!', 'fundraiser-pro' );

		$message = $this->get_template( 'donation-thank-you', $data );

		return $this->send_email( $email, $subject, $message, 'donation-thank-you' );
	}

	/**
	 * Send raffle ticket confirmation.
	 *
	 * @param string $email Email address.
	 * @param array  $data  Email data.
	 * @return bool Success status.
	 */
	public function send_raffle_confirmation( $email, $data ) {
		$subject = __( 'Your Raffle Tickets', 'fundraiser-pro' );

		$message = $this->get_template( 'raffle-confirmation', $data );

		return $this->send_email( $email, $subject, $message, 'raffle-confirmation' );
	}

	/**
	 * Send milestone notification.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param int   $milestone   Milestone percentage.
	 * @return bool Success status.
	 */
	public function send_milestone_notification( $campaign_id, $milestone ) {
		// Get campaign donors
		$donors = $this->get_campaign_donors( $campaign_id );

		$campaign = get_post( $campaign_id );

		foreach ( $donors as $donor ) {
			$data = array(
				'campaign_name' => $campaign->post_title,
				'milestone'     => $milestone,
				'donor_name'    => $donor['name'],
			);

			$subject = sprintf(
				/* translators: 1: Campaign name, 2: Milestone percentage */
				__( '%1$s has reached %2$d%% of its goal!', 'fundraiser-pro' ),
				$campaign->post_title,
				$milestone
			);

			$message = $this->get_template( 'milestone-notification', $data );

			$this->send_email( $donor['email'], $subject, $message, 'milestone-notification' );
		}

		return true;
	}

	/**
	 * Send email.
	 *
	 * @param string $to       Recipient email.
	 * @param string $subject  Email subject.
	 * @param string $message  Email message.
	 * @param string $template Template name.
	 * @return bool Success status.
	 */
	private function send_email( $to, $subject, $message, $template ) {
		global $wpdb;

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $to, $subject, $message, $headers );

		// Log email
		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'email_log';

		$wpdb->insert(
			$table_name,
			array(
				'recipient'     => $to,
				'subject'       => $subject,
				'template'      => $template,
				'status'        => $sent ? 'sent' : 'failed',
				'error_message' => $sent ? null : 'wp_mail returned false',
				'sent_at'       => $sent ? current_time( 'mysql' ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $sent;
	}

	/**
	 * Get email template.
	 *
	 * @param string $template Template name.
	 * @param array  $data     Template data.
	 * @return string Email content.
	 */
	private function get_template( $template, $data ) {
		$template_file = FUNDRAISER_PRO_PATH . "templates/emails/{$template}.php";

		if ( file_exists( $template_file ) ) {
			ob_start();
			extract( $data );
			include $template_file;
			return ob_get_clean();
		}

		// Fallback to basic template
		return $this->get_basic_template( $data );
	}

	/**
	 * Get basic email template.
	 *
	 * @param array $data Template data.
	 * @return string Email content.
	 */
	private function get_basic_template( $data ) {
		$content = '<html><body style="font-family: Arial, sans-serif; padding: 20px;">';
		$content .= '<h2>' . esc_html( get_bloginfo( 'name' ) ) . '</h2>';

		foreach ( $data as $key => $value ) {
			$content .= '<p><strong>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . ':</strong> ' . esc_html( $value ) . '</p>';
		}

		$content .= '</body></html>';

		return $content;
	}

	/**
	 * Get campaign donors.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array Donors.
	 */
	private function get_campaign_donors( $campaign_id ) {
		global $wpdb;

		// This is a simplified version - in production, query orders linked to campaign
		return array();
	}
}
