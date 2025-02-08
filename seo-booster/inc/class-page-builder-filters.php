<?php

/**
 * Page Builder Filters
 *
 * @package SEOBooster
 */

namespace Cleverplugins\SEOBooster;

/**
 * Class Page_Builder_Filters
 * Handles content filtering for various page builders
 */
class Page_Builder_Filters
{

  /**
   * Get filter priorities
   *
   * @return array Array of filters and their priorities
   */
  private static function get_filter_priority()
  {
    return [
      'the_content'                       => PHP_INT_MAX,
      'the_excerpt'                       => PHP_INT_MAX,
      'term_description'                  => PHP_INT_MAX,
      'render_block'                      => 20,
      'elementor/widget/render_content'   => 15,
      'elementor/frontend/the_content'    => 15,
      'elementor_pro/dynamic_tags/before_render' => 15,
      'elementor/frontend/builder_content_data'  => 15,
      'fl_builder_before_render_content'  => 15,
      'fl_builder_after_render_content'   => 15,
      'fl_builder_render_module_content'  => 15,
      'fl_builder_render_node'            => 15,
      'fl_builder_layout_data'            => 5,
      'fl_builder_post_content'           => 999,
      'et_builder_render_layout'          => 20,
      'oxygen_vsb_after_render_content'   => 20,
      'thrive_architect_template_content' => 15,
      'wpb_content'                       => 15,
      'fusion_component_content'          => 20,
      'cornerstone_content'               => 15,
      'bricks/frontend/render_content'    => 20,
      'woocommerce_short_description'     => 11,
      'kadence_blocks_render_content'     => 15,
    ];
  }

