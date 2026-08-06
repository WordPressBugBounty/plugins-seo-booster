/**
 * Dashboard Do next — health peek fill (reuses sb_setup_health_counts).
 */
(function ($) {
	'use strict';

	if (typeof sbDashboard === 'undefined') {
		return;
	}

	var maxCards = parseInt(sbDashboard.maxCards, 10) || 6;

	function init() {
		var $root = $('#sb-dashboard-do-next');
		if (!$root.length) {
			return;
		}

		$('[data-tool-link="meta"]').attr('href', sbDashboard.tools_meta);
		$('[data-tool-link="image"]').attr('href', sbDashboard.tools_image);
		$('[data-tool-link="llms"]').attr('href', sbDashboard.tools_llms);
		$('[data-tool-link="focus"]').attr(
			'href',
			sbDashboard.has_premium ? sbDashboard.tools_focus : sbDashboard.tools_meta
		);

		revealEmptyIfNeeded();

		$.post(sbDashboard.ajaxurl, {
			action: 'sb_setup_health_counts',
			nonce: sbDashboard.nonce
		})
			.done(function (res) {
				if (!res || !res.success) {
					hideLoadingHealthCards();
					revealEmptyIfNeeded();
					return;
				}
				applyHealth(res.data || {});
			})
			.fail(function () {
				hideLoadingHealthCards();
				revealEmptyIfNeeded();
			});
	}

	function hideLoadingHealthCards() {
		$('#sb-dashboard-health-cards .sb-action-card').attr('hidden', true);
	}

	function applyHealth(data) {
		var c = data.counts || {};
		var pro = data.pro || {};
		var $health = $('#sb-dashboard-health-cards');

		setHealthCard($health, 'missing_focus_keyword', c.missing_focus_keyword, true);
		setHealthCard($health, 'empty_alt', c.empty_alt, true);
		setHealthCard($health, 'missing_meta', c.missing_meta, true);
		setLlmsCard($health, !!c.llms_enabled);

		var $pro = $('#sb-dashboard-health-pro').empty();
		if (pro.content_decay !== undefined) {
			$pro.append(
				proCard(
					pro.content_decay,
					sbDashboard.strings.pagesLosingClicks,
					sbDashboard.tools_decay
				)
			);
		}
		if (pro.gsc_opportunities !== undefined) {
			$pro.append(
				proCard(
					pro.gsc_opportunities,
					sbDashboard.strings.gscOpportunities,
					sbDashboard.tools_opps
				)
			);
		}
		if ($pro.children().length) {
			$pro.removeAttr('hidden');
		}

		enforceCardCap();
		revealEmptyIfNeeded();
	}

	function setHealthCard($scope, key, num, hideWhenZero) {
		var $card = $scope.find('[data-health="' + key + '"]');
		if (!$card.length) {
			return;
		}
		var $n = $card.find('.sb-action-card__num');
		$n
			.removeClass(
				'sb-action-card__num--skeleton sb-action-card__num--loading sb-action-card__num--ok'
			)
			.removeAttr('aria-busy');
		var n = parseInt(num, 10) || 0;
		if (n === 0) {
			if (hideWhenZero) {
				$card.attr('hidden', true);
				return;
			}
			$n.addClass('sb-action-card__num--ok').text(sbDashboard.strings.allSet);
		} else {
			$card.removeAttr('hidden');
			$n.text(Number(n).toLocaleString());
		}
	}

	function setLlmsCard($scope, enabled) {
		var $card = $scope.find('[data-health="llms"]');
		if (!$card.length) {
			return;
		}
		var $n = $card.find('.sb-action-card__num');
		$n
			.removeClass('sb-action-card__num--skeleton sb-action-card__num--loading')
			.removeAttr('aria-busy');
		if (enabled) {
			$card.attr('hidden', true);
			return;
		}
		$card.removeAttr('hidden');
		$n.addClass('sb-action-card__num--ok').text(sbDashboard.strings.llmsOff);
	}

	function proCard(num, title, url) {
		var n = parseInt(num, 10) || 0;
		if (n === 0) {
			return $();
		}
		return $('<article class="sb-action-card" data-health-dynamic="1"/>').append(
			$('<div class="sb-action-card__num"/>').text(Number(n).toLocaleString()),
			$('<h3 class="sb-action-card__title"/>').text(title),
			$('<a class="sb-action-card__link"/>')
				.attr('href', url)
				.text(sbDashboard.strings.openTool)
		);
	}

	function enforceCardCap() {
		// Ask card (#sb-dashboard-ask) is pinned and never capped.
		var $cards = $(
			'#sb-dashboard-blockers .sb-action-card:not([hidden]), #sb-dashboard-health-cards .sb-action-card:not([hidden]), #sb-dashboard-health-pro .sb-action-card:not([hidden])'
		);
		$cards.each(function (i) {
			if (i >= maxCards) {
				$(this).attr('hidden', true);
			}
		});
	}

	function revealEmptyIfNeeded() {
		var visible = $(
			'#sb-dashboard-ask .sb-action-card:not([hidden]), #sb-dashboard-blockers .sb-action-card:not([hidden]), #sb-dashboard-health-cards .sb-action-card:not([hidden]), #sb-dashboard-health-pro .sb-action-card:not([hidden])'
		).length;
		var $empty = $('#sb-dashboard-do-next-empty');
		if (visible === 0) {
			$empty.removeAttr('hidden');
		} else {
			$empty.attr('hidden', true);
		}
	}

	function startOAuth(destination, $btn) {
		var dest = destination || 'dashboard';
		if ($btn && $btn.length) {
			$btn.prop('disabled', true);
		}
		$.post(sbDashboard.ajaxurl, {
			action: 'sb_oauth_prepare',
			nonce: sbDashboard.oauth_nonce,
			destination: dest
		})
			.done(function (res) {
				if (res && res.success && res.data && res.data.auth_url) {
					window.location.href = res.data.auth_url;
					return;
				}
				if ($btn && $btn.length) {
					$btn.prop('disabled', false);
				}
				var connectErr = (sbDashboard.strings && sbDashboard.strings.connectError) || 'Could not start Google authentication.';
				if (window.SBModal && typeof window.SBModal.alert === 'function') {
					window.SBModal.alert(connectErr);
				}
			})
			.fail(function () {
				if ($btn && $btn.length) {
					$btn.prop('disabled', false);
				}
				var connectErr = (sbDashboard.strings && sbDashboard.strings.connectError) || 'Could not start Google authentication.';
				if (window.SBModal && typeof window.SBModal.alert === 'function') {
					window.SBModal.alert(connectErr);
				}
			});
	}

	$(document).on('click', '.sb-oauth-start', function (e) {
		e.preventDefault();
		var $el = $(this);
		startOAuth($el.data('sb-oauth-destination') || 'dashboard', $el.is('button') ? $el : null);
	});

	$(init);
})(jQuery);
