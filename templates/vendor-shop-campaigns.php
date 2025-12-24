<?php
/**
 * Vendor Shop - Campaign Listing Template.
 * Shows all active campaigns for a fundraiser/vendor.
 *
 * @package FundraiserPro
 */

get_header();

// Get current vendor
$vendor_id = WCV_Vendors::get_vendor_from_permalink();
$vendor = get_userdata( $vendor_id );

if ( ! $vendor ) {
	echo '<p>Vendor not found.</p>';
	get_footer();
	return;
}

// Get vendor's campaigns
$campaign_integration = new FundraiserPro\VendorCampaignIntegration();
$campaigns = $campaign_integration->get_vendor_campaigns( $vendor_id );

// Get vendor shop info
$shop_name = get_user_meta( $vendor_id, 'pv_shop_name', true );
$shop_description = get_user_meta( $vendor_id, 'pv_shop_description', true );

if ( empty( $shop_name ) ) {
	$shop_name = $vendor->display_name . "'s Fundraisers";
}
?>

<div class="fundraiser-vendor-shop">
	<div class="vendor-header" style="text-align: center; padding: 40px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 40px;">
		<h1 style="margin: 0; font-size: 2.5em;"><?php echo esc_html( $shop_name ); ?></h1>
		<?php if ( $shop_description ) : ?>
			<p style="font-size: 1.2em; margin-top: 10px; opacity: 0.9;"><?php echo esc_html( $shop_description ); ?></p>
		<?php endif; ?>
	</div>

	<div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
		<?php if ( ! empty( $campaigns ) ) : ?>
			<h2 style="margin-bottom: 30px;">Active Campaigns</h2>

			<div class="campaigns-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px; margin-bottom: 60px;">
				<?php foreach ( $campaigns as $campaign ) : ?>
					<?php
					$campaign_id = $campaign->ID;
					$progress = $campaign_integration->get_campaign_progress( $campaign_id );
					$vendor_shop_url = $campaign_integration->get_vendor_shop_url( $vendor_id );
					$campaign_url = trailingslashit( $vendor_shop_url ) . $campaign->post_name . '/';
					$featured_image = get_the_post_thumbnail_url( $campaign_id, 'large' );
					?>

					<div class="campaign-card" style="background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.2s;">
						<?php if ( $featured_image ) : ?>
							<div class="campaign-image" style="height: 200px; background-image: url('<?php echo esc_url( $featured_image ); ?>'); background-size: cover; background-position: center;"></div>
						<?php endif; ?>

						<div class="campaign-content" style="padding: 25px;">
							<h3 style="margin: 0 0 15px; font-size: 1.5em;">
								<a href="<?php echo esc_url( $campaign_url ); ?>" style="color: #333; text-decoration: none;">
									<?php echo esc_html( $campaign->post_title ); ?>
								</a>
							</h3>

							<?php if ( $campaign->post_excerpt ) : ?>
								<p style="color: #666; margin-bottom: 20px;">
									<?php echo esc_html( wp_trim_words( $campaign->post_excerpt, 20 ) ); ?>
								</p>
							<?php endif; ?>

							<!-- Progress Bar -->
							<div class="progress-section" style="margin-bottom: 15px;">
								<div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
									<span style="font-weight: bold; color: #667eea;">$<?php echo number_format( $progress['current'], 2 ); ?></span>
									<span style="color: #666;">of $<?php echo number_format( $progress['goal'], 2 ); ?></span>
								</div>
								<div style="background: #e0e0e0; height: 12px; border-radius: 6px; overflow: hidden;">
									<div style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: <?php echo esc_attr( $progress['percentage'] ); ?>%; transition: width 0.3s;"></div>
								</div>
								<div style="text-align: center; margin-top: 8px; color: #666; font-size: 0.9em;">
									<?php echo round( $progress['percentage'] ); ?>% Funded
								</div>
							</div>

							<!-- Campaign Dates -->
							<?php if ( $progress['end_date'] && strtotime( $progress['end_date'] ) > time() ) : ?>
								<?php
								$days_left = max( 0, floor( ( strtotime( $progress['end_date'] ) - time() ) / DAY_IN_SECONDS ) );
								?>
								<div style="text-align: center; padding: 10px; background: #f5f5f5; border-radius: 6px; margin-bottom: 15px;">
									<strong><?php echo esc_html( $days_left ); ?> days left</strong>
								</div>
							<?php endif; ?>

							<a href="<?php echo esc_url( $campaign_url ); ?>" class="view-campaign-btn" style="display: block; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; transition: opacity 0.2s;">
								View Campaign →
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

		<?php else : ?>
			<div style="text-align: center; padding: 60px 20px;">
				<h2>No Active Campaigns</h2>
				<p style="color: #666; font-size: 1.1em;">This fundraiser doesn't have any active campaigns at the moment. Check back soon!</p>
			</div>
		<?php endif; ?>
	</div>
</div>

<style>
.campaign-card:hover {
	transform: translateY(-5px);
	box-shadow: 0 8px 12px rgba(0,0,0,0.15);
}

.view-campaign-btn:hover {
	opacity: 0.9;
}

@media (max-width: 768px) {
	.campaigns-grid {
		grid-template-columns: 1fr !important;
	}

	.vendor-header h1 {
		font-size: 2em !important;
	}
}
</style>

<?php get_footer(); ?>
