=== SEO Booster ===
Contributors: cleverplugins, lkoudal, freemius
Donate link: https://seoboosterpro.com
Tags: seo, google-search-console, internal-links, analytics, woocommerce
Requires at least: 6.8
Requires PHP: 7.4
Tested up to: 7.0.2
Stable tag: 7.4.8
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


= 7.4.8 | 2026-08-21 =
* Fixed: Google Search Console OAuth callback cookie fallback now also requires an HMAC signature from seoboosterauth.com, closing an administrator CSRF window during connect (CVE-2026-15660 follow-up).
* Fixed: Page overview keyword footer now shows real counts (for example “Showing top 10 of 42 keywords”) instead of the raw translation placeholders `%1$d` and `%2$d`.
* Improved: Outbound seoboosterpro.com links from the plugin admin now use one UTM helper (free vs Pro source, stable campaign, placement slug) so marketing traffic is easier to attribute.
* Fixed: Page overview no longer sounds like the page has no data when Search Console keywords are present. It now says there is no on-page SEO analysis yet, and points to the keyword list below when GSC data exists.
* Fixed: GSC Overview Trends charts load when rows scroll into view instead of only on hover, with a hard cap on live charts so hovering no longer crashes Chrome tabs.
* Improved: Trends cells show a consistent loading state; charts pause when scrolled away to keep memory use stable.
* Fixed: GSC Overview no longer defaults to an empty “Recent Activity” view. That broken checkbox (which duplicated the 30-day dropdown filters) was removed; use New Keywords / Not Seen in the Filter menu instead.
* Improved: GSC Overview and editor keyword mini charts load a short series of real daily Search Console points (up to 90 days) instead of pulling full multi-year history into memory; “Show full history” caps at 180 real days. Table clicks, impressions, CTR, and position totals stay all-time.
* Improved: Tools and Ask about your SEO GSC insight scans use a recent traffic window so large history tables do not exhaust memory on long-running sites.
* Improved: Release packaging excludes dev-only reference files and unused assets from the build zip.
* Improved: Removed unused Composer dependency and legacy admin AJAX endpoints that had no UI callers.
* Fixed: SEO Possibilities no longer shows a filtered type count (for example “1”) in the URL row when expanding still lists every finding on that page.
* Improved: Danish admin copy reads more naturally (Roadmap, Do next, fokusord, SEO analysis messages).
* Improved: Release build compiles translation artifacts (.mo, .l10n.php, JSON) via scripts/compile-languages.sh.

= 7.4.7 | 2026-08-11 =
* Fixed: Keywords that include a percent sign (for example cbd 4%) are detected in page content again. Matching no longer uses PHP word boundaries that skip trailing punctuation, and Search Console queries are no longer sanitized in a way that can strip % sequences.

= 7.4.6 | 2026-08-03 =
* Fixed: Bulk meta scan no longer flags titles or descriptions as missing when the SEO plugin fills them via templates or global defaults (for example Rank Math %excerpt%). Duplicate and keyword checks use the same resolved values.
* Fixed: Category and tag SEO title/description templates are resolved for SEOPress, All in One SEO, and The SEO Framework (same idea as Yoast and Rank Math), so analysis and Tools are less likely to report false missing meta on terms.
* Improved: AI bot and referral hit tables now enforce a soft row cap during daily maintenance, in addition to the retention window.
* Improved: Clearer copy when Search Console keywords are missing from the page content (including Danish).

= 7.4.5 | 2026-08-03 =
* Fixed: SEO title and meta checks no longer treat Rank Math-style templates (for example %excerpt%) as missing titles. Page overview and GSC keyword location resolve templates; Tools backup and bulk fill still use raw stored values.
* Fixed: Full-page SEO analysis no longer reports 0 words when an empty main landmark hides real body content; word counts use UTF-8 tokens. Re-analysis may refresh length and density scores.
* Fixed: Google Search Console URL Inspection no longer treats INDEXING_STATE_UNSPECIFIED / “URL is unknown to Google” as a high-severity indexing problem, and only marks indexing OK when Google’s verdict is PASS.
* Fixed: Bulk analysis no longer wipes prior GSC indexing findings when URL Inspection is deferred; rich-result warnings are opportunities, not high severity.
* Fixed: Indexability is read from the active SEO plugin (or page robots meta) instead of always reporting pages as indexable; The SEO Framework correctly skips focus-keyword suggestions.
* Fixed: SEO plugin adapters: All in One SEO uses current APIs for title, description, noindex, and focus keywords; Yoast category/tag fields, The SEO Framework v5 term meta, and SEOPress empty noindex are handled correctly.
* Improved: Danish dashboard copy for Ask about your SEO (no repeated Spørge) and clearer Do next wording; Ask card label/CTA are Advisor / Open.

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


*For older releases, see the [full changelog on seoboosterpro.com](https://seoboosterpro.com/changelog/).*
