<?php
/**
 * Admin Dashboard View.
 *
 * @package FundraiserPro
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

global $wpdb;

$table_campaigns = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';
$table_raffles   = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'raffles';
$table_cash      = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'cash_transactions';

// Get statistics
$total_campaigns = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_campaigns}" );
$active_campaigns = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_campaigns} WHERE status = 'active'" );
$total_raised = $wpdb->get_var( "SELECT SUM(current_amount) FROM {$table_campaigns}" );
$pending_transactions = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_cash} WHERE approval_status = 'pending'" );

// Get recent campaigns
$recent_campaigns = $wpdb->get_results(
	"SELECT id, title, goal_amount, current_amount, status, created_at
	FROM {$table_campaigns}
	ORDER BY created_at DESC
	LIMIT 5"
);

?>

<div class="fundraiser-pro-wrap">
	<div class="fundraiser-pro-header">
		<h1><?php esc_html_e( 'Fundraiser Pro Dashboard', 'fundraiser-pro' ); ?></h1>
		<p><?php esc_html_e( 'Complete fundraising platform overview', 'fundraiser-pro' ); ?></p>
	</div>

	<!-- Statistics Grid -->
	<div class="fp-stats-grid">
		<div class="fp-stat-card">
			<div class="fp-stat-label"><?php esc_html_e( 'Total Campaigns', 'fundraiser-pro' ); ?></div>
			<div class="fp-stat-value"><?php echo esc_html( number_format_i18n( $total_campaigns ) ); ?></div>
			<div class="fp-stat-change positive">
				<span class="dashicons dashicons-arrow-up-alt"></span>
				<?php esc_html_e( 'All time', 'fundraiser-pro' ); ?>
			</div>
		</div>

		<div class="fp-stat-card">
			<div class="fp-stat-label"><?php esc_html_e( 'Active Campaigns', 'fundraiser-pro' ); ?></div>
			<div class="fp-stat-value"><?php echo esc_html( number_format_i18n( $active_campaigns ) ); ?></div>
			<div class="fp-stat-change positive">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Currently running', 'fundraiser-pro' ); ?>
			</div>
		</div>

		<div class="fp-stat-card">
			<div class="fp-stat-label"><?php esc_html_e( 'Total Raised', 'fundraiser-pro' ); ?></div>
			<div class="fp-stat-value">$<?php echo esc_html( number_format( $total_raised, 2 ) ); ?></div>
			<div class="fp-stat-change positive">
				<span class="dashicons dashicons-chart-line"></span>
				<?php esc_html_e( 'All campaigns', 'fundraiser-pro' ); ?>
			</div>
		</div>

		<div class="fp-stat-card">
			<div class="fp-stat-label"><?php esc_html_e( 'Pending Approvals', 'fundraiser-pro' ); ?></div>
			<div class="fp-stat-value"><?php echo esc_html( number_format_i18n( $pending_transactions ) ); ?></div>
			<div class="fp-stat-change <?php echo $pending_transactions > 0 ? 'negative' : ''; ?>">
				<span class="dashicons dashicons-clock"></span>
				<?php esc_html_e( 'Cash transactions', 'fundraiser-pro' ); ?>
			</div>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="fp-card">
		<div class="fp-card-header">
			<h2 class="fp-card-title"><?php esc_html_e( 'Quick Actions', 'fundraiser-pro' ); ?></h2>
		</div>
		<div class="fp-card-body">
			<div style="display: flex; gap: 12px; flex-wrap: wrap;">
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=fundraiser_campaign' ) ); ?>" class="fp-btn fp-btn-primary">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'New Campaign', 'fundraiser-pro' ); ?>
				</a>

				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=fundraiser_raffle' ) ); ?>" class="fp-btn fp-btn-secondary">
					<span class="dashicons dashicons-tickets-alt"></span>
					<?php esc_html_e( 'New Raffle', 'fundraiser-pro' ); ?>
				</a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fundraiser-pro-cash' ) ); ?>" class="fp-btn fp-btn-secondary">
					<span class="dashicons dashicons-money-alt"></span>
					<?php esc_html_e( 'Enter Cash Payment', 'fundraiser-pro' ); ?>
				</a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fundraiser-pro-analytics' ) ); ?>" class="fp-btn fp-btn-secondary">
					<span class="dashicons dashicons-chart-bar"></span>
					<?php esc_html_e( 'View Analytics', 'fundraiser-pro' ); ?>
				</a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fundraiser-pro-settings' ) ); ?>" class="fp-btn fp-btn-secondary">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Settings', 'fundraiser-pro' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Recent Campaigns -->
	<div class="fp-card">
		<div class="fp-card-header">
			<h2 class="fp-card-title"><?php esc_html_e( 'Recent Campaigns', 'fundraiser-pro' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=fundraiser_campaign' ) ); ?>" class="fp-btn fp-btn-sm fp-btn-secondary">
				<?php esc_html_e( 'View All', 'fundraiser-pro' ); ?>
			</a>
		</div>
		<div class="fp-card-body">
			<?php if ( $recent_campaigns ) : ?>
				<table class="fp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Campaign', 'fundraiser-pro' ); ?></th>
							<th><?php esc_html_e( 'Goal', 'fundraiser-pro' ); ?></th>
							<th><?php esc_html_e( 'Raised', 'fundraiser-pro' ); ?></th>
							<th><?php esc_html_e( 'Progress', 'fundraiser-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'fundraiser-pro' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'fundraiser-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_campaigns as $campaign ) : ?>
							<?php
							$progress = $campaign->goal_amount > 0 ? ( $campaign->current_amount / $campaign->goal_amount ) * 100 : 0;
							$progress = min( $progress, 100 );
							?>
							<tr>
								<td><strong><?php echo esc_html( $campaign->title ); ?></strong></td>
								<td>$<?php echo esc_html( number_format( $campaign->goal_amount, 2 ) ); ?></td>
								<td>$<?php echo esc_html( number_format( $campaign->current_amount, 2 ) ); ?></td>
								<td>
									<div class="fp-progress">
										<div class="fp-progress-bar" style="width: <?php echo esc_attr( $progress ); ?>%;"></div>
									</div>
									<small><?php echo esc_html( number_format( $progress, 1 ) ); ?>%</small>
								</td>
								<td>
									<?php
									$status_class = 'primary';
									if ( 'active' === $campaign->status ) {
										$status_class = 'success';
									} elseif ( 'completed' === $campaign->status ) {
										$status_class = 'warning';
									}
									?>
									<span class="fp-badge fp-badge-<?php echo esc_attr( $status_class ); ?>">
										<?php echo esc_html( ucfirst( $campaign->status ) ); ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $campaign->id ) ); ?>" class="fp-btn fp-btn-sm fp-btn-secondary">
										<?php esc_html_e( 'Edit', 'fundraiser-pro' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="fp-alert fp-alert-info">
					<span class="dashicons dashicons-info"></span>
					<div>
						<strong><?php esc_html_e( 'No campaigns yet', 'fundraiser-pro' ); ?></strong>
						<p><?php esc_html_e( 'Get started by creating your first fundraising campaign!', 'fundraiser-pro' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=fundraiser_campaign' ) ); ?>" class="fp-btn fp-btn-primary">
							<?php esc_html_e( 'Create Campaign', 'fundraiser-pro' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $pending_transactions > 0 ) : ?>
	<!-- Pending Approvals Alert -->
	<div class="fp-alert fp-alert-warning">
		<span class="dashicons dashicons-warning"></span>
		<div>
			<strong><?php esc_html_e( 'Pending Cash Transactions', 'fundraiser-pro' ); ?></strong>
			<p>
				<?php
				printf(
					/* translators: %d: Number of pending transactions */
					esc_html( _n( 'There is %d cash transaction awaiting approval.', 'There are %d cash transactions awaiting approval.', $pending_transactions, 'fundraiser-pro' ) ),
					$pending_transactions
				);
				?>
			</p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=fundraiser-pro-cash&status=pending' ) ); ?>" class="fp-btn fp-btn-warning">
				<?php esc_html_e( 'Review Transactions', 'fundraiser-pro' ); ?>
			</a>
		</div>
	</div>
	<?php endif; ?>

	<!-- Getting Started Guide -->
	<div class="fp-card">
		<div class="fp-card-header">
			<h2 class="fp-card-title"><?php esc_html_e( 'Getting Started', 'fundraiser-pro' ); ?></h2>
		</div>
		<div class="fp-card-body">
			<ol style="margin-left: 20px;">
				<li style="margin-bottom: 12px;">
					<strong><?php esc_html_e( 'Configure Settings', 'fundraiser-pro' ); ?></strong>
					<p style="margin: 4px 0 0;"><?php esc_html_e( 'Set up currency, email, and AI assistant in plugin settings.', 'fundraiser-pro' ); ?></p>
				</li>
				<li style="margin-bottom: 12px;">
					<strong><?php esc_html_e( 'Create Your First Campaign', 'fundraiser-pro' ); ?></strong>
					<p style="margin: 4px 0 0;"><?php esc_html_e( 'Use the campaign wizard to set up your fundraising goals.', 'fundraiser-pro' ); ?></p>
				</li>
				<li style="margin-bottom: 12px;">
					<strong><?php esc_html_e( 'Add Fundraising Components', 'fundraiser-pro' ); ?></strong>
					<p style="margin: 4px 0 0;"><?php esc_html_e( 'Create raffles, donation products, and embed using Gutenberg blocks.', 'fundraiser-pro' ); ?></p>
				</li>
				<li style="margin-bottom: 12px;">
					<strong><?php esc_html_e( 'Track & Manage', 'fundraiser-pro' ); ?></strong>
					<p style="margin: 4px 0 0;"><?php esc_html_e( 'Monitor progress in analytics, manage cash donations, and communicate with supporters.', 'fundraiser-pro' ); ?></p>
				</li>
			</ol>
		</div>
	</div>
</div>

<style>
	.fundraiser-pro-wrap .dashicons {
		font-size: 16px;
		width: 16px;
		height: 16px;
		vertical-align: middle;
	}
</style>