  /**
   * Initialize the filters
   */
  public static function init()
  {
    try {
        $filters = self::get_filter_priority();
        
        foreach ($filters as $filter => $priority) {
            if (!is_string($filter) || !is_numeric($priority)) {
                continue;
            }

            try {
        
                
                switch ($filter) {
                    case 'fl_builder_before_render_content':
                    case 'fl_builder_after_render_content':
                    case 'fl_builder_render_module_content':
                    case 'fl_builder_render_node':
                        add_filter($filter, [__CLASS__, 'filter_beaver_content'], $priority);
                        break;
                    case 'render_block':
                        add_filter($filter, [__CLASS__, 'filter_block_content'], $priority, 2);
                        break;
                    case 'woocommerce_short_description':
                        add_filter($filter, [__CLASS__, 'filter_wc_short_description'], $priority);
                        break;
                    default:
                        $method = self::get_filter_method($filter);
                        if (method_exists(__CLASS__, $method)) {
                            add_filter($filter, [__CLASS__, $method], $priority);
                        } else {
                        }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    } catch (\Exception $e) {
        // error_log('SEO Booster: Critical error in Page Builder Filters init: ' . $e->getMessage());
    }
  }

  /**
   * Get appropriate filter method based on filter name
   *
   * @param string $filter Filter hook name.
   * @return string Method name to handle the filter.
   */
  private static function get_filter_method($filter)
  {
    $method_map = [
      'thrive_architect_template_content' => 'filter_thrive_content',
      'wpb_content' => 'filter_wpbakery_content',
      'fusion_component_content' => 'filter_fusion_content',
      'cornerstone_content' => 'filter_cornerstone_content',
      'bricks/frontend/render_content' => 'filter_bricks_content',
      'kadence_blocks_render_content' => 'filter_kadence_content',
      'render_block' => 'filter_block_content',
      'woocommerce_short_description' => 'filter_wc_short_description',
      'elementor/widget/render_content'   => 'filter_elementor_content',
      'elementor/frontend/the_content'    => 'filter_elementor_content',
      'elementor_pro/dynamic_tags/before_render' => 'filter_elementor_content',
      'elementor/frontend/builder_content_data'  => 'filter_elementor_content',
    ];

    return $method_map[$filter] ?? 'filter_generic_content';
  }

  /**
   * Check if content filtering should be skipped
   *
   * @return boolean True if filtering should be skipped
   */
  private static function should_skip_filtering($content, $context = 'content')
  {
    if ($context === 'beaver') {
        
        // Skip these module types completely
        $skip_modules = [
            'fl-photo-content',
            'fl-map-content',
            'fl-video-content',
            'fl-gallery-content',
            'fl-icon-content',
            'fl-separator-content',
            
            'elementor-button',
            'elementor-heading',
            'elementor-image',
            'elementor-video',
            'elementor-icon',
            'elementor-divider',
            'elementor-spacer',

            'fl-heading-text', // heading text is not processed
            'fl-button-wrap', // button wrap is not processed
            'fl-button', // button is not processed
            'fl-button-text', // button text is not processed
            'fl-html', // html is not processed
        ];
        
        foreach ($skip_modules as $module) {
            if (strpos($content, $module) !== false) {
                return true;
            }
        }

        // Process these modules
        $process_modules = [
            'fl-rich-text',
            'fl-accordion-content',
            'fl-tab-content',
            
            'elementor-text-editor',
            'elementor-tab-content',
            'elementor-accordion-content',
            'elementor-toggle-content',
            
        ];
        
        foreach ($process_modules as $module) {
            if (strpos($content, $module) !== false) {
                return false;
            }
        }
    }
    if ($context === 'elementor') {
        // Skip these Elementor widgets
        $skip_widgets = [
            'elementor-button',
            'elementor-heading',
            'elementor-image',
            'elementor-video',
            'elementor-icon',
            'elementor-divider',
            'elementor-spacer',
        ];
        
        foreach ($skip_widgets as $widget) {
            if (strpos($content, $widget) !== false) {
                return true;
            }
        }

        // Process these widgets
        $process_widgets = [
            'elementor-text-editor',
            'elementor-tab-content',
            'elementor-accordion-content',
            'elementor-toggle-content',
        ];
        
        foreach ($process_widgets as $widget) {
            if (strpos($content, $widget) !== false) {
                return false;
            }
        }
    }
    // Skip empty content
    if (empty($content) || !is_string($content)) {
      return true;
    }
    // Skip if content is just HTML without text
    if (strlen(strip_tags($content)) < 2) {
      return true;
    }
    // Skip content inside existing links
    if (preg_match('/<a[^>]*>.*?<\\/a>/is', $content)) {
      return true;
    }
    // Skip headings
    if (preg_match('/<h[1-6][^>]*>.*?<\\/h[1-6]>/is', $content)) {
      return true;
    }
    return false;
  }


  /**
   * Filter Beaver Builder content
   *
   * @param string $content The content to filter.
   * @return string
   */
  public static function filter_beaver_content($content)
  {
    
    if (self::should_skip_filtering($content, 'beaver')) {
        return $content;
    }
    
    if (method_exists('Cleverplugins\\SEOBooster\\seobooster2', 'reset_processed_keywords')) {
        seobooster2::reset_processed_keywords();
    }
    
    $filtered = seobooster2::do_filter_the_content($content, true);
    
    return $filtered;
  }

  /**
   * Filter Elementor content
   *
   * @param string $content The content to filter.
   * @return string
   */
  public static function filter_elementor_content($content)
  {
    // Skip processing in Elementor editor
    if (isset($_GET['elementor-preview']) || 
        (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) ||
        (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->preview->is_preview_mode())) {
        return $content;
    }

    // Handle array input from builder_content_data filter
    if (is_array($content)) {
        return $content;
    }
    
    // Continue with string content
    if (!is_string($content)) {
        return $content;
    }
        
    if (self::should_skip_filtering($content, 'elementor')) {
        return $content;
    }
    
    if (method_exists('Cleverplugins\\SEOBooster\\seobooster2', 'reset_processed_keywords')) {
        seobooster2::reset_processed_keywords();
    }
    
    $filtered = seobooster2::do_filter_the_content($content, true);
    
    return $filtered;
  }

  /**
   * Filter Divi content
   *
   * @param string $content The content to filter.
   * @return string
   */
  public static function filter_divi_content($content)
  {
    return seobooster2::do_filter_the_content($content);
  }

  /**
   * Filter Oxygen content
   *
   * @param string $content The content to filter.
   * @return string
   */
  public static function filter_oxygen_content($content)
  {
    return seobooster2::do_filter_the_content($content);
  }

  /**
   * Generic content filter for other page builders
   *
   * @param string $content The content to filter.
   * @return string
   */
  public static function filter_generic_content($content)
  {
    return seobooster2::do_filter_the_content($content);
  }

  /**
   * Filter Gutenberg block content
   *
   * @param string $block_content The block content.
   * @param array  $block The full block, including name and attributes.
   * @return string
   */
  public static function filter_block_content($block_content = '', $block = null) {
    // Ensure we have valid input
    if (empty($block_content) || !is_string($block_content)) {
        return $block_content;
    }

    // Handle case where $block is not provided
    if (!is_array($block)) {
        return $block_content;
    }

    if (self::should_skip_filtering($block_content)) {
        return $block_content;
    }

    $skip_blocks = [
        'core/shortcode',
        'core/html',
        'core/code',
        'core/preformatted',
        'core/template-part'
    ];

    if (isset($block['blockName']) && in_array($block['blockName'], $skip_blocks, true)) {
        return $block_content;
    }

    return seobooster2::do_filter_the_content($block_content, true);
  }

  /**
   * Filter WooCommerce short description
   *
   * @param string $short_description The short description content.
   * @return string
   */
  public static function filter_wc_short_description($short_description) {
    if (self::should_skip_filtering($short_description) || empty($short_description)) {
        return $short_description;
    }
    return seobooster2::do_filter_the_content($short_description, true);
  }

  /**
   * Filter Thrive Architect content
   *
   * @param string $content The content to filter.
   * @return string
   */
  public static function filter_thrive_content($content) {
    if (self::should_skip_filtering($content)) {
      return $content;
    }
    return seobooster2::do_filter_the_content($content);
  }

  /**
   * Filter WPBakery content
   *
   * @param string $content The content to filter.
   * @return string
   */
  public static function filter_wpbakery_content($content) {
    if (self::should_skip_filtering($content)) {
      return $content;
    }
    return seobooster2::do_filter_the_content($content);
  }

  /**
   * Filter Fusion Builder content
   *
   * @param string $content The content to filter.
   * @return string
   */
  public static function filter_fusion_content($content) {
    if (self::should_skip_filtering($content)) {
      return $content;
    }
    return seobooster2::do_filter_the_content($content);
  }

  /**
   * Filter Cornerstone content
   *
   * @param string $content The content to filter.
   * @return string
   */
  public static function filter_cornerstone_content($content) {
    if (self::should_skip_filtering($content)) {
      return $content;
    }
    return seobooster2::do_filter_the_content($content);
  }

  /**
   * Filter Bricks Builder content
   *
   * @param string $content The content to filter.
   * @return string
   */
  public static function filter_bricks_content($content) {
    if (self::should_skip_filtering($content)) {
      return $content;
    }
    return seobooster2::do_filter_the_content($content);
  }

  /**
   * Filter Kadence Blocks content
   *
   * @param string $content The content to filter.
   * @return string
   */
  public static function filter_kadence_content($content) {
    if (self::should_skip_filtering($content)) {
      return $content;
    }
    return seobooster2::do_filter_the_content($content);
  }
}
