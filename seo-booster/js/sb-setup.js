(function ($) {
	'use strict';

	if (typeof sbSetup === 'undefined') {
		return;
	}

	var steps = sbSetup.steps || [];
	var state = sbSetup.state || {};
	var scanActive = false;
	var scanAbort = false;
	var importActive = false;

	function currentStep() {
		return $('#sb-setup').attr('data-step') || 'welcome';
	}

	function stepIndex(id) {
		return steps.indexOf(id);
	}

	function setAmbient(text) {
		var $el = $('#sb-setup-ambient');
		if (!text) {
			$el.attr('hidden', true).text('');
			return;
		}
		$el.removeAttr('hidden').text(text);
	}

	function updateDots(activeId) {
		var idx = stepIndex(activeId);
		$('.sb-setup__dot').each(function () {
			var id = $(this).data('step');
			var i = stepIndex(id);
			$(this).removeClass('is-current is-done');
			if (id === activeId) {
				$(this).addClass('is-current');
			} else if (i < idx || (state.completions && state.completions[id])) {
				$(this).addClass('is-done');
			}
		});
	}

	function syncChromeForStep(stepId) {
		var isReady = stepId === 'ready';
		var canBack = !isReady && stepIndex(stepId) > 0;
		var $close = $('#sb-setup-close');
		var $footer = $('#sb-setup-footer');
		var $back = $('#sb-setup-back');

		if (isReady) {
			$footer.attr('hidden', true);
			$close
				.attr('aria-label', sbSetup.strings.close || 'Close')
				.removeAttr('data-sb-setup-dismiss')
				.attr('data-sb-setup-finish', '1');
		} else {
			$footer.removeAttr('hidden');
			$close
				.attr('aria-label', sbSetup.strings.skipSetup || 'Exit setup')
				.removeAttr('data-sb-setup-finish')
				.attr('data-sb-setup-dismiss', '1');
		}

		if (canBack) {
			$back.removeAttr('hidden');
		} else {
			$back.attr('hidden', true);
		}
	}

	function saveBotTracking(done) {
		var enabled = $('#sb-setup-bot-tracking').length
			? $('#sb-setup-bot-tracking').is(':checked')
			: true;
		$.post(sbSetup.ajaxurl, {
			action: 'sb_setup_save_bot_tracking',
			nonce: sbSetup.nonce,
			enabled: enabled ? '1' : '0'
		}).always(function () {
			if (typeof done === 'function') {
				done();
			}
		});
	}

	function showStep(stepId, persist) {
		if (steps.indexOf(stepId) === -1) {
			return;
		}

		// Leaving AI: persist AI bot tracking choice.
		if (currentStep() === 'ai' && stepId !== 'ai') {
			saveBotTracking(function () {
				finishShowStep(stepId, persist);
			});
			return;
		}

		finishShowStep(stepId, persist);
	}

	function finishShowStep(stepId, persist) {
		$('#sb-setup').attr('data-step', stepId);
		$('.sb-setup__step').attr('hidden', true).removeClass('is-active');
		$('[data-step-panel="' + stepId + '"]').removeAttr('hidden').addClass('is-active');
		updateDots(stepId);
		syncChromeForStep(stepId);

		if (persist !== false) {
			$.post(sbSetup.ajaxurl, {
				action: 'sb_setup_set_step',
				nonce: sbSetup.nonce,
				setup_action: 'step',
				step: stepId
			});
		}

		if (stepId === 'autolink') {
			loadAutolinkSuggestions();
		}
		if (stepId === 'scan') {
			startScan();
		}
		if (stepId === 'health') {
			loadHealthCounts();
		}
		if (stepId === 'gsc') {
			syncGscPhase();
		}
		if (stepId === 'ai') {
			syncAiVariant();
		}
	}

	function goNext(stepId) {
		showStep(stepId, true);
	}

	function goBack() {
		var idx = stepIndex(currentStep());
		if (idx > 0) {
			showStep(steps[idx - 1], true);
		}
	}

	function syncGscPhase() {
		var phase = (state && state.gsc_phase) || 'connect';
		$('.sb-setup-gsc').attr('data-gsc-phase', phase);
		$('[data-gsc-panel]').attr('hidden', true);
		if (phase === 'done') {
			$('[data-gsc-panel="import"]').removeAttr('hidden');
			$('#sb-setup-gsc-continue').prop('disabled', false);
			$('#sb-setup-gsc-status').text(sbSetup.strings.importComplete);
			$('#sb-setup-gsc-bar').css('width', '100%');
		} else if (phase === 'import') {
			$('[data-gsc-panel="import"]').removeAttr('hidden');
		} else if (phase === 'site') {
			$('[data-gsc-panel="site"]').removeAttr('hidden');
		} else {
			$('[data-gsc-panel="connect"]').removeAttr('hidden');
		}
	}

	function syncAiVariant() {
		var v = (state && state.ai_variant) || 'preview';
		$('[data-ai-panel]').attr('hidden', true);
		$('[data-ai-panel="' + v + '"]').removeAttr('hidden');
	}

	function loadAutolinkSuggestions() {
		$.post(sbSetup.ajaxurl, {
			action: 'sb_setup_get_autolink_suggestions',
			nonce: sbSetup.nonce
		}).done(function (res) {
			if (!res || !res.success || !res.data.suggestions || !res.data.suggestions.length) {
				$('#sb-setup-autolink-suggestions').attr('hidden', true);
				return;
			}
			var $list = $('#sb-setup-autolink-list').empty();
			res.data.suggestions.forEach(function (s) {
				var slugPath = s.path || '';
				if (!slugPath && s.slug) {
					slugPath = '/' + String(s.slug).replace(/^\//, '') + '/';
				}
				var $li = $('<li/>');
				var $label = $('<label class="sb-setup-suggestions__row"/>');
				var $cb = $('<input type="checkbox" checked/>')
					.attr('data-query-id', s.query_id)
					.attr('data-post-id', s.post_id);
				var $line = $('<span class="sb-setup-suggestions__line"/>');
				$line.append($('<span class="sb-setup-suggestions__kw"/>').text(s.keyword));
				$line.append($('<span class="sb-setup-suggestions__arrow" aria-hidden="true"/>').text('→'));
				if (slugPath) {
					$line.append($('<span class="sb-setup-suggestions__slug"/>').text(slugPath));
				}
				if (s.title) {
					$line.append($('<span class="sb-setup-suggestions__sep" aria-hidden="true"/>').text('·'));
					$line.append(
						$('<span class="sb-setup-suggestions__title"/>')
							.text(s.title)
							.attr('title', s.title)
					);
				} else if (!slugPath && s.url) {
					$line.append($('<span class="sb-setup-suggestions__title"/>').text(s.url));
				}
				$label.append($cb, $line);
				$li.append($label);
				$list.append($li);
			});
			$('#sb-setup-autolink-suggestions').removeAttr('hidden');
		});
	}

	function loadHealthCounts() {
		$('[data-tool-link="meta"]').attr('href', sbSetup.tools_meta);
		$('[data-tool-link="image"]').attr('href', sbSetup.tools_image);
		$('[data-tool-link="llms"]').attr('href', sbSetup.tools_llms);
		$('[data-tool-link="possibilities"]').attr('href', sbSetup.issues_url);
		$('[data-tool-link="focus"]').attr(
			'href',
			state.has_premium ? sbSetup.tools_focus : sbSetup.tools_meta
		);

		$.post(sbSetup.ajaxurl, {
			action: 'sb_setup_health_counts',
			nonce: sbSetup.nonce
		})
			.done(function (res) {
				if (!res || !res.success) {
					markHealthLoadFailed();
					return;
				}
				var c = res.data.counts || {};
				setCardNum('possibilities', c.seo_possibilities);
				setCardNum('missing_focus_keyword', c.missing_focus_keyword);
				setCardNum('empty_alt', c.empty_alt);
				setCardNum('missing_meta', c.missing_meta);

				var $llms = $('[data-health="llms"] .sb-action-card__num');
				$llms
					.removeClass('sb-action-card__num--skeleton sb-action-card__num--loading')
					.removeAttr('aria-busy');
				if (c.llms_enabled) {
					$llms.addClass('sb-action-card__num--ok').text(sbSetup.strings.llmsLive);
				} else {
					$llms.addClass('sb-action-card__num--ok').text(sbSetup.strings.llmsOff);
				}

				var pro = res.data.pro || {};
				var $pro = $('#sb-setup-health-pro').empty();
				if (pro.content_decay !== undefined || pro.gsc_opportunities !== undefined) {
					if (pro.content_decay !== undefined) {
						$pro.append(proCard(pro.content_decay, 'Pages losing clicks', sbSetup.tools_decay));
					}
					if (pro.gsc_opportunities !== undefined) {
						$pro.append(proCard(pro.gsc_opportunities, 'GSC opportunities', sbSetup.tools_opps));
					}
					$pro.removeAttr('hidden');
				}
			})
			.fail(function () {
				markHealthLoadFailed();
			});
	}

	function markHealthLoadFailed() {
		$('#sb-setup-health-cards .sb-action-card__num--loading').each(function () {
			$(this)
				.removeClass('sb-action-card__num--loading')
				.removeAttr('aria-busy')
				.addClass('sb-action-card__num--ok')
				.text('-');
		});
	}

	function setCardNum(key, num) {
		var $n = $('[data-health="' + key + '"] .sb-action-card__num');
		$n
			.removeClass(
				'sb-action-card__num--skeleton sb-action-card__num--loading sb-action-card__num--ok'
			)
			.removeAttr('aria-busy');
		if (parseInt(num, 10) === 0) {
			$n.addClass('sb-action-card__num--ok').text(sbSetup.strings.allSet);
		} else {
			$n.text(Number(num).toLocaleString());
		}
	}

	function proCard(num, title, url) {
		var label = parseInt(num, 10) === 0 ? sbSetup.strings.allSet : Number(num).toLocaleString();
		var okClass = parseInt(num, 10) === 0 ? ' sb-action-card__num--ok' : '';
		return $(
			'<article class="sb-action-card"/>'
		).append(
			$('<div class="sb-action-card__num' + okClass + '"/>').text(label),
			$('<h3 class="sb-action-card__title"/>').text(title),
			$('<a class="sb-action-card__link"/>').attr('href', url).text(sbSetup.strings.openTool)
		);
	}

	function startGscImport() {
		var siteUrl = $('#sb-setup-gsc-site').val();
		var days = $('#sb-setup-gsc-days').val() || 90;
		if (!siteUrl) {
			$('#sb-setup-gsc-status').text(sbSetup.strings.selectSite);
			return;
		}

		$('[data-gsc-panel]').attr('hidden', true);
		$('[data-gsc-panel="import"]').removeAttr('hidden');
		$('#sb-setup-gsc-continue').prop('disabled', false);
		setAmbient(sbSetup.strings.importing);
		importActive = true;
		$('#sb-setup-gsc-status').text(sbSetup.strings.pleaseWait);
		$('#sb-setup-gsc-bar').css('width', '8%');
		$('#sb-setup-gsc-hint').text(
			sbSetup.strings.importBackground ||
				'Import keeps running while you continue. You don’t need to wait here.'
		);

		function sendRequest(step) {
			$.post(sbSetup.ajaxurl, {
				action: 'sb_gsc_import_data',
				step: step,
				site_url: siteUrl,
				days: days,
				nonce: sbSetup.gsc_nonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						$('#sb-setup-gsc-status').text(
							(response && response.data) || sbSetup.strings.errorGeneric
						);
						setAmbient('');
						importActive = false;
						return;
					}
					var d = response.data || {};
					var pct = Math.min(95, 10 + step * 8);
					$('#sb-setup-gsc-bar').css('width', pct + '%');
					$('#sb-setup-gsc-status').html(
						sbSetup.strings.uniqueKeywords +
							': ' +
							(d.total_keywords || 0) +
							'<br>' +
							sbSetup.strings.totalEntries +
							': ' +
							(d.total_entries || 0)
					);

					if (d.more_results) {
						sendRequest(step + 1);
					} else {
						$('#sb-setup-gsc-bar').css('width', '100%');
						$('#sb-setup-gsc-status').text(sbSetup.strings.importComplete);
						$('#sb-setup-gsc-hint').text(
							sbSetup.strings.importDoneHint || 'Keyword import finished. You can continue.'
						);
						setAmbient('');
						importActive = false;
						state.gsc_phase = 'done';
						state.has_gsc_data = true;
					}
				})
				.fail(function () {
					$('#sb-setup-gsc-status').text(sbSetup.strings.errorGeneric);
					setAmbient('');
					importActive = false;
				});
		}

		sendRequest(0);
	}

	function startScan() {
		if (scanActive) {
			return;
		}

		scanAbort = false;
		scanActive = true;
		setAmbient(sbSetup.strings.scanning);

		function runBatch() {
			if (scanAbort) {
				return;
			}
			$.post(sbSetup.ajaxurl, {
				action: 'sb_analyze_direct',
				nonce: sbSetup.issues_nonce
			})
				.done(function (res) {
					if (scanAbort) {
						return;
					}
					if (!res || !res.success) {
						$('#sb-setup-scan-status').text(sbSetup.strings.errorGeneric);
						setAmbient('');
						scanActive = false;
						return;
					}
					var d = res.data || {};
					var pct = d.progress_percentage != null ? d.progress_percentage : 0;
					$('#sb-setup-scan-bar').css('width', pct + '%');
					$('#sb-setup-scan-status').text(
						sbSetup.strings.pagesAnalyzed +
							': ' +
							(d.analyzed || 0) +
							' · ' +
							sbSetup.strings.pagesRemaining +
							': ' +
							(d.remaining || 0)
					);

					if (d.completed || !d.remaining) {
						$('#sb-setup-scan-bar').css('width', '100%');
						setAmbient('');
						scanActive = false;
						return;
					}

					window.setTimeout(runBatch, 1000);
				})
				.fail(function () {
					if (!scanAbort) {
						$('#sb-setup-scan-status').text(sbSetup.strings.errorGeneric);
					}
					setAmbient('');
					scanActive = false;
				});
		}

		runBatch();
	}

	function completeWizard(thenUrl) {
		$.post(sbSetup.ajaxurl, {
			action: 'sb_setup_set_step',
			nonce: sbSetup.nonce,
			setup_action: 'complete'
		}).always(function () {
			if (thenUrl) {
				window.location.href = thenUrl;
			}
		});
	}

	$(function () {
		updateDots(currentStep());
		syncChromeForStep(currentStep());
		syncGscPhase();
		syncAiVariant();

		if (currentStep() === 'autolink') {
			loadAutolinkSuggestions();
		}
		if (currentStep() === 'scan') {
			startScan();
		}
		if (currentStep() === 'health') {
			loadHealthCounts();
		}

		$(document).on('click', '[data-sb-setup-next]', function (e) {
			e.preventDefault();
			goNext($(this).data('sb-setup-next'));
		});

		$(document).on('click', '[data-sb-setup-back]', function (e) {
			e.preventDefault();
			goBack();
		});

		$(document).on('click', '[data-sb-setup-dismiss]', function (e) {
			e.preventDefault();
			var $btn = $(this).addClass('is-busy').prop('disabled', true);
			var dismissLabel = (sbSetup.strings && sbSetup.strings.dismissing) ? sbSetup.strings.dismissing : '';
			if (dismissLabel && $btn.is('button') && !$btn.hasClass('sb-setup__close')) {
				$btn.text(dismissLabel);
			}
			$.post(sbSetup.ajaxurl, {
				action: 'sb_setup_set_step',
				nonce: sbSetup.nonce,
				setup_action: 'dismiss'
			}).done(function (res) {
				if (res && res.success && res.data.redirect_url) {
					window.location.href = res.data.redirect_url;
					return;
				}
				$btn.removeClass('is-busy').prop('disabled', false);
			}).fail(function () {
				$btn.removeClass('is-busy').prop('disabled', false);
			});
		});

		$(document).on('click', '[data-sb-setup-finish]', function (e) {
			e.preventDefault();
			completeWizard(sbSetup.dashboard_url);
		});

		$('#sb-setup-gsc-connect').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this).prop('disabled', true);
			$.post(sbSetup.ajaxurl, {
				action: 'sb_oauth_prepare',
				nonce: sbSetup.oauth_nonce,
				destination: 'setup'
			})
				.done(function (res) {
					if (res && res.success && res.data && res.data.auth_url) {
						window.location.href = res.data.auth_url;
						return;
					}
					$btn.prop('disabled', false);
					var connectErr = (sbSetup.strings && sbSetup.strings.connectError) ? sbSetup.strings.connectError : 'Could not start Google authentication.';
					if (window.SBModal && typeof window.SBModal.alert === 'function') {
						window.SBModal.alert(connectErr);
					}
				})
				.fail(function () {
					$btn.prop('disabled', false);
					var connectErr = (sbSetup.strings && sbSetup.strings.connectError) ? sbSetup.strings.connectError : 'Could not start Google authentication.';
					if (window.SBModal && typeof window.SBModal.alert === 'function') {
						window.SBModal.alert(connectErr);
					}
				});
		});

		$('#sb-setup-gsc-import').on('click', function (e) {
			e.preventDefault();
			startGscImport();
		});

		$('#sb-setup-email-save').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this).prop('disabled', true);
			$.post(sbSetup.ajaxurl, {
				action: 'sb_setup_save_email',
				nonce: sbSetup.nonce,
				email: $('#sb-setup-email').val()
			})
				.done(function (res) {
					if (res && res.success) {
						state = res.data.state || state;
						goNext('autolink');
					} else {
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					$btn.prop('disabled', false);
				});
		});

		$('#sb-setup-autolink-enable').on('click', function (e) {
			e.preventDefault();
			var suggestions = [];
			$('#sb-setup-autolink-list input:checked').each(function () {
				suggestions.push({
					query_id: $(this).data('query-id'),
					post_id: $(this).data('post-id')
				});
			});
			var $btn = $(this).prop('disabled', true);
			$.post(sbSetup.ajaxurl, {
				action: 'sb_setup_enable_autolink',
				nonce: sbSetup.nonce,
				suggestions: suggestions
			})
				.done(function (res) {
					if (res && res.success) {
						state = res.data.state || state;
						goNext('scan');
					} else {
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					$btn.prop('disabled', false);
				});
		});

		$('#sb-setup-ai-enable').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this).prop('disabled', true);
			$.post(sbSetup.ajaxurl, {
				action: 'sb_setup_enable_ai',
				nonce: sbSetup.nonce
			})
				.done(function (res) {
					if (res && res.success) {
						state = res.data.state || state;
						goNext('health');
					} else {
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					$btn.prop('disabled', false);
				});
		});

		$(document).on('click', '[data-sb-setup-complete]', function (e) {
			e.preventDefault();
			var href = $(this).attr('href');
			completeWizard(href);
		});

		// If landing with auth=1 after OAuth, refresh state for site phase.
		if (window.location.search.indexOf('auth=1') !== -1 || window.location.search.indexOf('step=gsc') !== -1) {
			$.post(sbSetup.ajaxurl, {
				action: 'sb_setup_get_state',
				nonce: sbSetup.nonce
			}).done(function (res) {
				if (res && res.success) {
					state = res.data;
					syncGscPhase();
					// Populate site select if empty.
					var $sel = $('#sb-setup-gsc-site');
					if ($sel.length && state.sites && state.sites.length && $sel.find('option').length <= 1) {
						state.sites.forEach(function (s) {
							var url = typeof s === 'string' ? s : (s && s.siteUrl ? s.siteUrl : '');
							if (!url) {
								return;
							}
							$sel.append($('<option/>').val(url).text(url));
						});
					}
					if (state.selected_site) {
						$sel.val(state.selected_site);
					}
				}
			});
		}
	});
})(jQuery);
