<?php
/**
 * Admin Settings View.
 *
 * @package FundraiserPro
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Save settings if form was submitted
if ( isset( $_POST['fundraiser_pro_save_settings'] ) && check_admin_referer( 'fundraiser_pro_settings' ) ) {
	// General Settings
	update_option( 'fundraiser_pro_currency', sanitize_text_field( $_POST['currency'] ?? 'USD' ) );
	update_option( 'fundraiser_pro_currency_position', sanitize_text_field( $_POST['currency_position'] ?? 'before' ) );
	update_option( 'fundraiser_pro_thousand_separator', sanitize_text_field( $_POST['thousand_separator'] ?? ',' ) );
	update_option( 'fundraiser_pro_decimal_separator', sanitize_text_field( $_POST['decimal_separator'] ?? '.' ) );

	// Email Settings
	update_option( 'fundraiser_pro_email_from_name', sanitize_text_field( $_POST['email_from_name'] ?? get_bloginfo( 'name' ) ) );
	update_option( 'fundraiser_pro_email_from_address', sanitize_email( $_POST['email_from_address'] ?? get_bloginfo( 'admin_email' ) ) );

	// Fundraiser Notification Settings
	update_option( "fundraiser_pro_notify_fundraisers_on_sale", isset( $_POST["notify_fundraisers_on_sale"] ) ? 1 : 0 );
	update_option( "fundraiser_pro_notify_fundraisers_on_donation", isset( $_POST["notify_fundraisers_on_donation"] ) ? 1 : 0 );
	update_option( "fundraiser_pro_notify_fundraisers_on_raffle", isset( $_POST["notify_fundraisers_on_raffle"] ) ? 1 : 0 );

	// AI Settings
	update_option( 'fundraiser_pro_enable_ai_assistant', isset( $_POST['enable_ai_assistant'] ) ? 1 : 0 );

	if ( ! empty( $_POST['openai_api_key'] ) ) {
		// Encrypt API key (basic base64 - use proper encryption in production)
		update_option( 'fundraiser_pro_openai_api_key', base64_encode( sanitize_text_field( $_POST['openai_api_key'] ) ) );
	}

	update_option( 'fundraiser_pro_openai_model', sanitize_text_field( $_POST['openai_model'] ?? 'gpt-4-turbo-preview' ) );
	update_option( 'fundraiser_pro_openai_max_tokens', absint( $_POST['openai_max_tokens'] ?? 1000 ) );

	// Receipt Settings
	update_option( 'fundraiser_pro_receipt_prefix', sanitize_text_field( $_POST['receipt_prefix'] ?? 'FP-' ) );
	update_option( 'fundraiser_pro_receipt_number_length', absint( $_POST['receipt_number_length'] ?? 6 ) );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully!', 'fundraiser-pro' ) . '</p></div>';
}

// Get current settings
$currency = get_option( 'fundraiser_pro_currency', 'USD' );
$currency_position = get_option( 'fundraiser_pro_currency_position', 'before' );
$thousand_separator = get_option( 'fundraiser_pro_thousand_separator', ',' );
$decimal_separator = get_option( 'fundraiser_pro_decimal_separator', '.' );

$email_from_name = get_option( 'fundraiser_pro_email_from_name', get_bloginfo( 'name' ) );
$email_from_address = get_option( 'fundraiser_pro_email_from_address', get_bloginfo( 'admin_email' ) );
$notify_on_sale = get_option( "fundraiser_pro_notify_fundraisers_on_sale", 1 );
$notify_on_donation = get_option( "fundraiser_pro_notify_fundraisers_on_donation", 1 );
$notify_on_raffle = get_option( "fundraiser_pro_notify_fundraisers_on_raffle", 1 );

$enable_ai = get_option( 'fundraiser_pro_enable_ai_assistant', false );
$openai_model = get_option( 'fundraiser_pro_openai_model', 'gpt-4-turbo-preview' );
$openai_max_tokens = get_option( 'fundraiser_pro_openai_max_tokens', 1000 );

$receipt_prefix = get_option( 'fundraiser_pro_receipt_prefix', 'FP-' );
$receipt_number_length = get_option( 'fundraiser_pro_receipt_number_length', 6 );

?>

<div class="fundraiser-pro-wrap">
	<div class="fundraiser-pro-header">
		<h1><?php esc_html_e( 'Fundraiser Pro Settings', 'fundraiser-pro' ); ?></h1>
		<p><?php esc_html_e( 'Configure your fundraising platform', 'fundraiser-pro' ); ?></p>
	</div>

	<form method="post" action="">
		<?php wp_nonce_field( 'fundraiser_pro_settings' ); ?>

		<!-- Tab Navigation -->
		<div class="fp-tabs">
			<button type="button" class="fp-tab-btn active" data-tab="general">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'General', 'fundraiser-pro' ); ?>
			</button>
			<button type="button" class="fp-tab-btn" data-tab="email">
				<span class="dashicons dashicons-email"></span>
				<?php esc_html_e( 'Email', 'fundraiser-pro' ); ?>
			</button>
			<button type="button" class="fp-tab-btn" data-tab="ai">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'AI Assistant', 'fundraiser-pro' ); ?>
			</button>
			<button type="button" class="fp-tab-btn" data-tab="receipts">
				<span class="dashicons dashicons-media-document"></span>
				<?php esc_html_e( 'Receipts', 'fundraiser-pro' ); ?>
			</button>
		</div>

		<!-- General Settings Tab -->
		<div class="fp-tab-content active" data-tab="general">
			<div class="fp-card">
				<div class="fp-card-header">
					<h2 class="fp-card-title"><?php esc_html_e( 'General Settings', 'fundraiser-pro' ); ?></h2>
				</div>
				<div class="fp-card-body">
					<div class="fp-form-group">
						<label for="currency">
							<?php esc_html_e( 'Currency', 'fundraiser-pro' ); ?>
						</label>
						<select name="currency" id="currency" class="fp-input">
							<option value="USD" <?php selected( $currency, 'USD' ); ?>>USD - US Dollar</option>
							<option value="EUR" <?php selected( $currency, 'EUR' ); ?>>EUR - Euro</option>
							<option value="GBP" <?php selected( $currency, 'GBP' ); ?>>GBP - British Pound</option>
							<option value="CAD" <?php selected( $currency, 'CAD' ); ?>>CAD - Canadian Dollar</option>
							<option value="AUD" <?php selected( $currency, 'AUD' ); ?>>AUD - Australian Dollar</option>
						</select>
						<small class="fp-help-text"><?php esc_html_e( 'Currency for displaying amounts', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-form-group">
						<label for="currency_position">
							<?php esc_html_e( 'Currency Position', 'fundraiser-pro' ); ?>
						</label>
						<select name="currency_position" id="currency_position" class="fp-input">
							<option value="before" <?php selected( $currency_position, 'before' ); ?>><?php esc_html_e( 'Before amount ($99.99)', 'fundraiser-pro' ); ?></option>
							<option value="after" <?php selected( $currency_position, 'after' ); ?>><?php esc_html_e( 'After amount (99.99$)', 'fundraiser-pro' ); ?></option>
						</select>
					</div>

					<div class="fp-form-group">
						<label for="thousand_separator">
							<?php esc_html_e( 'Thousand Separator', 'fundraiser-pro' ); ?>
						</label>
						<input type="text" name="thousand_separator" id="thousand_separator" value="<?php echo esc_attr( $thousand_separator ); ?>" class="fp-input" maxlength="1">
						<small class="fp-help-text"><?php esc_html_e( 'Character for thousands (e.g., 1,000)', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-form-group">
						<label for="decimal_separator">
							<?php esc_html_e( 'Decimal Separator', 'fundraiser-pro' ); ?>
						</label>
						<input type="text" name="decimal_separator" id="decimal_separator" value="<?php echo esc_attr( $decimal_separator ); ?>" class="fp-input" maxlength="1">
						<small class="fp-help-text"><?php esc_html_e( 'Character for decimals (e.g., 99.99)', 'fundraiser-pro' ); ?></small>
					</div>
				</div>
			</div>
		</div>

		<!-- Email Settings Tab -->
		<div class="fp-tab-content" data-tab="email">
			<div class="fp-card">
				<div class="fp-card-header">
					<h2 class="fp-card-title"><?php esc_html_e( 'Email Settings', 'fundraiser-pro' ); ?></h2>
				</div>
				<div class="fp-card-body">
					<div class="fp-form-group">
						<label for="email_from_name">
							<?php esc_html_e( 'From Name', 'fundraiser-pro' ); ?>
						</label>
						<input type="text" name="email_from_name" id="email_from_name" value="<?php echo esc_attr( $email_from_name ); ?>" class="fp-input">
						<small class="fp-help-text"><?php esc_html_e( 'Name shown in outgoing emails', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-form-group">
						<label for="email_from_address">
							<?php esc_html_e( 'From Email Address', 'fundraiser-pro' ); ?>
						</label>
						<input type="email" name="email_from_address" id="email_from_address" value="<?php echo esc_attr( $email_from_address ); ?>" class="fp-input">
						<small class="fp-help-text"><?php esc_html_e( 'Email address for outgoing emails', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-alert fp-alert-info">
						<span class="dashicons dashicons-info"></span>
						<strong><?php esc_html_e( 'Tip:', 'fundraiser-pro' ); ?></strong>
						<?php esc_html_e( 'For reliable email delivery, we recommend using an SMTP plugin like WP Mail SMTP or Post SMTP.', 'fundraiser-pro' ); ?>
					</div>


			<!-- Fundraiser Notifications -->
			<div class="fp-card" style="margin-top: 20px;">
				<div class="fp-card-header">
					<h2 class="fp-card-title"><?php esc_html_e( 'Fundraiser Notifications', 'fundraiser-pro' ); ?></h2>
				</div>
				<div class="fp-card-body">
					<p class="fp-help-text" style="margin-bottom: 20px;">
						<?php esc_html_e( 'Control when fundraisers receive email notifications about their campaigns.', 'fundraiser-pro' ); ?>
					</p>

					<div class="fp-form-group">
						<label class="fp-checkbox-label">
							<input type="checkbox" name="notify_fundraisers_on_sale" value="1" <?php checked( $notify_on_sale, 1 ); ?>>
							<?php esc_html_e( 'Notify fundraisers on product sales', 'fundraiser-pro' ); ?>
						</label>
						<small class="fp-help-text"><?php esc_html_e( 'Send an email to the campaign owner when someone purchases a product from their campaign', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-form-group">
						<label class="fp-checkbox-label">
							<input type="checkbox" name="notify_fundraisers_on_donation" value="1" <?php checked( $notify_on_donation, 1 ); ?>>
							<?php esc_html_e( 'Notify fundraisers on donations', 'fundraiser-pro' ); ?>
						</label>
						<small class="fp-help-text"><?php esc_html_e( 'Send an email to the campaign owner when someone makes a donation', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-form-group">
						<label class="fp-checkbox-label">
							<input type="checkbox" name="notify_fundraisers_on_raffle" value="1" <?php checked( $notify_on_raffle, 1 ); ?>>
							<?php esc_html_e( 'Notify fundraisers on raffle ticket purchases', 'fundraiser-pro' ); ?>
						</label>
						<small class="fp-help-text"><?php esc_html_e( 'Send an email to the campaign owner when someone purchases raffle tickets', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-alert fp-alert-success">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Fundraisers can also view all transactions in their campaign dashboard.', 'fundraiser-pro' ); ?>
					</div>
				</div>
			</div>
				</div>
			</div>
		</div>

		<!-- AI Assistant Tab -->
		<div class="fp-tab-content" data-tab="ai">
			<div class="fp-card">
				<div class="fp-card-header">
					<h2 class="fp-card-title"><?php esc_html_e( 'AI Assistant Settings', 'fundraiser-pro' ); ?></h2>
				</div>
				<div class="fp-card-body">
					<div class="fp-form-group">
						<label class="fp-checkbox-label">
							<input type="checkbox" name="enable_ai_assistant" value="1" <?php checked( $enable_ai, 1 ); ?>>
							<?php esc_html_e( 'Enable AI Assistant', 'fundraiser-pro' ); ?>
						</label>
						<small class="fp-help-text"><?php esc_html_e( 'Use OpenAI to help create campaigns, landing pages, generate images, and analyze reports', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-form-group">
						<label for="openai_api_key">
							<?php esc_html_e( 'OpenAI API Key', 'fundraiser-pro' ); ?>
						</label>
						<input type="password" name="openai_api_key" id="openai_api_key" class="fp-input" placeholder="sk-...">
						<small class="fp-help-text">
							<?php esc_html_e( 'Get your API key from', 'fundraiser-pro' ); ?>
							<a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a>
						</small>
					</div>

					<div class="fp-form-group">
						<label for="openai_model">
							<?php esc_html_e( 'Model', 'fundraiser-pro' ); ?>
						</label>
						<select name="openai_model" id="openai_model" class="fp-input">
							<option value="gpt-4-turbo-preview" <?php selected( $openai_model, 'gpt-4-turbo-preview' ); ?>>GPT-4 Turbo (Recommended)</option>
							<option value="gpt-4" <?php selected( $openai_model, 'gpt-4' ); ?>>GPT-4</option>
							<option value="gpt-3.5-turbo" <?php selected( $openai_model, 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo (Faster/Cheaper)</option>
						</select>
						<small class="fp-help-text"><?php esc_html_e( 'GPT-4 Turbo provides best results for complex tasks', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-form-group">
						<label for="openai_max_tokens">
							<?php esc_html_e( 'Max Tokens', 'fundraiser-pro' ); ?>
						</label>
						<input type="number" name="openai_max_tokens" id="openai_max_tokens" value="<?php echo esc_attr( $openai_max_tokens ); ?>" class="fp-input" min="100" max="4000" step="100">
						<small class="fp-help-text"><?php esc_html_e( 'Maximum tokens per request (higher = longer responses but more cost)', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-alert fp-alert-info">
						<span class="dashicons dashicons-lightbulb"></span>
						<strong><?php esc_html_e( 'AI Features Include:', 'fundraiser-pro' ); ?></strong>
						<ul style="margin-top: 10px;">
							<li><?php esc_html_e( 'Campaign content generation and suggestions', 'fundraiser-pro' ); ?></li>
							<li><?php esc_html_e( 'Automated landing page creation with compelling copy', 'fundraiser-pro' ); ?></li>
							<li><?php esc_html_e( 'Image generation from prompts for campaign visuals', 'fundraiser-pro' ); ?></li>
							<li><?php esc_html_e( 'Analytics reporting with insights and recommendations', 'fundraiser-pro' ); ?></li>
							<li><?php esc_html_e( 'Comprehensive fundraising assistance', 'fundraiser-pro' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<!-- Receipt Settings Tab -->
		<div class="fp-tab-content" data-tab="receipts">
			<div class="fp-card">
				<div class="fp-card-header">
					<h2 class="fp-card-title"><?php esc_html_e( 'Receipt Settings', 'fundraiser-pro' ); ?></h2>
				</div>
				<div class="fp-card-body">
					<div class="fp-form-group">
						<label for="receipt_prefix">
							<?php esc_html_e( 'Receipt Number Prefix', 'fundraiser-pro' ); ?>
						</label>
						<input type="text" name="receipt_prefix" id="receipt_prefix" value="<?php echo esc_attr( $receipt_prefix ); ?>" class="fp-input" maxlength="10">
						<small class="fp-help-text"><?php esc_html_e( 'Prefix for receipt numbers (e.g., FP-000001)', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-form-group">
						<label for="receipt_number_length">
							<?php esc_html_e( 'Receipt Number Length', 'fundraiser-pro' ); ?>
						</label>
						<input type="number" name="receipt_number_length" id="receipt_number_length" value="<?php echo esc_attr( $receipt_number_length ); ?>" class="fp-input" min="4" max="10">
						<small class="fp-help-text"><?php esc_html_e( 'Number of digits in receipt numbers', 'fundraiser-pro' ); ?></small>
					</div>

					<div class="fp-alert fp-alert-info">
						<span class="dashicons dashicons-media-document"></span>
						<?php esc_html_e( 'Receipts are automatically generated for approved cash transactions and online donations.', 'fundraiser-pro' ); ?>
					</div>
				</div>
			</div>
		</div>


		<!-- Payouts & Stripe Tab -->
		<div class="fp-tab-content" data-tab="payouts">
			<div class="fp-card">
				<div class="fp-card-header">
					<h2>
						<span class="dashicons dashicons-money-alt"></span>
						<?php esc_html_e( 'Stripe Connect Integration', 'fundraiser-pro' ); ?>
					</h2>
					<p><?php esc_html_e( 'Connect your Stripe account to enable automated payouts to fundraisers', 'fundraiser-pro' ); ?></p>
				</div>
				<div class="fp-card-body">
					<?php
					$stripe_oauth = new FundraiserPro\StripeOAuthManager();
					$is_connected = $stripe_oauth->is_connected();
					?>

					<?php if ( $is_connected ) : ?>
						<!-- Connected State -->
						<div class="fp-alert fp-alert-success">
							<span class="dashicons dashicons-yes-alt"></span>
							<strong><?php esc_html_e( 'Stripe Connected!', 'fundraiser-pro' ); ?></strong>
							<p><?php esc_html_e( 'Your Stripe account is connected and ready to process payouts.', 'fundraiser-pro' ); ?></p>
						</div>

						<div class="fp-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
							<div class="fp-stat-card" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0;">
								<div style="font-size: 2em; font-weight: bold; color: #667eea; margin-bottom: 10px;">
									<?php
									global $wpdb;
									$connected_vendors = $wpdb->get_var(
										"SELECT COUNT(*) FROM {$wpdb->prefix}fundraiser_payout_accounts
										WHERE payout_method = 'stripe' AND account_status = 'verified'"
									);
									echo number_format( $connected_vendors );
									?>
								</div>
								<div style="color: #666;"><?php esc_html_e( 'Connected Fundraisers', 'fundraiser-pro' ); ?></div>
							</div>

							<div class="fp-stat-card" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0;">
								<div style="font-size: 2em; font-weight: bold; color: #667eea; margin-bottom: 10px;">
									<?php
									global $wpdb;
									$total_payouts = $wpdb->get_var(
										"SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}fundraiser_payouts
										WHERE status = 'completed'"
									);
									echo '$' . number_format( $total_payouts, 2 );
									?>
								</div>
								<div style="color: #666;"><?php esc_html_e( 'Total Payouts', 'fundraiser-pro' ); ?></div>
							</div>

							<div class="fp-stat-card" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0;">
								<div style="font-size: 2em; font-weight: bold; color: #667eea; margin-bottom: 10px;">7%</div>
								<div style="color: #666;"><?php esc_html_e( 'Platform Commission', 'fundraiser-pro' ); ?></div>
							</div>
						</div>

					<?php else : ?>
						<!-- Not Connected State -->
						<div class="fp-alert fp-alert-warning" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
							<span class="dashicons dashicons-warning"></span>
							<strong><?php esc_html_e( 'Stripe Not Connected', 'fundraiser-pro' ); ?></strong>
							<p><?php esc_html_e( 'Connect your Stripe account to enable automated payouts.', 'fundraiser-pro' ); ?></p>
						</div>

						<div class="fp-form-group">
							<h3><?php esc_html_e( 'How It Works', 'fundraiser-pro' ); ?></h3>
							<ol style="line-height: 1.8; color: #666;">
								<li><?php esc_html_e( 'Connect your platform Stripe account (one-time setup)', 'fundraiser-pro' ); ?></li>
								<li><?php esc_html_e( 'Fundraisers connect their Stripe accounts via dashboard', 'fundraiser-pro' ); ?></li>
								<li><?php esc_html_e( 'Payouts happen automatically after orders complete', 'fundraiser-pro' ); ?></li>
								<li><?php esc_html_e( '7% commission retained, 93% sent to fundraiser', 'fundraiser-pro' ); ?></li>
							</ol>
						</div>

						<?php if ( defined( 'STRIPE_CLIENT_ID' ) && defined( 'STRIPE_CLIENT_SECRET' ) ) : ?>
							<div class="fp-form-group">
								<a href="<?php echo esc_url( $stripe_oauth->get_connect_url() ); ?>" class="button button-primary button-hero">
									<span class="dashicons dashicons-admin-plugins"></span>
									<?php esc_html_e( 'Connect with Stripe', 'fundraiser-pro' ); ?>
								</a>
							</div>
						<?php else : ?>
							<div class="fp-alert fp-alert-error" style="background: #f8d7da; border: 1px solid #f5c2c7; padding: 15px; border-radius: 4px;">
								<span class="dashicons dashicons-warning"></span>
								<strong><?php esc_html_e( 'Stripe Credentials Missing', 'fundraiser-pro' ); ?></strong>
								<p>
									<?php
									esc_html_e( 'Add your Stripe Connect credentials to wp-config.php:', 'fundraiser-pro' );
									?>
								</p>
								<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">define( 'STRIPE_CLIENT_ID', 'ca_your_client_id_here' );
define( 'STRIPE_CLIENT_SECRET', 'sk_live_your_secret_key_here' );</pre>
								<p>
									<a href="https://dashboard.stripe.com/settings/applications" target="_blank" rel="noopener">
										<?php esc_html_e( 'Create a Stripe Connect application', 'fundraiser-pro' ); ?> →
									</a>
								</p>
							</div>
						<?php endif; ?>

					<?php endif; ?>

					<div class="fp-alert fp-alert-info" style="background: #cfe2ff; border: 1px solid #b6d4fe; padding: 15px; border-radius: 4px; margin-top: 20px;">
						<span class="dashicons dashicons-info"></span>
						<strong><?php esc_html_e( 'About Stripe Connect', 'fundraiser-pro' ); ?></strong>
						<p>
							<?php
							esc_html_e( 'Stripe Connect allows you to create instant payouts to fundraisers\' bank accounts. Each fundraiser connects their own Stripe account, ensuring secure and compliant payment processing.', 'fundraiser-pro' );
							?>
						</p>
					</div>
				</div>
			</div>

			<!-- Payout Settings -->
			<div class="fp-card" style="margin-top: 20px;">
				<div class="fp-card-header">
					<h2>
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Payout Settings', 'fundraiser-pro' ); ?>
					</h2>
				</div>
				<div class="fp-card-body">
					<div class="fp-form-group">
						<label>
							<input type="checkbox" name="enable_auto_payouts" value="1"
								   <?php checked( get_option( 'fundraiser_pro_enable_auto_payouts', 1 ) ); ?>>
							<?php esc_html_e( 'Enable automatic payouts', 'fundraiser-pro' ); ?>
						</label>
						<small class="fp-help-text">
							<?php esc_html_e( 'Automatically send payouts when commissions are due', 'fundraiser-pro' ); ?>
						</small>
					</div>

					<div class="fp-form-group">
						<label for="payout_schedule">
							<?php esc_html_e( 'Payout Schedule', 'fundraiser-pro' ); ?>
						</label>
						<select name="payout_schedule" id="payout_schedule" class="fp-input">
							<option value="instant" <?php selected( get_option( 'fundraiser_pro_payout_schedule', 'instant' ), 'instant' ); ?>>
								<?php esc_html_e( 'Instant (after order completion)', 'fundraiser-pro' ); ?>
							</option>
							<option value="daily" <?php selected( get_option( 'fundraiser_pro_payout_schedule' ), 'daily' ); ?>>
								<?php esc_html_e( 'Daily', 'fundraiser-pro' ); ?>
							</option>
							<option value="weekly" <?php selected( get_option( 'fundraiser_pro_payout_schedule' ), 'weekly' ); ?>>
								<?php esc_html_e( 'Weekly', 'fundraiser-pro' ); ?>
							</option>
							<option value="monthly" <?php selected( get_option( 'fundraiser_pro_payout_schedule' ), 'monthly' ); ?>>
								<?php esc_html_e( 'Monthly', 'fundraiser-pro' ); ?>
							</option>
						</select>
					</div>

					<div class="fp-form-group">
						<label for="minimum_payout">
							<?php esc_html_e( 'Minimum Payout Amount', 'fundraiser-pro' ); ?>
						</label>
						<input type="number" name="minimum_payout" id="minimum_payout"
							   value="<?php echo esc_attr( get_option( 'fundraiser_pro_minimum_payout', '25.00' ) ); ?>"
							   class="fp-input" step="0.01" min="0">
						<small class="fp-help-text">
							<?php esc_html_e( 'Minimum commission balance required before payout', 'fundraiser-pro' ); ?>
						</small>
					</div>
				</div>
			</div>
		</div>
		<!-- Submit Button -->
		<div class="fp-form-actions">
			<button type="submit" name="fundraiser_pro_save_settings" class="fp-btn fp-btn-primary fp-btn-lg">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e( 'Save Settings', 'fundraiser-pro' ); ?>
			</button>
		</div>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	// Tab switching
	$('.fp-tab-btn').on('click', function() {
		var tab = $(this).data('tab');

		$('.fp-tab-btn').removeClass('active');
		$(this).addClass('active');

		$('.fp-tab-content').removeClass('active');
		$('.fp-tab-content[data-tab="' + tab + '"]').addClass('active');
	});
});
</script>
