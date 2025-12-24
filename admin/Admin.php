<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro\Admin;

/**
 * Admin class.
 */
class Admin {

	/**
	 * Add admin menu pages.
	 */
	public function add_admin_menu() {
		// Main menu page
		add_menu_page(
			__( 'Fundraiser Pro', 'fundraiser-pro' ),
			__( 'Fundraiser Pro', 'fundraiser-pro' ),
			'manage_fundraiser_campaigns',
			'fundraiser-pro',
			array( $this, 'display_dashboard_page' ),
			'dashicons-heart',
			30
		);

		// Dashboard submenu (default)
		add_submenu_page(
			'fundraiser-pro',
			__( 'Dashboard', 'fundraiser-pro' ),
			__( 'Dashboard', 'fundraiser-pro' ),
			'manage_fundraiser_campaigns',
			'fundraiser-pro',
			array( $this, 'display_dashboard_page' )
		);

		// Analytics submenu
		add_submenu_page(
			'fundraiser-pro',
			__( 'Analytics', 'fundraiser-pro' ),
			__( 'Analytics', 'fundraiser-pro' ),
			'view_fundraiser_analytics',
			'fundraiser-pro-analytics',
			array( $this, 'display_analytics_page' )
		);

		// Cash Transactions submenu
		add_submenu_page(
			'fundraiser-pro',
			__( 'Cash Transactions', 'fundraiser-pro' ),
			__( 'Cash Transactions', 'fundraiser-pro' ),
			'enter_cash_payments',
			'fundraiser-pro-cash',
			array( $this, 'display_cash_transactions_page' )
		);

		// Reports submenu
		add_submenu_page(
			'fundraiser-pro',
			__( 'Reports', 'fundraiser-pro' ),
			__( 'Reports', 'fundraiser-pro' ),
			'view_fundraiser_analytics',
			'fundraiser-pro-reports',
			array( $this, 'display_reports_page' )
		);

		// Settings submenu
		add_submenu_page(
			'fundraiser-pro',
			__( 'Settings', 'fundraiser-pro' ),
			__( 'Settings', 'fundraiser-pro' ),
			'manage_fundraiser_settings',
			'fundraiser-pro-settings',
			array( $this, 'display_settings_page' )
		);
	}

	/**
	 * Enqueue admin styles.
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();

		// Only load on our plugin pages
		if ( strpos( $screen->id, 'fundraiser' ) !== false ||
		     strpos( $screen->post_type, 'fundraiser' ) !== false ) {

			wp_enqueue_style(
				'fundraiser-pro-admin',
				FUNDRAISER_PRO_URL . 'assets/css/admin.css',
				array(),
				FUNDRAISER_PRO_VERSION,
				'all'
			);

			// Chart.js for analytics
			wp_enqueue_style(
				'chart-js',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.css',
				array(),
				'4.4.0'
			);
		}
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		// Only load on our plugin pages
		if ( strpos( $screen->id, 'fundraiser' ) !== false ||
		     strpos( $screen->post_type, 'fundraiser' ) !== false ) {

			// Chart.js for analytics
			wp_enqueue_script(
				'chart-js',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
				array(),
				'4.4.0',
				true
			);

			// Main admin script
			wp_enqueue_script(
				'fundraiser-pro-admin',
				FUNDRAISER_PRO_URL . 'assets/js/admin.js',
				array( 'jquery', 'chart-js' ),
				FUNDRAISER_PRO_VERSION,
				true
			);

			// Localize script with AJAX URL and nonces
			wp_localize_script(
				'fundraiser-pro-admin',
				'fundraiserProAdmin',
				array(
					'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
					'nonce'             => wp_create_nonce( 'fundraiser_pro_admin' ),
					'strings'           => array(
						'confirmDelete' => __( 'Are you sure you want to delete this?', 'fundraiser-pro' ),
						'saving'        => __( 'Saving...', 'fundraiser-pro' ),
						'saved'         => __( 'Saved!', 'fundraiser-pro' ),
						'error'         => __( 'An error occurred. Please try again.', 'fundraiser-pro' ),
					),
				)
			);
		}
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		// General Settings
		register_setting( 'fundraiser_pro_general', 'fundraiser_pro_currency' );
		register_setting( 'fundraiser_pro_general', 'fundraiser_pro_currency_symbol' );
		register_setting( 'fundraiser_pro_general', 'fundraiser_pro_currency_position' );
		register_setting( 'fundraiser_pro_general', 'fundraiser_pro_date_format' );

		// Email Settings
		register_setting( 'fundraiser_pro_email', 'fundraiser_pro_email_from_name' );
		register_setting( 'fundraiser_pro_email', 'fundraiser_pro_email_from_email' );
		register_setting( 'fundraiser_pro_email', 'fundraiser_pro_milestone_emails_enabled' );

		// AI Settings
		register_setting( 'fundraiser_pro_ai', 'fundraiser_pro_enable_ai_assistant', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
		) );
		register_setting( 'fundraiser_pro_ai', 'fundraiser_pro_openai_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_api_key' ),
		) );
		register_setting( 'fundraiser_pro_ai', 'fundraiser_pro_openai_model' );

		// Receipt Settings
		register_setting( 'fundraiser_pro_receipt', 'fundraiser_pro_receipt_prefix' );
		register_setting( 'fundraiser_pro_receipt', 'fundraiser_pro_receipt_number_start' );
		register_setting( 'fundraiser_pro_receipt', 'fundraiser_pro_receipt_logo' );
	}

	/**
	 * Sanitize API key (encrypt for storage).
	 *
	 * @param string $value API key value.
	 * @return string Encrypted API key.
	 */
	public function sanitize_api_key( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Basic encryption using base64 (in production, use proper encryption)
		return base64_encode( $value );
	}

	/**
	 * Display dashboard page.
	 */
	public function display_dashboard_page() {
		require_once FUNDRAISER_PRO_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Display analytics page.
	 */
	public function display_analytics_page() {
		require_once FUNDRAISER_PRO_PATH . 'admin/views/analytics.php';
	}

	/**
	 * Display cash transactions page.
	 */
	public function display_cash_transactions_page() {
		require_once FUNDRAISER_PRO_PATH . 'admin/views/cash-transactions.php';
	}

	/**
	 * Display reports page.
	 */
	public function display_reports_page() {
		require_once FUNDRAISER_PRO_PATH . 'admin/views/reports.php';
	}

	/**
	 * Display settings page.
	 */
	public function display_settings_page() {
		require_once FUNDRAISER_PRO_PATH . 'admin/views/settings.php';
	}
}
