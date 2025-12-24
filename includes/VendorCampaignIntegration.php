<?php
/**
 * Vendor Campaign Integration.
 * Integrates campaigns with WC Vendors shop pages.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * VendorCampaignIntegration class.
 */
class VendorCampaignIntegration {

	/**
	 * Initialize integration.
	 */
	public function __construct() {
		// Override vendor shop template
		add_filter( 'wcvendors_pro_store_template_path', array( $this, 'override_vendor_shop_template' ) );
		add_filter( 'template_include', array( $this, 'load_vendor_campaign_template' ), 99 );

		// Add campaign rewrite rules
		add_action( 'init', array( $this, 'add_campaign_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_campaign_query_vars' ) );

		// Link campaigns to vendors
		add_action( 'save_post_fundraiser_campaign', array( $this, 'link_campaign_to_vendor' ), 10, 2 );
	}

	/**
	 * Override vendor shop template.
	 *
	 * @param string $template Template path.
	 * @return string Modified template path.
	 */
	public function override_vendor_shop_template( $template ) {
		return FUNDRAISER_PRO_PATH . 'templates/vendor-shop-campaigns.php';
	}

	/**
	 * Load vendor campaign template.
	 *
	 * @param string $template Current template.
	 * @return string Modified template.
	 */
	public function load_vendor_campaign_template( $template ) {
		global $wp_query;

		// Check if this is a vendor page
		if ( ! function_exists( 'wcv_is_store_page' ) || ! wcv_is_store_page() ) {
			return $template;
		}

		// Check if campaign slug is present
		if ( get_query_var( 'vendor_campaign' ) ) {
			$campaign_template = FUNDRAISER_PRO_PATH . 'templates/vendor-campaign-page.php';
			if ( file_exists( $campaign_template ) ) {
				return $campaign_template;
			}
		} else {
			// Main vendor shop - show campaign listing
			$listing_template = FUNDRAISER_PRO_PATH . 'templates/vendor-shop-campaigns.php';
			if ( file_exists( $listing_template ) ) {
				return $listing_template;
			}
		}

		return $template;
	}

	/**
	 * Add campaign rewrite rules.
	 */
	public function add_campaign_rewrite_rules() {
		// Get vendor base from WC Vendors settings
		$vendor_base = get_option( 'wcvendors_vendor_shop_permalink', 'vendor' );

		// Add rule for: /vendor/{vendor-slug}/{campaign-slug}/
		add_rewrite_rule(
			'^' . $vendor_base . '/([^/]+)/([^/]+)/?$',
			'index.php?' . $vendor_base . '=$matches[1]&vendor_campaign=$matches[2]',
			'top'
		);
	}

	/**
	 * Add campaign query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array Modified query vars.
	 */
	public function add_campaign_query_vars( $vars ) {
		$vars[] = 'vendor_campaign';
		return $vars;
	}

	/**
	 * Link campaign to vendor when saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function link_campaign_to_vendor( $post_id, $post ) {
		// Get campaign author
		$fundraiser_id = $post->post_author;

		// Check if fundraiser is a vendor
		if ( class_exists( 'FundraiserPro\VendorIntegration' ) ) {
			$vendor_integration = new VendorIntegration();
			if ( ! $vendor_integration->is_vendor( $fundraiser_id ) ) {
				// Convert to vendor if not already
				$vendor_integration->convert_fundraiser_to_vendor( $fundraiser_id );
			}
		}

		// Store vendor ID in campaign meta
		update_post_meta( $post_id, '_vendor_id', $fundraiser_id );

		// Store vendor shop URL
		$vendor_shop_url = $this->get_vendor_shop_url( $fundraiser_id );
		update_post_meta( $post_id, '_vendor_shop_url', $vendor_shop_url );
	}

	/**
	 * Get vendor shop URL.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return string Vendor shop URL.
	 */
	public function get_vendor_shop_url( $vendor_id ) {
		if ( function_exists( 'WCV_Vendors::get_vendor_shop_page' ) ) {
			return WCV_Vendors::get_vendor_shop_page( $vendor_id );
		}

		// Fallback
		$vendor_base = get_option( 'wcvendors_vendor_shop_permalink', 'vendor' );
		$user = get_userdata( $vendor_id );
		$shop_slug = get_user_meta( $vendor_id, 'pv_shop_slug', true );

		if ( empty( $shop_slug ) && $user ) {
			$shop_slug = sanitize_title( $user->display_name );
		}

		return home_url( '/' . $vendor_base . '/' . $shop_slug . '/' );
	}

	/**
	 * Get vendor's campaigns.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array Array of campaign posts.
	 */
	public function get_vendor_campaigns( $vendor_id ) {
		$args = array(
			'post_type'      => 'fundraiser_campaign',
			'author'         => $vendor_id,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		return get_posts( $args );
	}

	/**
	 * Get campaign by slug and vendor.
	 *
	 * @param string $campaign_slug Campaign slug.
	 * @param int    $vendor_id     Vendor user ID.
	 * @return WP_Post|null Campaign post or null.
	 */
	public function get_campaign_by_slug( $campaign_slug, $vendor_id ) {
		$args = array(
			'post_type'      => 'fundraiser_campaign',
			'name'           => $campaign_slug,
			'author'         => $vendor_id,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		);

		$posts = get_posts( $args );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Get campaign products.
	 *
	 * @param int $campaign_id Campaign post ID.
	 * @return array Array of product IDs.
	 */
	public function get_campaign_products( $campaign_id ) {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_fundraiser_campaign_id',
					'value' => $campaign_id,
				),
			),
		);

		$products = get_posts( $args );
		return wp_list_pluck( $products, 'ID' );
	}

	/**
	 * Get campaign progress data.
	 *
	 * @param int $campaign_id Campaign post ID.
	 * @return array Progress data.
	 */
	public function get_campaign_progress( $campaign_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fundraiser_campaigns';

		$campaign = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT goal_amount, current_amount, start_date, end_date FROM {$table_name} WHERE id = %d",
				$campaign_id
			)
		);

		if ( ! $campaign ) {
			return array(
				'goal'       => 0,
				'current'    => 0,
				'percentage' => 0,
				'remaining'  => 0,
				'start_date' => '',
				'end_date'   => '',
			);
		}

		$goal = floatval( $campaign->goal_amount );
		$current = floatval( $campaign->current_amount );
		$percentage = $goal > 0 ? ( $current / $goal ) * 100 : 0;
		$remaining = max( 0, $goal - $current );

		return array(
			'goal'       => $goal,
			'current'    => $current,
			'percentage' => min( 100, $percentage ),
			'remaining'  => $remaining,
			'start_date' => $campaign->start_date,
			'end_date'   => $campaign->end_date,
		);
	}
}
