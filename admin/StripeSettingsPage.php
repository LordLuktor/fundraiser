<?php
/**
 * Stripe Settings Page.
 * Admin UI for Stripe Connect OAuth integration.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro\Admin;

/**
 * StripeSettingsPage class.
 */
class StripeSettingsPage {

	/**
	 * OAuth manager instance.
	 *
	 * @var \FundraiserPro\StripeOAuthManager
	 */
	private $oauth_manager;

	/**
	 * Initialize settings page.
	 */
	public function __construct() {
		if ( class_exists( 'FundraiserPro\StripeOAuthManager' ) ) {
			$this->oauth_manager = new \FundraiserPro\StripeOAuthManager();
		}

		add_action( 'admin_menu', array( $this, 'add_settings_submenu' ) );
		add_action( 'admin_init', array( $this, 'handle_disconnect' ) );
		add_action( 'admin_notices', array( $this, 'show_connection_notices' ) );
	}

	/**
	 * Add settings submenu page.
	 */
	public function add_settings_submenu() {
		add_submenu_page(
			'fundraiser-pro',
			__( 'Stripe Settings', 'fundraiser-pro' ),
			__( 'Stripe Settings', 'fundraiser-pro' ),
			'manage_options',
			'fundraiser-pro-stripe',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php $this->render_stripe_connection_section(); ?>

			<?php if ( $this->oauth_manager && $this->oauth_manager->is_connected() ) : ?>
				<?php $this->render_payout_statistics(); ?>
			<?php endif; ?>

			<?php $this->render_setup_instructions(); ?>
		</div>
		<?php
	}

	/**
	 * Render Stripe connection section.
	 */
	private function render_stripe_connection_section() {
		if ( ! $this->oauth_manager ) {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Stripe OAuth Manager not loaded. Please check plugin installation.', 'fundraiser-pro' ); ?></p>
			</div>
			<?php
			return;
		}

		$is_connected = $this->oauth_manager->is_connected();
		$connection_info = $this->oauth_manager->get_connection_info();

		?>
		<div class="card" style="max-width: 800px;">
			<h2><?php esc_html_e( 'Stripe Connect Integration', 'fundraiser-pro' ); ?></h2>

			<?php if ( $is_connected ) : ?>
				<div class="notice notice-success inline">
					<p>
						<strong><?php esc_html_e( 'Connected to Stripe!', 'fundraiser-pro' ); ?></strong>
					</p>
				</div>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Stripe Account ID', 'fundraiser-pro' ); ?></th>
						<td><code><?php echo esc_html( $connection_info['stripe_user_id'] ); ?></code></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Mode', 'fundraiser-pro' ); ?></th>
						<td>
							<?php if ( $connection_info['livemode'] ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<strong><?php esc_html_e( 'Live Mode', 'fundraiser-pro' ); ?></strong>
							<?php else : ?>
								<span class="dashicons dashicons-warning" style="color: orange;"></span>
								<strong><?php esc_html_e( 'Test Mode', 'fundraiser-pro' ); ?></strong>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Connected', 'fundraiser-pro' ); ?></th>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $connection_info['connected_at'] ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Permissions', 'fundraiser-pro' ); ?></th>
						<td><code><?php echo esc_html( $connection_info['scope'] ); ?></code></td>
					</tr>
				</table>

				<p>
					<a href="<?php echo esc_url( $this->oauth_manager->get_disconnect_url() ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Disconnect Stripe', 'fundraiser-pro' ); ?>
					</a>
					<a href="https://dashboard.stripe.com/<?php echo $connection_info['livemode'] ? '' : 'test/'; ?>dashboard" class="button" target="_blank">
						<?php esc_html_e( 'Open Stripe Dashboard', 'fundraiser-pro' ); ?>
					</a>
				</p>

			<?php else : ?>
				<p><?php esc_html_e( 'Connect your Stripe account to enable automated payouts for fundraisers.', 'fundraiser-pro' ); ?></p>

				<p>
					<a href="<?php echo esc_url( $this->oauth_manager->get_connect_url() ); ?>" class="button button-primary button-hero" style="background: #635bff; border-color: #635bff; text-shadow: none;">
						<span class="dashicons dashicons-admin-plugins" style="margin-top: 4px;"></span>
						<?php esc_html_e( 'Connect with Stripe', 'fundraiser-pro' ); ?>
					</a>
				</p>

				<p class="description">
					<?php esc_html_e( "You'll be redirected to Stripe to authorize the connection. This allows the platform to create vendor accounts and process automated payouts.", 'fundraiser-pro' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render payout statistics.
	 */
	private function render_payout_statistics() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fundraiser_payouts';

		// Get payout stats
		$total_payouts = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$total_amount = $wpdb->get_var( "SELECT SUM(amount) FROM {$table_name} WHERE status = 'completed'" );
		$pending_payouts = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'" );
		$pending_amount = $wpdb->get_var( "SELECT SUM(amount) FROM {$table_name} WHERE status = 'pending'" );

		?>
		<div class="card" style="max-width: 800px;">
			<h2><?php esc_html_e( 'Payout Statistics', 'fundraiser-pro' ); ?></h2>

			<table class="widefat striped">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Total Payouts Processed', 'fundraiser-pro' ); ?></strong></td>
						<td><?php echo esc_html( number_format( $total_payouts ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Total Amount Paid', 'fundraiser-pro' ); ?></strong></td>
						<td>$<?php echo esc_html( number_format( floatval( $total_amount ), 2 ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Pending Payouts', 'fundraiser-pro' ); ?></strong></td>
						<td><?php echo esc_html( number_format( $pending_payouts ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Pending Amount', 'fundraiser-pro' ); ?></strong></td>
						<td>$<?php echo esc_html( number_format( floatval( $pending_amount ), 2 ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render setup instructions.
	 */
	private function render_setup_instructions() {
		?>
		<div class="card" style="max-width: 800px;">
			<h2><?php esc_html_e( 'Setup Instructions', 'fundraiser-pro' ); ?></h2>

			<h3><?php esc_html_e( '1. Create a Stripe Connect Application', 'fundraiser-pro' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Go to', 'fundraiser-pro' ); ?> <a href="https://dashboard.stripe.com/settings/applications" target="_blank">https://dashboard.stripe.com/settings/applications</a></li>
				<li><?php esc_html_e( 'Click "New Application"', 'fundraiser-pro' ); ?></li>
				<li><?php esc_html_e( 'Name: "Fundraiser Platform" (or your choice)', 'fundraiser-pro' ); ?></li>
				<li><?php esc_html_e( 'Copy your Client ID and paste it below', 'fundraiser-pro' ); ?></li>
			</ol>

			<h3><?php esc_html_e( '2. Add Credentials to wp-config.php', 'fundraiser-pro' ); ?></h3>
			<p><?php esc_html_e( 'Add these lines to your wp-config.php file:', 'fundraiser-pro' ); ?></p>
			<pre style="background: #f5f5f5; padding: 15px; border-left: 4px solid #635bff; overflow-x: auto;">
// Stripe Connect OAuth
define( 'STRIPE_CLIENT_ID', 'ca_XXXXXXXXXXXXXXXXXXXX' );
define( 'STRIPE_CLIENT_SECRET', 'sk_XXXXXXXXXXXXXXXXXXXX' );
			</pre>

			<p class="description">
				<strong><?php esc_html_e( 'Note:', 'fundraiser-pro' ); ?></strong>
				<?php esc_html_e( 'For security, use Test mode credentials during development, then switch to Live mode when ready for production.', 'fundraiser-pro' ); ?>
			</p>

			<h3><?php esc_html_e( '3. Configure Redirect URI', 'fundraiser-pro' ); ?></h3>
			<p><?php esc_html_e( 'In your Stripe application settings, add this redirect URI:', 'fundraiser-pro' ); ?></p>
			<pre style="background: #f5f5f5; padding: 15px; border-left: 4px solid #635bff; overflow-x: auto;">
<?php echo esc_html( admin_url( 'admin.php?page=fundraiser-pro-stripe' ) ); ?>
			</pre>

			<h3><?php esc_html_e( '4. Test the Connection', 'fundraiser-pro' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Click "Connect with Stripe" button above', 'fundraiser-pro' ); ?></li>
				<li><?php esc_html_e( 'Log in to your Stripe account', 'fundraiser-pro' ); ?></li>
				<li><?php esc_html_e( 'Authorize the connection', 'fundraiser-pro' ); ?></li>
				<li><?php esc_html_e( "You'll be redirected back here with a success message", 'fundraiser-pro' ); ?></li>
			</ol>
		</div>
		<?php
	}

	/**
	 * Handle disconnect action.
	 */
	public function handle_disconnect() {
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'stripe_disconnect' ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'stripe_disconnect' ) ) {
			wp_die( 'Invalid nonce' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		if ( $this->oauth_manager ) {
			$this->oauth_manager->disconnect();
		}

		wp_redirect(
			add_query_arg(
				array(
					'page'                => 'fundraiser-pro-stripe',
					'stripe_disconnected' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Show connection notices.
	 */
	public function show_connection_notices() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fundraiser-pro-stripe' ) {
			return;
		}

		if ( isset( $_GET['stripe_connected'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Stripe connected successfully!', 'fundraiser-pro' ); ?></strong></p>
			</div>
			<?php
		}

		if ( isset( $_GET['stripe_disconnected'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Stripe disconnected successfully.', 'fundraiser-pro' ); ?></strong></p>
			</div>
			<?php
		}
	}
}
