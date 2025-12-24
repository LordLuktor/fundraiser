<?php
/**
 * Stripe OAuth Manager.
 * Handles Stripe Connect OAuth2 flow for platform account connection.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * StripeOAuthManager class.
 */
class StripeOAuthManager {

	/**
	 * Stripe OAuth endpoints.
	 */
	const OAUTH_AUTHORIZE_URL = 'https://connect.stripe.com/oauth/authorize';
	const OAUTH_TOKEN_URL = 'https://connect.stripe.com/oauth/token';

	/**
	 * Initialize OAuth manager.
	 */
	public function __construct() {
		// Register OAuth callback endpoint
		add_action( 'rest_api_init', array( $this, 'register_oauth_callback' ) );
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
	}

	/**
	 * Get Stripe Connect authorization URL.
	 *
	 * @return string Authorization URL.
	 */
	public function get_connect_url() {
		$client_id = $this->get_client_id();

		if ( empty( $client_id ) ) {
			return '#';
		}

		$redirect_uri = $this->get_redirect_uri();
		$state = $this->generate_state_token();

		// Store state token for verification
		set_transient( 'stripe_oauth_state_' . get_current_user_id(), $state, 600 ); // 10 min expiry

		$params = array(
			'response_type' => 'code',
			'client_id'     => $client_id,
			'scope'         => 'read_write',
			'redirect_uri'  => $redirect_uri,
			'state'         => $state,
		);

		return self::OAUTH_AUTHORIZE_URL . '?' . http_build_query( $params );
	}

	/**
	 * Get disconnect URL.
	 *
	 * @return string Disconnect URL.
	 */
	public function get_disconnect_url() {
		return add_query_arg(
			array(
				'action'   => 'stripe_disconnect',
				'_wpnonce' => wp_create_nonce( 'stripe_disconnect' ),
			),
			admin_url( 'admin.php?page=fundraiser-pro-settings' )
		);
	}

	/**
	 * Handle OAuth callback from Stripe.
	 */
	public function handle_oauth_callback() {
		// Check if this is a Stripe OAuth callback
		if ( ! isset( $_GET['code'] ) || ! isset( $_GET['state'] ) ) {
			return;
		}

		// Verify we're on the right page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fundraiser-pro-settings' ) {
			return;
		}

		// Verify state token
		$state = sanitize_text_field( $_GET['state'] );
		$stored_state = get_transient( 'stripe_oauth_state_' . get_current_user_id() );

		if ( $state !== $stored_state ) {
			wp_die( 'Invalid state token. Please try connecting again.' );
		}

		// Delete used state token
		delete_transient( 'stripe_oauth_state_' . get_current_user_id() );

		// Exchange code for access token
		$code = sanitize_text_field( $_GET['code'] );
		$token_data = $this->exchange_code_for_token( $code );

		if ( is_wp_error( $token_data ) ) {
			wp_die( 'Error connecting to Stripe: ' . $token_data->get_error_message() );
		}

		// Save token data
		$this->save_stripe_credentials( $token_data );

