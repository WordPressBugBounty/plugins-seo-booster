=== SEO Booster ===
Contributors: cleverplugins, lkoudal, freemius
Donate link: https://seoboosterpro.com
Tags: seo, google-search-console, internal-links, analytics, woocommerce
Requires at least: 6.8
Requires PHP: 7.4
Tested up to: 7.0.2
Stable tag: 7.4.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Google Search Console in WordPress: keyword insights, on-page SEO, internal links. AI via WordPress 7 Connectors.

== Description ==

Google Search Console tells you which keywords bring visitors to your site, but that data lives in Google's dashboard, separate from the pages you edit in WordPress.

**SEO Booster closes that gap.** Connect Search Console once, import your keyword data, and see real search performance on every post, page, and taxonomy you edit. Turn GSC insights into actionable SEO tasks, automate internal linking across your site, and optionally use AI, powered by WordPress Connectors, to speed up content and image optimization.

**Works alongside Yoast SEO, Rank Math, All in One SEO, SEOPress, and The SEO Framework**. SEO Booster complements them with GSC-driven keyword intelligence, internal linking, and on-page analysis they typically do not provide. Bulk meta and focus keyword tools write directly into your active SEO plugin’s fields.

**Requires WordPress 6.8 or later.**

= AI with WordPress Connectors (optional) =

Optional AI features use the **WordPress Connectors** system built into **WordPress 7.0 or later**. Set up your AI provider under **Settings → Connectors** in WordPress, then choose **WordPress Connectors** under **SEO Booster → Settings → AI/LLM**. GSC import, automatic links, and SEO analysis work fully without AI. Leave it disabled if you prefer.

= Free features =

**Google Search Console**

* Connect your site via OAuth; import 7, 30, or 90 days of keyword data with scheduled refresh
* Searchable **GSC Overview** with filters for new or stale keywords, content usage, and position bands
* **Dashboard** with 30-day trends, charts, top keywords, and period-over-period comparison
* **Weekly email reports** with key metrics: all processed on your server, not sent to third parties

**Keyword insights where you work**

* GSC metabox on posts, pages, taxonomies, and WooCommerce products
* Admin bar Page overview while browsing the frontend or editing: SEO score, issues/opportunities, saved AI suggestions, and compact top keywords (read-only)
* Optional frontend keyword highlighting to see GSC terms in your content
* Per-keyword history charts in the editor Keywords tab

**Automatic internal links**

* Define keyword-to-URL rules; matching text in body content links automatically site-wide
* WooCommerce products supported; automatic linking runs on rendered HTML from Gutenberg, Elementor, Beaver Builder, and similar (no in-editor builder integration)

**SEO Possibilities**

* On-page analysis with a full URL discovery list: expand findings, open the editor, and jump to free Tools
* Pro triage: Do next, work by problem type, Top URLs by severity and Search Console traffic, Mark as done / Ignore (Possibilities and editor Analysis tab)
* GSC-based opportunities including low CTR, missing keywords, and keyword cannibalization warnings
* Sitewide checks, bulk analysis from post lists, and optional SEO score admin columns
* Auto-scan URLs discovered from GSC imports (configurable frequency)

**AI-powered SEO (optional, WordPress 7.0+)**

* Uses **WordPress Connectors**: your AI provider is configured once in WordPress, not inside SEO Booster
* Metabox suggestions, WooCommerce product SEO, and comprehensive audits
* **Ask about this page**: question chips and free-text Q&A about rankings, issues, and titles on the editor AI tools tab
* AI writing outline with generate-article-from-outline workflow
* Media Library bulk alt text and title generation
* Image metadata batch processing (vision-capable connector required for image analysis)

**Tools**

* Tabbed **Tools** hub: Overview plus bulk utilities for common SEO fixes
* **Bulk meta**: scan posts and pages for missing, duplicate, or keyword-less SEO titles and descriptions; generate with AI via WordPress Connectors (Yoast SEO, Rank Math, All in One SEO, SEOPress, or The SEO Framework required); one-click revert of the last bulk run
* **Image metadata**: scan and batch-fix missing alt text, titles, captions, and descriptions (see below)
* **llms.txt**: generate and serve `/llms.txt` for AI crawlers, curated from your top content (GSC-aware when data exists)
* **Pro:** Needs analysis overview (never analyzed, stale, or open issues)
* **Pro:** Content decay: pages with declining Search Console clicks (30d vs prior 30d)
* **Pro:** Entity Map: publish `/entitymap.json` and `/entitymap.html` with human + AI curation

= SEO Booster Pro =

