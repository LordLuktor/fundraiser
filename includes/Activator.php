<?php
/**
 * Fired during plugin activation.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * Activator class.
 */
class Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create campaigns table
		$sql = "CREATE TABLE IF NOT EXISTS {$prefix}campaigns (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description longtext,
			goal_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			current_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			fundraiser_id bigint(20) UNSIGNED NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			start_date datetime DEFAULT NULL,
			end_date datetime DEFAULT NULL,
			featured_image varchar(500) DEFAULT NULL,
			category varchar(100) DEFAULT NULL,
			settings longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY fundraiser_id (fundraiser_id),
			KEY status (status),
			KEY category (category),
			KEY start_date (start_date),
			KEY end_date (end_date)
		) $charset_collate;";
		dbDelta( $sql );

		// Create raffles table
		$sql = "CREATE TABLE IF NOT EXISTS {$prefix}raffles (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) UNSIGNED NOT NULL,
			title varchar(255) NOT NULL,
			description longtext,
			prize_details longtext,
			ticket_price decimal(10,2) NOT NULL DEFAULT 0.00,
			total_tickets int(11) NOT NULL DEFAULT 0,
			tickets_sold int(11) NOT NULL DEFAULT 0,
			max_tickets_per_customer int(11) DEFAULT NULL,
			draw_date datetime DEFAULT NULL,
			winner_id bigint(20) UNSIGNED DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			product_id bigint(20) UNSIGNED DEFAULT NULL,
			settings longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY campaign_id (campaign_id),
			KEY status (status),
			KEY draw_date (draw_date),
			KEY product_id (product_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Create raffle tickets table
		$sql = "CREATE TABLE IF NOT EXISTS {$prefix}raffle_tickets (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			raffle_id bigint(20) UNSIGNED NOT NULL,
			ticket_number varchar(50) NOT NULL,
			order_id bigint(20) UNSIGNED NOT NULL,
			customer_id bigint(20) UNSIGNED NOT NULL,
			customer_email varchar(100) NOT NULL,
			purchase_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			is_winner tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY ticket_number (raffle_id, ticket_number),
			KEY raffle_id (raffle_id),
			KEY order_id (order_id),
			KEY customer_id (customer_id),
			KEY is_winner (is_winner)
		) $charset_collate;";
		dbDelta( $sql );

		// Create cash transactions table
		$sql = "CREATE TABLE IF NOT EXISTS {$prefix}cash_transactions (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) UNSIGNED NOT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			donor_name varchar(255) NOT NULL,
			donor_email varchar(100) DEFAULT NULL,
			donor_phone varchar(50) DEFAULT NULL,
			donor_address text,
			fundraiser_id bigint(20) UNSIGNED NOT NULL,
			entry_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			approval_status varchar(20) NOT NULL DEFAULT 'pending',
			approved_by bigint(20) UNSIGNED DEFAULT NULL,
			approved_date datetime DEFAULT NULL,
			receipt_number varchar(100) DEFAULT NULL,
			notes text,
			anonymous tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY receipt_number (receipt_number),
			KEY campaign_id (campaign_id),
			KEY fundraiser_id (fundraiser_id),
			KEY approval_status (approval_status),
			KEY entry_date (entry_date)
		) $charset_collate;";
		dbDelta( $sql );

		// Create product requests table
		$sql = "CREATE TABLE IF NOT EXISTS {$prefix}product_requests (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) UNSIGNED NOT NULL,
			fundraiser_id bigint(20) UNSIGNED NOT NULL,
			products_requested longtext NOT NULL,
			artwork_files longtext NOT NULL,
			notes text,
			status varchar(20) NOT NULL DEFAULT 'pending',
			admin_notes text,
			processed_by bigint(20) UNSIGNED DEFAULT NULL,
			processed_date datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY campaign_id (campaign_id),
			KEY fundraiser_id (fundraiser_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql );

		// Create campaign analytics table
		$sql = "CREATE TABLE IF NOT EXISTS {$prefix}campaign_analytics (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) UNSIGNED NOT NULL,
			date date NOT NULL,
			donations_count int(11) NOT NULL DEFAULT 0,
			donations_total decimal(10,2) NOT NULL DEFAULT 0.00,
			raffle_sales_count int(11) NOT NULL DEFAULT 0,
			raffle_sales_total decimal(10,2) NOT NULL DEFAULT 0.00,
			unique_donors int(11) NOT NULL DEFAULT 0,
			page_views int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY campaign_date (campaign_id, date),
			KEY campaign_id (campaign_id),
			KEY date (date)
		) $charset_collate;";
		dbDelta( $sql );

		// Create AI conversations table
		$sql = "CREATE TABLE IF NOT EXISTS {$prefix}ai_conversations (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			campaign_id bigint(20) UNSIGNED DEFAULT NULL,
			conversation_history longtext,
			tokens_used int(11) NOT NULL DEFAULT 0,
			cost decimal(10,4) NOT NULL DEFAULT 0.0000,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY campaign_id (campaign_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Create email log table
		$sql = "CREATE TABLE IF NOT EXISTS {$prefix}email_log (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			recipient varchar(100) NOT NULL,
			subject varchar(255) NOT NULL,
			template varchar(100) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			error_message text,
			sent_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY recipient (recipient),
			KEY template (template),
			KEY status (status),
			KEY sent_at (sent_at)
		) $charset_collate;";
		dbDelta( $sql );

		// Create activity log table for audit trail
		$sql = "CREATE TABLE IF NOT EXISTS {$prefix}activity_log (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			action varchar(100) NOT NULL,
			object_type varchar(50) NOT NULL,
			object_id bigint(20) UNSIGNED NOT NULL,
			old_value longtext,
			new_value longtext,
			ip_address varchar(45) DEFAULT NULL,
			user_agent varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY action (action),
			KEY object_type (object_type),
			KEY object_id (object_id),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql );

		// Create custom roles and capabilities
		self::create_roles_and_capabilities();

		// Set default options
		self::set_default_options();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set activation timestamp
		update_option( 'fundraiser_pro_activated', time() );
		update_option( 'fundraiser_pro_version', FUNDRAISER_PRO_VERSION );
	}

	/**
	 * Create custom roles and capabilities.
	 */
	private static function create_roles_and_capabilities() {
		// Add administrator capabilities
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_fundraiser_campaigns' );
			$admin->add_cap( 'manage_fundraiser_raffles' );
			$admin->add_cap( 'manage_fundraiser_settings' );
			$admin->add_cap( 'approve_cash_transactions' );
			$admin->add_cap( 'view_fundraiser_analytics' );
			$admin->add_cap( 'manage_fundraisers' );
			$admin->add_cap( 'export_fundraiser_data' );
		}

		// Create Fundraiser role
		add_role(
			'fundraiser',
			__( 'Fundraiser', 'fundraiser-pro' ),
			array(
				'read' => true,
				'edit_posts' => false,
				'delete_posts' => false,
				'upload_files' => true,
				'manage_own_campaigns' => true,
				'enter_cash_payments' => true,
				'view_own_analytics' => true,
				'manage_own_raffles' => true,
			)
		);
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		$defaults = array(
			'currency' => 'USD',
			'currency_symbol' => '$',
			'currency_position' => 'before',
			'decimal_separator' => '.',
			'thousand_separator' => ',',
			'number_of_decimals' => 2,
			'date_format' => 'Y-m-d',
			'time_format' => 'H:i:s',
			'receipt_prefix' => 'FP',
			'receipt_number_start' => 1000,
			'receipt_number_padding' => 6,
			'email_from_name' => get_bloginfo( 'name' ),
			'email_from_email' => get_bloginfo( 'admin_email' ),
			'enable_recurring_donations' => true,
			'enable_anonymous_donations' => true,
			'enable_donor_wall' => true,
			'enable_ai_assistant' => false,
			'openai_api_key' => '',
			'openai_model' => 'gpt-4-turbo-preview',
			'openai_max_tokens' => 1000,
			'campaign_default_duration' => 30,
			'cash_approval_email_notify' => true,
			'milestone_emails_enabled' => true,
		);

		foreach ( $defaults as $key => $value ) {
			$option_name = 'fundraiser_pro_' . $key;
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $value );
			}
		}
	}
}