		// Redirect to settings page with success message
		wp_redirect(
			add_query_arg(
				array(
					'page'            => 'fundraiser-pro-settings',
					'stripe_connected' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Exchange authorization code for access token.
	 *
	 * @param string $code Authorization code.
	 * @return array|\WP_Error Token data or error.
	 */
	private function exchange_code_for_token( $code ) {
		$client_id = $this->get_client_id();
		$client_secret = $this->get_client_secret();

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new \WP_Error( 'missing_credentials', 'Stripe client credentials not configured' );
		}

		$response = wp_remote_post(
			self::OAUTH_TOKEN_URL,
			array(
				'body' => array(
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'client_secret' => $client_secret,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error( 'stripe_error', $body['error_description'] ?? $body['error'] );
		}

		return $body;
	}

	/**
	 * Save Stripe credentials.
	 *
	 * @param array $token_data Token data from Stripe.
	 */
	private function save_stripe_credentials( $token_data ) {
		update_option( 'fundraiser_pro_stripe_access_token', $token_data['access_token'] );
		update_option( 'fundraiser_pro_stripe_refresh_token', $token_data['refresh_token'] ?? '' );
		update_option( 'fundraiser_pro_stripe_token_type', $token_data['token_type'] ?? 'bearer' );
		update_option( 'fundraiser_pro_stripe_stripe_publishable_key', $token_data['stripe_publishable_key'] ?? '' );
		update_option( 'fundraiser_pro_stripe_stripe_user_id', $token_data['stripe_user_id'] ?? '' );
		update_option( 'fundraiser_pro_stripe_scope', $token_data['scope'] ?? '' );
		update_option( 'fundraiser_pro_stripe_livemode', $token_data['livemode'] ?? false );
		update_option( 'fundraiser_pro_stripe_connected_at', time() );

		// The access_token can be used as the secret key for API calls
		update_option( 'fundraiser_pro_stripe_secret_key', $token_data['access_token'] );
	}

	/**
	 * Disconnect Stripe account.
	 */
	public function disconnect() {
		// Revoke access token (optional but recommended)
		$this->revoke_access_token();

		// Delete stored credentials
		delete_option( 'fundraiser_pro_stripe_access_token' );
		delete_option( 'fundraiser_pro_stripe_refresh_token' );
		delete_option( 'fundraiser_pro_stripe_token_type' );
		delete_option( 'fundraiser_pro_stripe_stripe_publishable_key' );
		delete_option( 'fundraiser_pro_stripe_stripe_user_id' );
		delete_option( 'fundraiser_pro_stripe_scope' );
		delete_option( 'fundraiser_pro_stripe_livemode' );
		delete_option( 'fundraiser_pro_stripe_connected_at' );
		delete_option( 'fundraiser_pro_stripe_secret_key' );
	}

	/**
	 * Revoke Stripe access token.
	 */
	private function revoke_access_token() {
		$access_token = get_option( 'fundraiser_pro_stripe_access_token' );
		$client_id = $this->get_client_id();
		$client_secret = $this->get_client_secret();

		if ( empty( $access_token ) || empty( $client_id ) || empty( $client_secret ) ) {
			return;
		}

		wp_remote_post(
			'https://connect.stripe.com/oauth/deauthorize',
			array(
				'body' => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'stripe_user_id' => get_option( 'fundraiser_pro_stripe_stripe_user_id' ),
				),
			)
		);
	}

	/**
	 * Check if Stripe is connected.
	 *
	 * @return bool True if connected.
	 */
	public function is_connected() {
		return ! empty( get_option( 'fundraiser_pro_stripe_access_token' ) );
	}

	/**
	 * Get connection info.
	 *
	 * @return array Connection details.
	 */
	public function get_connection_info() {
		if ( ! $this->is_connected() ) {
			return null;
		}

		return array(
			'stripe_user_id' => get_option( 'fundraiser_pro_stripe_stripe_user_id' ),
			'livemode'       => get_option( 'fundraiser_pro_stripe_livemode' ),
			'connected_at'   => get_option( 'fundraiser_pro_stripe_connected_at' ),
			'scope'          => get_option( 'fundraiser_pro_stripe_scope' ),
		);
	}

	/**
	 * Get Stripe client ID from wp-config.php or options.
	 *
	 * @return string Client ID.
	 */
	private function get_client_id() {
		// Check wp-config.php first
		if ( defined( 'STRIPE_CLIENT_ID' ) ) {
			return STRIPE_CLIENT_ID;
		}

		// Fallback to options
		return get_option( 'fundraiser_pro_stripe_client_id', '' );
	}

	/**
	 * Get Stripe client secret from wp-config.php or options.
	 *
	 * @return string Client secret.
	 */
	private function get_client_secret() {
		// Check wp-config.php first
		if ( defined( 'STRIPE_CLIENT_SECRET' ) ) {
			return STRIPE_CLIENT_SECRET;
		}

		// Fallback to options
		return get_option( 'fundraiser_pro_stripe_client_secret', '' );
	}

	/**
	 * Generate state token for CSRF protection.
	 *
	 * @return string State token.
	 */
	private function generate_state_token() {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Get OAuth redirect URI.
	 *
	 * @return string Redirect URI.
	 */
	private function get_redirect_uri() {
		return add_query_arg(
			array(
				'page' => 'fundraiser-pro-settings',
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Register REST API endpoint for OAuth callback.
	 */
	public function register_oauth_callback() {
		register_rest_route(
			'fundraiser-pro/v1',
			'/stripe/oauth/callback',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_oauth_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST API OAuth callback handler.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_oauth_callback( $request ) {
		$code = $request->get_param( 'code' );
		$state = $request->get_param( 'state' );

		if ( empty( $code ) || empty( $state ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'Missing code or state parameter' ),
				400
			);
		}

		// Exchange code for token
		$token_data = $this->exchange_code_for_token( $code );

		if ( is_wp_error( $token_data ) ) {
			return new \WP_REST_Response(
				array( 'error' => $token_data->get_error_message() ),
				400
			);
		}

		$this->save_stripe_credentials( $token_data );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Stripe connected successfully',
			),
			200
		);
	}
}