Upgrade at [seoboosterpro.com](https://seoboosterpro.com) for:

* **404 and redirect monitoring**: track broken URLs and redirects with a searchable admin report
* **Weekly email Pro insights**: content-decay and GSC opportunity counts in the weekly report
* **Autolink column**: enable or disable automatic linking per post from the posts list and Quick Edit
* **Autolink status in GSC popup**: see which keywords are auto-linked, or create links in one click from the keyword details view
* **Needs analysis (Tools)**: find content never analyzed or with stale SEO analysis; queue bulk re-analysis from the Tools page
* **Content decay (Tools)**: find pages whose GSC clicks fell over the last 30 days vs the previous 30 days; open Possibilities or queue re-analysis
* **Entity Map (Tools)**: structured `/entitymap.json` and `/entitymap.html` for AI discovery; build from GSC + bot traffic, edit relations/chunks, optional AI draft via WordPress Connectors; links from llms.txt when published
* **Markdown discovery (Tools)**: optional `*.md` and `/index.md` page versions plus `/llms-full.txt` full Markdown export linked from llms.txt; per-page Include Markdown version in the editor
* **More Pro Tools**: GSC opportunities, Focus keywords, and Autolink opportunities
* **Ask about this page (editor)**: Apply focus keyword and create autolink from AI proposed actions (Ask itself is available with WordPress Connectors on free)
* **AI bot blocking**: block selected AI crawlers at the PHP level when monitoring is enabled

== Automatic Links ==

Automatically turn keywords in your content into internal links. Every time you write "contact us", it can link to your contact page. No manual linking required.

Works with WooCommerce products. Automatic linking runs on the rendered HTML of pages built with Gutenberg, Elementor, Beaver Builder, and similar; there is no in-editor integration for those builders. See [how automatic links work](https://seoboosterpro.com/docs/automatic-links/how-does-automatic-links-work/) for details.

== Image metadata batch tool ==

Find and fix missing image metadata across your Media Library without opening each attachment one by one. Go to **SEO Booster → Tools → Image metadata**.

* **Scan** JPEG, PNG, and WebP attachments for missing title, alt text, caption, or description; preview matches before you act
* **Generate with AI** by analyzing the actual image file: choose which fields to apply; only checked fields are saved
* **Process in batches**: selected images or every match in your library, with live preview while processing
* **Fail-safe**: if the AI cannot verify an image, nothing is saved; the tool prefers empty fields over wrong alt text
* **Works without AI**: run scans and review gaps even when AI processing is not configured
* **Requires vision-capable AI** for generation via WordPress 7 **Connectors**; text-only connectors cannot analyze images

Learn more in the [SEO Booster documentation](https://seoboosterpro.com/docs/).

== SEO plugin compatibility ==

SEO Booster works **alongside** your SEO plugin. It does not replace title tags, sitemaps, or schema. When a supported plugin is active, SEO Booster reads and writes SEO titles, meta descriptions, and (where supported) focus keywords.

= Supported plugins =

* **Yoast SEO**
* **Rank Math**
* **SEOPress**
* **All in One SEO (v4+)**: not legacy v3. Per-term custom title/description requires AIOSEO Pro (`aioseo_terms`); posts and focus keywords work on Lite.
* **The SEO Framework (v5+)**

= What SEO Booster writes =

* **SEO title** and **meta description**: bulk Tools, GSC opportunities, and editor AI "Use" buttons
* **Focus keyword**: bulk focus keyword tool and editor apply (not available with The SEO Framework)

= Which plugin is used? =

If more than one supported SEO plugin is active, SEO Booster uses the **first match** in this order: Yoast SEO → Rank Math → SEOPress → All in One SEO → The SEO Framework. Only that plugin receives writes. The **Dashboard**, **Settings**, and **Tools** pages show which plugin is active.

= WooCommerce =

Product pages use the same integration as posts. Product categories and tags use taxonomy SEO fields from your active plugin.

= Without an SEO plugin =

GSC import, automatic links, and on-page analysis still work. Analysis uses page content and excerpts when no SEO plugin meta is available.

== Bulk meta batch tool ==

Fix SEO titles and meta descriptions across many posts and pages at once. Go to **SEO Booster → Tools → Bulk meta**.

* **Requires a supported SEO plugin**: Yoast SEO, Rank Math, All in One SEO, SEOPress, or The SEO Framework. SEO Booster writes to your active plugin’s title and description fields (SEO Booster does not replace those plugins)
* **Scan** for missing titles/descriptions, duplicates sitewide, or meta that omit focus/GSC keywords
* **Generate with AI** via WordPress 7 **Connectors** (same engine as the post metabox); seeded with GSC keywords and condensed page content
* **Apply selectively**: choose title and/or description; fill empty fields by default, with optional overwrite
* **Revert**: restore previous values from your last bulk run in one click
* **Works without AI**: scan and review issues even when AI is disabled

== llms.txt generator ==

Help AI crawlers discover your best content. Go to **SEO Booster → Tools → llms.txt**.

* Generate curated content in the admin; **Download llms.txt** to upload manually, or **serve dynamically** at `/llms.txt` (virtual file: no FTP upload; recommended)
* Detects an existing physical `llms.txt` in your WordPress root and warns if it would override dynamic serving
* Curate links from posts, pages, and other public content types (prefers top GSC pages when import data exists)
* Preview the file before publishing
* **Pro:** optional Markdown URLs (`*.md`, `/index.md`) and `/llms-full.txt` full export with discovery signals and per-page control

== Installation ==

= Installing from WordPress =
1. Visit 'Plugins > Add New'
2. Search for 'SEO Booster'
3. Activate SEO Booster from your Plugins page.

= Installing Manually =
1. Upload the `seo-booster` folder to the `/wp-content/plugins/` directory
2. Activate the SEO Booster plugin through the 'Plugins' menu in WordPress

== Disclaimer ==

This plugin is not affiliated with, endorsed, or sponsored by Google. Google Search Console, Google APIs, and any related trademarks are the property of Google LLC. All product and company names are trademarks™ or registered® trademarks of their respective holders. Use of them does not imply any affiliation with or endorsement by them.

== Frequently Asked Questions ==

= Is this a replacement for Yoast SEO or All in One SEO? =

No. Those plugins are excellent for titles, meta tags, sitemaps, and schema. SEO Booster does not replace them. It complements them with Google Search Console keyword data on your edit screens, automatic internal linking, and on-page analysis focused on real search performance. Bulk meta and focus keyword tools write into Yoast SEO, Rank Math, All in One SEO, SEOPress, or The SEO Framework (title and description only for TSF).

= Do I need a Google Search Console account? =

Yes, for keyword import and GSC-based features such as the dashboard, GSC Overview, keyword metaboxes, and GSC-driven SEO Possibilities checks. Automatic internal links and many on-page SEO checks work without connecting Search Console.

= How does AI work? =

AI is optional and can stay disabled. When you want it, you need **WordPress 7.0 or later** with **Connectors** set up under **Settings → Connectors** in WordPress. In SEO Booster, go to **Settings → AI/LLM** and select **WordPress Connectors**. AI then powers metabox suggestions, writing outlines, image metadata generation, and more, using whichever provider you connected in WordPress.

= What is included in SEO Booster Pro? =

Pro adds 404 and redirect monitoring, per-post autolink controls from the posts list, autolink status with one-click link creation in the GSC keyword popup, Pro Tools (Needs analysis, Content decay, GSC opportunities, Focus keywords, Autolink opportunities, Entity Map), weekly email Pro insights (content decay and GSC opportunity counts), AI bot blocking, and Apply actions from Ask about this page (focus keyword and autolink). See [seoboosterpro.com](https://seoboosterpro.com) for details and pricing.

= Other questions =

Please contact us at [seoboosterpro.com/contact/](https://seoboosterpro.com/contact/)

== Screenshots ==

1. Connect Google Search Console from the dashboard
2. 30-day traffic overview with trends and top keywords
3. GSC Overview: search, filter, and keyword performance
4. Keyword insights on post edit screens with history charts
5. Automatic internal links: keyword rules that link site-wide
6. SEO Possibilities: prioritized on-page improvement list
7. Tools: tabbed bulk utilities (meta, images, llms.txt)

== Changelog ==

= 7.4.5 | 2026-08-03 =
* Improved: Danish dashboard copy for Ask about your SEO (no repeated Spørge) and clearer Do next wording; Ask card label/CTA are Advisor / Open.
* Fixed: Full-page SEO analysis no longer reports 0 words when an empty main landmark hides real body content; word counts use UTF-8 tokens. Re-analysis may refresh length and density scores.
* Fixed: SEO title and meta description checks (admin bar Page overview and background GSC keyword location) resolve SEO plugin templates (for example Rank Math %excerpt%) instead of treating empty custom fields as missing. Tools backup and bulk fill still use raw stored values.
* Fixed: Google Search Console URL Inspection no longer treats INDEXING_STATE_UNSPECIFIED / “URL is unknown to Google” as a high-severity indexing problem, and only marks indexing OK when Google’s verdict is PASS.
* Fixed: Bulk analysis no longer wipes prior GSC indexing findings when URL Inspection is deferred; rich-result warnings are opportunities, not high severity.
* Fixed: Indexability is read from the active SEO plugin (or page robots meta) instead of always reporting pages as indexable; The SEO Framework correctly skips focus-keyword suggestions.
* Fixed: SEO plugin adapters: All in One SEO uses current APIs for title, description, noindex, and focus keywords; Yoast category/tag fields, The SEO Framework v5 term meta, and SEOPress empty noindex are handled correctly.

= 7.4.4 | 2026-07-31 =
* Fixed: Content length word count now uses the downloaded page content when available, so page-builder pages are no longer reported as 0 words. Thanks Tom.
* Fixed: AI Readiness "Re-run analysis" now uses the same full-page SEO analysis as the editor, and no longer overwrites results with a post-content-only pass.
* Improved: Removed in-editor Beaver Builder and Elementor shortcuts; Page overview stays on the WordPress admin bar. Automatic linking still runs on rendered page HTML from common page builders.

= 7.4.3 | 2026-07-23 =
* NEW (Pro): Per-URL Markdown versions (*.md and /index.md) for AI crawlers, with HTML to Markdown conversion, FAQ extraction, and optional discovery signals.
* NEW (Pro): /llms-full.txt full Markdown export linked from llms.txt, with curated content and configurable limits.
* NEW (Pro): Per-page Include Markdown version toggle in the editor Page settings (on by default).
* Improved (Pro): Entity Map HTML page supports a custom title tag; AI drafts always include at least one source chunk per entity (EntityMap v1.0).
* Fixed (Pro): Entity Map custom and hub entities no longer publish without hasChunks.
* Fixed (Pro): Opted-out or excluded Markdown URLs return HTTP 410 instead of 404.

= 7.4.2 | 2026-07-23 =
* Fixed: Broken link checks now treat only HTTP 404 as broken; 403, 410, and similar HEAD responses are no longer reported as SEO possibilities. Thanks Thomas.
* Fixed: H1 detection no longer drops theme title headings inside <header> when analyzing the full page. Thanks Thomas.

= 7.4.1 | 2026-07-21 =
* Fixed: Focus keyword detection now correctly reads Rank Math, Yoast, SEOPress, and All in One SEO keyphrases (including live editor values), so analysis no longer suggests setting a focus keyword when one is already set.
* Improved: Settings, Search Console Overview, and Debug Log layouts aligned with the dashboard UI (background wash, panels, clearer sections).
* Fixed: Admin page background wash now reaches the edges of the content column (no gray strip beside the header).
* Improved: SEO Possibilities discovery stays free (full URL list, expand, editor, free Tools). Pro adds triage: Do next, By type, Top 10 URLs by severity and traffic, plus Mark as done, Ignore, and Undo.
* Improved: Pro Mark as done / Ignore / Undo also work on the editor SEO Analysis tab for saved findings (same triage as Possibilities).
* Fixed: SEO Possibilities Mark as done and Ignore no longer reopen the same finding after a re-analysis, including sitewide checks.
* Improved: SEO Possibilities defaults to active findings only, and URL counts stay aligned with the filtered worklist.
* Fixed: Dashboard Ask about your SEO showed `[object Object]` instead of a clear error when WordPress Connectors AI was not configured.
* Improved: Ask about your SEO is disabled with a setup notice until WordPress Connectors AI is ready.
* Improved: Editor Analysis tab always shows SEO review entire page so you can re-run a full page scan anytime.
* Improved: Setup wizard automatic link suggestions show keyword, URL path, and page title on one line.
* Improved: Setup wizard: AI bot tracking toggle moved to the AI step.
* Improved: Setup wizard health step: SEO possibilities card shows scan results with a Review link.
* Fixed: SEO Possibilities no longer runs full content audits on URLs that return errors or redirects from Search Console history.
* Improved: Deleted or redirected URLs show a single clear status; republishing the same URL requeues analysis.
* Fixed: Direct file URLs from Search Console (PDF, images, archives) no longer get on-page content audits; existing stored possibilities for file URLs are cleaned up automatically.

= 7.4.0 | 2026-07-20 =
* Improved: Automatic Links, 404 & Redirects, and Tools layouts aligned with the dashboard UI (panels, clearer sections, refreshed tool cards).
* Improved: All Tools tabs share consistent styling: pill tab navigation, matching titles, softer rounded notices, and brand-green action buttons.
* Fixed: Inline status notices on Tools tabs (such as which SEO plugin data is written to) were hidden by a global notice rule and now display correctly.
* Improved: AI Bots report layout aligned with the dashboard UI (panels, overview cards, clearer sections).
* Improved: Dashboard layout polish: Ask about your SEO sits below Do next (always open), section titles match that style, and two-column rows no longer nest cards inside an outer card.
* Improved (Pro): Ask about your SEO on the dashboard shows a Beta label.
* NEW: Admin bar Page overview: read-only snapshot of SEO score, issues/opportunities, current SEO title/meta, saved AI suggestions, and top Search Console keywords (open without a full page reload; close no longer refreshes the page).
* Improved: Admin bar panel loads much faster: dropped heavy chart/table libraries (Tabulator/uPlot) from that shell in favor of a light HTML layout.
* NEW: Ask about this page on the editor AI tools tab: question chips and free-text questions about rankings, SEO issues, titles, focus keywords, and internal links (WordPress Connectors).
* NEW (Pro): Apply proposed focus keyword and create autolink actions from Ask answers; free shows a Pro upgrade note on those actions.
* Improved: Saved AI title/meta suggestions and Ask answers load only when you open the AI tools tab (not on every editor page load); restored suggestions stay collapsed until expanded.
* Fixed: AI SEO suggestions no longer fail with "Malformed UTF-8" when page content or keywords contain non-English characters: prompt text is sanitized before sending to WordPress Connectors.
* Improved: Post language detection for AI covers more locales, no longer falls back silently to English for unknown codes, and shows the detected language on the AI tools tab.
* Improved: AI title/description prompts use traffic-ranked Search Console keywords, current meta, and a short cannibalization avoid-list (including the leading page) when relevant.
* Improved: AI tools generates 7 title and 7 meta description suggestions (was 5).
* Improved: Editor SEO Booster tabs are larger and easier to spot; the Keywords tab shows a count badge when GSC data exists for the page.
* Fixed: Google Search Console connection callback now requires an administrator and a one-time state token before saving access credentials.
* Fixed: Selecting a Search Console property validates against the connected site list and requires administrator access.
* Fixed: Editor and Tools AJAX actions that change a post, term, or media item now require permission to edit that specific item.
* Fixed: Sitewide SEO analysis and stop-analysis actions require administrator access.
* Fixed: GSC, Autolink, 404, and debug-log admin queries no longer nest prepared SQL fragments (search and pagination use a single prepared statement).
* Improved: Outbound link and image checks skip private or link-local addresses.
* Fixed: Tools Focus Keyword and Autolink Opportunities no longer preselect all scan results.
* Fixed: Bulk meta and GSC Opportunities Process buttons enable from scan results and selection (like Focus keywords); if WordPress Connectors AI is not ready, a warning shows and clicking Process explains what to fix.
* Fixed: Bulk meta "Retry failed" button now restarts processing for failed items.
* Improved: Process/Apply selected shows a selection count; clearer scan → select → process guidance on those Tools tabs.
* Improved: Focus Keyword tool explains that keywords are written to the detected SEO plugin and help keyword analysis and internal link suggestions.
* Fixed: Needs Analysis and Content Decay now run SEO analysis from the Tools page after you start a batch (keep the tab open), with a clear completed state.
* Fixed: Tools batch preview no longer keeps showing "Processing…" after a run finishes: it now shows a completion message.
* Improved: Tools overview and tabs mark tools that require AI (WordPress Connectors).
* Fixed: Weekly email Recipient(s) field on Settings no longer shows a large gap of leading spaces before the address.
* Improved (Pro): Entity Map wizard keeps AI apply panels visible across steps, confirms merge vs replace on rebuild, and soft-gates Continue without a draft.
* NEW (Pro): Entity Map imports organization name, description, and sameAs URLs from SEO Booster settings or the active SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress, TSF).
* Fixed (Pro): Entity Map no longer shows "Organization data imported" on every tab load: that success message only appears after you click Import.
* Improved (Pro): Entity Map publishing marks AI drafts as human-reviewed automatically (no separate "Mark as human-reviewed" button).
* Improved (Pro): Entity Map AI drafts prune junk pages, prefer specific relation predicates, support entity sameAs and ProprietaryTerm maturityStatus, and publish with an in-admin preflight checklist aligned with entitymap.org.

= 7.3.5 | 2026-07-15 =
* Improved: Keywords tab in the editor metabox shows a green or yellow indicator when GSC keyword data exists for the page, without loading the full charts until you click Load.

= 7.3.4 | 2026-07-15 =
* Fixed: Choosing WordPress (Connectors) as the AI provider in Settings now saves correctly instead of reverting to Disabled.
* Fixed: Bulk meta and GSC Opportunities Process buttons enable from scan results and selection; Connectors AI is still required to run generation.
* Fixed: Needs Analysis and Content Decay run SEO analysis from the Tools page after you start a batch (keep the tab open).
* Fixed: Tools batch preview shows a completed message instead of staying on "Processing…".
* Improved: Tools overview marks tools that require AI (WordPress Connectors).

= 7.3.3 | 2026-07-13 =
* Fixed: Google Search Console connection callback now requires an administrator and a one-time state token before saving access credentials.
* Fixed: Selecting a Search Console property validates against the connected site list and requires administrator access.
* Fixed: Editor and Tools AJAX actions that change a post, term, or media item now require permission to edit that specific item.
* Fixed: Sitewide SEO analysis and stop-analysis actions require administrator access.
* Fixed: GSC, Autolink, 404, and debug-log admin queries no longer nest prepared SQL fragments (search and pagination use a single prepared statement).
* Improved: Outbound link and image checks skip private or link-local addresses.
* Fixed: Tools Focus Keyword and Autolink Opportunities no longer preselect all scan results.
* Fixed: Bulk meta and GSC Opportunities Process buttons enable from scan results and selection (like Focus keywords); if WordPress Connectors AI is not ready, a warning shows and clicking Process explains what to fix.
* Fixed: Bulk meta "Retry failed" button now restarts processing for failed items.
* Improved: Process/Apply selected shows a selection count; clearer scan → select → process guidance on those Tools tabs.
* Improved: Focus Keyword tool explains that keywords are written to the detected SEO plugin and help keyword analysis and internal link suggestions.
* Fixed: Needs Analysis and Content Decay now run SEO analysis from the Tools page after you start a batch (keep the tab open), with a clear completed state.
* Fixed: Tools batch preview no longer keeps showing "Processing…" after a run finishes: it now shows a completion message.
* Improved: Tools overview and tabs mark tools that require AI (WordPress Connectors).

= 7.3.2 | 2026-07-09 =
* Fixed: GSC Overview and GSC keyword table sorting now allow only known column names and ASC/DESC: untrusted sort parameters can no longer change the SQL query (administrator access required).
* Fixed: Fatal error when opening the post editor SEO metabox (missing SEO plugin registry import in analysis context).
* Fixed: Freemius integration restored missing product flags (premium version, WordPress.org compliance/gatekeeper, affiliation) and the post-activation welcome first-path.
* NEW (Pro): Content decay tool: list pages whose Search Console clicks fell over the last 30 days vs the previous 30 days; open SEO Possibilities or queue re-analysis.
* Improved: Needs analysis results link to SEO Possibilities for URLs with open issues.
* Improved: FAQ and description Pro sections list current Pro features including Content decay and AI bot blocking.
* Improved: Post editor SEO Booster and Keyword Analysis metaboxes are combined into one tabbed panel (Analysis, AI tools, Keywords), with keyword charts still loaded on demand.
* Improved: Detected SEO plugin / focus keywords, Automatic Linking, and Exclude from SEO Analysis are grouped in one Page settings area above the tabs; the side Autolink metabox is merged in.
* Improved: Full-page SEO review lives under Analysis and only shows when needed (not analyzed or after content-change warnings); metabox quick review removed; saved AI suggestions open expanded on the AI tools tab.
* Improved: AI bot block list shows visit counts and latest visit (loaded on demand), with sort by activity and a "visited only" filter.
* Fixed: AI bot block visit stats AJAX is Pro-only; visit counts retry if the first load fails; "visited only" stays disabled until counts load.
* Improved: Settings Stats lists all plugin tables including AI bot and referral data, with lighter approximate size reporting loaded when the tab opens.
* Improved: AI bot retention help notes that 30–60 days is usually enough and links to Stats for database usage.
* Improved: Entity Map workflow uses clearer Sources & organization → Edit & refine → Publish steps with an always-visible health strip (live, unsaved draft, static-file shadowing, AI busy).
* NEW: Improve with AI enriches the current draft in place: locked entities are skipped, nothing is deleted, and a summary shows what changed.
* Improved: Generate with AI warns before replacing an existing draft; org sameAs is only filled when empty; sticky AI status panel and disabled controls during long AI requests.
* Improved: Review with AI and Improve with AI have distinct labels and helper copy; friendly AI errors log to Debug Log.
* Improved: Admin alert and confirm dialogs use a consistent custom modal (ESC, click-outside, Enter) across plugin screens instead of native browser popups.

= 7.3.1 | 2026-07-09 =
* Improved: Entity Map generator always exports EntityMap v1.0 core types and predicates: legacy WebPage/Article/PUBLISHES values are migrated on save and export.
* Improved: Entity Map uses smarter page classification (Service, ProprietaryTerm, SoftwareProduct) and optional org OFFERS for primary product pages.
* Improved: Entity Map AI draft supports all v1.0 types, optional hub entities (product/person), and a non-destructive AI review with apply-selected suggestions.
* Improved: AI-generated maps publish as `verificationStatus: generator-draft` until marked human-reviewed; site builds stay `self-declared`.
* Improved: Entity Map editor adds per-entity sameAs, relevance scores from curation, richer chunk types, filter chips, unsaved-draft banner, and live vs preview check.
* Fixed: Static `entitymap.json` shadowing is called out clearly; bot-gap "Add as entity" returns the new entity to the editor.
* Fixed: Entity Map admin UI stayed unresponsive when entity JSON was large or the page hit a render error: entity data now loads from a hidden textarea and admin render failures are caught safely.

= 7.3.0 | 2026-07-08 =
* NEW: Entity Map (Pro): publish structured `/entitymap.json` and `/entitymap.html` so AI systems understand your organization, key content, and relationships.
* Improved: Entity Map export includes `profile: core` and optional publisher `sameAs` URLs (one per line) for entitymap.org validator compliance.
* Improved: Entity Map save and preview show a spinner, disabled controls, and status text; rewrite rules flush only when publish endpoints change.
* Improved: Entity Map editor uses collapsible entity rows, clearer publish success panel, live endpoint links, and a link to validate at entitymap.org.
* Improved: Entity Map output conforms to EntityMap v1.0: chunk publisher attribution, 600-character chunk limit, spec entity types and predicates, optional `EntityMap:` robots.txt discovery line.
* Improved: Entity Map editor builds from GSC + AI bot traffic, supports human editing (relations, chunks, locks), bot crawl gaps, and optional AI drafts via WordPress Connectors.
* Improved: Entity Map workflow uses clearer Build draft → Edit entities → Publish live steps so nothing goes public until you enable endpoints and save.
* Improved: All Tools tabs use Settings-style toggles and the same two-column form layout as llms.txt (label left, controls right), with documentation links in each tool header.
* Improved: When Entity Map is published, llms.txt can include a Structured knowledge section linking to the JSON/HTML endpoints.
* Improved: Free installs see Entity Map as a locked Tools tab, an llms.txt upsell notice, and a soft AI Readiness / sitewide improvement (not an error).
* Fixed: The SEO Framework adapter now passes title and description values in the correct order to TSF’s update API.
* Fixed: All in One SEO term reads/writes no longer treat a WP_Term object as SEO data; uses the aioseo_terms table when no Term model is available.
* Fixed: AI Readiness and SEO possibilities no longer require a post excerpt on pages; the checklist uses meta description (compatible with Yoast, Rank Math, and other SEO plugins), with excerpt as fallback only when no SEO plugin is active.
* Improved: Unified SEO plugin integration for Yoast SEO, Rank Math, All in One SEO, SEOPress, and The SEO Framework.
* Improved: Bulk meta, GSC opportunities, and focus keyword tools now write to all five supported SEO plugins.
* Improved: Tools page shows which SEO plugin SEO Booster is writing to.
* Improved: AI suggestion "Use" now saves title, description, and focus keyword to your SEO plugin immediately, not only in the editor UI.
* Improved: SEO analysis on categories and tags reads meta title and description from your active SEO plugin.
* Improved: Duplicate title and meta description checks include taxonomy pages when a supported SEO plugin is active.
* Improved: Dashboard and Settings show SEO plugin compatibility status (same as Tools).
* Fixed: Sites with more than one SEO plugin active now see a warning about which plugin SEO Booster uses for writes.
* Improved: Added SEO plugin compatibility section in readme for supported plugins, priority order, and WooCommerce notes.
* Improved: SEO Booster meta box AI section now hides empty request and suggestion areas until there is activity or saved results.
* Fixed: Focus keywords tool Scan did not run when content types were selected.
* Improved: Focus keywords tool copy and status notices clarify GSC-only suggestions (no AI).
* Improved: llms.txt tool adds robots.txt LLMS discovery, HTML/HTTP Link signals, directory include/exclude rules, FAQ export, bot crawl gap insights, configurable cache TTL, and AI intro/page suggestions from GSC + bot traffic.
* Improved: llms.txt link descriptions now use your active SEO plugin meta when available.

= 7.2.5 | 2026-07-10 =
* Fixed: AI Readiness and SEO possibilities no longer require a post excerpt on pages; the checklist uses meta description (compatible with Yoast, Rank Math, and other SEO plugins), with excerpt as fallback only when no SEO plugin is active.
* Improved: Unified SEO plugin integration for Yoast SEO, Rank Math, All in One SEO, SEOPress, and The SEO Framework.
* Improved: Bulk meta, GSC opportunities, and focus keyword tools now write to all five supported SEO plugins.
* Improved: Tools page shows which SEO plugin SEO Booster is writing to.
* Improved: AI suggestion "Use" now saves title, description, and focus keyword to your SEO plugin immediately, not only in the editor UI.
* Improved: SEO analysis on categories and tags reads meta title and description from your active SEO plugin.
* Improved: Duplicate title and meta description checks include taxonomy pages when a supported SEO plugin is active.
* Improved: Dashboard and Settings show SEO plugin compatibility status (same as Tools).
* Fixed: Sites with more than one SEO plugin active now see a warning about which plugin SEO Booster uses for writes.
* Improved: Added SEO plugin compatibility section in readme for supported plugins, priority order, and WooCommerce notes.
* Improved: SEO Booster meta box AI section now hides empty request and suggestion areas until there is activity or saved results.
* Fixed: Focus keywords tool Scan did not run when content types were selected.
* Improved: Focus keywords tool copy and status notices clarify GSC-only suggestions (no AI).
* Improved: llms.txt tool adds robots.txt LLMS discovery, HTML/HTTP Link signals, directory include/exclude rules, FAQ export, bot crawl gap insights, configurable cache TTL, and AI intro/page suggestions from GSC + bot traffic.
* Improved: llms.txt link descriptions now use your active SEO plugin meta when available.

= 7.2.4 | 2026-07-01 =
* Improved: SEO analysis report lists are more compact and easier to skim; image issues show a thumbnail instead of raw HTML.
* Fixed: Post editor no longer fatals on the AI Readiness / Site checks section when sitewide SEO analysis results exist.
* Fixed: Settings → Tools maintenance buttons now run the intended actions: Clear All Data and Options wipes plugin data (preserving autolink rules), Reset SEO Booster Debug Log clears sb2_log, and Restart Keyword Scanning queues a full GSC keyword rescan.

= 7.2.3 | 2026-06-30 =
* NEW: AI referral tracking: records human visits from ChatGPT, Perplexity, Claude, Gemini, and Copilot on the AI Bots page (summary, chart, and Referrals tab).
* NEW: AI Readiness checklist in the post editor: powered by SEO analysis and sitewide checks; classic editor shows readiness in the main SEO Booster metabox; block editor has a unified sidebar panel.
* Fixed: AI Bots Content status no longer shows 301 from redirect hops mixed with successful crawls; redirect bot visits are counted separately.
* Improved: AI Bots View link uses the tracked canonical URL; bot breakdown splits content vs redirect visits.
* Fixed: Automatic Links "Last Used On" no longer lists pages where the keyword is no longer injected (e.g. after a manual link was added).
* Improved: Toned down severity labels on the SEO Possibilities page to match the existing admin color palette.
* Improved: SEO Possibilities page no longer shows checkboxes or bulk marking: expanded rows list Issues and Suggestions only (Not applicable and Good practices hidden).
* Improved: AI Bots report groups visits by content page with Content crawled, By bot, and Noise tabs plus date range filters.
* Improved: AI bot tracking records real HTTP status, canonicalizes search trap URLs, and can skip unmapped noise when "Track mapped content only" is enabled.
* NEW: AI Bots charts (visits over time and purpose breakdown), top content insights, GSC and SEO issue badges, and per-page bot breakdown.
* Improved: Dashboard AI Bots card shows mapped content page count, content vs noise ratio, and top crawled pages.
* Improved: Settings opens on the AI/LLM tab by default (first tab in the navigation).
* Improved: Automatic Links settings tab copy and layout: clearer sections, grouped excluded elements, and plainer help text.
* Improved: Settings toggles on Automatic Links, AI/LLM, and Tools tabs now use the same modern switch design as Email and SEO Possibilities.
* Improved: Stats settings tab tables now use standard WordPress list-table styling with proper section layout.
* Fixed: Tools admin page no longer fatals when loading scripts (missing namespace imports for shared plugin classes).
* Fixed: Tools and other admin screens now show the correct "AI disabled" message when AI is off in Settings, instead of a misleading Credits error from a stale stored provider value.
* Fixed: Selecting "WordPress (Connectors)" as the AI provider now saves and stays selected; previously a casing mismatch made the choice appear to do nothing.
* Improved: Plugin admin UI now uses a clean in-page dialog instead of the browser's default pop-ups for confirmations and messages: press Enter to confirm, Esc to cancel (Tools, metaboxes, post list bulk actions, media library, and Elementor editor).
* Improved: Friendlier wording when queuing SEO analysis from the Needs analysis tool (no more internal jargon about the processing engine).
* Improved (Pro): GSC opportunities now groups all of a page's similar queries into one row and rewrites each page once using the whole query cluster: the AI picks the best representative keywords instead of stuffing every variation or producing templated meta.
* Improved: Tools results tables now show each page's slug with the full title on hover and reveal Edit, View, and Dismiss actions on hover (matching the standard WordPress list view).
* Fixed: Bulk meta and GSC opportunities no longer fail when only the SEO title or meta description needs updating (overwrite off).
* Improved: Settings → Stats now lists all plugin database tables and reports Action Scheduler / cron status more accurately.
* Improved: Automatic Links page adds and deletes keywords without reloading the page; new rows stay inline-editable.
* NEW: **AI bot tracking**: detect known AI crawlers on frontend requests, classify activity as research/training or citation/answer-engine, and store raw request paths plus normalized URLs for reporting.
* NEW: **AI Bots** admin page and dashboard widget with top bots, recent activity, and purpose breakdown.
* Improved: **Dashboard** redesigned with KPI scorecard tiles, GSC chart at the top, keyword and SEO Possibilities summary cards, and module cards for Automatic Links, AI Bots, Tools quick wins, and AI Credits.
* Improved: Free builds show a single random Pro feature highlight card (404 monitoring or autolink column) without blocking dashboard use.
* NEW (Pro): **Block AI bots** at the server when detected (HTTP 403) by bot name or purpose: independent of robots.txt.
* NEW (Pro): **GSC opportunities** tool: scan striking-distance, low-CTR, and high-impression/low-click pages from imported GSC data; batch-rewrite SEO titles and meta with AI seeded by the opportunity query.
* NEW (Pro): **Focus keywords** tool: suggest unique focus keywords from GSC for pages missing one; skips utility pages and content without search demand; one-click revert.
* NEW (Pro): **Autolink opportunities** tool: find high-value GSC queries without autolink rules and bulk-create rules; enable autolink on high-traffic pages where it is off.
* Fixed: Pro Tools tabs (Needs analysis, GSC opportunities, Focus keywords, Autolink opportunities) did not appear for licensed Premium installs.
* Fixed: Autolink opportunities tab fatal error from a missing class import in the tool view.
* Improved: Pro Tools tabs use the same locked teaser pattern as the dashboard for free users; licensed Pro users see normal tabs and full tool UI (no "(Pro)" suffix).
* Improved: Autolink opportunities lists one row per target page with paginated navigation, collapses near-duplicate GSC queries (word-order and subset variants), and caps suggested keywords per page so heavy URLs no longer flood the preview.
* Improved: Focus keywords tool shows up to 10 GSC suggestions per page in an expandable picker, lets you edit the keyword before applying, and displays formatted impression/position metrics.
* Improved: Autolink opportunities groups suggested keywords under each target page, shows the target slug, and lets you select a whole page's keywords at once: while still skipping keywords that already have a rule.
* Fixed: Autolink opportunities batch processing failed when creating rules (missing class reference); batches now record per-item failures instead of aborting the whole run.
* Improved: GSC opportunities, Focus keywords, and Autolink opportunities Tools tabs show live batch progress, last-processed results, and failed items above the results table (matching Image metadata).
* Improved: Needs analysis tab shows how many items were queued above the results table after you start a bulk run.
* Improved: Bulk meta uses editor content (the_content) instead of fetching full rendered pages: faster batches and lower AI token usage.
* Improved: Bulk meta skips AI calls when selected fields are already filled (unless overwrite is enabled).
* Improved: Bulk meta sends a smaller prompt (top 10 GSC keywords, field-aware generation, no local analysis dump).
* Improved: Bulk meta enforces SEO title and meta description length limits on save.
* Improved: SEO possibility scanner refactored with scoped DOM parsing, smarter image alt checks (skips lazy data: placeholders), unified severity (issues vs opportunities vs passed vs not applicable), cached link/image validation, and throttled GSC URL inspection during bulk runs.
* Fixed: Full-page analysis no longer inflates empty alt-text counts from Optimole/lazy-load placeholder images and noscript duplicates.
* Fixed: Dashboard and SEO Possibilities counts now exclude suggestions and skipped checks from actionable issue totals.
* Improved: SEO Possibilities inline expand shows issues, suggestions, and skipped checks consistently with the metabox.
* Fixed: URL status cache table auto-created on upgrade via dbDelta.
* Fixed: SEO score no longer stays at 100 when warnings and suggestions are open (removed good-check bonus inflation; suggestions now apply a small score deduction).
* Fixed: rel="author" and contact-info checks scan the full rendered page (including footer/site chrome), not only main post content.

= 7.2.2 | 2026-06-23 =
* NEW: Tabbed **SEO Booster → Tools** page with Overview, Bulk meta, Image metadata, and llms.txt tabs.
* NEW: **Bulk meta** tool: scan for missing, duplicate, or keyword-less SEO titles and descriptions; generate with AI via WordPress Connectors (Yoast SEO or Rank Math); preview before/after; revert last bulk run.
* NEW: **llms.txt generator**: build curated content, download manually, or serve `/llms.txt` dynamically (virtual file); detects an existing physical llms.txt on disk.
* NEW (Pro): **Needs analysis** tool: find never-analyzed, stale, or issue-bearing content and queue bulk SEO re-analysis.
* Improved: Shared batch-processing engine for Tools bulk actions; bulk meta shows a clear completion message and a detected SEO plugin badge.
* Fixed: Bulk meta no longer triggers a fatal error when WordPress Connectors returns an AI error: the item is marked failed and processing continues.
* Improved: Bulk meta uses a lighter AI prompt and a 90-second Connectors timeout (up from WordPress’s 30s default) so large pages are less likely to fail mid-batch.
* Fixed: llms.txt GSC curation queried a non-existent `clicks` column. Now joins keyword history like the rest of the plugin.

= 7.2.1 | 2026-06-23 =
* NEW: Bulk enable/disable automatic internal links from the posts list (Premium).
* Fixed: Automatic internal links no longer run on search results pages, REST API (wp-json) responses, or other non-page requests (POST, XML-RPC, sitemaps, embeds, 404s, previews).
* Fixed: Automatic internal links now only run on singular posts and pages that have automatic linking enabled: not on archives, the blog home, or taxonomy listings.
* Improved: The "Last Used On" column no longer lists search results pages or wp-json paths; usage is only recorded from opted-in singular content.
* Fixed: Automatic link injection no longer corrupts escaped HTML entities (e.g. code snippets) on the page.
* Improved: Keyword matching supports UTF-8/accented keywords and skips regex when the keyword is not present in a text block.
* Improved: Autolink keyword list is cached per request and via object cache; page caches are purged when keywords are added, updated, or deleted (WP Rocket, W3TC, WP Super Cache, LiteSpeed).
* Improved: Tools → Image metadata scan skips images whose files are missing on disk and reports how many were skipped, so batch processing no longer wastes time on broken attachments.

= 7.2 | 2026-06-19 =
* New: **Tools → Image metadata**: scan the Media Library for missing alt text, title, caption, or description; preview matches and batch-process with AI. Choose which fields to apply; live before/after preview while processing.
* Improved: Image metadata batch tool shows total match count with a 50-image preview table; process selected or all matching images. AI skips images it cannot verify instead of saving guessed metadata.
* Improved: When a batch finishes, you now get a clear summary with total time (minutes and seconds), plus how many images were processed and how many failed.
* Improved: The browser tab title updates while processing (for example, "12/50") and shows a short success or cancelled message when done: handy when you work in another tab.
* Improved: While a batch runs, a warning reminds you to keep the tab open, and your browser asks for confirmation if you try to leave.
* Improved: Estimated time remaining appears in the progress header after a few images have been processed.
* Improved: Failed images are listed when a batch finishes, with a **Retry failed** button to run only those again.
* Improved: The Cancel button disappears when processing is done, so it is obvious when nothing is still running.
* Improved: After processing, the results table clears and refreshes automatically so you see up-to-date counts without clicking Scan again.
* Improved: The results table now looks like the standard WordPress Media Library list: thumbnail, title, filename, and Edit/View links on each row.
* Improved: Tools → Image metadata explains when a text-only connector (e.g. DeepSeek) cannot analyze images and links to Connectors or SEO Booster Credits.
* Improved: Freemius free vs Pro build alignment: premium 404/redirect tracking and related admin UI are excluded from the free WordPress.org release.
* Improved: Database tables now update automatically when an administrator visits wp-admin after a schema update: manual "fix database" is only needed as a fallback.
* Fixed: Dashboard shortcuts for frontend keywords and SEO Possibilities are no longer incorrectly styled as Pro-only.
* Improved: Pro weekly email reports include a 404 errors summary when 404 monitoring is enabled.
* Improved: SEO Booster Credits provider in settings is labeled coming soon until public release.

= 7.1.1 | 2026-06-04 =
* Fixed: SEO analysis from Search Console / scheduled scans now runs correctly for discovered URLs (was showing "No URL provided" and not analyzing).

= 7.0.4 | 2026-01-22 =
* New version. Too much to explain, check https://seoboosterpro.com

= 7.0.3 =
* New version. Too much to explain, check https://seoboosterpro.com
* Upgraded dependencies: dealerdirect/phpcodesniffer-composer-installer, freem

= 7.0.2 =
* New version. Too much to explain, check https://seoboosterpro.com
* Upgraded dependencies: dealerdirect/phpcodesniffer-composer-installer, freem

= 7.0.0 =
* New version - tons of features added, now a fully fledged SEO plugin that handles titles, descritions, social media data, open graph, sitemap and much more. Direct keyword integration with your data from Google Search Console.

== Upgrade Notice ==
7.3.2: Security hardening for GSC table sorting, Content decay (Pro), combined post editor metabox, Entity Map AI workflow, and admin modal dialogs.
