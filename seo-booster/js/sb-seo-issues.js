/* global sb_seo_issues, SBModal, jQuery:true */
/**
 * SEO Possibilities admin page.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */

(function ($) {
	'use strict';

	window.SB_SEO_Issues = {
		initialized: false,

		init: function () {
			if (this.initialized) {
				return;
			}
			this.bindEvents();
			this.initialized = true;
		},

		bindEvents: function () {
			var self = this;

			$(document).on('click', '#sb-run-sitewide-analysis, #sb-run-sitewide-analysis-secondary', function (e) {
				e.preventDefault();
				self.runSitewideAnalysis();
			});

			$(document).on('click', '.sb-expand-url', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var $btn = $(this);
				var urlId = $btn.data('url-id');
				var $row = $btn.closest('tr.sb-url-row');
				if (window.SB_SEO_Issues.expandUrlIssues) {
					window.SB_SEO_Issues.expandUrlIssues(urlId, $row);
				}
			});

			$(document).on('click', 'tr.sb-url-row', function (e) {
				if ($(e.target).is('a, button, input, select') || $(e.target).closest('a, button').length) {
					return;
				}
				var $row = $(this);
				var urlId = $row.data('url-id');
				if (window.SB_SEO_Issues.expandUrlIssues) {
					window.SB_SEO_Issues.expandUrlIssues(urlId, $row);
				}
			});

			$(document).on('click', '.sb-mark-done', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var $btn = $(this);
				var issueId = parseInt($btn.data('issue-id'), 10) || 0;
				if (window.SB_SEO_Issues.updateIssueStatus) {
					window.SB_SEO_Issues.updateIssueStatus(issueId, 'fixed', $btn);
				}
			});

			$(document).on('click', '.sb-ignore-issue', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var $btn = $(this);
				var issueId = parseInt($btn.data('issue-id'), 10) || 0;
				if (window.SB_SEO_Issues.updateIssueStatus) {
					window.SB_SEO_Issues.updateIssueStatus(issueId, 'ignored_permanent', $btn);
				}
			});
		},

		severityLabel: function (severity) {
			var labels = sb_seo_issues.strings.severity_labels || {};
			if (labels[severity]) {
				return labels[severity];
			}
			return severity ? severity.charAt(0).toUpperCase() + severity.slice(1) : '';
		},

		formatNumber: function (num) {
			var n = parseInt(num, 10) || 0;
			return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		},

		setLastRunDisplay: function (displayText) {
			$('#sb-sitewide-last-run').text(
				displayText || sb_seo_issues.strings.not_run_yet || 'Not run yet'
			);
		},

		updateStatCards: function (pageStats, urlsCount) {
			if (!pageStats) {
				return;
			}
			var $cards = $('#sb-possibilities-stat-cards');
			$cards.find('[data-stat="total_issues"]').text(this.formatNumber(pageStats.total_issues || 0));
			var $critical = $cards.find('[data-stat="critical"]');
			$critical.text(this.formatNumber(pageStats.critical || 0));
			$critical.toggleClass('sb-action-card__num--warn', (pageStats.critical || 0) > 0);
			$cards.find('[data-stat="high"]').text(this.formatNumber(pageStats.high || 0));
			if (typeof urlsCount !== 'undefined') {
				$cards.find('[data-stat="urls_count"]').text(this.formatNumber(urlsCount));
			}
		},

		updateSitewideChips: function (stats) {
			if (!stats) {
				return;
			}
			var critical = parseInt(stats.critical, 10) || 0;
			var high = parseInt(stats.high, 10) || 0;
			var medium = parseInt(stats.medium, 10) || 0;
			var good = parseInt(stats.good, 10) || 0;
			var open = critical + high + medium;
			var strings = sb_seo_issues.strings;

			$('[data-chip="open"]').text(
				open === 1 ? (strings.open_one || '%d open').replace('%d', open) : (strings.open_many || '%d open').replace('%d', open)
			);
			$('[data-chip="critical"]').text((strings.critical_count || '%d critical').replace('%d', critical));
			$('[data-chip="high"]').text((strings.high_count || '%d high').replace('%d', high));
			$('[data-chip="medium"]').text((strings.medium_count || '%d medium').replace('%d', medium));
			$('[data-chip="good"]').text((strings.good_count || '%d good').replace('%d', good));
		},

		renderChecklist: function (checklist) {
			var $container = $('#sb-sitewide-checklist');
			if (!checklist || !checklist.length) {
				$container.html(
					'<p class="sb-ui-empty" id="sb-sitewide-empty">' +
						(sb_seo_issues.strings.sitewide_empty || 'No sitewide checks yet.') +
						'</p>'
				);
				return;
			}

			var self = this;
			var html = '<ul class="sb-sitewide-list">';
			checklist.forEach(function (item) {
				var severity = item.severity || 'medium';
				var label = self.severityLabel(severity);
				html +=
					'<li class="sb-sitewide-item sb-sitewide-item--' +
					severity +
					'">' +
					'<span class="sb-severity-badge sb-severity-' +
					severity +
					'">' +
					label +
					'</span>' +
					'<span class="sb-sitewide-item__message">' +
					$('<div>').text(item.message || '').html() +
					'</span></li>';
			});
			html += '</ul>';
			$container.html(html);
		},

		setSitewideButtonsLoading: function (loading) {
			var $buttons = $('#sb-run-sitewide-analysis, #sb-run-sitewide-analysis-secondary');
			if (loading) {
				$buttons.prop('disabled', true).addClass('is-busy');
				$('#sb-run-sitewide-analysis').text(sb_seo_issues.strings.analyzing || 'Analyzing…');
				$('#sb-run-sitewide-analysis-secondary').text(sb_seo_issues.strings.analyzing || 'Analyzing…');
				$('#sb-sitewide-checklist').addClass('is-loading');
			} else {
				$buttons.prop('disabled', false).removeClass('is-busy');
				$('#sb-run-sitewide-analysis').text(sb_seo_issues.strings.rerun_sitewide || 'Re-run sitewide checks');
				$('#sb-run-sitewide-analysis-secondary').text(sb_seo_issues.strings.rerun_short || 'Re-run');
				$('#sb-sitewide-checklist').removeClass('is-loading');
			}
		},

		runSitewideAnalysis: function () {
			var self = this;

			this.setSitewideButtonsLoading(true);

			$.ajax({
				url: sb_seo_issues.ajaxurl,
				type: 'POST',
				data: {
					action: 'sb_run_sitewide_analysis',
					nonce: sb_seo_issues.nonce
				},
				success: function (response) {
					if (response.success) {
						self.renderChecklist(response.data.checklist || []);
						self.updateSitewideChips(response.data.stats || {});
						self.updateStatCards(response.data.page_stats, response.data.urls_count);
						self.setLastRunDisplay(response.data.last_run_display);
						$('#sb-sitewide-first-run').slideUp(200, function () {
							$(this).remove();
						});

						if (typeof SBModal !== 'undefined' && SBModal.alert) {
							SBModal.alert(response.data.message || sb_seo_issues.strings.sitewide_done);
						} else {
							self.showNotice(response.data.message || sb_seo_issues.strings.sitewide_done, 'success');
						}
					} else {
						var errMsg = (response.data && response.data.message) || sb_seo_issues.strings.error;
						if (typeof SBModal !== 'undefined' && SBModal.alert) {
							SBModal.alert(errMsg);
						} else {
							self.showNotice(errMsg, 'error');
						}
					}
				},
				error: function () {
					var errMsg = sb_seo_issues.strings.error;
					if (typeof SBModal !== 'undefined' && SBModal.alert) {
						SBModal.alert(errMsg);
					} else {
						self.showNotice(errMsg, 'error');
					}
				},
				complete: function () {
					self.setSitewideButtonsLoading(false);
				}
			});
		},

		showNotice: function (message, type) {
			type = type || 'info';
			var $notice = $('<div class="notice notice-' + type + ' is-dismissible">');
			$notice.append($('<p>').text(message));
			$('.sb-seo-issues-page .sb-ui-cta').after($notice);
			setTimeout(function () {
				$notice.fadeOut();
			}, 5000);
		},

		showError: function (message) {
			if (typeof SBModal !== 'undefined' && SBModal.alert) {
				SBModal.alert(message);
			} else {
				this.showNotice(message, 'error');
			}
		}
	};

	jQuery(document).ready(function () {
		window.SB_SEO_Issues.init();
	});
})(jQuery);
