<?php

namespace Cleverplugins\SEOBooster;

class ContentProcessing
{

  // Only use this variable for database update tracking
  private static $replaced_keywords_tracking = array();

  // Add this new static property at the class level
  private static $processed_keywords_page = array();

  // Add this new static property at the class level
  private static $page_processing_started = false;


  /**
   * Processes the given content for SEO enhancements.
   *
   * This function modifies the content by adding SEO-related links and updates
   * the database with keyword usage information.
   *
   * @since   0.0.1
   * @version 1.0.0 Tuesday, April 22nd, 2025
   * @access  public
   * @param   mixed   $content The content to be processed.
   * @param   bool    $forced  Optional. Whether to force processing. Default false.
   * @return  void
   */
  public static function process_content($content, $forced = false)
  {
    if (is_admin()) {
      return $content;
    }

    // Skip favicon.ico and other asset requests
    if (isset($_SERVER['REQUEST_URI'])) {
      $request_uri = strtolower($_SERVER['REQUEST_URI']);
      $skip_extensions = ['.ico', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.css', '.js', '.webp', '.scss'];

      foreach ($skip_extensions as $ext) {
        if (strpos($request_uri, $ext) !== false) {
          return $content;
        }
      }
    }

    if (empty($content) || !is_string($content)) {
      return $content;
    }
    // Flag to track if we made any replacements
    $made_replacements = false;

    try {
      // Parse the HTML content
      $html = \voku\helper\HtmlDomParser::str_get_html($content);
      if (!$html) {
        return $content;
      }

      // Get autolinks
      $autolinks = Seobooster2::return_autolinks();
      if (empty($autolinks)) {
        return $content;
      }

      // Check if internal linking is enabled globally
      $seobooster_internal_linking = get_option('seobooster_internal_linking', false);
      if (!$seobooster_internal_linking) {
        return $content;
      }

      // Get settings that will be used for replacements
      $replace_limit = intval(get_option('seobooster_replace_kw_limit', 5));
      $replace_kw_multiple = get_option('seobooster_replace_kw_multiple', false);
      $match_capitalization = get_option('seobooster_match_capitalization', false);

      $current_url = Utils::seobooster_currenturl(true);
      $current_url = strtok($current_url, '?');


      // Only reset the keywords array if this is the first processing run for this page load
      // AND if multiple replacements are disabled (we don't need to track keywords when multiple replacements are enabled)
      if (!self::$page_processing_started && !$replace_kw_multiple) {
        self::$processed_keywords_page = array();
        self::$page_processing_started = true;
      } else if (self::$page_processing_started && !$replace_kw_multiple) {
        // Log the currently tracked keywords
        if (!empty(self::$processed_keywords_page)) {
        }
      } 

      $replace_count = 0;

      // Create a DOMDocument for proper HTML manipulation
      $dom = new \DOMDocument('1.0', 'UTF-8');

      // Disable error reporting temporarily to prevent warnings about HTML5 tags
      $previous_error_state = libxml_use_internal_errors(true);

      // Detect character encoding if possible
      $detected_encoding = 'UTF-8'; // Default
      if (function_exists('mb_detect_encoding')) {
        $detected_encoding = mb_detect_encoding($content, 'UTF-8, ISO-8859-1, ISO-8859-15', true) ?: 'UTF-8';
      }

      // Check if content already has DOCTYPE
      $has_doctype = (stripos($content, '<!DOCTYPE') !== false);
      
      // Load the HTML content with proper encoding while preserving the original structure
      if ($has_doctype) {
        // If content already has DOCTYPE, load it directly
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
      } else {
        // If no DOCTYPE, add a minimal one
        $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="' . $detected_encoding . '"></head><body>' . $content . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
      }

      // Create XPath object for searching
      $xpath = new \DOMXPath($dom);

      // Get excluded elements from settings
      $excluded_elements = get_option('seobooster_excluded_elements', array(
        'h1' => 1,
        'h2' => 1,
        'h3' => 1,
        'h4' => 1,
        'h5' => 1,
        'h6' => 1,
        'li' => 1,
        'ul' => 1,
        'ol' => 1,
        'blockquote' => 1,
        'a' => 1,
        'script' => 1,
        'style' => 1
      ));

      // Build XPath query - mandatory elements are always excluded
      $xpath_conditions = array();

      // Always exclude these elements for technical reasons (required)
      $xpath_conditions[] = 'not(ancestor::a)';
      $xpath_conditions[] = 'not(ancestor::script)';
      $xpath_conditions[] = 'not(ancestor::style)';
      $xpath_conditions[] = 'not(ancestor::head)';
      $xpath_conditions[] = 'not(ancestor::title)';

      // Add configurable excluded elements
      foreach (array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'ul', 'ol', 'blockquote') as $element) {
        if (isset($excluded_elements[$element]) && $excluded_elements[$element]) {
          $xpath_conditions[] = 'not(ancestor::' . $element . ')';
        }
      }

      // Combine conditions and construct the XPath query
      $xpath_query = '//text()[' . implode(' and ', $xpath_conditions) . ']';

      // Find all text nodes that are not within excluded elements
      $text_nodes = $xpath->query($xpath_query);

      // Process each text node
      $node_index = 0;
      $keyword_match_attempts = 0;
      $keyword_matches_found = 0;

      // Convert NodeList to array to avoid issues with live NodeList
      $text_nodes_array = [];
      foreach ($text_nodes as $node) {
        $text_nodes_array[] = $node;
      }

      foreach ($text_nodes_array as $text_node) {
        $node_index++;

        if ($replace_count >= $replace_limit) {
          break;
        }

        $text_content = $text_node->nodeValue;

        $matches = array();

        // First pass: collect all possible matches with their positions
        foreach ($autolinks as $found_kw) {
          // Generate a unique hash for this keyword
          $keyword_hash = md5(strtolower($found_kw['kw']));
          $keyword_match_attempts++;

          // Only check the processed keywords array if multiple replacements is DISABLED
          // When multiple replacements is enabled, we don't track processed keywords
          if (!$replace_kw_multiple) {
            // CRITICAL: Check if already processed globally and multiple replacements not allowed
            // This check MUST happen before collecting any matches to ensure consistency
            if (isset(self::$processed_keywords_page[$keyword_hash])) {
              continue; // Skip this keyword entirely
            }
          }
        
          // Check if we're linking to the current page
          if ($current_url === $found_kw['lp']) {
            continue;
          }

          // Prepare pattern based on capitalization setting
          $pattern = $match_capitalization
            ? '/\b(' . preg_quote($found_kw['kw'], '/') . ')\b/'
            : '/\b(' . preg_quote($found_kw['kw'], '/') . ')\b/i';

          // Find all matches in this text node
          if (preg_match_all($pattern, $text_content, $pattern_matches, PREG_OFFSET_CAPTURE)) {
            $keyword_matches_found++;

            // Process the matches
            $match_items = $pattern_matches[0];

            // If multiple replacements is disabled, only process the first match
            // and mark the keyword as processed
            if (!$replace_kw_multiple && count($match_items) > 0) {
              // CRITICAL: Mark this keyword as processed IMMEDIATELY 
              // This prevents the keyword from being processed in other text nodes
              self::$processed_keywords_page[$keyword_hash] = $found_kw['kw'];

              // Only process the first match
              $match = $match_items[0];
              $matches[] = array(
                'keyword' => $found_kw['kw'],
                'url' => $found_kw['lp'],
                'position' => $match[1],
                'length' => strlen($match[0])
              );
              break; // Break after first match if multiple replacements disabled
            } else {
              // Process all matches if multiple replacements enabled
              foreach ($match_items as $match) {
                $matches[] = array(
                  'keyword' => $found_kw['kw'],
                  'url' => $found_kw['lp'],
                  'position' => $match[1],
                  'length' => strlen($match[0])
                );
              }
            }
          }
        }

        // Sort matches by position (descending) to avoid position shifts
        usort($matches, function($a, $b) {
          return $b['position'] - $a['position'];
        });

        // Apply replacements
        foreach ($matches as $match) {
          if ($replace_count >= $replace_limit) {
            break;
          }

          // Create a new text node for the text before the match
          $before_text = substr($text_content, 0, $match['position']);
          $before_node = $dom->createTextNode($before_text);
          
          // Create the link element
          $link = $dom->createElement('a');
          $link->setAttribute('href', esc_url($match['url']));

          // If highlighting the links
          if (isset($_GET['seobooster_showlinks']) && intval($_GET['seobooster_showlinks']) === 1) {
            $link->setAttribute('data-sbfb', '1');
          }
        
          $link->textContent = $match['keyword'];
          
          // Create a new text node for the text after the match
          $after_text = substr($text_content, $match['position'] + $match['length']);
          $after_node = $dom->createTextNode($after_text);
          
          // Replace the original text node with our new nodes
          $parent = $text_node->parentNode;
          if ($parent) {
            // Create a document fragment to hold all our nodes
            $fragment = $dom->createDocumentFragment();
            
            // Add the before text if it exists
            if (!empty($before_text)) {
              $fragment->appendChild($before_node);
            }
            
            // Add the link
            $fragment->appendChild($link);
            
            // Add the after text if it exists
            if (!empty($after_text)) {
              $fragment->appendChild($after_node);
            }
            
            // Replace the original text node with our fragment
            $parent->replaceChild($fragment, $text_node);
          }
          
          $replace_count++;
          $made_replacements = true;

          // Track for database updates
          self::$replaced_keywords_tracking[] = array(
            'keyword' => $match['keyword'],
            'url' => $match['url']
          );
          
          // Break after first replacement if we're not doing multiple replacements
          if (!$replace_kw_multiple) {
            break;
          }
        }
      }

      // Restore error reporting state
      libxml_use_internal_errors($previous_error_state);

      // Save the modified content while preserving the original structure
      $processed_content = $dom->saveHTML();
      
      // Fix double-encoded HTML entities
      $processed_content = html_entity_decode($processed_content, ENT_QUOTES | ENT_HTML5, $detected_encoding);
      
      // If we loaded with a wrapper, extract just the body content
      if (!$has_doctype) {
        // Extract content between <body> and </body>
        if (preg_match('/<body>(.*?)<\/body>/is', $processed_content, $matches)) {
          $processed_content = $matches[1];
        }
      }
      
      // If we made replacements, update the database
      if ($made_replacements && !$forced) {
        // Update lastseen with the current URL
        if (!empty(self::$replaced_keywords_tracking)) {
          // Get current page URL
          if (is_singular()) {
            global $post;
            $current_url = get_permalink($post->ID);
          } else {
            $current_url = home_url(add_query_arg(array(), $GLOBALS['wp']->request));
          }

          // Update the database with last usage info
          Seobooster2::update_keywords_last_usage(self::$replaced_keywords_tracking, $current_url);
        }
      }

      return $processed_content;
    } catch (\Exception $e) {
      Utils::log('Error processing content: ' . $e->getMessage(), 2);

      return $content;
    } finally {
      if (isset($html)) {
        unset($html);
      }
    }
  }

}
