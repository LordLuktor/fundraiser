<?php
/**
 * Enhanced AI Assistant using OpenAI API.
 * Supports: Campaign assistance, Landing pages, Image generation, Reporting.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * AIAssistant class.
 */
class AIAssistant {

	/**
	 * OpenAI API endpoints.
	 *
	 * @var array
	 */
	private $api_endpoints = array(
		'chat'  => 'https://api.openai.com/v1/chat/completions',
		'image' => 'https://api.openai.com/v1/images/generations',
	);

	/**
	 * Handle chat request.
	 */
	public function handle_chat_request() {
		check_ajax_referer( 'fundraiser_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_own_campaigns' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'fundraiser-pro' ) ) );
		}

		$message = isset( $_POST['message'] ) ? sanitize_text_field( $_POST['message'] ) : '';
		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$context_type = isset( $_POST['context_type'] ) ? sanitize_text_field( $_POST['context_type'] ) : 'campaign';

		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Message is required', 'fundraiser-pro' ) ) );
		}

		$response = $this->chat( $message, $campaign_id, $context_type );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		wp_send_json_success( array( 'reply' => $response ) );
	}

	/**
	 * Handle generate request.
	 */
	public function handle_generate_request() {
		check_ajax_referer( 'fundraiser_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_own_campaigns' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'fundraiser-pro' ) ) );
		}

		$field = isset( $_POST['field'] ) ? sanitize_text_field( $_POST['field'] ) : '';
		$campaign_data = isset( $_POST['campaign_data'] ) ? $_POST['campaign_data'] : array();

		if ( empty( $field ) ) {
			wp_send_json_error( array( 'message' => __( 'Field is required', 'fundraiser-pro' ) ) );
		}

		$suggestion = $this->generate_suggestion( $field, $campaign_data );

		if ( is_wp_error( $suggestion ) ) {
			wp_send_json_error( array( 'message' => $suggestion->get_error_message() ) );
		}

		wp_send_json_success( array( 'suggestion' => $suggestion ) );
	}

	/**
	 * Handle image generation request.
	 */
	public function handle_generate_image_request() {
		check_ajax_referer( 'fundraiser_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_own_campaigns' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'fundraiser-pro' ) ) );
		}

		$prompt = isset( $_POST['prompt'] ) ? sanitize_text_field( $_POST['prompt'] ) : '';
		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$style = isset( $_POST['style'] ) ? sanitize_text_field( $_POST['style'] ) : 'natural';

		if ( empty( $prompt ) ) {
			wp_send_json_error( array( 'message' => __( 'Prompt is required', 'fundraiser-pro' ) ) );
		}

		$image_data = $this->generate_image( $prompt, $campaign_id, $style );

		if ( is_wp_error( $image_data ) ) {
			wp_send_json_error( array( 'message' => $image_data->get_error_message() ) );
		}

		wp_send_json_success( $image_data );
	}

	/**
	 * Handle landing page generation request.
	 */
	public function handle_generate_landing_page_request() {
		check_ajax_referer( 'fundraiser_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_own_campaigns' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'fundraiser-pro' ) ) );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$style = isset( $_POST['style'] ) ? sanitize_text_field( $_POST['style'] ) : 'modern';
		$include_sections = isset( $_POST['sections'] ) ? $_POST['sections'] : array();

		if ( ! $campaign_id ) {
			wp_send_json_error( array( 'message' => __( 'Campaign ID is required', 'fundraiser-pro' ) ) );
		}

		$landing_page = $this->generate_landing_page( $campaign_id, $style, $include_sections );

		if ( is_wp_error( $landing_page ) ) {
			wp_send_json_error( array( 'message' => $landing_page->get_error_message() ) );
		}

		wp_send_json_success( $landing_page );
	}

	/**
	 * Handle report generation request.
	 */
	public function handle_generate_report_request() {
		check_ajax_referer( 'fundraiser_pro_admin', 'nonce' );

		if ( ! current_user_can( 'view_fundraiser_analytics' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'fundraiser-pro' ) ) );
		}

		$report_type = isset( $_POST['report_type'] ) ? sanitize_text_field( $_POST['report_type'] ) : 'summary';
		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$date_range = isset( $_POST['date_range'] ) ? $_POST['date_range'] : array();

		$report = $this->generate_report( $report_type, $campaign_id, $date_range );

		if ( is_wp_error( $report ) ) {
			wp_send_json_error( array( 'message' => $report->get_error_message() ) );
		}

		wp_send_json_success( $report );
	}

	/**
	 * Chat with AI.
	 *
	 * @param string $message      User message.
	 * @param int    $campaign_id  Campaign ID.
	 * @param string $context_type Context type (campaign, landing_page, report, general).
	 * @return string|WP_Error AI response or error.
	 */
	public function chat( $message, $campaign_id = 0, $context_type = 'campaign' ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'ai_disabled', __( 'AI Assistant is not enabled. Please configure your OpenAI API key in settings.', 'fundraiser-pro' ) );
		}

		// Get conversation history
		$history = $this->get_conversation_history( $campaign_id, $context_type );

		// Add user message to history
		$history[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		// Build messages for API
		$messages = $this->build_messages( $history, $campaign_id, $context_type );

		// Call OpenAI API
		$response = $this->call_api( $messages );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Extract reply
		$reply = $response['choices'][0]['message']['content'] ?? '';

		// Add assistant reply to history
		$history[] = array(
			'role'    => 'assistant',
			'content' => $reply,
		);

		// Save conversation history
		$this->save_conversation_history( $campaign_id, $context_type, $history, $response['usage'] ?? array() );

		return $reply;
	}

	/**
	 * Generate suggestion for a field.
	 *
	 * @param string $field         Field name.
	 * @param array  $campaign_data Campaign data.
	 * @return string|WP_Error Suggestion or error.
	 */
	public function generate_suggestion( $field, $campaign_data ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'ai_disabled', __( 'AI Assistant is not enabled.', 'fundraiser-pro' ) );
		}

		$prompts = array(
			'title'              => 'Generate a compelling, concise fundraising campaign title (max 60 characters) that captures attention and clearly communicates the cause.',
			'description'        => 'Write a detailed, emotionally engaging campaign description (200-300 words) that tells a compelling story and motivates people to donate.',
			'goal_amount'        => 'Suggest a realistic fundraising goal amount based on the campaign type and scope. Just provide the number.',
			'email_template'     => 'Create a professional email template for campaign updates that supporters can send to their network.',
			'social_media_post'  => 'Create 3 social media posts (Twitter/X, Facebook, Instagram) for promoting this campaign. Keep each platform-appropriate in length and tone.',
			'landing_page_title' => 'Generate a compelling landing page headline (max 80 characters) that maximizes conversions.',
			'call_to_action'     => 'Create 3 compelling call-to-action button texts that will drive donations.',
			'impact_statement'   => 'Write an impact statement showing what different donation amounts can accomplish (e.g., $25, $50, $100, $250).',
		);

		if ( ! isset( $prompts[ $field ] ) ) {
			return new \WP_Error( 'invalid_field', __( 'Invalid field specified.', 'fundraiser-pro' ) );
		}

		$context = '';
		if ( ! empty( $campaign_data['title'] ) ) {
			$context .= "\nCampaign Title: {$campaign_data['title']}";
		}
		if ( ! empty( $campaign_data['description'] ) ) {
			$context .= "\nCurrent Description: " . substr( $campaign_data['description'], 0, 500 );
		}
		if ( ! empty( $campaign_data['category'] ) ) {
			$context .= "\nCategory: {$campaign_data['category']}";
		}
		if ( ! empty( $campaign_data['goal_amount'] ) ) {
			$context .= "\nGoal Amount: {$campaign_data['goal_amount']}";
		}

		$prompt = $prompts[ $field ] . $context;

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are a helpful fundraising expert assistant. Provide practical, actionable suggestions that maximize donor engagement and conversions.',
			),
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		$response = $this->call_api( $messages );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * Generate image using DALL-E.
	 *
	 * @param string $prompt      Image description.
	 * @param int    $campaign_id Campaign ID.
	 * @param string $style       Image style (natural, vivid, artistic).
	 * @return array|WP_Error Image data or error.
	 */
	public function generate_image( $prompt, $campaign_id = 0, $style = 'natural' ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'ai_disabled', __( 'AI Assistant is not enabled.', 'fundraiser-pro' ) );
		}

		// Enhance prompt with campaign context
		if ( $campaign_id ) {
			$campaign = get_post( $campaign_id );
			if ( $campaign ) {
				$prompt = "For a fundraising campaign titled '{$campaign->post_title}': {$prompt}";
			}
		}

		// Add style guidance
		$style_guidance = array(
			'natural'  => 'Create a realistic, professional photo-style image.',
			'vivid'    => 'Create a vibrant, eye-catching image with bold colors.',
			'artistic' => 'Create an artistic, illustrated image with a unique style.',
		);

		if ( isset( $style_guidance[ $style ] ) ) {
			$prompt .= ' ' . $style_guidance[ $style ];
		}

		$api_key = $this->get_api_key();

		if ( ! $api_key ) {
			return new \WP_Error( 'no_api_key', __( 'OpenAI API key not configured.', 'fundraiser-pro' ) );
		}

		$body = array(
			'model'  => 'dall-e-3',
			'prompt' => $prompt,
			'n'      => 1,
			'size'   => '1024x1024',
			'quality' => 'standard',
		);

		$response = wp_remote_post(
			$this->api_endpoints['image'],
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$error_message = $data['error']['message'] ?? __( 'Unknown API error', 'fundraiser-pro' );
			return new \WP_Error( 'api_error', $error_message );
		}

		// Download and save image to media library
		$image_url = $data['data'][0]['url'] ?? '';
		$revised_prompt = $data['data'][0]['revised_prompt'] ?? $prompt;

		if ( ! $image_url ) {
			return new \WP_Error( 'no_image', __( 'No image URL returned', 'fundraiser-pro' ) );
		}

		// Download image
		$media_id = $this->download_and_save_image( $image_url, $campaign_id, $revised_prompt );

		if ( is_wp_error( $media_id ) ) {
			return $media_id;
		}

		// Log usage
		$this->log_image_generation( $campaign_id, $prompt, $revised_prompt );

		return array(
			'media_id'       => $media_id,
			'url'            => wp_get_attachment_url( $media_id ),
			'prompt'         => $prompt,
			'revised_prompt' => $revised_prompt,
		);
	}

	/**
	 * Generate landing page HTML.
	 *
	 * @param int    $campaign_id      Campaign ID.
	 * @param string $style            Page style (modern, minimal, bold, elegant).
	 * @param array  $include_sections Sections to include.
	 * @return array|WP_Error Landing page data or error.
	 */
	public function generate_landing_page( $campaign_id, $style = 'modern', $include_sections = array() ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'ai_disabled', __( 'AI Assistant is not enabled.', 'fundraiser-pro' ) );
		}

		$campaign = get_post( $campaign_id );
		if ( ! $campaign ) {
			return new \WP_Error( 'invalid_campaign', __( 'Campaign not found.', 'fundraiser-pro' ) );
		}

		// Get campaign metadata
		global $wpdb;
		$campaign_table = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';
		$campaign_data = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$campaign_table} WHERE post_id = %d", $campaign_id )
		);

		// Default sections
		if ( empty( $include_sections ) ) {
			$include_sections = array( 'hero', 'about', 'impact', 'donation_form', 'progress', 'social_share' );
		}

		// Build prompt
		$prompt = $this->build_landing_page_prompt( $campaign, $campaign_data, $style, $include_sections );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are an expert web designer and copywriter specializing in high-converting fundraising landing pages. Generate clean, modern HTML with inline CSS that follows conversion best practices. Use responsive design and include compelling copy that drives donations.',
			),
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		$response = $this->call_api( $messages, 'gpt-4-turbo-preview', 2000 );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$html_content = $response['choices'][0]['message']['content'] ?? '';

		// Extract HTML from markdown code blocks if present
		$html_content = $this->extract_html_from_response( $html_content );

		return array(
			'html'     => $html_content,
			'style'    => $style,
			'sections' => $include_sections,
			'usage'    => $response['usage'] ?? array(),
		);
	}

	/**
	 * Generate analytics report.
	 *
	 * @param string $report_type Report type (summary, detailed, insights, recommendations).
	 * @param int    $campaign_id Campaign ID (0 for all campaigns).
	 * @param array  $date_range  Date range array with 'start' and 'end'.
	 * @return array|WP_Error Report data or error.
	 */
	public function generate_report( $report_type, $campaign_id = 0, $date_range = array() ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'ai_disabled', __( 'AI Assistant is not enabled.', 'fundraiser-pro' ) );
		}

		// Gather analytics data
		$analytics_data = $this->gather_analytics_data( $campaign_id, $date_range );

		if ( is_wp_error( $analytics_data ) ) {
			return $analytics_data;
		}

		// Build prompt based on report type
		$prompts = array(
			'summary'         => 'Generate a concise executive summary (2-3 paragraphs) of the fundraising performance, highlighting key metrics and overall success.',
			'detailed'        => 'Generate a detailed analysis of fundraising performance, including trends, patterns, and notable events. Use bullet points and clear sections.',
			'insights'        => 'Analyze the data and provide actionable insights about what\'s working well and what could be improved. Focus on data-driven recommendations.',
			'recommendations' => 'Based on the performance data, provide specific, actionable recommendations to improve fundraising results. Prioritize by potential impact.',
		);

		if ( ! isset( $prompts[ $report_type ] ) ) {
			return new \WP_Error( 'invalid_report_type', __( 'Invalid report type.', 'fundraiser-pro' ) );
		}

		$data_summary = $this->format_analytics_for_prompt( $analytics_data );
		$prompt = $prompts[ $report_type ] . "\n\nData:\n" . $data_summary;

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are an expert fundraising analyst. Analyze data objectively and provide clear, actionable insights. Use specific numbers and percentages when referring to the data.',
			),
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		$response = $this->call_api( $messages, 'gpt-4-turbo-preview', 1500 );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$analysis = $response['choices'][0]['message']['content'] ?? '';

		return array(
			'type'         => $report_type,
			'analysis'     => $analysis,
			'data'         => $analytics_data,
			'generated_at' => current_time( 'mysql' ),
			'usage'        => $response['usage'] ?? array(),
		);
	}

	/**
	 * Call OpenAI Chat API.
	 *
	 * @param array  $messages   Messages to send.
	 * @param string $model      Model to use.
	 * @param int    $max_tokens Max tokens.
	 * @return array|WP_Error API response or error.
	 */
	private function call_api( $messages, $model = null, $max_tokens = null ) {
		$api_key = $this->get_api_key();

		if ( ! $api_key ) {
			return new \WP_Error( 'no_api_key', __( 'OpenAI API key not configured.', 'fundraiser-pro' ) );
		}

		if ( ! $model ) {
			$model = get_option( 'fundraiser_pro_openai_model', 'gpt-4-turbo-preview' );
		}

		if ( ! $max_tokens ) {
			$max_tokens = get_option( 'fundraiser_pro_openai_max_tokens', 1000 );
		}

		$body = array(
			'model'       => $model,
			'messages'    => $messages,
			'max_tokens'  => $max_tokens,
			'temperature' => 0.7,
		);

		$response = wp_remote_post(
			$this->api_endpoints['chat'],
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$error_message = $data['error']['message'] ?? __( 'Unknown API error', 'fundraiser-pro' );
			return new \WP_Error( 'api_error', $error_message );
		}

		return $data;
	}

	/**
	 * Build messages for API.
	 *
	 * @param array  $history      Conversation history.
	 * @param int    $campaign_id  Campaign ID.
	 * @param string $context_type Context type.
	 * @return array Messages array.
	 */
	private function build_messages( $history, $campaign_id, $context_type = 'campaign' ) {
		$system_messages = array(
			'campaign'     => 'You are an expert fundraising consultant helping to create successful campaigns. Provide practical, actionable advice. Be encouraging and supportive while being realistic.',
			'landing_page' => 'You are an expert landing page designer and conversion specialist. Help create high-converting fundraising pages with compelling copy and design.',
			'report'       => 'You are a data analyst specializing in fundraising metrics. Provide clear insights and actionable recommendations based on performance data.',
			'general'      => 'You are a comprehensive fundraising assistant. Help with all aspects of fundraising including campaigns, design, analytics, and strategy.',
		);

		$system_message = array(
			'role'    => 'system',
			'content' => $system_messages[ $context_type ] ?? $system_messages['general'],
		);

		$messages = array( $system_message );

		// Add campaign context if available
		if ( $campaign_id ) {
			$campaign = get_post( $campaign_id );
			if ( $campaign ) {
				$context = "Campaign Title: {$campaign->post_title}\n";
				$context .= "Description: " . substr( $campaign->post_content, 0, 500 );

				// Add campaign stats
				global $wpdb;
				$campaign_table = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';
				$stats = $wpdb->get_row(
					$wpdb->prepare( "SELECT goal_amount, current_amount FROM {$campaign_table} WHERE post_id = %d", $campaign_id )
				);

				if ( $stats ) {
					$context .= "\nGoal: $" . number_format( $stats->goal_amount, 2 );
					$context .= "\nRaised: $" . number_format( $stats->current_amount, 2 );
					$progress = $stats->goal_amount > 0 ? ( $stats->current_amount / $stats->goal_amount ) * 100 : 0;
					$context .= "\nProgress: " . number_format( $progress, 1 ) . "%";
				}

				$messages[] = array(
					'role'    => 'system',
					'content' => "Context: {$context}",
				);
			}
		}

		// Add conversation history (limit to last 10 messages)
		$messages = array_merge( $messages, array_slice( $history, -10 ) );

		return $messages;
	}

	/**
	 * Download and save image to media library.
	 *
	 * @param string $image_url   Image URL.
	 * @param int    $campaign_id Campaign ID.
	 * @param string $description Image description.
	 * @return int|WP_Error Media ID or error.
	 */
	private function download_and_save_image( $image_url, $campaign_id, $description ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$temp_file = download_url( $image_url );

		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		$file_array = array(
			'name'     => 'ai-generated-' . time() . '.png',
			'tmp_name' => $temp_file,
		);

		$media_id = media_handle_sideload( $file_array, $campaign_id );

		if ( is_wp_error( $media_id ) ) {
			@unlink( $temp_file );
			return $media_id;
		}

		// Set alt text and description
		update_post_meta( $media_id, '_wp_attachment_image_alt', $description );
		wp_update_post(
			array(
				'ID'           => $media_id,
				'post_content' => $description,
			)
		);

		return $media_id;
	}

	/**
	 * Build landing page prompt.
	 *
	 * @param WP_Post $campaign         Campaign post.
	 * @param object  $campaign_data    Campaign data from database.
	 * @param string  $style            Style preference.
	 * @param array   $include_sections Sections to include.
	 * @return string Prompt.
	 */
	private function build_landing_page_prompt( $campaign, $campaign_data, $style, $include_sections ) {
		$prompt = "Create a complete, responsive HTML landing page for this fundraising campaign:\n\n";
		$prompt .= "Campaign: {$campaign->post_title}\n";
		$prompt .= "Description: {$campaign->post_content}\n";

		if ( $campaign_data ) {
			$prompt .= "Goal: $" . number_format( $campaign_data->goal_amount, 2 ) . "\n";
			$prompt .= "Current Amount: $" . number_format( $campaign_data->current_amount, 2 ) . "\n";
		}

		$prompt .= "\nStyle: {$style} (use this aesthetic throughout)\n";
		$prompt .= "\nInclude these sections: " . implode( ', ', $include_sections ) . "\n\n";

		$prompt .= "Requirements:\n";
		$prompt .= "- Use clean, modern HTML5 with semantic tags\n";
		$prompt .= "- Include inline CSS for complete styling\n";
		$prompt .= "- Make it fully responsive (mobile-first)\n";
		$prompt .= "- Use compelling copy that drives donations\n";
		$prompt .= "- Include clear call-to-action buttons\n";
		$prompt .= "- Add a working donation form placeholder\n";
		$prompt .= "- Use professional color schemes\n";
		$prompt .= "- Include social sharing buttons\n";
		$prompt .= "- Add microdata for SEO\n\n";

		$prompt .= "Return only the complete HTML code, ready to use.";

		return $prompt;
	}

	/**
	 * Gather analytics data for reporting.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $date_range  Date range.
	 * @return array|WP_Error Analytics data or error.
	 */
	private function gather_analytics_data( $campaign_id, $date_range ) {
		global $wpdb;

		$analytics_table = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaign_analytics';
		$campaign_table = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';

		// Build date filter
		$date_filter = '';
		if ( ! empty( $date_range['start'] ) && ! empty( $date_range['end'] ) ) {
			$date_filter = $wpdb->prepare(
				' AND analytics_date BETWEEN %s AND %s',
				$date_range['start'],
				$date_range['end']
			);
		}

		// Get campaign stats
		if ( $campaign_id ) {
			$campaign_stats = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$campaign_table} WHERE post_id = %d",
					$campaign_id
				)
			);

			$daily_stats = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$analytics_table} WHERE campaign_id = %d {$date_filter} ORDER BY analytics_date DESC LIMIT 30",
					$campaign_id
				)
			);
		} else {
			// Get all campaigns summary
			$campaign_stats = $wpdb->get_results(
				"SELECT * FROM {$campaign_table} WHERE status = 'active'"
			);

			$daily_stats = $wpdb->get_results(
				"SELECT * FROM {$analytics_table} WHERE 1=1 {$date_filter} ORDER BY analytics_date DESC LIMIT 30"
			);
		}

		return array(
			'campaign_stats' => $campaign_stats,
			'daily_stats'    => $daily_stats,
			'date_range'     => $date_range,
		);
	}

	/**
	 * Format analytics data for AI prompt.
	 *
	 * @param array $analytics_data Analytics data.
	 * @return string Formatted string.
	 */
	private function format_analytics_for_prompt( $analytics_data ) {
		$output = '';

		if ( isset( $analytics_data['campaign_stats'] ) ) {
			$stats = $analytics_data['campaign_stats'];

			if ( is_array( $stats ) ) {
				// Multiple campaigns
				$output .= "Total Campaigns: " . count( $stats ) . "\n";
				$total_goal = array_sum( array_column( (array) $stats, 'goal_amount' ) );
				$total_raised = array_sum( array_column( (array) $stats, 'current_amount' ) );
				$output .= "Combined Goal: $" . number_format( $total_goal, 2 ) . "\n";
				$output .= "Total Raised: $" . number_format( $total_raised, 2 ) . "\n";
				$output .= "Overall Progress: " . number_format( ( $total_raised / $total_goal ) * 100, 1 ) . "%\n\n";
			} else {
				// Single campaign
				$output .= "Goal: $" . number_format( $stats->goal_amount, 2 ) . "\n";
				$output .= "Raised: $" . number_format( $stats->current_amount, 2 ) . "\n";
				$output .= "Donors: " . $stats->donor_count . "\n";
				$progress = ( $stats->current_amount / $stats->goal_amount ) * 100;
				$output .= "Progress: " . number_format( $progress, 1 ) . "%\n\n";
			}
		}

		if ( isset( $analytics_data['daily_stats'] ) && ! empty( $analytics_data['daily_stats'] ) ) {
			$output .= "Recent Daily Performance:\n";
			foreach ( array_slice( $analytics_data['daily_stats'], 0, 7 ) as $day ) {
				$output .= "- {$day->analytics_date}: {$day->donations_count} donations, $" . number_format( $day->donations_amount, 2 ) . "\n";
			}
		}

		return $output;
	}

	/**
	 * Extract HTML from markdown code blocks.
	 *
	 * @param string $content Content with possible markdown.
	 * @return string Extracted HTML.
	 */
	private function extract_html_from_response( $content ) {
		// Check if content is wrapped in markdown code blocks
		if ( preg_match( '/```html\s*(.*?)\s*```/s', $content, $matches ) ) {
			return trim( $matches[1] );
		}

		if ( preg_match( '/```\s*(.*?)\s*```/s', $content, $matches ) ) {
			return trim( $matches[1] );
		}

		return $content;
	}

	/**
	 * Get conversation history.
	 *
	 * @param int    $campaign_id  Campaign ID.
	 * @param string $context_type Context type.
	 * @return array Conversation history.
	 */
	private function get_conversation_history( $campaign_id, $context_type = 'campaign' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'ai_conversations';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT conversation_history FROM {$table_name}
				WHERE user_id = %d AND campaign_id = %d AND context_type = %s
				ORDER BY updated_at DESC LIMIT 1",
				get_current_user_id(),
				$campaign_id,
				$context_type
			)
		);

		if ( $row && $row->conversation_history ) {
			$history = json_decode( $row->conversation_history, true );
			return is_array( $history ) ? $history : array();
		}

		return array();
	}

	/**
	 * Save conversation history.
	 *
	 * @param int    $campaign_id  Campaign ID.
	 * @param string $context_type Context type.
	 * @param array  $history      Conversation history.
	 * @param array  $usage        API usage data.
	 */
	private function save_conversation_history( $campaign_id, $context_type, $history, $usage ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'ai_conversations';

		$tokens_used = $usage['total_tokens'] ?? 0;

		// Cost calculation based on model
		$model = get_option( 'fundraiser_pro_openai_model', 'gpt-4-turbo-preview' );
		$cost_per_1k_tokens = $this->get_model_cost( $model );
		$cost = ( $tokens_used / 1000 ) * $cost_per_1k_tokens;

		// Check if record exists
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name}
				WHERE user_id = %d AND campaign_id = %d AND context_type = %s",
				get_current_user_id(),
				$campaign_id,
				$context_type
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$table_name,
				array(
					'conversation_history' => wp_json_encode( $history ),
					'tokens_used'          => $wpdb->prepare( 'tokens_used + %d', $tokens_used ),
					'cost'                 => $wpdb->prepare( 'cost + %f', $cost ),
					'updated_at'           => current_time( 'mysql' ),
				),
				array( 'id' => $existing ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table_name,
				array(
					'user_id'              => get_current_user_id(),
					'campaign_id'          => $campaign_id,
					'context_type'         => $context_type,
					'conversation_history' => wp_json_encode( $history ),
					'tokens_used'          => $tokens_used,
					'cost'                 => $cost,
					'created_at'           => current_time( 'mysql' ),
					'updated_at'           => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%d', '%f', '%s', '%s' )
			);
		}
	}

	/**
	 * Log image generation.
	 *
	 * @param int    $campaign_id     Campaign ID.
	 * @param string $original_prompt Original prompt.
	 * @param string $revised_prompt  Revised prompt.
	 */
	private function log_image_generation( $campaign_id, $original_prompt, $revised_prompt ) {
		global $wpdb;

		$log_table = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'activity_log';

		$wpdb->insert(
			$log_table,
			array(
				'user_id'     => get_current_user_id(),
				'action'      => 'ai_image_generated',
				'object_type' => 'campaign',
				'object_id'   => $campaign_id,
				'description' => "Generated image: {$original_prompt}",
				'metadata'    => wp_json_encode(
					array(
						'original_prompt' => $original_prompt,
						'revised_prompt'  => $revised_prompt,
						'model'           => 'dall-e-3',
					)
				),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get model cost per 1K tokens.
	 *
	 * @param string $model Model name.
	 * @return float Cost per 1K tokens.
	 */
	private function get_model_cost( $model ) {
		$costs = array(
			'gpt-4-turbo-preview' => 0.01,
			'gpt-4'               => 0.03,
			'gpt-3.5-turbo'       => 0.0015,
		);

		return $costs[ $model ] ?? 0.01;
	}

	/**
	 * Check if AI is enabled.
	 *
	 * @return bool Whether AI is enabled.
	 */
	private function is_enabled() {
		return get_option( 'fundraiser_pro_enable_ai_assistant', false ) && $this->get_api_key();
	}

	/**
	 * Get API key.
	 *
	 * @return string|false API key or false.
	 */
	private function get_api_key() {
		$encrypted_key = get_option( 'fundraiser_pro_openai_api_key', '' );

		if ( empty( $encrypted_key ) ) {
			return false;
		}

		// Decrypt the key (basic base64 - use proper encryption in production)
		return base64_decode( $encrypted_key );
	}
}
