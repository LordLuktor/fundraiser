/**
 * Fundraiser Pro Admin JavaScript
 * Modern, interactive admin interface
 */

(function($) {
	'use strict';

	const FundraiserProAdmin = {
		/**
		 * Initialize admin functionality
		 */
		init() {
			this.initTabs();
			this.initModals();
			this.initForms();
			this.initCharts();
			this.initTooltips();
			this.initAjaxActions();
			this.initCampaignWizard();
			this.initCashTransactions();
			this.initAIAssistant();
		},

		/**
		 * Initialize tab navigation
		 */
		initTabs() {
			$('.fp-tab').on('click', function(e) {
				e.preventDefault();

				const tab = $(this);
				const target = tab.data('tab');

				// Update active tab
				tab.siblings().removeClass('active');
				tab.addClass('active');

				// Update active content
				$('.fp-tab-content').removeClass('active');
				$('#' + target).addClass('active');
			});
		},

		/**
		 * Initialize modals
		 */
		initModals() {
			// Open modal
			$('[data-modal]').on('click', function(e) {
				e.preventDefault();
				const modalId = $(this).data('modal');
				$('#' + modalId).fadeIn(200);
			});

			// Close modal
			$('.fp-modal-close, .fp-modal-overlay').on('click', function(e) {
				if (e.target === this) {
					$(this).closest('.fp-modal-overlay').fadeOut(200);
				}
			});

			// Close on Escape key
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					$('.fp-modal-overlay').fadeOut(200);
				}
			});
		},

		/**
		 * Initialize form handling
		 */
		initForms() {
			// Form validation
			$('.fp-form').on('submit', function(e) {
				const form = $(this);
				let isValid = true;

				// Clear previous errors
				form.find('.fp-form-error').remove();

				// Validate required fields
				form.find('[required]').each(function() {
					const field = $(this);
					if (!field.val()) {
						isValid = false;
						field.after('<span class="fp-form-error" style="color: var(--fp-danger); font-size: 13px; margin-top: 4px; display: block;">This field is required</span>');
					}
				});

				// Validate email fields
				form.find('[type="email"]').each(function() {
					const field = $(this);
					const email = field.val();
					if (email && !FundraiserProAdmin.isValidEmail(email)) {
						isValid = false;
						field.after('<span class="fp-form-error" style="color: var(--fp-danger); font-size: 13px; margin-top: 4px; display: block;">Please enter a valid email address</span>');
					}
				});

				if (!isValid) {
					e.preventDefault();
					FundraiserProAdmin.showNotification('Please fix the errors in the form', 'error');
				}
			});

			// Auto-save functionality
			$('[data-autosave]').on('change', function() {
				const field = $(this);
				FundraiserProAdmin.autoSave(field);
			});
		},

		/**
		 * Initialize Chart.js charts
		 */
		initCharts() {
			// Revenue Chart
			const revenueChart = document.getElementById('fp-revenue-chart');
			if (revenueChart) {
				new Chart(revenueChart, {
					type: 'line',
					data: {
						labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
						datasets: [{
							label: 'Revenue',
							data: [12000, 19000, 15000, 25000, 22000, 30000],
							borderColor: '#6366f1',
							backgroundColor: 'rgba(99, 102, 241, 0.1)',
							tension: 0.4,
							fill: true
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: {
								display: false
							}
						},
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									callback: function(value) {
										return '$' + value.toLocaleString();
									}
								}
							}
						}
					}
				});
			}

			// Campaign Progress Charts
			$('.fp-campaign-chart').each(function() {
				const chart = $(this)[0];
				const progress = $(this).data('progress') || 0;

				new Chart(chart, {
					type: 'doughnut',
					data: {
						datasets: [{
							data: [progress, 100 - progress],
							backgroundColor: ['#6366f1', '#e5e7eb'],
							borderWidth: 0
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						cutout: '75%',
						plugins: {
							legend: {
								display: false
							},
							tooltip: {
								enabled: false
							}
						}
					}
				});
			});
		},

		/**
		 * Initialize tooltips
		 */
		initTooltips() {
			$('[data-tooltip]').each(function() {
				const element = $(this);
				const text = element.data('tooltip');

				element.addClass('fp-tooltip');
				element.append(`<span class="fp-tooltiptext">${text}</span>`);
			});
		},

		/**
		 * Initialize AJAX actions
		 */
		initAjaxActions() {
			// Approve cash transaction
			$('.fp-approve-transaction').on('click', function(e) {
				e.preventDefault();
				const button = $(this);
				const transactionId = button.data('transaction-id');

				FundraiserProAdmin.approveTransaction(transactionId);
			});

			// Reject cash transaction
			$('.fp-reject-transaction').on('click', function(e) {
				e.preventDefault();
				const button = $(this);
				const transactionId = button.data('transaction-id');

				if (confirm('Are you sure you want to reject this transaction?')) {
					FundraiserProAdmin.rejectTransaction(transactionId);
				}
			});

			// Load more campaigns
			$('.fp-load-more-campaigns').on('click', function(e) {
				e.preventDefault();
				FundraiserProAdmin.loadMoreCampaigns();
			});
		},

		/**
		 * Initialize campaign creation wizard
		 */
		initCampaignWizard() {
			let currentStep = 1;
			const totalSteps = $('.fp-wizard-step').length;

			$('.fp-wizard-next').on('click', function() {
				if (currentStep < totalSteps) {
					$('.fp-wizard-step').eq(currentStep - 1).fadeOut(200, function() {
						currentStep++;
						$('.fp-wizard-step').eq(currentStep - 1).fadeIn(200);
						FundraiserProAdmin.updateWizardProgress(currentStep, totalSteps);
					});
				}
			});

			$('.fp-wizard-prev').on('click', function() {
				if (currentStep > 1) {
					$('.fp-wizard-step').eq(currentStep - 1).fadeOut(200, function() {
						currentStep--;
						$('.fp-wizard-step').eq(currentStep - 1).fadeIn(200);
						FundraiserProAdmin.updateWizardProgress(currentStep, totalSteps);
					});
				}
			});
		},

		/**
		 * Initialize cash transaction management
		 */
		initCashTransactions() {
			// Calculate change
			$('#fp-amount-tendered').on('input', function() {
				const amountDue = parseFloat($('#fp-amount-due').val()) || 0;
				const amountTendered = parseFloat($(this).val()) || 0;
				const change = amountTendered - amountDue;

				$('#fp-change').val(change >= 0 ? change.toFixed(2) : '0.00');
			});

			// Print receipt
			$('.fp-print-receipt').on('click', function(e) {
				e.preventDefault();
				const receiptId = $(this).data('receipt-id');
				FundraiserProAdmin.printReceipt(receiptId);
			});
		},

		/**
		 * Initialize AI Assistant
		 */
		initAIAssistant() {
			$('.fp-ai-chat-send').on('click', function() {
				FundraiserProAdmin.sendAIMessage();
			});

			$('#fp-ai-chat-input').on('keypress', function(e) {
				if (e.which === 13 && !e.shiftKey) {
					e.preventDefault();
					FundraiserProAdmin.sendAIMessage();
				}
			});

			// AI suggestions
			$('[data-ai-suggest]').on('click', function(e) {
				e.preventDefault();
				const field = $(this).data('ai-suggest');
				FundraiserProAdmin.getAISuggestion(field);
			});
		},

		/**
		 * Send AI chat message
		 */
		sendAIMessage() {
			const input = $('#fp-ai-chat-input');
			const message = input.val().trim();

			if (!message) return;

			const chatBox = $('.fp-ai-chat-messages');

			// Add user message
			chatBox.append(`
				<div class="fp-ai-message fp-ai-message-user">
					<div class="fp-ai-message-content">${FundraiserProAdmin.escapeHtml(message)}</div>
				</div>
			`);

			input.val('');

			// Show typing indicator
			chatBox.append('<div class="fp-ai-typing">AI is thinking...</div>');
			chatBox.scrollTop(chatBox[0].scrollHeight);

			$.ajax({
				url: fundraiserProAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fundraiser_ai_chat',
					nonce: fundraiserProAdmin.nonce,
					message: message,
					campaign_id: $('#post_ID').val()
				},
				success(response) {
					$('.fp-ai-typing').remove();

					if (response.success) {
						chatBox.append(`
							<div class="fp-ai-message fp-ai-message-assistant">
								<div class="fp-ai-message-content">${response.data.reply}</div>
							</div>
						`);
					} else {
						chatBox.append(`
							<div class="fp-ai-message fp-ai-message-error">
								<div class="fp-ai-message-content">Error: ${response.data.message}</div>
							</div>
						`);
					}

					chatBox.scrollTop(chatBox[0].scrollHeight);
				},
				error() {
					$('.fp-ai-typing').remove();
					chatBox.append(`
						<div class="fp-ai-message fp-ai-message-error">
							<div class="fp-ai-message-content">Failed to communicate with AI assistant.</div>
						</div>
					`);
				}
			});
		},

		/**
		 * Get AI suggestion for a field
		 */
		getAISuggestion(field) {
			const button = $(`[data-ai-suggest="${field}"]`);
			const originalText = button.text();

			button.html('<span class="fp-spinner"></span> Generating...').prop('disabled', true);

			$.ajax({
				url: fundraiserProAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fundraiser_ai_generate',
					nonce: fundraiserProAdmin.nonce,
					field: field,
					campaign_data: FundraiserProAdmin.getCampaignData()
				},
				success(response) {
					if (response.success) {
						$(`#${field}`).val(response.data.suggestion);
						FundraiserProAdmin.showNotification('Suggestion applied! Feel free to edit it.', 'success');
					} else {
						FundraiserProAdmin.showNotification(response.data.message, 'error');
					}
				},
				error() {
					FundraiserProAdmin.showNotification('Failed to generate suggestion', 'error');
				},
				complete() {
					button.text(originalText).prop('disabled', false);
				}
			});
		},

		/**
		 * Approve cash transaction
		 */
		approveTransaction(transactionId) {
			$.ajax({
				url: fundraiserProAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'approve_cash_transaction',
					nonce: fundraiserProAdmin.nonce,
					transaction_id: transactionId
				},
				success(response) {
					if (response.success) {
						FundraiserProAdmin.showNotification('Transaction approved successfully', 'success');
						location.reload();
					} else {
						FundraiserProAdmin.showNotification(response.data.message, 'error');
					}
				}
			});
		},

		/**
		 * Reject cash transaction
		 */
		rejectTransaction(transactionId) {
			$.ajax({
				url: fundraiserProAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'reject_cash_transaction',
					nonce: fundraiserProAdmin.nonce,
					transaction_id: transactionId
				},
				success(response) {
					if (response.success) {
						FundraiserProAdmin.showNotification('Transaction rejected', 'success');
						location.reload();
					} else {
						FundraiserProAdmin.showNotification(response.data.message, 'error');
					}
				}
			});
		},

		/**
		 * Auto-save field
		 */
		autoSave(field) {
			const key = field.attr('name');
			const value = field.val();

			$.ajax({
				url: fundraiserProAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fundraiser_autosave',
					nonce: fundraiserProAdmin.nonce,
					key: key,
					value: value
				}
			});
		},

		/**
		 * Print receipt
		 */
		printReceipt(receiptId) {
			window.open(
				`${fundraiserProAdmin.adminUrl}admin-ajax.php?action=print_receipt&receipt_id=${receiptId}`,
				'_blank',
				'width=800,height=600'
			);
		},

		/**
		 * Update wizard progress
		 */
		updateWizardProgress(current, total) {
			const percentage = (current / total) * 100;
			$('.fp-wizard-progress-bar').css('width', percentage + '%');
			$('.fp-wizard-step-number').text(`Step ${current} of ${total}`);
		},

		/**
		 * Get current campaign data
		 */
		getCampaignData() {
			return {
				title: $('#title').val(),
				description: $('#content').val(),
				goal_amount: $('#fp-goal-amount').val(),
				category: $('#fp-campaign-category').val()
			};
		},

		/**
		 * Show notification
		 */
		showNotification(message, type = 'info') {
			const notification = $(`
				<div class="fp-notification fp-notification-${type}" style="position: fixed; top: 32px; right: 32px; z-index: 999999; min-width: 300px; animation: slideInRight 0.3s ease;">
					<div class="fp-alert fp-alert-${type}">
						${message}
					</div>
				</div>
			`);

			$('body').append(notification);

			setTimeout(() => {
				notification.fadeOut(200, function() {
					$(this).remove();
				});
			}, 3000);
		},

		/**
		 * Validate email
		 */
		isValidEmail(email) {
			const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return re.test(email);
		},

		/**
		 * Escape HTML
		 */
		escapeHtml(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, m => map[m]);
		},

		/**
		 * Load more campaigns
		 */
		loadMoreCampaigns() {
			const button = $('.fp-load-more-campaigns');
			const page = parseInt(button.data('page')) || 1;
			const nextPage = page + 1;

			button.html('<span class="fp-spinner"></span> Loading...').prop('disabled', true);

			$.ajax({
				url: fundraiserProAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'load_more_campaigns',
					nonce: fundraiserProAdmin.nonce,
					page: nextPage
				},
				success(response) {
					if (response.success) {
						$('.fp-campaigns-list').append(response.data.html);
						button.data('page', nextPage);

						if (!response.data.has_more) {
							button.hide();
						}
					}
				},
				complete() {
					button.text('Load More').prop('disabled', false);
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(() => {
		FundraiserProAdmin.init();
	});

})(jQuery);
