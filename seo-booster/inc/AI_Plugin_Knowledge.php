<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compact, routed product knowledge for AI help answers.
 *
 * This is a model-facing condensation of the customer documentation at
 * https://seoboosterpro.com/docs/. Keep navigation, availability, and
 * behavioral claims aligned with the plugin and documentation.
 *
 * @package Cleverplugins\SEOBooster
 * @since 7.4.0
 */
final class AI_Plugin_Knowledge {

	/**
	 * Maximum knowledge characters sent with one request.
	 */
	const MAX_CHARS = 15500;

	/**
	 * Small product grounding used for non-help questions.
	 *
	 * @return string
	 */
	public static function get_core_summary() {
		return 'SEO Booster is a WordPress SEO plugin with Google Search Console data, on-page analysis, SEO Possibilities, automatic internal links, bulk metadata and image tools, llms.txt, optional Pro Markdown URLs and llms-full.txt, AI bot and referral tracking, and optional AI through WordPress Connectors. Pro adds Possibilities triage (Do next, By type, traffic Top 10, Mark as done / Ignore on Possibilities and the editor Analysis tab), 404 and redirect monitoring, advanced opportunity tools, Entity Map, Markdown discovery, dashboard Ask about your SEO, and other workflow conveniences. The plugin never changes content or runs a tool merely because the advisor suggested it.';
	}

	/**
	 * Return question-relevant product guidance.
	 *
	 * @param string $question User question.
	 * @return string
	 */
	public static function get_for_question( $question ) {
		$question = wp_strip_all_tags( (string) $question );
		if ( function_exists( 'remove_accents' ) ) {
			$question = remove_accents( $question );
		}
		$question = strtolower( $question );
		$sections = self::get_sections();
		$selected = array( 'essentials', 'navigation' );

		if ( preg_match( '/\b(what can|everything|all features|overview|capabilit|help me with|what does seo booster do)\b/', $question ) ) {
			$selected = array_keys( $sections );
		} else {
			$routes = self::get_routes();
			foreach ( $routes as $pattern => $keys ) {
				if ( preg_match( $pattern, $question ) ) {
					$selected = array_merge( $selected, $keys );
				}
			}
		}

		$selected = array_values( array_unique( $selected ) );
		if ( count( $selected ) <= 2 ) {
			$selected[] = 'overview';
			$selected[] = 'settings';
			$selected[] = 'troubleshooting';
		}

		$output = array(
			'SEO BOOSTER COMPACT HELP KNOWLEDGE',
			'This is the authoritative list of SEO Booster settings, scanner checks, and capabilities. Only describe options and checks that appear below. Do NOT invent or assume settings, scanner checks, tools, results, prices, or actions. If a setting or check is not listed here, tell the user it is not available or that you are not certain, and do not guess. Give exact admin paths and short numbered steps, and distinguish Free from Pro.',
		);

		foreach ( $selected as $key ) {
			if ( isset( $sections[ $key ] ) ) {
				$output[] = $sections[ $key ];
			}
		}

		$knowledge = implode( "\n\n", $output );
		if ( strlen( $knowledge ) > self::MAX_CHARS ) {
			$knowledge = substr( $knowledge, 0, self::MAX_CHARS );
		}

		return $knowledge;
	}

