<?php
/**
 * REST API Handler for Fundraiser Dashboard
 *
 * @package FundraiserPro
 * @since 1.0.0
 */

class Fundraiser_Pro_REST_API {

    /**
     * API namespace
     */
    const NAMESPACE = 'fundraiser-pro/v1';

    /**
     * Initialize the REST API
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register all REST API routes
     */
    public function register_routes() {
        // Campaign endpoints
        register_rest_route(self::NAMESPACE, '/campaigns', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_campaigns'),
                'permission_callback' => array($this, 'check_fundraiser_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_campaign'),
                'permission_callback' => array($this, 'check_fundraiser_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_campaign'),
                'permission_callback' => array($this, 'check_campaign_owner'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_campaign'),
                'permission_callback' => array($this, 'check_campaign_owner'),
            ),
        ));

        // Product request endpoints
        register_rest_route(self::NAMESPACE, '/product-requests', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_product_request'),
            'permission_callback' => array($this, 'check_fundraiser_permission'),
        ));

        register_rest_route(self::NAMESPACE, '/products/(?P<id>\d+)/prices', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_product_prices'),
            'permission_callback' => array($this, 'check_product_owner'),
        ));

        // Cash transaction endpoints
        register_rest_route(self::NAMESPACE, '/cash-transactions', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_cash_transaction'),
            'permission_callback' => array($this, 'check_fundraiser_permission'),
        ));

        // Raffle entry endpoints
        register_rest_route(self::NAMESPACE, '/raffle-entries', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_raffle_entry'),
            'permission_callback' => array($this, 'check_fundraiser_permission'),
        ));

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>\d+)/recent-entries', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_recent_entries'),
            'permission_callback' => array($this, 'check_campaign_owner'),
        ));

        // Analytics endpoints
        register_rest_route(self::NAMESPACE, '/analytics/campaign/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_campaign_analytics'),
            'permission_callback' => array($this, 'check_campaign_owner'),
        ));

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>\d+)/export', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_campaign_data'),
            'permission_callback' => array($this, 'check_campaign_owner'),
        ));
    }

    // ==================== CAMPAIGN ENDPOINTS ====================

    /**
     * Get campaigns for current user
     */
    public function get_campaigns($request) {
        $user_id = get_current_user_id();

        $args = array(
            'post_type' => 'fundraiser_campaign',
            'author' => $user_id,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending'),
        );

        $campaigns = get_posts($args);
        $result = array();

        foreach ($campaigns as $campaign) {
            $result[] = $this->format_campaign($campaign);
        }

        return rest_ensure_response($result);
    }

    /**
     * Get single campaign
     */
    public function get_campaign($request) {
        $campaign_id = $request['id'];
        $campaign = get_post($campaign_id);

        if (!$campaign || $campaign->post_type !== 'fundraiser_campaign') {
            return new WP_Error('not_found', 'Campaign not found', array('status' => 404));
        }

        return rest_ensure_response($this->format_campaign($campaign));
    }

    /**
     * Create new campaign
     */
    public function create_campaign($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        // Sanitize inputs
        $title = sanitize_text_field($params['title'] ?? '');
        $description = wp_kses_post($params['description'] ?? '');
        $goal = floatval($params['goal'] ?? 0);
        $duration = intval($params['duration'] ?? 30);

        if (empty($title)) {
            return new WP_Error('missing_title', 'Campaign title is required', array('status' => 400));
        }

        // Create campaign post
        $campaign_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $description,
            'post_type' => 'fundraiser_campaign',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ));

        if (is_wp_error($campaign_id)) {
            return $campaign_id;
        }

        // Save meta data
        update_post_meta($campaign_id, 'fundraiser_goal', $goal);
        update_post_meta($campaign_id, 'fundraiser_duration', $duration);
        update_post_meta($campaign_id, 'fundraiser_donations_enabled', !empty($params['donations_enabled']));
        update_post_meta($campaign_id, 'fundraiser_products_enabled', !empty($params['products_enabled']));
        update_post_meta($campaign_id, 'fundraiser_raffles_enabled', !empty($params['raffles_enabled']));

        if (!empty($params['video_url'])) {
            update_post_meta($campaign_id, 'fundraiser_video_url', esc_url_raw($params['video_url']));
        }

        $campaign = get_post($campaign_id);
        return rest_ensure_response($this->format_campaign($campaign));
    }

    /**
     * Update existing campaign
     */
    public function update_campaign($request) {
        $campaign_id = $request['id'];
        $params = $request->get_json_params();

        $update_data = array(
            'ID' => $campaign_id,
        );

        if (isset($params['title'])) {
            $update_data['post_title'] = sanitize_text_field($params['title']);
        }

        if (isset($params['description'])) {
            $update_data['post_content'] = wp_kses_post($params['description']);
        }

        if (isset($params['status'])) {
            $update_data['post_status'] = sanitize_text_field($params['status']);
        }

        $result = wp_update_post($update_data);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update meta fields
        if (isset($params['goal'])) {
            update_post_meta($campaign_id, 'fundraiser_goal', floatval($params['goal']));
        }

        if (isset($params['donations_enabled'])) {
            update_post_meta($campaign_id, 'fundraiser_donations_enabled', !empty($params['donations_enabled']));
        }

        if (isset($params['products_enabled'])) {
            update_post_meta($campaign_id, 'fundraiser_products_enabled', !empty($params['products_enabled']));
        }

        if (isset($params['raffles_enabled'])) {
            update_post_meta($campaign_id, 'fundraiser_raffles_enabled', !empty($params['raffles_enabled']));
        }

        $campaign = get_post($campaign_id);
        return rest_ensure_response($this->format_campaign($campaign));
    }

    /**
     * Format campaign data for API response
     */
    private function format_campaign($campaign) {
        global $wpdb;

        $campaign_id = $campaign->ID;

        // Get meta data
        $goal = floatval(get_post_meta($campaign_id, 'fundraiser_goal', true));
        $donations_enabled = get_post_meta($campaign_id, 'fundraiser_donations_enabled', true);
        $products_enabled = get_post_meta($campaign_id, 'fundraiser_products_enabled', true);
        $raffles_enabled = get_post_meta($campaign_id, 'fundraiser_raffles_enabled', true);

        // Get analytics data
        $analytics = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fundraiser_campaign_analytics WHERE campaign_id = %d",
            $campaign_id
        ));

        $total_raised = $analytics ? floatval($analytics->total_raised) : 0;
        $progress = $goal > 0 ? min(100, ($total_raised / $goal) * 100) : 0;

        return array(
            'id' => $campaign_id,
            'title' => $campaign->post_title,
            'description' => $campaign->post_content,
            'status' => $campaign->post_status,
            'goal' => $goal,
            'total_raised' => $total_raised,
            'progress' => round($progress, 1),
            'donations_enabled' => !empty($donations_enabled),
            'products_enabled' => !empty($products_enabled),
            'raffles_enabled' => !empty($raffles_enabled),
            'url' => get_permalink($campaign_id),
            'edit_url' => home_url('/campaign-detail/?campaign_id=' . $campaign_id),
            'created_at' => $campaign->post_date,
        );
    }

    // ==================== PRODUCT REQUEST ENDPOINTS ====================

    /**
     * Submit product request with artwork
     */
    public function submit_product_request($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        $campaign_id = intval($params['campaign_id'] ?? 0);
        $products_requested = $params['products'] ?? array();
        $notes = sanitize_textarea_field($params['notes'] ?? '');

        if (empty($campaign_id) || empty($products_requested)) {
            return new WP_Error('missing_data', 'Campaign ID and products are required', array('status' => 400));
        }

        // Verify campaign ownership
        $campaign = get_post($campaign_id);
        if (!$campaign || $campaign->post_author != $user_id) {
            return new WP_Error('unauthorized', 'You do not own this campaign', array('status' => 403));
        }

        // Handle file uploads
        $artwork_files = array();
        if (!empty($_FILES['artwork'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');

            $files = $_FILES['artwork'];
            $file_count = count($files['name']);

            for ($i = 0; $i < $file_count; $i++) {
                $file = array(
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                );

                // Validate file
                $allowed_types = array('image/jpeg', 'image/png', 'image/svg+xml', 'application/pdf');
                if (!in_array($file['type'], $allowed_types)) {
                    continue;
                }

                if ($file['size'] > 10485760) { // 10MB max
                    continue;
                }

                // Upload file
                $upload = wp_handle_upload($file, array('test_form' => false));

                if (!isset($upload['error'])) {
                    $artwork_files[] = $upload['url'];
                }
            }
        }

        // Insert into database
        $table_name = $wpdb->prefix . 'fundraiser_product_requests';
        $result = $wpdb->insert(
            $table_name,
            array(
                'campaign_id' => $campaign_id,
                'fundraiser_id' => $user_id,
                'products_requested' => json_encode($products_requested),
                'artwork_files' => json_encode($artwork_files),
                'notes' => $notes,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to save product request', array('status' => 500));
        }

        // Send email notification to admin
        $admin_email = get_option('admin_email');
        $campaign_title = get_the_title($campaign_id);
        $subject = 'New Product Request for Campaign: ' . $campaign_title;
        $message = "A new product request has been submitted.\n\n";
        $message .= "Campaign: {$campaign_title}\n";
        $message .= "Products: " . implode(', ', $products_requested) . "\n";
        $message .= "View details in the admin dashboard.";

        wp_mail($admin_email, $subject, $message);

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Product request submitted successfully',
            'request_id' => $wpdb->insert_id,
        ));
    }

    /**
     * Update product prices
     */
    public function update_product_prices($request) {
        $product_id = $request['id'];
        $params = $request->get_json_params();

        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_Error('not_found', 'Product not found', array('status' => 404));
        }

        // Update prices
        if (isset($params['regular_price'])) {
            $product->set_regular_price(floatval($params['regular_price']));
        }

        if (isset($params['sale_price'])) {
            $sale_price = $params['sale_price'];
            if (empty($sale_price)) {
                $product->set_sale_price('');
            } else {
                $product->set_sale_price(floatval($sale_price));
            }
        }

        $product->save();

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Product prices updated',
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
        ));
    }

    // ==================== CASH TRANSACTION ENDPOINTS ====================

    /**
     * Create cash transaction (manual entry)
     */
    public function create_cash_transaction($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        $campaign_id = intval($params['campaign_id'] ?? 0);
        $donor_name = sanitize_text_field($params['donor_name'] ?? '');
        $donor_email = sanitize_email($params['donor_email'] ?? '');
        $amount = floatval($params['amount'] ?? 0);
        $payment_method = sanitize_text_field($params['payment_method'] ?? 'cash');
        $notes = sanitize_textarea_field($params['notes'] ?? '');

        if (empty($campaign_id) || empty($donor_name) || $amount <= 0) {
            return new WP_Error('missing_data', 'Campaign ID, donor name, and amount are required', array('status' => 400));
        }

        // Verify campaign ownership
        $campaign = get_post($campaign_id);
        if (!$campaign || $campaign->post_author != $user_id) {
            return new WP_Error('unauthorized', 'You do not own this campaign', array('status' => 403));
        }

        // Create cash transaction post
        $transaction_id = wp_insert_post(array(
            'post_type' => 'fundraiser_cash',
            'post_title' => $donor_name . ' - $' . number_format($amount, 2),
            'post_status' => 'pending',
            'post_author' => $user_id,
        ));

        if (is_wp_error($transaction_id)) {
            return $transaction_id;
        }

        // Save meta data
        update_post_meta($transaction_id, 'campaign_id', $campaign_id);
        update_post_meta($transaction_id, 'donor_name', $donor_name);
        update_post_meta($transaction_id, 'donor_email', $donor_email);
        update_post_meta($transaction_id, 'amount', $amount);
        update_post_meta($transaction_id, 'payment_method', $payment_method);
        update_post_meta($transaction_id, 'notes', $notes);

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Cash transaction recorded (pending approval)',
            'transaction_id' => $transaction_id,
        ));
    }

    // ==================== RAFFLE ENTRY ENDPOINTS ====================

    /**
     * Create raffle entry (manual entry)
     */
    public function create_raffle_entry($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        $raffle_id = intval($params['raffle_id'] ?? 0);
        $participant_name = sanitize_text_field($params['participant_name'] ?? '');
        $participant_email = sanitize_email($params['participant_email'] ?? '');
        $ticket_count = intval($params['ticket_count'] ?? 1);
        $amount = floatval($params['amount'] ?? 0);

        if (empty($raffle_id) || empty($participant_name) || $ticket_count <= 0) {
            return new WP_Error('missing_data', 'Raffle ID, participant name, and ticket count are required', array('status' => 400));
        }

        // Get raffle and verify campaign ownership
        $raffle = get_post($raffle_id);
        if (!$raffle || $raffle->post_type !== 'fundraiser_raffle') {
            return new WP_Error('not_found', 'Raffle not found', array('status' => 404));
        }

        $campaign_id = get_post_meta($raffle_id, 'campaign_id', true);
        $campaign = get_post($campaign_id);
        if (!$campaign || $campaign->post_author != $user_id) {
            return new WP_Error('unauthorized', 'You do not own this campaign', array('status' => 403));
        }

        // Get next ticket number
        $table_name = $wpdb->prefix . 'fundraiser_raffle_tickets';
        $max_ticket = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(ticket_number) FROM {$table_name} WHERE raffle_id = %d",
            $raffle_id
        ));

        $next_ticket = intval($max_ticket) + 1;
        $ticket_numbers = array();

        // Generate tickets
        for ($i = 0; $i < $ticket_count; $i++) {
            $ticket_number = $next_ticket + $i;
            $ticket_numbers[] = $ticket_number;

            $wpdb->insert(
                $table_name,
                array(
                    'raffle_id' => $raffle_id,
                    'ticket_number' => $ticket_number,
                    'purchaser_name' => $participant_name,
                    'purchaser_email' => $participant_email,
                    'purchase_date' => current_time('mysql'),
                    'payment_status' => 'pending',
                    'amount' => $amount / $ticket_count,
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%f')
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Raffle entries recorded (pending approval)',
            'ticket_numbers' => $ticket_numbers,
        ));
    }

    /**
     * Get recent entries for campaign
     */
    public function get_recent_entries($request) {
        $campaign_id = $request['id'];
        $user_id = get_current_user_id();

        // Get recent cash transactions
        $cash_transactions = get_posts(array(
            'post_type' => 'fundraiser_cash',
            'post_status' => 'pending',
            'author' => $user_id,
            'posts_per_page' => 10,
            'meta_query' => array(
                array(
                    'key' => 'campaign_id',
                    'value' => $campaign_id,
                ),
            ),
        ));

        $entries = array();

        foreach ($cash_transactions as $transaction) {
            $entries[] = array(
                'id' => $transaction->ID,
                'type' => 'cash',
                'name' => get_post_meta($transaction->ID, 'donor_name', true),
                'amount' => get_post_meta($transaction->ID, 'amount', true),
                'date' => $transaction->post_date,
                'status' => $transaction->post_status,
            );
        }

        return rest_ensure_response($entries);
    }

    // ==================== ANALYTICS ENDPOINTS ====================

    /**
     * Get campaign analytics
     */
    public function get_campaign_analytics($request) {
        global $wpdb;
        $campaign_id = $request['id'];

        // Get analytics record
        $analytics = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fundraiser_campaign_analytics WHERE campaign_id = %d",
            $campaign_id
        ));

        if (!$analytics) {
            return rest_ensure_response(array(
                'total_raised' => 0,
                'donation_revenue' => 0,
                'product_revenue' => 0,
                'raffle_revenue' => 0,
                'total_donors' => 0,
                'campaign_views' => 0,
            ));
        }

        // Get progress over time (last 30 days)
        $progress_data = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(ct.created_at) as date, SUM(ct.amount) as daily_total
            FROM {$wpdb->prefix}fundraiser_cash_transactions ct
            WHERE ct.campaign_id = %d
            AND ct.status = 'approved'
            AND ct.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(ct.created_at)
            ORDER BY date ASC",
            $campaign_id
        ));

        return rest_ensure_response(array(
            'total_raised' => floatval($analytics->total_raised),
            'donation_revenue' => floatval($analytics->donation_revenue),
            'product_revenue' => floatval($analytics->product_revenue),
            'raffle_revenue' => floatval($analytics->raffle_revenue),
            'total_donors' => intval($analytics->total_donors),
            'campaign_views' => intval($analytics->campaign_views),
            'progress_over_time' => $progress_data,
        ));
    }

    /**
     * Export campaign data
     */
    public function export_campaign_data($request) {
        global $wpdb;
        $campaign_id = $request['id'];
        $format = $request->get_param('format') ?: 'csv';

        $campaign = get_post($campaign_id);

        if ($format === 'csv') {
            // Generate CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="campaign-' . $campaign_id . '-export.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, array('Date', 'Donor Name', 'Email', 'Amount', 'Type', 'Status'));

            // Get all transactions
            $transactions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fundraiser_cash_transactions WHERE campaign_id = %d ORDER BY created_at DESC",
                $campaign_id
            ));

            foreach ($transactions as $transaction) {
                fputcsv($output, array(
                    $transaction->created_at,
                    $transaction->donor_name,
                    $transaction->donor_email,
                    $transaction->amount,
                    $transaction->transaction_type,
                    $transaction->status,
                ));
            }

            fclose($output);
            exit;
        }

        return new WP_Error('invalid_format', 'Invalid export format', array('status' => 400));
    }

    // ==================== PERMISSION CALLBACKS ====================

    /**
     * Check if user has fundraiser permission
     */
    public function check_fundraiser_permission($request) {
        return current_user_can('manage_fundraiser_campaigns') || current_user_can('edit_posts');
    }

    /**
     * Check if user owns the campaign
     */
    public function check_campaign_owner($request) {
        if (!$this->check_fundraiser_permission($request)) {
            return false;
        }

        $campaign_id = $request['id'];
        $campaign = get_post($campaign_id);

        if (!$campaign || $campaign->post_type !== 'fundraiser_campaign') {
            return false;
        }

        return $campaign->post_author == get_current_user_id() || current_user_can('manage_options');
    }

    /**
     * Check if user owns the product's campaign
     */
    public function check_product_owner($request) {
        if (!$this->check_fundraiser_permission($request)) {
            return false;
        }

        $product_id = $request['id'];
        $product = wc_get_product($product_id);

        if (!$product) {
            return false;
        }

        // Get campaign from product category
        $categories = $product->get_category_ids();

        foreach ($categories as $cat_id) {
            $campaign_id = get_term_meta($cat_id, 'campaign_id', true);
            if ($campaign_id) {
                $campaign = get_post($campaign_id);
                if ($campaign && ($campaign->post_author == get_current_user_id() || current_user_can('manage_options'))) {
                    return true;
                }
            }
        }

        return current_user_can('manage_options');
    }
}

// Initialize the REST API
new Fundraiser_Pro_REST_API();
