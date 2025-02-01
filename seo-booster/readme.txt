=== SEO Booster ===
Contributors: cleverplugins, lkoudal, freemius
Donate link: https://cleverplugins.com
Tags: SEO, google, Google Search Console, GSC
Requires at least: 5.2
Requires PHP: 7.4
Tested up to: 6.7
Stable tag: 6.1.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Discover new keywords, create automatic internal links, monitor 404 errors, and track incoming links. Not your usual SEO plugin.

== Description ==

Create internal links easily everywhere by entering a keyword or phrase and where to link to.

This allows you to enter a keyword you wish to rank for on an individual page. All other pages and posts that have that keyword in the content will convert that keyword in to a link to the page you want to rank for.

== Automatic Links =
Automatically make keywords in your text link to the right pages on your website. Easy way to create internal links on your website - all places you write "contact us" you can link to your contact page automatically.

Works great with WooCommerce products also.

== Installation ==

= Installing from WordPress =
1. Visit 'Plugins > Add New'
2. Search for 'SEO Booster'
3. Activate SEO Booster from your Plugins page.

= Installing Manually =
1. Upload the `seo-booster` folder to the `/wp-content/plugins/` directory
2. Activate the SEO Booster plugin through the 'Plugins' menu in WordPress

## Disclaimer

This plugin is not affiliated with, endorsed, or sponsored by Google. Google Search Console, Google APIs, and any related trademarks are the property of Google LLC. All product and company names are trademarks™ or registered® trademarks of their respective holders. Use of them does not imply any affiliation with or endorsement by them.

== Frequently Asked Questions ==

= Is this a replacement for WordPress SEO by Yoast or the All in One SEO Pack by Michael Torbert?

No! These plugins are great and I use and recommend using those to everyone that works with WordPress and who are serious about optimizing their SEO.

This plugin does not do a single thing they do, SEO Booster does a lot of things that other SEO tools dont do.

SEO Booster was born in 2008 from another need. The original version was premium and was focused on gathering keyword data and rankings from Google. Back then a lot more information was given by Google about what keywords visitors used to find your website, but often even the actual position on the search result page - SERP for short.

It has been years since Google stopped doing that, and SEO Booster evolved to monitoring vistors from 400+ different sources - giving much more details about how your content is found by people searching online.

Most websites will get over 90% of their search engine traffic from Google, but almost no keyword data.

By listening to many other sources you will get a much better picture of your keyword traffic.

You can use the keywords module to dive in to the keyword data - fast using AJAX so you can navigate without waiting for page to reload.

Over the years SEO Booster grew up and went through many different variations and new features.


= Other questions =

Please contact us at [cleverplugins.com/contact/](https://cleverplugins.com/contact/)

== Screenshots ==

1. Integration with Google Search Console

== Changelog ==

= 6.1.5 =
* Fixed issue with fatal error warning for some free users.


= 6.1.4 =
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
* Fix issues with keyword replacements in content.
* Improved description of the reports page.
* Improved support for page builders.
* Improved report page loading times.
* Updated 3rd party libraries - Freemius SDK to 2.11.0

= 6.1.2 =
* BETA: Added comprehensive page builder support for:
Gutenberg Block Editor, Elementor, Beaver Builder, Divi Builder, WPBakery Page Builder, Oxygen Builder, Bricks Builder and Piotnet Builder.

= 6.1.1 =
* Removing debug code notifications in JS console and error log.

= 6.1 =
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
6.0.1 Recommended update for a completely new experience!
