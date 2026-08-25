/* global seobooster_adminbar:true, WinBox:true, jQuery:true */

(function ($) {
	'use strict';

	var winboxInstance = null;
	var text = (seobooster_adminbar && seobooster_adminbar.text) ? seobooster_adminbar.text : {};

	function escapeHtml(str) {
		return String(str == null ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	/**
	 * Fill WordPress-style printf placeholders (%1$d, %2$d, or plain %d).
	 *
	 * @param {string} template Localized template.
	 * @param {...(string|number)} values Values in order for %1$d / sequential %d.
	 * @return {string}
	 */
	function formatString(template) {
		var out = String(template == null ? '' : template);
		var values = Array.prototype.slice.call(arguments, 1).map(function (value) {
			return String(value);
		});
		var i;

		for (i = 0; i < values.length; i++) {
			out = out.split('%' + (i + 1) + '$d').join(values[i]);
			out = out.split('%' + (i + 1) + '$s').join(values[i]);
		}
		for (i = 0; i < values.length; i++) {
			out = out.replace(/%[ds]/, values[i]);
		}

		return out;
	}

	function stripShowDetailsParam() {
		try {
			var url = new URL(window.location.href);
			if (!url.searchParams.has('seobooster_showdetails')) {
				return;
			}
			url.searchParams.delete('seobooster_showdetails');
			var next = url.pathname + url.search + url.hash;
			window.history.replaceState({}, document.title, next);
		} catch (e) {
			// Ignore browsers without URL/history support.
		}
	}

	function getUrlParameter(name) {
		name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
		var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
		var results = regex.exec(location.search);
		return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
	}

	function copyText(value, $btn) {
		var done = function () {
			var original = $btn.text();
			$btn.text(text.copied || 'Copied');
			setTimeout(function () {
				$btn.text(original);
			}, 1500);
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(value).then(done).catch(function () {
				fallbackCopy(value, done);
			});
			return;
		}
		fallbackCopy(value, done);
	}

	function fallbackCopy(value, done) {
		var $ta = $('<textarea>').val(value).css({ position: 'fixed', left: '-9999px' }).appendTo('body');
		$ta[0].select();
		try {
			document.execCommand('copy');
			done();
		} catch (e) {
			// Ignore.
		}
		$ta.remove();
	}

	function ctaLink(label, editUrl) {
		if (!editUrl) {
			return '';
		}
		return '<p class="sb-overview-cta"><a class="button button-secondary" href="' + escapeHtml(editUrl) + '">' + escapeHtml(label) + '</a></p>';
	}

	function renderItemList(items, emptyLabel) {
		if (!items || !items.length) {
			return '<p class="sb-overview-empty-inline">' + escapeHtml(emptyLabel) + '</p>';
		}
		var html = '<ul class="sb-overview-list">';
		items.forEach(function (item) {
			var severity = item.severity ? String(item.severity) : '';
			html += '<li class="sb-overview-list-item">';
			if (severity) {
				html += '<span class="sb-overview-severity sb-overview-severity--' + escapeHtml(severity) + '">' + escapeHtml(severity) + '</span>';
			}
			html += '<span class="sb-overview-message">' + escapeHtml(item.message || '') + '</span>';
			html += '</li>';
		});
		html += '</ul>';
		return html;
	}

	function renderSuggestionList(values, label) {
		if (!values || !values.length) {
			return '';
		}
		var html = '<div class="sb-overview-suggestions-block"><h4>' + escapeHtml(label) + '</h4><ul class="sb-overview-suggestions">';
		values.forEach(function (value) {
			html += '<li><span class="sb-overview-suggestion-text">' + escapeHtml(value) + '</span>';
			html += ' <button type="button" class="button-link sb-overview-copy" data-copy="' + escapeHtml(value) + '">' + escapeHtml(text.copy || 'Copy') + '</button></li>';
		});
		html += '</ul></div>';
		return html;
	}

	function renderKeywords(keywords, total) {
		if (!keywords || !keywords.length) {
			return '';
		}
		var html = '<div class="sb-overview-keywords-wrap"><table class="sb-overview-keywords"><thead><tr>';
		html += '<th>' + escapeHtml(text.query || 'Query') + '</th>';
		html += '<th>' + escapeHtml(text.impressions || 'Impressions') + '</th>';
		html += '<th>' + escapeHtml(text.clicks || 'Clicks') + '</th>';
		html += '<th>' + escapeHtml(text.ctr || 'CTR') + '</th>';
		html += '<th>' + escapeHtml(text.position || 'Pos.') + '</th>';
		html += '<th>' + escapeHtml(text.used || 'Used') + '</th>';
		html += '</tr></thead><tbody>';
		keywords.forEach(function (row) {
			html += '<tr>';
			html += '<td>' + escapeHtml(row.query || '') + '</td>';
			html += '<td>' + escapeHtml(row.impressions) + '</td>';
			html += '<td>' + escapeHtml(row.clicks) + '</td>';
			html += '<td>' + escapeHtml(row.ctr || '') + '</td>';
			html += '<td>' + escapeHtml(row.position || '') + '</td>';
			html += '<td>' + escapeHtml(row.used || '') + '</td>';
			html += '</tr>';
		});
		html += '</tbody></table>';
		if (total > keywords.length) {
			var more = formatString(
				text.keywords_more || 'Showing top %1$d of %2$d keywords.',
				keywords.length,
				total
			);
			html += '<p class="sb-overview-keywords-more">' + escapeHtml(more) + '</p>';
		}
		html += '</div>';
		return html;
	}

	function renderSnapshot(data) {
		var editUrl = data.edit_url || seobooster_adminbar.edit_url || '';
		var empty = data.empty || {};
		var html = '<div class="sb-page-overview">';

		html += '<section class="sb-overview-section sb-overview-score">';
		if (empty.analysis) {
			var noAnalysisMsg = text.no_analysis || 'No on-page SEO analysis yet.';
			if (!empty.keywords && (text.no_analysis_with_keywords || '')) {
				noAnalysisMsg = text.no_analysis_with_keywords;
			}
			html += '<p class="sb-overview-empty">' + escapeHtml(noAnalysisMsg) + '</p>';
			html += ctaLink(text.cta_analyze || text.cta_editor || '', editUrl);
		} else {
			html += '<div class="sb-overview-score-row">';
			html += '<div class="sb-overview-score-value">' + escapeHtml(data.score) + '</div>';
			html += '<div class="sb-overview-score-meta">';
			html += '<div class="sb-overview-score-label">' + escapeHtml(text.score || 'SEO score') + '</div>';
			if (data.analyzed_at) {
				html += '<div class="sb-overview-analyzed">' + escapeHtml(text.analyzed_at || '') + ': ' + escapeHtml(data.analyzed_at) + '</div>';
			}
			html += '</div></div>';
			if (data.content_changed) {
				html += '<p class="sb-overview-stale">' + escapeHtml(text.stale || '') + '</p>';
				html += ctaLink(text.cta_analyze || text.cta_editor || '', editUrl);
			}
		}
		html += '</section>';

		html += '<section class="sb-overview-section">';
		html += '<h3>' + escapeHtml(text.seo_title || 'SEO title') + '</h3>';
		html += '<p class="sb-overview-meta">' + escapeHtml(data.seo_title || text.empty_meta || '(empty)') + '</p>';
		html += '<h3>' + escapeHtml(text.seo_description || 'Meta description') + '</h3>';
		html += '<p class="sb-overview-meta">' + escapeHtml(data.seo_description || text.empty_meta || '(empty)') + '</p>';
		html += '</section>';

		if (!empty.analysis) {
			html += '<section class="sb-overview-section">';
			html += '<h3>' + escapeHtml(text.issues || 'Issues') + '</h3>';
			html += renderItemList(data.issues, text.no_issues || '');
			html += '<h3>' + escapeHtml(text.opportunities || 'Opportunities') + '</h3>';
			html += renderItemList(data.opportunities, text.no_opportunities || '');
			html += '</section>';
		}

		html += '<section class="sb-overview-section">';
		html += '<h3>' + escapeHtml(text.ai_suggestions || 'Saved AI suggestions') + '</h3>';
		if (empty.suggestions) {
			html += '<p class="sb-overview-empty">' + escapeHtml(text.no_suggestions || '') + '</p>';
			html += ctaLink(text.cta_suggestions || text.cta_editor || '', editUrl);
		} else {
			var ai = data.ai_suggestions || {};
			html += renderSuggestionList(ai.titles, text.titles || 'Titles');
			html += renderSuggestionList(ai.descriptions, text.descriptions || 'Descriptions');
		}
		html += '</section>';

		html += '<section class="sb-overview-section">';
		html += '<h3>' + escapeHtml(text.keywords || 'Top keywords') + '</h3>';
		if (empty.keywords) {
			html += '<p class="sb-overview-empty">' + escapeHtml(text.no_keywords || '') + '</p>';
		} else {
			html += renderKeywords(data.keywords, data.keyword_total || 0);
		}
		html += '</section>';

		html += '</div>';
		return html;
	}

	function loadSnapshot($body) {
		$body.html('<div class="sb-loading"><div class="sb-spinner"></div><span>' + escapeHtml(text.loading || 'Loading…') + '</span></div>');

		$.ajax({
			url: seobooster_adminbar.ajax_url,
			type: 'POST',
			data: {
				action: 'sb_adminbar_page_snapshot',
				security: seobooster_adminbar.security,
				item_id: seobooster_adminbar.item_id || 0,
				content_type: seobooster_adminbar.content_type || '',
				public_url: seobooster_adminbar.public_url || ''
			}
		}).done(function (response) {
			if (response && response.success && response.data) {
				$body.html(renderSnapshot(response.data));
				return;
			}
			var message = (response && response.data && response.data.message) ? response.data.message : (text.error || 'Error');
			$body.html('<div class="sb-overview-error">' + escapeHtml(message) + '</div>');
		}).fail(function (xhr, status, error) {
			$body.html('<div class="sb-overview-error">' + escapeHtml((text.error || 'Error') + ': ' + error) + '</div>');
		});
	}

	function openFloatingWindow() {
		if (winboxInstance && !winboxInstance.closed && document.querySelector('.winbox.sb-page-overview-winbox')) {
			winboxInstance.focus();
			return;
		}

		winboxInstance = null;
		stripShowDetailsParam();

		var adminBarHeight = $('#wpadminbar').height() || 0;

		winboxInstance = new WinBox({
			title: text.title || 'SEO Booster: Page overview',
			class: ['modern', 'no-full', 'no-max', 'sb-page-overview-winbox'],
			x: '20px',
			y: (20 + adminBarHeight) + 'px',
			width: '640px',
			height: '80%',
			top: adminBarHeight,
			background: '#1e1e1e',
			border: '1px solid #282828',
			max: false,
			modal: false,
			autosize: false,
			root: document.body,
			index: 999999,
			html: '<div class="sb-page-overview-root"></div>',
			onclose: function () {
				stripShowDetailsParam();
				winboxInstance = null;
			}
		});

		if (winboxInstance && winboxInstance.window) {
			winboxInstance.focus();
			document.body.appendChild(winboxInstance.window);
		}

		var $body = $(winboxInstance.body).find('.sb-page-overview-root');
		if (!$body.length) {
			$body = $(winboxInstance.body);
		}

		var hasContext = parseInt(seobooster_adminbar.item_id, 10) > 0 || (seobooster_adminbar.public_url && seobooster_adminbar.public_url.length);
		if (!hasContext) {
			$body.html('<div class="sb-overview-empty">' + escapeHtml(text.unavailable || '') + '</div>');
			return;
		}

		loadSnapshot($body);
	}

	$(document).ready(function () {
		$(document).on('click', '#wp-admin-bar-seobooster-details > a, #wp-admin-bar-seobooster-details .ab-item', function (e) {
			e.preventDefault();
			openFloatingWindow();
		});

		$(document).on('click', '.sb-overview-copy', function (e) {
			e.preventDefault();
			var $btn = $(this);
			copyText($btn.attr('data-copy') || '', $btn);
		});

		var showDetailsParam = getUrlParameter('seobooster_showdetails');
		if (seobooster_adminbar.auto_open || showDetailsParam === 'true' || showDetailsParam === '1') {
			openFloatingWindow();
		}
	});
})(jQuery);
