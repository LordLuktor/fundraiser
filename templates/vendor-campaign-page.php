<?php
/**
 * Vendor Campaign Page Template.
 * Shows individual campaign with products.
 *
 * @package FundraiserPro
 */

get_header();

// Get current vendor and campaign
$vendor_id = WCV_Vendors::get_vendor_from_permalink();
$campaign_slug = get_query_var( 'vendor_campaign' );

$campaign_integration = new FundraiserPro\VendorCampaignIntegration();
$campaign = $campaign_integration->get_campaign_by_slug( $campaign_slug, $vendor_id );

if ( ! $campaign ) {
	echo '<p>Campaign not found.</p>';
	get_footer();
	return;
}

// Get campaign data
$campaign_id = $campaign->ID;
$progress = $campaign_integration->get_campaign_progress( $campaign_id );
$product_ids = $campaign_integration->get_campaign_products( $campaign_id );
$featured_image = get_the_post_thumbnail_url( $campaign_id, 'full' );
$vendor = get_userdata( $vendor_id );
$vendor_shop_url = $campaign_integration->get_vendor_shop_url( $vendor_id );
?>

<div class="fundraiser-campaign-page">
	<!-- Campaign Header -->
	<div class="campaign-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 60px 20px; text-align: center;">
		<div class="container" style="max-width: 1200px; margin: 0 auto;">
			<!-- Breadcrumbs -->
			<div style="margin-bottom: 20px; opacity: 0.9;">
				<a href="<?php echo esc_url( $vendor_shop_url ); ?>" style="color: white; text-decoration: none;">
					← Back to <?php echo esc_html( $vendor->display_name ); ?>'s Campaigns
				</a>
			</div>

			<h1 style="margin: 0; font-size: 3em;"><?php echo esc_html( $campaign->post_title ); ?></h1>

			<?php if ( $campaign->post_excerpt ) : ?>
				<p style="font-size: 1.3em; margin-top: 15px; opacity: 0.95;">
					<?php echo esc_html( $campaign->post_excerpt ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Campaign Hero Image -->
	<?php if ( $featured_image ) : ?>
		<div class="campaign-hero" style="height: 400px; background-image: url('<?php echo esc_url( $featured_image ); ?>'); background-size: cover; background-position: center;"></div>
	<?php endif; ?>

	<div class="container" style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;">
		<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px; margin-bottom: 60px;">
			<!-- Main Content -->
			<div class="campaign-description">
				<h2>About This Campaign</h2>
				<div style="line-height: 1.8; color: #333;">
					<?php echo wpautop( $campaign->post_content ); ?>
				</div>
			</div>

			<!-- Sidebar - Progress -->
			<div class="campaign-sidebar">
				<div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: sticky; top: 20px;">
					<h3 style="margin-top: 0;">Campaign Progress</h3>

					<div style="margin-bottom: 20px;">
						<div style="font-size: 2em; font-weight: bold; color: #667eea; margin-bottom: 5px;">
							$<?php echo number_format( $progress['current'], 2 ); ?>
						</div>
						<div style="color: #666;">
							raised of $<?php echo number_format( $progress['goal'], 2 ); ?> goal
						</div>
					</div>

					<!-- Progress Bar -->
					<div style="background: #e0e0e0; height: 16px; border-radius: 8px; overflow: hidden; margin-bottom: 10px;">
						<div style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: <?php echo esc_attr( $progress['percentage'] ); ?>%;"></div>
					</div>

					<div style="text-align: center; color: #666; font-weight: bold; margin-bottom: 20px;">
						<?php echo round( $progress['percentage'] ); ?>% Complete
					</div>

					<?php if ( $progress['end_date'] && strtotime( $progress['end_date'] ) > time() ) : ?>
						<?php
						$days_left = max( 0, floor( ( strtotime( $progress['end_date'] ) - time() ) / DAY_IN_SECONDS ) );
						?>
						<div style="background: #f5f5f5; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
							<div style="font-size: 1.5em; font-weight: bold; color: #333;">
								<?php echo esc_html( $days_left ); ?>
							</div>
							<div style="color: #666;">days remaining</div>
						</div>
					<?php endif; ?>

					<?php if ( $progress['end_date'] ) : ?>
						<div style="border-top: 1px solid #e0e0e0; padding-top: 15px; margin-top: 15px; font-size: 0.9em; color: #666;">
							<strong>Campaign Dates:</strong><br>
							<?php echo date_i18n( 'F j, Y', strtotime( $progress['start_date'] ) ); ?> -
							<?php echo date_i18n( 'F j, Y', strtotime( $progress['end_date'] ) ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Products Section -->
		<div class="campaign-products">
			<h2 style="margin-bottom: 30px; text-align: center; font-size: 2em;">Support This Campaign</h2>

			<?php if ( ! empty( $product_ids ) ) : ?>
				<div class="products-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 30px;">
					<?php foreach ( $product_ids as $product_id ) : ?>
						<?php
						$product = wc_get_product( $product_id );
						if ( ! $product ) continue;
						?>

						<div class="product-card" style="background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.2s;">
							<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
								<?php echo $product->get_image( 'medium', array( 'style' => 'width: 100%; height: 250px; object-fit: cover;' ) ); ?>
							</a>

							<div style="padding: 20px;">
								<h3 style="margin: 0 0 10px; font-size: 1.2em;">
									<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" style="color: #333; text-decoration: none;">
										<?php echo esc_html( $product->get_name() ); ?>
									</a>
								</h3>

								<div style="font-size: 1.5em; font-weight: bold; color: #667eea; margin-bottom: 15px;">
									<?php echo $product->get_price_html(); ?>
								</div>

								<?php if ( $product->get_short_description() ) : ?>
									<p style="color: #666; font-size: 0.9em; margin-bottom: 15px;">
										<?php echo wp_trim_words( $product->get_short_description(), 15 ); ?>
									</p>
								<?php endif; ?>

								<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
								   class="add-to-cart-btn"
								   data-product_id="<?php echo esc_attr( $product_id ); ?>"
								   style="display: block; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px; border-radius: 6px; text-decoration: none; font-weight: bold; transition: opacity 0.2s;">
									Add to Cart
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div style="text-align: center; padding: 60px 20px; background: #f5f5f5; border-radius: 12px;">
					<p style="font-size: 1.2em; color: #666;">Products coming soon! Check back later.</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<style>
.product-card:hover {
	transform: translateY(-5px);
	box-shadow: 0 8px 12px rgba(0,0,0,0.15);
}

.add-to-cart-btn:hover {
	opacity: 0.9;
}

@media (max-width: 968px) {
	.container > div[style*="grid-template-columns: 2fr 1fr"] {
		grid-template-columns: 1fr !important;
	}

	.campaign-header h1 {
		font-size: 2em !important;
	}

	.products-grid {
		grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)) !important;
	}
}
</style>

<?php get_footer(); ?>
