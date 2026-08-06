(function ($) {
	'use strict';

	var visitsChart = null;
	var purposeChart = null;
	var referralsChart = null;

	function getDays() {
		var $days = $('#filter-days');
		return $days.length ? parseInt($days.val(), 10) || 30 : 30;
	}

	function fetchChartData() {
		if (typeof sbAiBotsData === 'undefined') {
			return;
		}

		$.post(sbAiBotsData.ajaxurl, {
			action: 'sb_ai_bots_chart_data',
			security: sbAiBotsData.nonce,
			days: getDays()
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				return;
			}
			renderVisitsChart(response.data.daily);
			renderPurposeChart(response.data.purpose);
			renderReferralsChart(response.data.referrals);
		});
	}

	function renderReferralsChart(referrals) {
		var canvas = document.getElementById('sb-ai-referrals-visits-chart');
		if (!canvas || typeof Chart === 'undefined' || !referrals) {
			return;
		}

		if (referralsChart) {
			referralsChart.destroy();
		}

		referralsChart = new Chart(canvas, {
			type: 'line',
			data: {
				labels: referrals.labels || [],
				datasets: [
					{
						label: sbAiBotsData.strings.referralVisits,
						data: referrals.visits || [],
						borderColor: '#00824c',
						backgroundColor: 'rgba(0, 130, 76, 0.12)',
						fill: true,
						tension: 0.2
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { position: 'bottom' } },
				scales: {
					y: { beginAtZero: true, ticks: { precision: 0 } }
				}
			}
		});
	}

	function renderVisitsChart(daily) {
		var canvas = document.getElementById('sb-ai-bots-visits-chart');
		if (!canvas || typeof Chart === 'undefined' || !daily) {
			return;
		}

		if (visitsChart) {
			visitsChart.destroy();
		}

		visitsChart = new Chart(canvas, {
			type: 'line',
			data: {
				labels: daily.labels || [],
				datasets: [
					{
						label: sbAiBotsData.strings.contentVisits,
						data: daily.content || [],
						borderColor: '#0073aa',
						backgroundColor: 'rgba(0, 115, 170, 0.12)',
						fill: true,
						tension: 0.2
					},
					{
						label: sbAiBotsData.strings.noiseVisits,
						data: daily.noise || [],
						borderColor: '#d63638',
						backgroundColor: 'rgba(214, 54, 56, 0.1)',
						fill: true,
						tension: 0.2
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: { legend: { position: 'bottom' } },
				scales: {
					y: { beginAtZero: true, ticks: { precision: 0 } }
				}
			}
		});
	}

	function renderPurposeChart(purpose) {
		var canvas = document.getElementById('sb-ai-bots-purpose-chart');
		if (!canvas || typeof Chart === 'undefined' || !purpose) {
			return;
		}

		if (purposeChart) {
			purposeChart.destroy();
		}

		purposeChart = new Chart(canvas, {
			type: 'doughnut',
			data: {
				labels: [
					sbAiBotsData.strings.research,
					sbAiBotsData.strings.citation
				],
				datasets: [{
					data: [
						purpose.research || 0,
						purpose.citation || 0
					],
					backgroundColor: ['#0073aa', '#00824c']
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				plugins: { legend: { display: false } }
			}
		});
	}

	function bindPeriodFilter() {
		$('#filter-days').on('change', function () {
			var form = document.getElementById('sb-ai-bots-days-form');
			if (form) {
				form.submit();
			}
		});
	}

	function bindBreakdownToggles() {
		$('.sb-ai-bots-expand').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var objectId = $btn.data('object-id');
			var objectType = $btn.data('object-type');
			var targetId = 'sb-ai-breakdown-' + objectType + '-' + objectId;
			var $target = $('#' + targetId);

			if ($target.hasClass('sb-loaded')) {
				$target.toggleClass('sb-hidden');
				return;
			}

			if (typeof sbAiBotsData === 'undefined') {
				return;
			}

			$target.removeClass('sb-hidden').html('<p>' + sbAiBotsData.strings.loading + '</p>');

			$.post(sbAiBotsData.ajaxurl, {
				action: 'sb_ai_bots_object_breakdown',
				security: sbAiBotsData.nonce,
				object_id: objectId,
				object_type: objectType,
				days: getDays()
			}).done(function (response) {
				if (response && response.success && response.data && response.data.html) {
					$target.html(response.data.html).addClass('sb-loaded');
				} else {
					$target.html('<p>' + sbAiBotsData.strings.error + '</p>');
				}
			}).fail(function () {
				$target.html('<p>' + sbAiBotsData.strings.error + '</p>');
			});
		});
	}

	$(function () {
		if (!$('.sb-ai-bots-page').length) {
			return;
		}
		bindPeriodFilter();
		fetchChartData();
		bindBreakdownToggles();
	});
})(jQuery);
