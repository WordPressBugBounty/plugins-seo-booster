=== SEO Booster ===
Contributors: cleverplugins, lkoudal, freemius
Donate link: https://seoboosterpro.com
Tags: seo, google-search-console, internal-links, analytics, woocommerce
Requires at least: 6.8
Requires PHP: 7.4
Tested up to: 7.0
Stable tag: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Google Search Console in WordPress: keyword insights, on-page SEO, internal links. AI via WordPress 7 Connectors.

== Description ==

Google Search Console tells you which keywords bring visitors to your site — but that data lives in Google's dashboard, separate from the pages you edit in WordPress.

**SEO Booster closes that gap.** Connect Search Console once, import your keyword data, and see real search performance on every post, page, and taxonomy you edit. Turn GSC insights into actionable SEO tasks, automate internal linking across your site, and optionally use AI — powered by WordPress Connectors — to speed up content and image optimization.

**Works alongside Yoast SEO, All in One SEO, and similar plugins** — SEO Booster complements them with GSC-driven keyword intelligence, internal linking, and on-page analysis they typically do not provide.

**Requires WordPress 6.8 or later.**

= AI with WordPress Connectors (optional) =

Optional AI features use the **WordPress Connectors** system built into **WordPress 7.0 or later**. Set up your AI provider under **Settings → Connectors** in WordPress, then choose **WordPress Connectors** under **SEO Booster → Settings → AI/LLM**. GSC import, automatic links, and SEO analysis work fully without AI — leave it disabled if you prefer.

= Free features =

**Google Search Console**

* Connect your site via OAuth; import 7, 30, or 90 days of keyword data with scheduled refresh
* Searchable **GSC Overview** with filters for new or stale keywords, content usage, and position bands
* **Dashboard** with 30-day trends, charts, top keywords, and period-over-period comparison
* **Weekly email reports** with key metrics — all processed on your server, not sent to third parties

**Keyword insights where you work**

* GSC metabox on posts, pages, taxonomies, and WooCommerce products
* Admin bar keyword popup while browsing the frontend
* Optional frontend keyword highlighting to see GSC terms in your content
* Per-keyword history mini-charts and one-click re-analyze

**Automatic internal links**

* Define keyword-to-URL rules; matching text in body content links automatically site-wide
* WooCommerce products supported; works with major page builders (Gutenberg, Elementor, Beaver Builder, and more)

**SEO Possibilities**

* On-page analysis with prioritized suggestions for titles, meta descriptions, headings, images, links, and more
* GSC-based opportunities including low CTR, missing keywords, and keyword cannibalization warnings
* Sitewide checks, bulk analysis from post lists, and optional SEO score admin columns
* Auto-scan URLs discovered from GSC imports (configurable frequency)

**AI-powered SEO (optional, WordPress 7.0+)**

* Uses **WordPress Connectors** — your AI provider is configured once in WordPress, not inside SEO Booster
* Metabox suggestions, WooCommerce product SEO, and comprehensive audits
* AI writing outline with generate-article-from-outline workflow
* Media Library bulk alt text and title generation
* Image metadata batch processing (vision-capable connector required for image analysis)

**Tools**

* Image metadata scanner and AI batch fix — see **Image metadata batch tool** below

= SEO Booster Pro =