	/**
	 * Match help questions to focused knowledge sections.
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function get_routes() {
		return array(
			'/\b(setup|wizard|first run|getting started|start using)\b/' => array( 'setup' ),
			'/\b(search console|gsc|google data|oauth|property|import|refresh data)\b/' => array( 'gsc' ),
			'/\b(seo possibilit|analysis|analy[sz]e|scan|scanner|check|checks|seo score|issue|readiness|editor|metabox)\b/' => array( 'analysis', 'scanner' ),
			'/\b(automatic links?|autolink|internal links?|keyword.?to.?url|last used on)\b/' => array( 'autolink' ),
			'/\b(tool|bulk|meta title|meta description|image metadata|alt text|focus keyword)\b/' => array( 'tools' ),
			'/\b(llms\.?txt|ai crawler|entity ?map|entitymap)\b/' => array( 'ai-discovery' ),
			'/\b(connector|credit|ai provider|ai writing|ask about|artificial intelligence)\b/' => array( 'ai' ),
			'/\b(ai bot|crawler hit|referral|chatgpt|perplexity|bot blocking)\b/' => array( 'tracking' ),
			'/\b(404|redirect|broken url|not found)\b/' => array( 'redirects' ),
			'/\b(email|weekly report|recipient|digest|cannibali[sz]ation)\b/' => array( 'email' ),
			'/\b(setting|retention|database|scheduled action|cron|maintenance|delete data)\b/' => array( 'settings' ),
			'/\b(yoast|rank math|aioseo|all in one seo|seopress|seo framework|compatib)\b/' => array( 'compatibility' ),
			'/\b(error|not (?:working|showing)|missing|failed|debug|troubleshoot|reset)\b/' => array( 'troubleshooting' ),
		);
	}

	/**
	 * Curated knowledge sections.
	 *
	 * @return array<string, string>
	 */
	private static function get_sections() {
		return array(
			'essentials'      => '[ESSENTIALS]
- Advice is read-only. The assistant does not run scans, change settings, edit content, publish files, alter Google, or crawl live pages.
- Use only controls that exist in the named admin area. Destructive and batch actions require the user to confirm them in the UI.
- Main documentation: https://seoboosterpro.com/docs/.',
			'navigation'      => '[NAVIGATION]
- Main menu: SEO Booster Dashboard, GSC Overview, Automatic Links, SEO Possibilities, Tools, AI Bots, Settings, and Debug Log. Pro also exposes 404 & Redirects.
- Dashboard shows Search Console KPIs, setup status, and Do next cards. The Pro Ask about your SEO panel sits below Do next (always open); the Do next Ask card and #ai link scroll to it.
- Settings uses tabs for AI/LLM, Automatic Links, Email, Google Search Console, SEO Possibilities, Tools, and Stats.',
			'overview'        => '[FREE AND PRO OVERVIEW]
- Free: GSC integration, SEO analysis and issues, automatic-link engine, bulk meta and image tools, llms.txt, AI bot and referral tracking, weekly reports, and optional AI providers.
- Pro: 404 and redirect monitoring, post-list autolink controls, Needs analysis, Content decay, GSC and autolink opportunity workflows, Entity Map, AI writing assistance, and Ask about your SEO.
- Free screens may show Pro upgrade cards. Pricing: https://seoboosterpro.com/pricing/.',
			'setup'           => '[SETUP WIZARD]
- Open the Dashboard setup card or SEO Booster -> Settings -> Email to restart the wizard.
- The wizard covers welcome, GSC connection and property, weekly email, autolink defaults, initial scan, AI, health, workplace, and completion.
- It saves progress. It does not force an automatic redirect after installation.
- If setup is incomplete, resume it from the Dashboard. Guide: https://seoboosterpro.com/docs/setup-wizard/how-to-use-the-setup-wizard/.',
			'gsc'             => '[GOOGLE SEARCH CONSOLE]
- Connect with OAuth in Setup Wizard or SEO Booster -> Settings -> Google Search Console, then choose the matching property.
- Use the manual refresh/import control on that Settings tab. Imports and URL keyword processing continue in background jobs.
- SEO Booster stores query, page, click, impression, CTR, and position history for its reports. GSC is delayed source data, not live rankings.
- GSC Overview is site-wide. Editor keyword panels and admin-bar Page overview focus on the current URL.
- If empty, verify token, selected property, property access, date range, and scheduled jobs. Docs: https://seoboosterpro.com/docs/google-search-console/gsc-overview/.',
			'analysis'        => '[SEO ANALYSIS, POSSIBILITIES, AND EDITOR]
- SEO Booster -> SEO Possibilities lists stored on-page findings by URL and severity. Free includes the full URL list, expand, Open in editor, free Tools links, severity filters, and sitewide checks. Pro adds triage: Do next, By type worklist, Top URLs by severity and Search Console traffic, and Mark as done / Ignore / Undo. The same Mark as done / Ignore / Undo actions appear on the editor SEO Analysis tab for saved findings. Run direct or sitewide analysis from that screen, or reanalyze from the editor metabox.
- Each finding is an Issue (critical/high/medium), an Improvement (low), or a Good result, plus a 0 to 100 score. Reanalyze after important content, template, or classification changes; background progress may continue through Action Scheduler.
- The editor SEO Booster metabox contains analysis, page settings, GSC keywords, and available AI help. AI Readiness translates analysis checks into an AI-focused checklist.
- SEO metadata is read and written through the active supported SEO plugin when one exists.
- Analysis explains detectable page conditions. It does not guarantee rankings. Docs: https://seoboosterpro.com/docs/seo-analysis/seo-analysis-overview/.',
			'scanner'         => '[SCANNER CHECKS: AUTHORITATIVE AND COMPLETE]
This is the full set of checks the scanner performs. If a check is not in this list, the scanner does not do it; say so rather than guessing.
Per-page checks that always run (content based):
- Title: missing title; focus keyword not in title; focus keyword in title.
- Meta description: missing description; focus keyword not in description; focus keyword in description.
- Focus keyword: set, or missing.
- Noindex vs indexable status.
- Content length: under 300 words (too short); 300 to 599 (opportunity); over 3000 (long); 600 to 3000 (good).
- Post excerpt present (posts only).
- Featured image present (posts only).
- FAQ-style heading: an H2 or H3 ending with a question mark.
- Heading structure: exactly one H1, missing H1, multiple H1, and presence of H2.
- Image alt text: images with missing or empty alt attributes.
- Internal links present (flags none only when over 500 words).
- External links present.
- Keyword density: target 0.5% to 3% (only when a focus keyword is set).
- Readability: average words per sentence.
- Too-short/duplicate content (under 50 words).
- Duplicate SEO titles and duplicate meta descriptions across other published posts.
- Broken images (up to 25 checked) and external images.
- Suspicious shortener links: bit.ly, tinyurl.com, goo.gl, t.co, ow.ly, short.link.
- Contact info presence (pages only: email, phone, or address).
Per-page checks that run only when the live page HTML is fetched (full-page mode):
- Broken external links (up to 10) and broken internal links (up to 15), including redirects.
- Structured data present (JSON-LD, microdata, or RDFa).
- Open Graph tags (og:title, og:description, og:image, og:url, og:type).
- Twitter Card tags.
- Canonical URL, robots meta, meta viewport, favicon, and html lang.
- Page speed indicators: images without width/height, more than 3 inline styles, more than 5 external CSS or JS files.
- rel=author link.
- Accessibility: image alt text and form field labels.
- Content readability: sentence length and long paragraphs (over 150 words).
GSC checks (only when Search Console is connected and has data for the URL):
- Indexing/coverage state and rich-result issues from URL Inspection.
- Low CTR with good position (position under 10, CTR under 2%).
- High impressions but low clicks (impressions over 1000, clicks under 50).
- Keywords not used in the page content.
- Keyword cannibalization (one query, multiple ranking pages).
- Long-tail opportunities (4+ word queries, position 4 to 20).
- Content freshness/decline (last 30 days vs prior 30, decline over 20%).
- Question queries (query begins with a question word).
Sitewide checks (homepage only, shown under SEO Possibilities sitewide): SSL, robots.txt, sitemap, favicon, viewport, html lang, llms.txt served, Entity Map served (Pro), and AI bot tracking enabled.
Not analyzed: private posts; WooCommerce cart, checkout, my account, and shop pages; posts marked excluded from analysis; attachments (analyzed but not saved).
Per-page score: start at 100; each error -5 (max -50), each warning -2.5 (max -30), each improvement -1 (max -15), each good +0.5 (max +10).',
			'autolink'        => '[AUTOMATIC LINKS]
- Add keyword-to-URL rules at SEO Booster -> Automatic Links. Double-click a keyword or URL to edit it; use bulk actions to delete.
- Links are injected into rendered output, not written into post content. Deleting a rule stops future injection.
- Processing requires global Automatic Links enabled and the individual singular post/page Page settings toggle enabled.
- It skips REST/JSON, search, feeds, sitemaps, embeds, previews, 404s, existing links, scripts, and styles. Headings, lists, and blockquotes are excluded by default and are configurable.
- Settings control repeated matches, capitalization, excluded elements, and maximum links per post. Pro adds post-list column, quick edit, and bulk enable/disable.
- Guide: https://seoboosterpro.com/docs/automatic-links/how-does-automatic-links-work/.',
			'tools'           => '[TOOLS AND BATCH WORKFLOWS]
- Open SEO Booster -> Tools. Common flow: choose filters, Scan, preview, select rows, Start batch, watch progress, review failures, and use Revert last run where offered.
- Free tools include Bulk meta, Image metadata, and llms.txt. Bulk meta writes titles/descriptions through the active supported SEO plugin. Image metadata can update alt, title, caption, and description.
- Pro tools include Entity Map, Needs analysis, Content decay, GSC opportunities, Focus keywords, and Autolink opportunities where available.
- Scanning and manual edits can work without AI. AI generation requires the selected provider under Settings -> AI/LLM.
- Guide: https://seoboosterpro.com/docs/tools/overview/.',
			'ai-discovery'    => '[LLMS.TXT, MARKDOWN, AND ENTITY MAP]
- Tools -> llms.txt can generate, preview, and serve /llms.txt. It is a curated discovery index, not a ranking switch or access-control file.
- Pro Markdown discovery (same Tools tab): optional *.md and /index.md page versions, plus /llms-full.txt full Markdown export linked from llms.txt. Per-page Include Markdown version is in the editor Page settings (on by default).
- Entity Map is Pro. Tools -> Entity Map builds and edits /entitymap.json and /entitymap.html to describe the organization, entities, relationships, and important content. Optional custom title for entitymap.html.
- Review generated drafts before publishing. Keep organization details and sameAs links accurate. Every entity needs at least one source chunk (EntityMap v1.0).
- These files help compatible AI systems understand the site but do not force crawling, citation, indexing, or visibility.
- Guides: https://seoboosterpro.com/docs/tools/llms-txt/ and https://seoboosterpro.com/docs/tools/entity-map/.',
			'ai'              => '[AI PROVIDERS AND AI FEATURES]
- Configure SEO Booster -> Settings -> AI/LLM. Providers are Disabled, WordPress Connectors, or SEO Booster Credits when available.
- WordPress Connectors requires a compatible WordPress installation and a configured connector. Credits requires registration and sufficient balance.
- AI may assist page writing, metadata, images, tools, and Pro dashboard questions depending on the screen and license.
- AI output is a draft. Review facts, tone, metadata length, and destination before applying.
- Ask about your SEO uses stored plugin data plus this compact knowledge. It cannot execute recommended actions.',
			'tracking'        => '[AI BOTS AND REFERRALS]
- Enable tracking at Settings -> AI/LLM. View crawler hits and human visits from AI answer engines at SEO Booster -> AI Bots.
- Mapped-content-only is on by default; search, junk, and unmapped requests are separated as noise.
- Retention controls stored history. Thirty to sixty days is usually enough for normal review.
- Pro can block selected bots or purposes with HTTP 403. Blocking may reduce discovery and does not remove prior data.
- Referral tracking records human visits with recognized AI referrers and skips known crawler user agents.',
			'redirects'       => '[404 AND REDIRECTS, PRO]
- Enable 404 monitoring in SEO Booster Settings, then review SEO Booster -> 404 & Redirects.
- Logging depends on real visitor requests. The tracker records 404 activity and redirect behavior; it cannot discover every broken URL without requests.
- Confirm destination URLs before adding redirects and avoid redirect loops or chains.
- If no entries appear, verify the Pro license, monitoring setting, cache behavior, and that the tested request returned a real 404.',
			'email'           => '[WEEKLY EMAIL]
- Configure SEO Booster -> Settings -> Email. Enable Weekly Email Reports and enter one address or a comma-separated recipient list.
- The report summarizes stored SEO Booster and Search Console observations, including useful keyword patterns where data exists.
- Use Send Email Now for a manual test when available.
- If mail does not arrive, verify recipient spelling, WordPress mail delivery, spam folders, cron, and Debug Log. The plugin cannot repair host-level mail delivery.',
			'settings'        => '[SETTINGS: AUTHORITATIVE CONTROLS BY TAB]
Save with the primary Save Settings button. These are the actual controls; do not describe options not listed here.
- AI/LLM: AI provider (Disabled, WordPress Connectors, or SEO Booster Credits when available); Credits register/balance/sync when Credits is selected; AI bot tracking on/off; AI referral tracking on/off; track mapped content only (default on); retention days (7 to 365). Pro: AI bot blocking on/off, blocked bots list, and blocked purposes (research, citation).
- Automatic Links: enable internal linking; link repeated keywords or first only; match capitalization; excluded elements (a, script, and style are always excluded); maximum links per post; ignore list.
- Email: weekly email reports on/off; recipient(s) as one address or comma-separated list.
- Google Search Console: connected property/site picker; manual refresh; import status. Connect via OAuth.
- SEO Possibilities: auto-scan on/off; frequency (60, 300, 900, 1800, or 3600 seconds); batch size (1 to 5).
- Tools/maintenance buttons: update database tables; restart keyword scanning; reset debug log; reset keyword data; clear all data (preserves autolink rules); send weekly email now; clear page cache; delete data on deactivate.
- Stats: plugin table sizes, cron, Action Scheduler, and cache overview.
Also configurable elsewhere in Settings: site name, tagline, alternate name, title separator, site image; global and per post type/taxonomy title and description templates; default noindex post types and taxonomies; Open Graph image and type; Twitter card type and username; schema on/off, site representation, organization details (name, alternate name, logo, description, url, email, phone, legal name, founding date), and schema policy pages.
- 404 and redirect monitoring is a Pro toggle.
- Full data wipe and delete-on-deactivate are destructive. Settings guide: https://seoboosterpro.com/docs/settings/settings-page/.',
			'compatibility'   => '[SEO PLUGIN COMPATIBILITY]
- SEO Booster integrates with supported active SEO plugins for post and term metadata, including Yoast SEO, Rank Math, AIOSEO, SEOPress, and The SEO Framework at supported tiers.
- Bulk and editor writes target the selected active integration. Some fields or workflows are unavailable when the SEO plugin does not expose an equivalent field.
- Multiple active SEO plugins can create ambiguity. Follow the Dashboard, Settings, or Tools compatibility notice before batch writes.
- Guide: https://seoboosterpro.com/docs/installation/seo-booster-compatibility-with-popular-seo-plugins/.',
			'troubleshooting' => '[TROUBLESHOOTING]
- Start at SEO Booster -> Debug Log for plugin messages. This is the SEO Booster database log, not wp-content/debug.log.
- For stalled scans/imports, check Settings -> Stats and WordPress Tools -> Scheduled Actions for failed SEO Booster jobs.
- For empty GSC reports, verify OAuth, selected property, access, import status, and date coverage.
- For missing autolinks, verify both global and per-page enablement, the keyword rule, exclusions, request type, and page cache.
- For AI failures, verify provider selection, connector/credit availability, license access, and then check Debug Log.
- Do not clear all data as a first troubleshooting step. Support: https://seoboosterpro.com/support/.',
		);
	}
}
