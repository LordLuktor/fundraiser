<?php
/**
 * Shortcode handlers.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * Shortcodes class.
 */
class Shortcodes {

	/**
	 * Register all shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( 'campaign', array( $this, 'campaign_shortcode' ) );
		add_shortcode( 'campaign_grid', array( $this, 'campaign_grid_shortcode' ) );
		add_shortcode( 'donation_form', array( $this, 'donation_form_shortcode' ) );
		add_shortcode( 'raffle', array( $this, 'raffle_shortcode' ) );
		add_shortcode( 'fundraiser_profile', array( $this, 'fundraiser_profile_shortcode' ) );
		add_shortcode( 'donor_wall', array( $this, 'donor_wall_shortcode' ) );
		add_shortcode( 'campaign_progress', array( $this, 'campaign_progress_shortcode' ) );
	}

	/**
	 * Campaign shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function campaign_shortcode( $atts ) {
		global $wpdb;

		$atts = shortcode_atts( array(
			'id'            => 0,
			'show_progress' => true,
			'show_donate'   => true,
		), $atts, 'campaign' );

		$campaign_id = absint( $atts['id'] );

		if ( ! $campaign_id ) {
			return '';
		}

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';

		$campaign = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $campaign_id )
		);

		if ( ! $campaign ) {
			return '';
		}

		$progress = $campaign->goal_amount > 0 ? ( $campaign->current_amount / $campaign->goal_amount ) * 100 : 0;
		$progress = min( $progress, 100 );

		ob_start();
		?>
		<div class="fp-campaign-single">
			<h2><?php echo esc_html( $campaign->title ); ?></h2>

			<?php if ( $campaign->featured_image ) : ?>
				<div class="fp-campaign-image">
					<img src="<?php echo esc_url( $campaign->featured_image ); ?>" alt="<?php echo esc_attr( $campaign->title ); ?>">
				</div>
			<?php endif; ?>

			<div class="fp-campaign-description">
				<?php echo wp_kses_post( $campaign->description ); ?>
			</div>

			<?php if ( $atts['show_progress'] ) : ?>
				<div class="fp-campaign-stats">
					<div class="fp-stat">
						<span class="fp-stat-label"><?php esc_html_e( 'Raised', 'fundraiser-pro' ); ?></span>
						<span class="fp-stat-value">$<?php echo esc_html( number_format( $campaign->current_amount, 2 ) ); ?></span>
					</div>
					<div class="fp-stat">
						<span class="fp-stat-label"><?php esc_html_e( 'Goal', 'fundraiser-pro' ); ?></span>
						<span class="fp-stat-value">$<?php echo esc_html( number_format( $campaign->goal_amount, 2 ) ); ?></span>
					</div>
					<div class="fp-stat">
						<span class="fp-stat-label"><?php esc_html_e( 'Progress', 'fundraiser-pro' ); ?></span>
						<span class="fp-stat-value"><?php echo esc_html( number_format( $progress, 1 ) ); ?>%</span>
					</div>
				</div>

				<div class="fp-progress" style="height: 12px; background: #e5e7eb; border-radius: 999px; overflow: hidden; margin: 20px 0;">
					<div class="fp-progress-bar" style="height: 100%; background: linear-gradient(90deg, #6366f1, #10b981); width: <?php echo esc_attr( $progress ); ?>%;"></div>
				</div>
			<?php endif; ?>

			<?php if ( $atts['show_donate'] ) : ?>
				<div class="fp-campaign-actions">
					<a href="#donate" class="fp-btn fp-btn-primary"><?php esc_html_e( 'Donate Now', 'fundraiser-pro' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Campaign grid shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function campaign_grid_shortcode( $atts ) {
		global $wpdb;

		$atts = shortcode_atts( array(
			'category' => '',
			'columns'  => 3,
			'limit'    => 9,
			'status'   => 'active',
		), $atts, 'campaign_grid' );

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';

		$query = "SELECT * FROM {$table_name} WHERE 1=1";

		if ( $atts['status'] ) {
			$query .= $wpdb->prepare( " AND status = %s", $atts['status'] );
		}

		if ( $atts['category'] ) {
			$query .= $wpdb->prepare( " AND category = %s", $atts['category'] );
		}

		$query .= " ORDER BY created_at DESC";
		$query .= $wpdb->prepare( " LIMIT %d", absint( $atts['limit'] ) );

		$campaigns = $wpdb->get_results( $query );

		if ( empty( $campaigns ) ) {
			return '<p>' . esc_html__( 'No campaigns found.', 'fundraiser-pro' ) . '</p>';
		}

		$columns = absint( $atts['columns'] );

		ob_start();
		?>
		<div class="fp-campaign-grid" style="display: grid; grid-template-columns: repeat(<?php echo esc_attr( $columns ); ?>, 1fr); gap: 24px; margin: 30px 0;">
			<?php foreach ( $campaigns as $campaign ) : ?>
				<?php
				$progress = $campaign->goal_amount > 0 ? ( $campaign->current_amount / $campaign->goal_amount ) * 100 : 0;
				$progress = min( $progress, 100 );
				?>
				<div class="fp-campaign-card" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.3s;">
					<?php if ( $campaign->featured_image ) : ?>
						<div class="fp-campaign-card-image" style="aspect-ratio: 16/9; overflow: hidden;">
							<img src="<?php echo esc_url( $campaign->featured_image ); ?>" alt="<?php echo esc_attr( $campaign->title ); ?>" style="width: 100%; height: 100%; object-fit: cover;">
						</div>
					<?php endif; ?>

					<div class="fp-campaign-card-content" style="padding: 20px;">
						<h3 style="margin: 0 0 12px; font-size: 18px; font-weight: 600;"><?php echo esc_html( $campaign->title ); ?></h3>

						<div class="fp-campaign-card-stats" style="margin-bottom: 12px;">
							<div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
								<span><?php esc_html_e( 'Raised:', 'fundraiser-pro' ); ?> <strong>$<?php echo esc_html( number_format( $campaign->current_amount, 0 ) ); ?></strong></span>
								<span><?php esc_html_e( 'Goal:', 'fundraiser-pro' ); ?> <strong>$<?php echo esc_html( number_format( $campaign->goal_amount, 0 ) ); ?></strong></span>
							</div>

							<div class="fp-progress" style="height: 8px; background: #e5e7eb; border-radius: 999px; overflow: hidden;">
								<div class="fp-progress-bar" style="height: 100%; background: linear-gradient(90deg, #6366f1, #10b981); width: <?php echo esc_attr( $progress ); ?>%;"></div>
							</div>
						</div>

						<a href="<?php echo esc_url( get_permalink( $campaign->id ) ); ?>" class="fp-btn fp-btn-primary" style="display: inline-block; padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 500;">
							<?php esc_html_e( 'View Campaign', 'fundraiser-pro' ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Donation form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function donation_form_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'campaign_id' => 0,
			'amounts'     => '25,50,100,250',
		), $atts, 'donation_form' );

		$amounts = array_map( 'absint', explode( ',', $atts['amounts'] ) );

		ob_start();
		?>
		<div class="fp-donation-form" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 500px; margin: 30px auto;">
			<h3 style="margin: 0 0 20px; text-align: center;"><?php esc_html_e( 'Make a Donation', 'fundraiser-pro' ); ?></h3>

			<form method="post">
				<div class="fp-amount-options" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
					<?php foreach ( $amounts as $amount ) : ?>
						<label style="cursor: pointer;">
							<input type="radio" name="donation_amount" value="<?php echo esc_attr( $amount ); ?>" style="margin-right: 8px;">
							$<?php echo esc_html( number_format( $amount ) ); ?>
						</label>
					<?php endforeach; ?>
				</div>

				<div style="margin-bottom: 20px;">
					<label style="display: block; margin-bottom: 8px; font-weight: 500;"><?php esc_html_e( 'Custom Amount', 'fundraiser-pro' ); ?></label>
					<input type="number" name="custom_amount" placeholder="Enter amount" style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px;">
				</div>

				<button type="submit" class="fp-btn fp-btn-primary" style="width: 100%; padding: 14px; background: #6366f1; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 500; cursor: pointer;">
					<?php esc_html_e( 'Donate Now', 'fundraiser-pro' ); ?>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Raffle shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function raffle_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'id' => 0,
		), $atts, 'raffle' );

		// Implementation would query raffle data and display
		return '<div class="fp-raffle">Raffle display (ID: ' . absint( $atts['id'] ) . ')</div>';
	}

	/**
	 * Fundraiser profile shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function fundraiser_profile_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'user_id' => 0,
		), $atts, 'fundraiser_profile' );

		// Implementation would display fundraiser profile
		return '<div class="fp-fundraiser-profile">Fundraiser profile (User ID: ' . absint( $atts['user_id'] ) . ')</div>';
	}

	/**
	 * Donor wall shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function donor_wall_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'campaign_id' => 0,
			'limit'       => 20,
		), $atts, 'donor_wall' );

		// Implementation would display donor wall
		return '<div class="fp-donor-wall">Donor wall display</div>';
	}

	/**
	 * Campaign progress shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function campaign_progress_shortcode( $atts ) {
		global $wpdb;

		$atts = shortcode_atts( array(
			'id' => 0,
		), $atts, 'campaign_progress' );

		$campaign_id = absint( $atts['id'] );

		if ( ! $campaign_id ) {
			return '';
		}

		$table_name = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';

		$campaign = $wpdb->get_row(
			$wpdb->prepare( "SELECT goal_amount, current_amount FROM {$table_name} WHERE id = %d", $campaign_id )
		);

		if ( ! $campaign ) {
			return '';
		}

		$progress = $campaign->goal_amount > 0 ? ( $campaign->current_amount / $campaign->goal_amount ) * 100 : 0;
		$progress = min( $progress, 100 );

		ob_start();
		?>
		<div class="fp-campaign-progress-widget" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			<div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
				<div>
					<div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;"><?php esc_html_e( 'Raised', 'fundraiser-pro' ); ?></div>
					<div style="font-size: 24px; font-weight: 700; color: #111827;">$<?php echo esc_html( number_format( $campaign->current_amount, 0 ) ); ?></div>
				</div>
				<div style="text-align: right;">
					<div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;"><?php esc_html_e( 'Goal', 'fundraiser-pro' ); ?></div>
					<div style="font-size: 24px; font-weight: 700; color: #111827;">$<?php echo esc_html( number_format( $campaign->goal_amount, 0 ) ); ?></div>
				</div>
			</div>

			<div class="fp-progress" style="height: 12px; background: #e5e7eb; border-radius: 999px; overflow: hidden; margin-bottom: 8px;">
				<div class="fp-progress-bar" style="height: 100%; background: linear-gradient(90deg, #6366f1, #10b981); width: <?php echo esc_attr( $progress ); ?>%;"></div>
			</div>

			<div style="text-align: center; font-size: 14px; color: #6b7280;">
				<?php echo esc_html( number_format( $progress, 1 ) ); ?>% <?php esc_html_e( 'of goal reached', 'fundraiser-pro' ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
