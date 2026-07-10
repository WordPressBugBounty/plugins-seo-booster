(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var PluginSidebar = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var registerPlugin = wp.plugins.registerPlugin;
	var useSelect = wp.data.useSelect;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;

	function statusSymbol(item) {
		if (item.pass) {
			return '✓';
		}
		if (item.unknown) {
			return '—';
		}
		return '○';
	}

	function itemClass(item) {
		if (item.pass) {
			return 'is-pass';
		}
		if (item.unknown) {
			return 'is-unknown';
		}
		return 'is-fail';
	}

	function EditorPanel() {
		var postId = useSelect(function (select) {
			return select('core/editor').getCurrentPostId();
		});
		var data = typeof sbEditorPanelData !== 'undefined' ? sbEditorPanelData : {};
		var strings = data.strings || {};
		var initial = data.view || {};
		var loadingState = useState(false);
		var resultState = useState(initial);
		var isLoading = loadingState[0];
		var setLoading = loadingState[1];
		var viewData = resultState[0];
		var setViewData = resultState[1];

		function refreshScore(withAnalysis) {
			if (!postId) {
				return;
			}
			setLoading(true);
			var formData = new FormData();
			formData.append('action', 'sb_ai_readiness_score');
			formData.append('post_id', String(postId));
			formData.append('security', data.nonce || '');
			if (withAnalysis) {
				formData.append('refresh', '1');
			}
			fetch(data.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
				.then(function (response) {
					return response.json();
				})
				.then(function (response) {
					if (response && response.success) {
						setViewData(response.data);
					}
					setLoading(false);
				})
				.catch(function () {
					setLoading(false);
				});
		}

		if (!postId) {
			return el(
				Fragment,
				null,
				el(
					PluginSidebarMoreMenuItem,
					{ target: 'sb-editor-panel' },
					strings.title || 'SEO Booster'
				),
				el(
					PluginSidebar,
					{
						name: 'sb-editor-panel',
						title: strings.title || 'SEO Booster',
						icon: 'chart-area'
					},
					el(PanelBody, { initialOpen: true }, el('p', { className: 'sb-editor-panel-empty' }, strings.noAnalysis || ''))
				)
			);
		}

		var seoScore = viewData.seo_score;
		var seoGrade = viewData.seo_score_grade || {};
		var readinessGrade = viewData.grade || { label: '—', color: '#646970' };
		var postDetails = viewData.details || [];
		var sitewide = viewData.sitewide || [];
		var possibilities = viewData.top_possibilities || [];

		var seoScoreBlock = null;
		if (seoScore !== null && seoScore !== undefined) {
			seoScoreBlock = el(
				Fragment,
				null,
				el(
					'div',
					{ className: 'sb-editor-panel-score-row' },
					el(
						'div',
						{
							className: 'sb-editor-panel-score-circle',
							style: { borderColor: seoGrade.color || '#646970', color: seoGrade.color || '#646970' }
						},
						String(seoScore)
					),
					el(
						'div',
						{ className: 'sb-editor-panel-score-meta' },
						el('strong', null, seoGrade.label || ''),
						viewData.analyzed_at ? el('p', { className: 'description' }, viewData.analyzed_at) : null
					)
				)
			);
		} else {
			seoScoreBlock = el('p', { className: 'sb-editor-panel-empty' }, strings.noAnalysis || '');
		}

		var possibilitiesBlock = possibilities.length
			? el(
					'ul',
					{ className: 'sb-editor-panel-possibilities' },
					possibilities.map(function (item) {
						return el('li', { key: item.key }, item.message);
					})
			  )
			: el('p', { className: 'sb-editor-panel-empty' }, strings.noAnalysis || '');

		var readinessList = postDetails.map(function (item) {
			return el(
				'li',
				{
					key: item.key,
					className: itemClass(item)
				},
				el('span', { className: 'sb-ai-readiness-status' }, statusSymbol(item)),
				el('span', { className: 'sb-ai-readiness-label' }, item.label),
				item.points ? el('span', { className: 'sb-ai-readiness-points' }, '+' + item.points) : null
			);
		});

		var sitewideList = sitewide.map(function (item) {
			return el(
				'li',
				{
					key: item.key,
					className: itemClass(item)
				},
				el('span', { className: 'sb-ai-readiness-status' }, statusSymbol(item)),
				el('span', { className: 'sb-ai-readiness-label' }, item.label)
			);
		});

		var quickLinks = el(
			'div',
			{ className: 'sb-editor-panel-links' },
			viewData.issues_url ? el('a', { href: viewData.issues_url }, strings.viewAllIssues || '') : null,
			viewData.tools_url ? el('a', { href: viewData.tools_url }, strings.tools || 'Tools') : null,
			viewData.ai_bots_url ? el('a', { href: viewData.ai_bots_url }, strings.aiBots || 'AI Bots') : null,
			viewData.settings_url ? el('a', { href: viewData.settings_url }, strings.settings || 'Settings') : null
		);

		return el(
			Fragment,
			null,
			el(
				PluginSidebarMoreMenuItem,
				{ target: 'sb-editor-panel' },
				strings.title || 'SEO Booster'
			),
			el(
				PluginSidebar,
				{
					name: 'sb-editor-panel',
					title: strings.title || 'SEO Booster',
					icon: 'chart-area'
				},
				el(
					PanelBody,
					{ title: strings.seoScore || 'SEO Score', initialOpen: true },
					seoScoreBlock,
					el(
						Button,
						{
							variant: 'secondary',
							onClick: function () {
								refreshScore(true);
							},
							disabled: isLoading
						},
						isLoading ? strings.loading || 'Loading…' : strings.refresh || 'Run quick review'
					),
					isLoading ? el(Spinner, null) : null
				),
				el(PanelBody, { title: strings.topPossibilities || 'Top possibilities', initialOpen: true },
					possibilitiesBlock,
					viewData.issues_url
						? el('p', null, el('a', { href: viewData.issues_url }, strings.viewAllIssues || 'View all on SEO Possibilities'))
						: null
				),
				el(
					PanelBody,
					{ title: strings.aiReadiness || 'AI Readiness', initialOpen: true },
					el(
						'div',
						{ className: 'sb-ai-readiness-sidebar-score' },
						el(
							'div',
							{
								className: 'sb-ai-readiness-sidebar-score-value',
								style: { color: readinessGrade.color, borderColor: readinessGrade.color }
							},
							(viewData.score || 0) + '/' + (viewData.max || 0)
						),
						el('div', { className: 'sb-ai-readiness-sidebar-grade', style: { color: readinessGrade.color } }, readinessGrade.label)
					),
					el('ul', { className: 'sb-ai-readiness-checklist' }, readinessList)
				),
				el(
					PanelBody,
					{ title: strings.siteChecks || 'Site checks', initialOpen: false },
					el('ul', { className: 'sb-ai-readiness-checklist' }, sitewideList)
				),
				el(PanelBody, { title: strings.quickLinks || 'Quick links', initialOpen: false }, quickLinks)
			)
		);
	}

	registerPlugin('seo-booster-editor-panel', {
		render: EditorPanel
	});
})(window.wp);
