<?php
/**
 * Admin Analytics View.
 *
 * @package FundraiserPro
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

global $wpdb;

$table_campaigns = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaigns';
$table_analytics = $wpdb->prefix . FUNDRAISER_PRO_DB_PREFIX . 'campaign_analytics';

// Get date range from request
$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
$end_date = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : date( 'Y-m-d' );
$campaign_filter = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;

// Get campaigns for filter dropdown
$campaigns = $wpdb->get_results(
	"SELECT id, title FROM {$table_campaigns} ORDER BY title ASC"
);

// Get analytics data
$where_clause = "WHERE analytics_date BETWEEN '{$start_date}' AND '{$end_date}'";
if ( $campaign_filter ) {
	$where_clause .= " AND campaign_id = {$campaign_filter}";
}

$analytics_data = $wpdb->get_results(
	"SELECT * FROM {$table_analytics} {$where_clause} ORDER BY analytics_date ASC"
);

// Calculate totals
$total_donations = 0;
$total_amount = 0;
$total_views = 0;

foreach ( $analytics_data as $data ) {
	$total_donations += $data->donations_count ?? 0;
	$total_amount += $data->donations_amount ?? 0;
	$total_views += $data->page_views ?? 0;
}

$conversion_rate = $total_views > 0 ? ( $total_donations / $total_views ) * 100 : 0;
$average_donation = $total_donations > 0 ? $total_amount / $total_donations : 0;

?>

<div class="fundraiser-pro-wrap">
	<div class="fundraiser-pro-header">
		<h1><?php esc_html_e( 'Analytics Dashboard', 'fundraiser-pro' ); ?></h1>
		<p><?php esc_html_e( 'Track your fundraising performance and insights', 'fundraiser-pro' ); ?></p>
	</div>

	<!-- Filters -->
	<div class="fp-card">
		<div class="fp-card-body">
			<form method="get" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: end;">
				<input type="hidden" name="page" value="fundraiser-pro-analytics">

				<div class="fp-form-group" style="margin: 0;">
					<label for="campaign_id"><?php esc_html_e( 'Campaign', 'fundraiser-pro' ); ?></label>
					<select name="campaign_id" id="campaign_id" class="fp-input">
						<option value="0"><?php esc_html_e( 'All Campaigns', 'fundraiser-pro' ); ?></option>
						<?php foreach ( $campaigns as $campaign ) : ?>
							<option value="<?php echo esc_attr( $campaign->id ); ?>" <?php selected( $campaign_filter, $campaign->id ); ?>>
								<?php echo esc_html( $campaign->title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="fp-form-group" style="margin: 0;">
					<label for="start_date"><?php esc_html_e( 'Start Date', 'fundraiser-pro' ); ?></label>
					<input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( $start_date ); ?>" class="fp-input">
				</div>

				<div class="fp-form-group" style="margin: 0;">
					<label for="end_date"><?php esc_html_e( 'End Date', 'fundraiser-pro' ); ?></label>
					<input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( $end_date ); ?>" class="fp-input">
				</div>

				<button type="submit" class="fp-btn fp-btn-primary">
					<span class="dashicons dashicons-filter"></span>
					<?php esc_html_e( 'Apply Filters', 'fundraiser-pro' ); ?>
				</button>
			</form>
		</div>
	</div>

	<!-- Key Metrics -->
	<div class="fp-stats-grid">
		<div class="fp-stat-card">
			<div class="fp-stat-label"><?php esc_html_e( 'Total Donations', 'fundraiser-pro' ); ?></div>
			<div class="fp-stat-value"><?php echo esc_html( number_format_i18n( $total_donations ) ); ?></div>
			<div class="fp-stat-change">
				<span class="dashicons dashicons-chart-line"></span>
				<?php echo esc_html( $start_date ); ?> - <?php echo esc_html( $end_date ); ?>
			</div>
		</div>

		<div class="fp-stat-card">
			<div class="fp-stat-label"><?php esc_html_e( 'Total Amount', 'fundraiser-pro' ); ?></div>
			<div class="fp-stat-value">$<?php echo esc_html( number_format( $total_amount, 2 ) ); ?></div>
			<div class="fp-stat-change positive">
				<span class="dashicons dashicons-money-alt"></span>
				<?php esc_html_e( 'Raised', 'fundraiser-pro' ); ?>
			</div>
		</div>

		<div class="fp-stat-card">
			<div class="fp-stat-label"><?php esc_html_e( 'Average Donation', 'fundraiser-pro' ); ?></div>
			<div class="fp-stat-value">$<?php echo esc_html( number_format( $average_donation, 2 ) ); ?></div>
			<div class="fp-stat-change">
				<span class="dashicons dashicons-calculator"></span>
				<?php esc_html_e( 'Per donation', 'fundraiser-pro' ); ?>
			</div>
		</div>

		<div class="fp-stat-card">
			<div class="fp-stat-label"><?php esc_html_e( 'Conversion Rate', 'fundraiser-pro' ); ?></div>
			<div class="fp-stat-value"><?php echo esc_html( number_format( $conversion_rate, 2 ) ); ?>%</div>
			<div class="fp-stat-change">
				<span class="dashicons dashicons-admin-users"></span>
				<?php echo esc_html( number_format_i18n( $total_views ) ); ?> <?php esc_html_e( 'views', 'fundraiser-pro' ); ?>
			</div>
		</div>
	</div>

	<!-- Charts -->
	<div class="fp-card">
		<div class="fp-card-header">
			<h2 class="fp-card-title"><?php esc_html_e( 'Donations Over Time', 'fundraiser-pro' ); ?></h2>
			<button type="button" class="fp-btn fp-btn-sm fp-btn-secondary" id="generate-ai-report">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Generate AI Report', 'fundraiser-pro' ); ?>
			</button>
		</div>
		<div class="fp-card-body">
			<?php if ( ! empty( $analytics_data ) ) : ?>
				<canvas id="donationsChart" style="max-height: 400px;"></canvas>
			<?php else : ?>
				<div class="fp-alert fp-alert-info">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'No analytics data available for the selected period.', 'fundraiser-pro' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- AI Report Section -->
	<div class="fp-card" id="ai-report-section" style="display: none;">
		<div class="fp-card-header">
			<h2 class="fp-card-title"><?php esc_html_e( 'AI-Generated Insights', 'fundraiser-pro' ); ?></h2>
		</div>
		<div class="fp-card-body">
			<div id="ai-report-content">
				<div class="fp-loading">
					<span class="fp-spinner"></span>
					<?php esc_html_e( 'Generating insights...', 'fundraiser-pro' ); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Data Table -->
	<div class="fp-card">
		<div class="fp-card-header">
			<h2 class="fp-card-title"><?php esc_html_e( 'Daily Breakdown', 'fundraiser-pro' ); ?></h2>
			<button type="button" class="fp-btn fp-btn-sm fp-btn-secondary" id="export-csv">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'fundraiser-pro' ); ?>
			</button>
		</div>
		<div class="fp-card-body">
			<?php if ( ! empty( $analytics_data ) ) : ?>
				<table class="fp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'fundraiser-pro' ); ?></th>
							<th><?php esc_html_e( 'Page Views', 'fundraiser-pro' ); ?></th>
							<th><?php esc_html_e( 'Donations', 'fundraiser-pro' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'fundraiser-pro' ); ?></th>
							<th><?php esc_html_e( 'Conversion', 'fundraiser-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $analytics_data as $row ) : ?>
							<?php
							$row_views = $row->page_views ?? 0;
							$row_donations = $row->donations_count ?? 0;
							$row_conversion = $row_views > 0 ? ( $row_donations / $row_views ) * 100 : 0;
							?>
							<tr>
								<td><?php echo esc_html( date( 'M j, Y', strtotime( $row->analytics_date ) ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row_views ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row_donations ) ); ?></td>
								<td>$<?php echo esc_html( number_format( $row->donations_amount ?? 0, 2 ) ); ?></td>
								<td><?php echo esc_html( number_format( $row_conversion, 2 ) ); ?>%</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th><?php esc_html_e( 'Total', 'fundraiser-pro' ); ?></th>
							<th><?php echo esc_html( number_format_i18n( $total_views ) ); ?></th>
							<th><?php echo esc_html( number_format_i18n( $total_donations ) ); ?></th>
							<th>$<?php echo esc_html( number_format( $total_amount, 2 ) ); ?></th>
							<th><?php echo esc_html( number_format( $conversion_rate, 2 ) ); ?>%</th>
						</tr>
					</tfoot>
				</table>
			<?php else : ?>
				<div class="fp-alert fp-alert-info">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'No data available for the selected period.', 'fundraiser-pro' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php if ( ! empty( $analytics_data ) ) : ?>
<script>
jQuery(document).ready(function($) {
	// Prepare chart data
	var chartData = {
		labels: <?php echo wp_json_encode( array_column( $analytics_data, 'analytics_date' ) ); ?>,
		datasets: [
			{
				label: '<?php esc_html_e( 'Donation Amount', 'fundraiser-pro' ); ?>',
				data: <?php echo wp_json_encode( array_column( $analytics_data, 'donations_amount' ) ); ?>,
				borderColor: 'rgb(59, 130, 246)',
				backgroundColor: 'rgba(59, 130, 246, 0.1)',
				tension: 0.4,
				yAxisID: 'y'
			},
			{
				label: '<?php esc_html_e( 'Donation Count', 'fundraiser-pro' ); ?>',
				data: <?php echo wp_json_encode( array_column( $analytics_data, 'donations_count' ) ); ?>,
				borderColor: 'rgb(16, 185, 129)',
				backgroundColor: 'rgba(16, 185, 129, 0.1)',
				tension: 0.4,
				yAxisID: 'y1'
			}
		]
	};

	// Create chart
	if (typeof Chart !== 'undefined') {
		var ctx = document.getElementById('donationsChart').getContext('2d');
		var chart = new Chart(ctx, {
			type: 'line',
			data: chartData,
			options: {
				responsive: true,
				maintainAspectRatio: true,
				interaction: {
					mode: 'index',
					intersect: false
				},
				plugins: {
					legend: {
						position: 'top'
					},
					tooltip: {
						callbacks: {
							label: function(context) {
								var label = context.dataset.label || '';
								if (label) {
									label += ': ';
								}
								if (context.datasetIndex === 0) {
									label += '$' + context.parsed.y.toFixed(2);
								} else {
									label += context.parsed.y;
								}
								return label;
							}
						}
					}
				},
				scales: {
					y: {
						type: 'linear',
						display: true,
						position: 'left',
						title: {
							display: true,
							text: '<?php esc_html_e( 'Amount ($)', 'fundraiser-pro' ); ?>'
						}
					},
					y1: {
						type: 'linear',
						display: true,
						position: 'right',
						title: {
							display: true,
							text: '<?php esc_html_e( 'Count', 'fundraiser-pro' ); ?>'
						},
						grid: {
							drawOnChartArea: false
						}
					}
				}
			}
		});
	}

	// Generate AI Report
	$('#generate-ai-report').on('click', function() {
		var $btn = $(this);
		var $section = $('#ai-report-section');
		var $content = $('#ai-report-content');

		$section.slideDown();
		$btn.prop('disabled', true).find('.dashicons').addClass('rotating');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fundraiser_pro_generate_report',
				nonce: '<?php echo wp_create_nonce( 'fundraiser_pro_admin' ); ?>',
				report_type: 'insights',
				campaign_id: <?php echo absint( $campaign_filter ); ?>,
				date_range: {
					start: '<?php echo esc_js( $start_date ); ?>',
					end: '<?php echo esc_js( $end_date ); ?>'
				}
			},
			success: function(response) {
				if (response.success) {
					$content.html('<div class="fp-ai-report">' + response.data.analysis + '</div>');
				} else {
					$content.html('<div class="fp-alert fp-alert-error">' + response.data.message + '</div>');
				}
			},
			error: function() {
				$content.html('<div class="fp-alert fp-alert-error"><?php esc_html_e( 'Failed to generate report', 'fundraiser-pro' ); ?></div>');
			},
			complete: function() {
				$btn.prop('disabled', false).find('.dashicons').removeClass('rotating');
			}
		});
	});

	// Export CSV
	$('#export-csv').on('click', function() {
		window.location.href = '<?php echo admin_url( 'admin.php?page=fundraiser-pro-analytics&export=csv&start_date=' . urlencode( $start_date ) . '&end_date=' . urlencode( $end_date ) . '&campaign_id=' . absint( $campaign_filter ) ); ?>';
	});
});
</script>

<style>
.rotating {
	animation: rotation 1s infinite linear;
}

@keyframes rotation {
	from {
		transform: rotate(0deg);
	}
	to {
		transform: rotate(359deg);
	}
}

.fp-ai-report {
	line-height: 1.8;
	white-space: pre-wrap;
}

.fp-ai-report h3 {
	margin-top: 20px;
	margin-bottom: 10px;
	color: #1e293b;
}

.fp-ai-report ul, .fp-ai-report ol {
	margin-left: 20px;
	margin-bottom: 15px;
}

.fp-ai-report li {
	margin-bottom: 8px;
}
</style>
<?php endif; ?>