Upgrade at [seoboosterpro.com](https://seoboosterpro.com) for:

* **404 and redirect monitoring** — track broken URLs and redirects with a searchable admin report
* **404 summary in weekly email** — top broken URLs when monitoring is enabled (Pro)
* **Autolink column** — enable or disable automatic linking per post from the posts list and Quick Edit
* **Autolink status in GSC popup** — see which keywords are auto-linked, or create links in one click from the keyword details view

== Automatic Links ==

Automatically turn keywords in your content into internal links. Every time you write "contact us", it can link to your contact page — no manual linking required.

Works with WooCommerce products and major page builders. See the [supported page builders list](https://seoboosterpro.com/docs/automatic-links/supported-page-builders/) for details.

== Image metadata batch tool ==

Find and fix missing image metadata across your Media Library without opening each attachment one by one. Go to **SEO Booster → Tools → Image metadata**.

* **Scan** JPEG, PNG, and WebP attachments for missing title, alt text, caption, or description; preview matches before you act
* **Generate with AI** by analyzing the actual image file — choose which fields to apply; only checked fields are saved
* **Process in batches** — selected images or every match in your library, with live preview while processing
* **Fail-safe** — if the AI cannot verify an image, nothing is saved; the tool prefers empty fields over wrong alt text
* **Works without AI** — run scans and review gaps even when AI processing is not configured
* **Requires vision-capable AI** for generation via WordPress 7 **Connectors**; text-only connectors cannot analyze images

Learn more in the [SEO Booster documentation](https://seoboosterpro.com/docs/).

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

No. Those plugins are excellent for titles, meta tags, sitemaps, and schema. SEO Booster does not replace them — it complements them with Google Search Console keyword data on your edit screens, automatic internal linking, and on-page analysis focused on real search performance.

= Do I need a Google Search Console account? =

Yes, for keyword import and GSC-based features such as the dashboard, GSC Overview, keyword metaboxes, and GSC-driven SEO Possibilities checks. Automatic internal links and many on-page SEO checks work without connecting Search Console.

= How does AI work? =

AI is optional and can stay disabled. When you want it, you need **WordPress 7.0 or later** with **Connectors** set up under **Settings → Connectors** in WordPress. In SEO Booster, go to **Settings → AI/LLM** and select **WordPress Connectors**. AI then powers metabox suggestions, writing outlines, image metadata generation, and more — using whichever provider you connected in WordPress.

= What is included in SEO Booster Pro? =

Pro adds 404 and redirect monitoring with 404 highlights in weekly email reports, per-post autolink controls from the posts list, and autolink status with one-click link creation in the GSC keyword popup. See [seoboosterpro.com](https://seoboosterpro.com) for details and pricing.

= Other questions =

Please contact us at [seoboosterpro.com/contact/](https://seoboosterpro.com/contact/)

== Screenshots ==

1. Connect Google Search Console from the dashboard
2. 30-day traffic overview with trends and top keywords
3. GSC Overview — search, filter, and keyword performance
4. Keyword insights on post edit screens with history charts
5. Automatic internal links — keyword rules that link site-wide
6. SEO Possibilities — prioritized on-page improvement list
7. Tools — batch-fix missing image alt text and metadata with AI

== Changelog ==

= 7.2 =
* New: **Tools → Image metadata** — scan the Media Library for missing alt text, title, caption, or description; preview matches and batch-process with AI. Choose which fields to apply; live before/after preview while processing.
* Improved: Image metadata batch tool shows total match count with a 50-image preview table; process selected or all matching images. AI skips images it cannot verify instead of saving guessed metadata.
* Improved: When a batch finishes, you now get a clear summary with total time (minutes and seconds), plus how many images were processed and how many failed.
* Improved: The browser tab title updates while processing (for example, “12/50”) and shows a short success or cancelled message when done — handy when you work in another tab.
* Improved: While a batch runs, a warning reminds you to keep the tab open, and your browser asks for confirmation if you try to leave.
* Improved: Estimated time remaining appears in the progress header after a few images have been processed.
* Improved: Failed images are listed when a batch finishes, with a **Retry failed** button to run only those again.
* Improved: The Cancel button disappears when processing is done, so it is obvious when nothing is still running.
* Improved: After processing, the results table clears and refreshes automatically so you see up-to-date counts without clicking Scan again.
* Improved: The results table now looks like the standard WordPress Media Library list — thumbnail, title, filename, and Edit/View links on each row.
* Improved: Tools → Image metadata explains when a text-only connector (e.g. DeepSeek) cannot analyze images and links to Connectors or SEO Booster Credits.
* Improved: Freemius free vs Pro build alignment — premium 404/redirect tracking and related admin UI are excluded from the free WordPress.org release.
* Improved: Database tables now update automatically when an administrator visits wp-admin after a schema update — manual "fix database" is only needed as a fallback.
* Fixed: Dashboard shortcuts for frontend keywords and SEO Possibilities are no longer incorrectly styled as Pro-only.
* Improved: Pro weekly email reports include a 404 errors summary when 404 monitoring is enabled.
* Improved: SEO Booster Credits provider in settings is labeled coming soon until public release.

= 7.1.1 =
* Fixed: SEO analysis from Search Console / scheduled scans now runs correctly for discovered URLs (was showing "No URL provided" and not analyzing).

= 7.0.4 = 
* New version. Too much to explain, check https://seoboosterpro.com

= 6.1.26 =
* 2025-09-25
* Fixes to AI traffic module.
* Updates to 3rd party libraries.

= 6.1.25 =
* 2025-09-04
* NEW: AI tracking added. Tracks top 25 AI and LLM crawlers and systems. Get a better overview of how much traffic you are getting from the AI robots.
* Better cached files cleanup - saving space.
* Improved daily cleanup of log entries for busy websites.
* Cleanup of AI tracking data when clicking "Clear All Data and Options"
* See in GSC overview how long ago when a visitor last came for that keyword - see declining keywords instantly.


= 6.1.24 =
* 2025-08-20
* Clean up 

= 6.1.23 =
* 2025-08-17
* FIX: If the access details have expired, the plugin will try to reauthenticate for you before allowing you to reauthenticate manually if necessary.
* Improved: Cleaning of unused functions in code.
* Improved: Keyword cannibalization report now filters out pages not competing after 30 days.
* NEW: Added cache refresh button.
* Improved: Changed import from settings page to utilize AJAX loading to prevent timeouts for large websites with lots of data.
* Improved: Automatic keyword linking now better restricts links to body content. Enhancement applied in the ContentProcessing pipeline.
* FIX: Improved regex pattern in fallback string replacement to prevent overly aggressive matching.
* Improved: Enhanced content filtering to ensure keyword links are only injected in appropriate body content areas.

= 6.1.22 =
* 2025-06-26
* NEW: Quick Autolink Control - Added a new "Autolink" column to your Posts and Pages list, making it super easy to enable or disable automatic linking with just one click. Works with any custom post type that's publicly visible.
* NEW: Smart Sorting - You can now sort your posts to see which ones have automatic links enabled, helping you manage your internal linking strategy more efficiently.
* NEW: Keyword Highlighting - See exactly where your Google-found keywords appear in your content. This helps you understand how your content matches what people are searching for.
* IMPROVED: Faster Loading - We've streamlined the interface to load much faster. The popup window and keyword data now only load when you actually need them (when you click "show details" in the admin bar).
* IMPROVED: On-Demand Data Loading - When editing posts and pages, keyword data and charts now load only when you request them, reducing unnecessary code loading and improving performance.
* IMPROVED: Better Token Management - Fixed the access token expiration issue so you won't need to re-authenticate as frequently.
* IMPROVED: 404 & Redirects Priority - The 404 errors report now shows URLs with the most visits first, helping you focus on the most important issues that need attention.
* IMPROVED: Support System - We've moved away from HelpScout to a new support system for better customer service.

= 6.1.21 =
* 2025-05-08
* Fix to the 404 errors report. Now displays all redirects and not found 404 pages, and is renamed "404 & Redirects". Thank you Helle.
* Improvement: If the access token has expired and the user needs to reauthenticate there is now a big notice on the dashboard page allowing for easy one click reauthentication.
* Now working in regular edit category view also.
* "Show details" in admin bar now links correctly - Before the link would fail on taxonomy pages.
* Fix to the charts generated - now displays the dates correctly.
* Improvement: Adding short term caching to he chart data - improves load time in GSC overview and othe tables.
* Fix: Added visual fix to reports page where a closing div was missing.
* Added link to pages for easier navigation in the report tables.

= 6.1.20 =
* 2025-04-28
* NEW: Full compatibility with WooCommerce category pages. Now when you edit a category page you can see the keywords bringing that page traffic.
* NEW: You can also use the "View details" from the frontend via the admin bar on WooCommerce tags and product categories as well as products.
* NEW: Introducing a small mini chart covering history impressions, clicks and average position. Removed CTR and last seen columns.
* NEW: Hover over each point in the chart for details about the date.

= 6.1.19 =
* 2025-04-21
* NEW: Added the mini history chart to the popup window.
* NEW: Read it like Google: Improved keyword detection to handle hyphenated variations (e.g., "seo-bureau" matches "seo bureau").
* NEW: Enhanced database tracking to update existing entries based on URL and status code.
* Improved: Minor code improvements and styling here and there in the interface.

= 6.1.18 = 
* 2025-04-21
* FIX: Automatic linking, where keywords could link to themselves.
* NEW: See where links are pointing from in the keywords table. Easier to see where the internal links are coming from.
* NEW: Added interactive charts to keyword tables - giving you a quick overview of the basic metrics, impressions, clicks and average position.
* NEW: Added tooltips to inline charts for better data visualization.
* FIX: Properly storing the last 5 pages any keyword->link is used "Last Seen On".

= 6.1.17 =
* 2025-04-20
* Introducing inline charts on edit pages to display historical trends for each keyword.
* "Reanalyze" button added on pages to force particular page to reload keyword analysis.
* Fix 404 error detection not working properly. Thank you Thomas.
* Fix error when creating links. Thank you Sigurd.
* Removed dashboard widget loading RSS feed.

= 6.1.16 =
* 2025-04-15
* Fixed error that could show up during activation in debug log regarding missing "id" key. 
* Fixed error with interface sometimes being blocked or not working correctly when activating and importing websites.
* Fixed authentication issue with Google when using local development domains (.local, .dev, etc.).
* Improved return URL handling for OAuth2 authentication flow.
* Added warning for http only sites.
* Fix to the Keyword Highlighting in admin bar. This option would not work with the output-buffer automatic linking pipeline; it should now work correctly.
* Tested with WordPress 6.8

= 6.1.15 =
* 2025-04-07
* Fixed: Improved HTML structure preservation during keyword injection to ensure proper rendering of content.
* Fixed: Resolved issues with double-encoded HTML entities that caused HTML tags to display as text.
* Improved: Enhanced text node replacement logic to maintain the integrity of the original content structure.

= 6.1.14 = 
* 2025-03-29
* Added Export button for each report - download reports in CSV format.
* New: Enhanced keyword matching for automatic links to ensure accurate capitalization, ensuring "SEO" is replaced consistently. Thank you Fabrizio for the suggestion.
* Fix: Corrected the functionality for controlling the repetition of keyword injections within content, ensuring it now operates as intended.
* Removed code that incorrectly inserted links on the plugins page. Thank you Dennis.
* Improved: Keyword injection can now do several keyword replacements in same sentence.
* Improved: Added script and style to exclusion list for keyword injection.
* Fixed bug in the data returned in the "Long Tail Keyword Oppertunities" report. Thank you Kim.
* Adding more details to the individual reports in the admin, including links to help.
* Improved account registration routine, should help fix issue for some users getting a warning to activate their account.
* Increased caching of local files up to 7 days.

= 6.1.13 =
* 2025-03-09
* Enhanced the daily maintenance routine to more effectively clean up log entries, keeping entries up to 14 days old or a maximum of 10,000 entries.
- Enhanced page builder integration:
  - Added beta support for SiteOrigin Page Builder
  - Added beta support for Brizy Builder
  - Added beta support for Themify Builder
  - Beta implementations: Divi, Oxygen, WPBakery, Fusion Builder, Cornerstone, Thrive Architect, and Kadence Blocks.
* Beta: Improving integration with Bricks theme. Thank you Fabricio.

= 6.1.12 =
* 2025-03-05
* Enhanced: The dashboard page now more efficiently displays the core metrics of the past 30 days in clear and simple language for an easy overview.
* Enhanced: The dashboard now accounts for GSC API data delays by calculating the 30-day period with an appropriate offset.
* Improved: Weekly emails now provide a summary of key metrics in clear and straightforward language.
* Enhanced: Auto-link feature in admin edit pages now clearly shows whether links point to the current page or a different page.
* Fix: Action Scheduler library was not loading correctly

= 6.1.11 =
* 2025-03-04
* Improved: The visual look of the dashboard.
* Added: Traffic comparison to the dashboard for the past 30 days vs. past period.
* Added: Confirmation before clicking buttons that reset the database or any settings.
* Added: The weekly email now contains comparison of the past 7 days vs. the previous 7 day period.

= 6.1.10 =
* 2025-03-02
* NEW: BETA: Elementor page builder support. Use the floating button to interact with the keyword data while editing with Elementor.
* Improved: Keyword replacement performance for page builders.

= 6.1.9 =
* 2025-02-25
* Fix: Keyword analysis not running when updating a post.
* Fix: "Create links" button not working.
* Improved: Feed URL to use a CDN for better performance.
* Improved: Report page with improved design and better user experience. Thank you Bhupesh.
* Improved: Elementor support with better content filtering.
* Added: Scheduled actions statisics to see how many jobs are running and how many are pending.

= 6.1.8 =
* 2025-02-08
* Added: Comprehensive page builder support with proper content filtering
* Supported Page Builders: Gutenberg Blocks, Beaver Builder and Elementor.
* Improved: WooCommerce integration with short description support
* Added: Debug logging for troubleshooting
* Fixed: Skip filtering for special content types (buttons, existing links, etc.)

= 6.1.7 =
* 2025-02-05
* Improved: Report loading system now uses a queue to prevent concurrent AJAX requests
* Fixed: WinBox window now properly stays on top of other elements
* Fixed: 404 error report now shows clean URLs without HTML formatting
* Improved: Script versioning now uses file modification time for better cache control

= 6.1.6 =
* 2025-02-04
* Fix issue with floating window not opening for some page builders.
* NEW: Info window much improved, now with search and pagination.
* NEW: Info windows: Columns are responsive and resize to the width of the window.

= 6.1.5 =
* 2025-01-31
* Fixed issue with fatal error warning for some free users.

= 6.1.4 =
* 2025-01-29
* Fix issues with keyword replacements in content:
  - Fixed missing spaces after keyword replacements
  - Improved handling of multi-word keyword phrases
  - Better word boundary detection for keyword matching
  - Fixed issues with multiple keyword replacements in the same text
  - Fix ability to delete multiple automatic links at once
* Removed deprecated "Last Seen" functionality for automatic link creation for better performance
* Fixed PHP 8.1+ compatibility warnings
* Code cleanup and performance improvements

= 6.1.3 =
* 2025-01-27
* Fix issues with keyword replacements in content.
* Improved description of the reports page.
* Improved support for page builders.
* Improved report page loading times.
* Updated 3rd party libraries - Freemius SDK to 2.11.0

= 6.1.1 =
* 2025-01-16
* Removing debug code notifications in JS console and error log.

= 6.1 =
* 2025-01-16
* **Performance Enhancements:**
* Lazy loading for report tables.
* Smart queue system for concurrent report processing.
* Transient caching with tailored expiration times.
* **User Interface Improvements:**
* Interactive tables with search, sorting, and pagination.
* Floating navigation bar for quick access.
* Performance metrics displaying load times and cache status.
* **Data Visualization Enhancements:**
* Improved formatting with clickable URLs and responsive design.
* Clear loading indicators and error handling.
* **Technical Infrastructure Updates:**
* Modular report system with optimized database queries.
* Compliant AJAX handling and robust error management.
* Memory-efficient data processing structures.
* Updated 3rd party libraries.

= 6.0.16 =
* UX improvements
* Fix for when deactivating the plugin, cleanup routines were not running.

= 6.0.15 =
* Fix for cache cleanup method introduced in 6.0.14
* Checking for email recipients before sending status email.
* Added '?fl_builder_ui_iframe' to the list of query parameters to remove from the URL. Thank you Thomas.

= 6.0.14 =
* Cache cleanup more effective
* Fix for Beaver Builder editor query parameters - Thank you Thomas

== Upgrade Notice ==
7.2 — Image metadata tools, Pro 404 summary in weekly email, dashboard fixes, and launch polish.
