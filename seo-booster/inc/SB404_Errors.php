<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

class SB404_Errors {

  /**
   * Get the list of well-known URLs to ignore for 404 checks in the SEO Booster plugin.
   *
   * @return	mixed
   */
  public static function get_ignored_urls_for_404() {
      $ignored_urls = array(
          '/ads.txt',
          '/app-ads.txt',
          '/sellers.json',
          '/robots.txt',
          '/humans.txt',           // Used to include information about the site’s creators.
          '/favicon.ico',          // The favicon file, often requested by browsers and crawlers.
          '/browserconfig.xml',    // Microsoft uses this file for pinned site configurations.
          '/apple-touch-icon.png', // Used by iOS devices as a touch icon.
          '/crossdomain.xml',      // Flash or Silverlight site policy file.
          '/sitemap.xml',          // Standard XML Sitemap.
          '/sitemap_index.xml',    // WordPress or other CMSs often generate this file.
          '/sitemap.xml.gz',       // Compressed sitemap file.
          '/index.php',            // Default WordPress index file.
          '*.js.map',              // Any URL ending with .js.map for source map files.
          '/manifest.json',        // Web app manifest file.
          '/service-worker.js',    // Service worker file.
          '/.well-known/security.txt', // Security contact information.
          '/.well-known/assetlinks.json', // Android app links.
          '/.well-known/apple-app-site-association', // iOS app links.
          '/.well-known/openid-configuration', // OpenID Connect configuration.
          '/.well-known/change-password', // Change password URL for browsers.
          '/.well-known/dnt-policy.txt', // Do Not Track policy.
          '/.well-known/host-meta', // Host metadata.
          '/.well-known/webfinger', // WebFinger protocol.
          '/.well-known/nodeinfo', // NodeInfo protocol.
          '/.well-known/pgpkey.txt', // PGP key.
          '/.well-known/keybase.txt', // Keybase verification.
          '/.well-known/mta-sts.txt', // MTA-STS policy.
          '/.well-known/robots.txt', // Robots exclusion standard.
      );
  
      // Apply filters to allow modification of the ignored URLs list
      return apply_filters('seo_booster_ignored_urls_for_404', $ignored_urls);
  }
  
}

?>
