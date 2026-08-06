/**
 * Dashboard Ask about your SEO panel.
 */
(function ($) {
	'use strict';

	if (typeof sbSiteAssistant === 'undefined') {
		return;
	}

	var s = sbSiteAssistant.strings || {};
	var canSubmit = sbSiteAssistant.can_submit_ask !== false;
	var $dialog;
	var $input;
	var $result;
	var $loading;
	var $submit;
	var $jumpLink;
	var $history;
	var $historyItems;
	var asking = false;
	var loadingTimer = null;
	var loadingStartedAt = 0;

	function focusPanel(focusInput) {
		if (!$dialog || !$dialog.length) {
			return;
		}
		if ($dialog[0].scrollIntoView) {
			$dialog[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		}
		if (focusInput !== false && $input && $input.length) {
			$input.trigger('focus');
		}
	}

	function renderChips() {
		var $chips = $('#sb-site-assistant-chips').empty();
		var chips = s.chips || [];
		chips.forEach(function (chip) {
			var $btn = $('<button type="button" class="button sb-site-assistant__chip"/>')
				.text(chip.question)
				.attr('data-intent', chip.intent || '')
				.attr('data-question', chip.question || '');
			$chips.append($btn);
		});
	}

	function showAlert(message, tone) {
		if (!window.SBModal || !SBModal.alert) {
			return;
		}
		SBModal.alert(String(message || ''), { tone: tone || 'error' });
	}

	function getAjaxErrorMessage(res, fallback) {
		if (res && res.data) {
			if (typeof res.data.message === 'string' && res.data.message) {
				return res.data.message;
			}
			if (typeof res.data === 'string' && res.data) {
				return res.data;
			}
		}
		return fallback || s.genericError || 'Something went wrong.';
	}

	function setAskControlsDisabled(disabled) {
		var locked = !!disabled || !canSubmit;
		$submit.prop('disabled', locked || asking);
		$input.prop('disabled', locked || asking);
		setChipsDisabled(locked || asking);
	}

	function setChipsDisabled(disabled) {
		$('#sb-site-assistant-chips .sb-site-assistant__chip').prop('disabled', !!disabled);
		if ($historyItems) {
			$historyItems
				.find('.sb-site-assistant__history-button')
				.prop('disabled', !!disabled);
		}
	}

	function clearLoadingTimer() {
		if (loadingTimer) {
			clearInterval(loadingTimer);
			loadingTimer = null;
		}
	}

	function updateLoadingText() {
		if (!loadingStartedAt) {
			return;
		}
		var elapsed = Math.max(0, Math.floor((Date.now() - loadingStartedAt) / 1000));
		var tpl = s.askingElapsed || 'Thinking… %ss';
		var label = tpl.replace('%s', String(elapsed));
		$('#sb-site-assistant-loading-text').text(label);
	}

	function setLoading(on) {
		asking = !!on;
		setAskControlsDisabled(asking);
		if ($jumpLink && $jumpLink.length) {
			$jumpLink
				.attr('aria-disabled', asking ? 'true' : 'false')
				.toggleClass('is-disabled', asking);
		}

		if (asking) {
			$loading.removeAttr('hidden');
			clearLoadingTimer();
			loadingStartedAt = Date.now();
			updateLoadingText();
			loadingTimer = setInterval(updateLoadingText, 1000);
		} else {
			clearLoadingTimer();
			loadingStartedAt = 0;
			$loading.attr('hidden', true);
			if ($jumpLink && $jumpLink.length) {
				$jumpLink.attr('aria-disabled', 'false').removeClass('is-disabled');
			}
		}
	}

	function renderResult(data) {
		$result.empty().removeAttr('hidden');

		if (data.question) {
			$result.append(
				$('<div class="sb-site-assistant__question-echo"/>').text(data.question)
			);
		}

		$result.append(
			$('<div class="sb-site-assistant__answer"/>').text(data.answer || '')
		);

		if (data.confidence === 'low') {
			$result.append(
				$('<p class="sb-site-assistant__note"/>').text(
					s.lowConfidence || ''
				)
			);
		}

		var items = data.items || [];
		if (items.length) {
			$result.append(
				$('<h3 class="sb-site-assistant__subheading"/>').text(
					s.itemsHeading || ''
				)
			);
			var $list = $('<ul class="sb-site-assistant__items"/>');
			items.forEach(function (item) {
				var $li = $('<li class="sb-site-assistant__item"/>');
				var title = item.title || item.url || '';
				$li.append(
					$('<span class="sb-site-assistant__item-title"/>').text(title)
				);
				if (item.why) {
					$li.append(
						$('<span class="sb-site-assistant__item-why"/>').text(item.why)
					);
				}
				var $actions = $('<div class="sb-site-assistant__item-actions"/>');
				if (item.edit_url) {
					$actions.append(
						$('<a/>')
							.attr('href', item.edit_url)
							.attr('target', '_blank')
							.attr('rel', 'noopener noreferrer')
							.text(s.editPage || 'Edit')
					);
				}
				if (item.url) {
					$actions.append(
						$('<a/>')
							.attr('href', item.url)
							.attr('target', '_blank')
							.attr('rel', 'noopener noreferrer')
							.text(s.viewPage || 'View page')
					);
				}
				if (item.tool_url) {
					$actions.append(
						$('<a/>')
							.attr('href', item.tool_url)
							.text(s.openTool || 'Open tool')
					);
				}
				if ($actions.children().length) {
					$li.append($actions);
				}
				$list.append($li);
			});
			$result.append($list);
		}

		var steps = data.next_steps || [];
		if (steps.length) {
			$result.append(
				$('<h3 class="sb-site-assistant__subheading"/>').text(
					s.nextSteps || ''
				)
			);
			var $ol = $('<ol class="sb-site-assistant__steps"/>');
			steps.forEach(function (step) {
				$ol.append($('<li/>').text(step));
			});
			$result.append($ol);
		}

		if (data.unsupported || data.feature_idea) {
			var note = '';
			if (data.unsupported) {
				note = s.unsupported || '';
			}
			if (data.feature_idea) {
				note +=
					(note ? ' ' : '') +
					(s.featureIdea || '') +
					(data.feature_idea ? ' (' + data.feature_idea + ')' : '');
			}
			var $footer = $('<p class="sb-site-assistant__footer-note"/>').text(note + ' ');
			if (sbSiteAssistant.support_url) {
				$footer.append(
					$('<a/>')
						.attr('href', sbSiteAssistant.support_url)
						.attr('target', '_blank')
						.attr('rel', 'noopener noreferrer')
						.text(s.contactSupport || 'Contact support')
				);
			}
			$result.append($footer);
		}
	}

	function renderHistory(history) {
		$historyItems.empty();
		if (!history || !history.length) {
			$history.attr('hidden', true);
			return;
		}

		history.forEach(function (entry) {
			var $button = $(
				'<button type="button" class="sb-site-assistant__history-button"/>'
			)
				.attr('data-history-id', entry.id || '')
				.append(
					$('<span class="sb-site-assistant__history-question"/>').text(
						entry.question || ''
					)
				);

			if (entry.date) {
				$button.append(
					$('<span class="sb-site-assistant__history-date"/>').text(entry.date)
				);
			}
			$historyItems.append($button);
		});

		$history.removeAttr('hidden');
	}

	function loadHistory(restoreLatest) {
		$.post(sbSiteAssistant.ajaxurl, {
			action: 'sb_site_assistant_history',
			nonce: sbSiteAssistant.nonce
		}).done(function (res) {
			if (!res || !res.success || !res.data) {
				return;
			}
			renderHistory(res.data.history || []);
			if (restoreLatest && res.data.latest) {
				renderResult(res.data.latest);
			}
		});
	}

	function loadHistoryEntry(historyId) {
		if (!historyId || asking) {
			return;
		}

		$historyItems
			.find('.sb-site-assistant__history-button')
			.prop('disabled', true);
		$result
			.empty()
			.removeAttr('hidden')
			.append(
				$('<p class="sb-site-assistant__note"/>').text(
					s.loadingAnswer || 'Loading saved answer…'
				)
			);

		$.post(sbSiteAssistant.ajaxurl, {
			action: 'sb_site_assistant_history',
			nonce: sbSiteAssistant.nonce,
			history_id: historyId
		})
			.done(function (res) {
				if (res && res.success && res.data && res.data.response) {
					renderResult(res.data.response);
				}
			})
			.always(function () {
				$historyItems
					.find('.sb-site-assistant__history-button')
					.prop('disabled', false);
			});
	}

	function ask(question, intent) {
		question = (question || '').trim();
		if (!canSubmit) {
			showAlert(
				sbSiteAssistant.setup_message ||
					s.genericError ||
					'Something went wrong.',
				'warning'
			);
			return;
		}
		if (!question) {
			showAlert(s.emptyQuestion || 'Please enter a question.', 'warning');
			return;
		}
		if (asking) {
			return;
		}

		setLoading(true);
		$result.attr('hidden', true).empty();

		$.post(sbSiteAssistant.ajaxurl, {
			action: 'sb_site_assistant_ask',
			nonce: sbSiteAssistant.nonce,
			question: question,
			intent: intent || ''
		})
			.done(function (res) {
				setLoading(false);
				if (res && res.success && res.data) {
					renderResult(res.data);
					loadHistory(false);
					return;
				}
				showAlert(getAjaxErrorMessage(res), 'error');
			})
			.fail(function () {
				setLoading(false);
				showAlert(s.genericError || 'Something went wrong.', 'error');
			});
	}

	function init() {
		$dialog = $('#ai');
		if (!$dialog.length || !sbSiteAssistant.can_ask) {
			return;
		}

		$input = $('#sb-site-assistant-input');
		$result = $('#sb-site-assistant-result');
		$loading = $('#sb-site-assistant-loading');
		$submit = $('#sb-site-assistant-submit');
		$jumpLink = $('#sb-site-assistant-open');
		$history = $('#sb-site-assistant-history');
		$historyItems = $('#sb-site-assistant-history-items');

		$input.attr('placeholder', s.placeholder || '');
		renderChips();
		if (!canSubmit) {
			setAskControlsDisabled(true);
		}
		loadHistory(true);

		$(document).on('click', '.sb-site-assistant-open', function (e) {
			e.preventDefault();
			if (asking) {
				return;
			}
			focusPanel(true);
		});

		$(window).on('hashchange.sbSiteAssistant', function () {
			if (window.location.hash === '#ai') {
				focusPanel(false);
			}
		});

		if (window.location.hash === '#ai') {
			focusPanel(false);
		}

		$dialog.on('click', '.sb-site-assistant__chip', function () {
			if (asking || $(this).prop('disabled')) {
				return;
			}
			var intent = $(this).attr('data-intent') || '';
			var question = $(this).attr('data-question') || '';
			$input.val(question);
			ask(question, intent);
		});

		$dialog.on('click', '.sb-site-assistant__history-button', function () {
			loadHistoryEntry($(this).attr('data-history-id') || '');
		});

		$('#sb-site-assistant-form').on('submit', function (e) {
			e.preventDefault();
			ask($input.val(), '');
		});
	}

	$(init);
})(jQuery);
