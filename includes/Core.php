<?php
/**
 * The core plugin class.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * Core class.
 */
class Core {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Initialize the plugin.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_woocommerce_hooks();
		$this->define_cron_hooks();
		$this->define_rest_api_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		require_once FUNDRAISER_PRO_PATH . 'includes/Loader.php';
		$this->loader = new Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'fundraiser-pro',
			false,
			dirname( FUNDRAISER_PRO_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Register all admin-related hooks.
	 */
	private function define_admin_hooks() {
		// Admin Core
		require_once FUNDRAISER_PRO_PATH . 'admin/Admin.php';
		$admin = new \FundraiserPro\Admin\Admin();
		$this->loader->add_action( 'admin_menu', $admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_init', $admin, 'register_settings' );

		// Custom Post Types
		require_once FUNDRAISER_PRO_PATH . 'includes/PostTypes.php';
		$post_types = new PostTypes();
		$this->loader->add_action( 'init', $post_types, 'register_post_types' );
		$this->loader->add_action( 'init', $post_types, 'register_taxonomies' );

		// Campaign Management
		require_once FUNDRAISER_PRO_PATH . 'admin/CampaignAdmin.php';
		$campaign_admin = new \FundraiserPro\Admin\CampaignAdmin();
		$this->loader->add_action( 'add_meta_boxes', $campaign_admin, 'add_meta_boxes' );
		$this->loader->add_action( 'save_post', $campaign_admin, 'save_campaign_meta', 10, 2 );

		// Raffle Management
		require_once FUNDRAISER_PRO_PATH . 'admin/RaffleAdmin.php';
		$raffle_admin = new \FundraiserPro\Admin\RaffleAdmin();
		$this->loader->add_action( 'add_meta_boxes', $raffle_admin, 'add_meta_boxes' );
		$this->loader->add_action( 'save_post', $raffle_admin, 'save_raffle_meta', 10, 2 );

		// Cash Transaction Management
		require_once FUNDRAISER_PRO_PATH . 'admin/CashTransactionAdmin.php';
		$cash_admin = new \FundraiserPro\Admin\CashTransactionAdmin();
		$this->loader->add_action( 'admin_menu', $cash_admin, 'add_submenu_pages' );
		$this->loader->add_action( 'admin_post_approve_cash_transaction', $cash_admin, 'approve_transaction' );
		$this->loader->add_action( 'admin_post_reject_cash_transaction', $cash_admin, 'reject_transaction' );

		// Analytics Dashboard
		require_once FUNDRAISER_PRO_PATH . 'admin/AnalyticsDashboard.php';
		$analytics = new \FundraiserPro\Admin\AnalyticsDashboard();
		$this->loader->add_action( 'wp_dashboard_setup', $analytics, 'add_dashboard_widgets' );

		// AI Assistant
		require_once FUNDRAISER_PRO_PATH . 'includes/AIAssistant.php';
		$ai_assistant = new AIAssistant();
		$this->loader->add_action( 'wp_ajax_fundraiser_ai_chat', $ai_assistant, 'handle_chat_request' );
		$this->loader->add_action( 'wp_ajax_fundraiser_ai_generate', $ai_assistant, 'handle_generate_request' );
		$this->loader->add_action( 'wp_ajax_fundraiser_ai_generate_image', $ai_assistant, 'handle_generate_image_request' );
		$this->loader->add_action( 'wp_ajax_fundraiser_ai_generate_landing_page', $ai_assistant, 'handle_generate_landing_page_request' );
		$this->loader->add_action( 'wp_ajax_fundraiser_pro_generate_report', $ai_assistant, 'handle_generate_report_request' );

		// Email System
		require_once FUNDRAISER_PRO_PATH . 'includes/EmailManager.php';
		$email_manager = new EmailManager();
		$this->loader->add_filter( 'wp_mail_from', $email_manager, 'set_from_email' );
		$this->loader->add_filter( 'wp_mail_from_name', $email_manager, 'set_from_name' );
	}

	/**
	 * Register all public-facing hooks.
	 */
	private function define_public_hooks() {
		// Public Core
		require_once FUNDRAISER_PRO_PATH . 'public/PublicFacing.php';
		$public = new \FundraiserPro\PublicFacing\PublicFacing();
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );

		// Template System
		require_once FUNDRAISER_PRO_PATH . 'includes/TemplateManager.php';
		$template_manager = new TemplateManager();
		$this->loader->add_filter( 'template_include', $template_manager, 'load_template' );
		$this->loader->add_filter( 'single_template', $template_manager, 'load_single_template' );
		$this->loader->add_filter( 'archive_template', $template_manager, 'load_archive_template' );

		// Shortcodes
		require_once FUNDRAISER_PRO_PATH . 'includes/Shortcodes.php';
		$shortcodes = new Shortcodes();
		$this->loader->add_action( 'init', $shortcodes, 'register_shortcodes' );

		// Gutenberg Blocks
		require_once FUNDRAISER_PRO_PATH . 'blocks/BlocksManager.php';
		$blocks = new \FundraiserPro\Blocks\BlocksManager();
		$this->loader->add_action( 'init', $blocks, 'register_blocks' );
		$this->loader->add_action( 'enqueue_block_editor_assets', $blocks, 'enqueue_block_editor_assets' );

		// AJAX Handlers for Frontend
		require_once FUNDRAISER_PRO_PATH . 'includes/AjaxHandler.php';
		$ajax_handler = new AjaxHandler();
		$this->loader->add_action( 'wp_ajax_load_more_campaigns', $ajax_handler, 'load_more_campaigns' );
		$this->loader->add_action( 'wp_ajax_nopriv_load_more_campaigns', $ajax_handler, 'load_more_campaigns' );
		$this->loader->add_action( 'wp_ajax_get_campaign_stats', $ajax_handler, 'get_campaign_stats' );
		$this->loader->add_action( 'wp_ajax_nopriv_get_campaign_stats', $ajax_handler, 'get_campaign_stats' );

		// Analytics Tracking
		require_once FUNDRAISER_PRO_PATH . 'includes/AnalyticsTracker.php';
		$analytics_tracker = new AnalyticsTracker();
		$this->loader->add_action( 'wp', $analytics_tracker, 'track_page_view' );
	}

	/**
	 * Register all WooCommerce integration hooks.
	 */
	private function define_woocommerce_hooks() {
		// WooCommerce Integration
		require_once FUNDRAISER_PRO_PATH . 'includes/WooCommerce/Integration.php';
		$woo_integration = new \FundraiserPro\WooCommerce\Integration();
		$this->loader->add_filter( 'product_type_selector', $woo_integration, 'add_product_types' );
		$this->loader->add_action( 'woocommerce_product_options_general_product_data', $woo_integration, 'add_product_data_fields' );
		$this->loader->add_action( 'woocommerce_process_product_meta', $woo_integration, 'save_product_data_fields' );

		// Product Markup & Cost Tracking
		require_once FUNDRAISER_PRO_PATH . 'includes/ProductMarkup.php';
		$product_markup = new ProductMarkup();
		$this->loader->add_action( 'woocommerce_product_options_pricing', $product_markup, 'add_product_cost_fields' );
		$this->loader->add_action( 'woocommerce_process_product_meta', $product_markup, 'save_product_cost_fields' );
		$this->loader->add_action( 'woocommerce_admin_order_data_after_order_details', $product_markup, 'display_order_profit_summary' );

		// Order Processing
		require_once FUNDRAISER_PRO_PATH . 'includes/WooCommerce/OrderProcessor.php';
		$order_processor = new \FundraiserPro\WooCommerce\OrderProcessor();
		$this->loader->add_action( 'woocommerce_checkout_update_order_meta', $order_processor, 'save_custom_checkout_fields' );
		$this->loader->add_action( 'woocommerce_thankyou', $order_processor, 'process_order_completion', 10, 1 );
		$this->loader->add_action( 'woocommerce_order_status_completed', $order_processor, 'update_campaign_totals', 10, 1 );
		$this->loader->add_action( 'woocommerce_order_status_refunded', $order_processor, 'handle_refund', 10, 1 );
		// Fundraiser Email Notifications
		require_once FUNDRAISER_PRO_PATH . "includes/FundraiserEmailNotifications.php";
		new \FundraiserPro\FundraiserEmailNotifications();

		// Custom Checkout Fields
		require_once FUNDRAISER_PRO_PATH . 'includes/WooCommerce/CheckoutFields.php';
		$checkout_fields = new \FundraiserPro\WooCommerce\CheckoutFields();
		$this->loader->add_filter( 'woocommerce_checkout_fields', $checkout_fields, 'add_custom_fields' );
		$this->loader->add_action( 'woocommerce_checkout_process', $checkout_fields, 'validate_custom_fields' );

		// Custom Product Types
		require_once FUNDRAISER_PRO_PATH . 'includes/WooCommerce/ProductTypes.php';
		$product_types = new \FundraiserPro\WooCommerce\ProductTypes();
		$this->loader->add_action( 'init', $product_types, 'register_product_classes' );

		// Stripe OAuth Manager (always load for admin)
		require_once FUNDRAISER_PRO_PATH . 'includes/StripeOAuthManager.php';
		$stripe_oauth = new StripeOAuthManager();


		// WC Vendors Integration (if plugin is active)
		if ( class_exists( 'WC_Vendors' ) ) {
			require_once FUNDRAISER_PRO_PATH . 'includes/VendorIntegration.php';
			$vendor_integration = new VendorIntegration();
			// Hooks are registered in constructor

			// Vendor Campaign Integration - Maps campaigns to vendor shops
			require_once FUNDRAISER_PRO_PATH . 'includes/VendorCampaignIntegration.php';
			$vendor_campaign = new VendorCampaignIntegration();
			// Creates /vendor/{vendor-slug}/{campaign-slug}/ URLs

			// Payout System - Hybrid approach with WC Vendors
			require_once FUNDRAISER_PRO_PATH . 'includes/PayoutManager.php';
			require_once FUNDRAISER_PRO_PATH . 'includes/StripeConnectPayout.php';
			require_once FUNDRAISER_PRO_PATH . 'includes/WCVendorsPayoutBridge.php';
			$payout_bridge = new WCVendorsPayoutBridge();
			// Bridges WC Vendors withdrawal system with Stripe Connect automation
		}
	}

	/**
	 * Register all cron job hooks.
	 */
	private function define_cron_hooks() {
		// Schedule cron jobs if not already scheduled
		if ( ! wp_next_scheduled( 'fundraiser_pro_daily_analytics' ) ) {
			wp_schedule_event( time(), 'daily', 'fundraiser_pro_daily_analytics' );
		}

		if ( ! wp_next_scheduled( 'fundraiser_pro_raffle_draws' ) ) {
			wp_schedule_event( time(), 'hourly', 'fundraiser_pro_raffle_draws' );
		}

		if ( ! wp_next_scheduled( 'fundraiser_pro_abandoned_cart_emails' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'fundraiser_pro_abandoned_cart_emails' );
		}

		if ( ! wp_next_scheduled( 'fundraiser_pro_milestone_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'fundraiser_pro_milestone_check' );
		}

		// Cron Handlers
		require_once FUNDRAISER_PRO_PATH . 'includes/CronJobs.php';
		$cron_jobs = new CronJobs();
		$this->loader->add_action( 'fundraiser_pro_daily_analytics', $cron_jobs, 'process_daily_analytics' );
		$this->loader->add_action( 'fundraiser_pro_raffle_draws', $cron_jobs, 'process_raffle_draws' );
		$this->loader->add_action( 'fundraiser_pro_abandoned_cart_emails', $cron_jobs, 'send_abandoned_cart_emails' );
		$this->loader->add_action( 'fundraiser_pro_milestone_check', $cron_jobs, 'check_campaign_milestones' );
	}

	/**
	 * Register all REST API hooks.
	 */
	private function define_rest_api_hooks() {
		// REST API
		require_once FUNDRAISER_PRO_PATH . 'includes/RestAPI.php';
		// REST API is auto-initialized in its constructor
	}

	/**
	 * Run the loader to execute all hooks.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Get the loader.
	 *
	 * @return Loader
	 */
	public function get_loader() {
		return $this->loader;
	}
}
