/* global jQuery */
/**
 * SEO Examples Display Module
 * Handles rendering of examples for various SEO issues with expand/collapse functionality
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */

(function() {
    'use strict';

    // Create or extend the SB_SEO_ExamplesDisplay namespace
    if (typeof window.SB_SEO_ExamplesDisplay === 'undefined') {
        window.SB_SEO_ExamplesDisplay = {};
    }

    /**
     * Render examples list with expand/collapse functionality
     *
     * @since 6.1.26
     * @param {Object} config Configuration object
     * @param {string} config.title Label for the examples list
     * @param {Array} config.items Array of example items
     * @param {Function} config.renderItem Function to render a single item
     * @param {number} config.maxVisible Maximum visible items (default: 5)
     * @param {number} config.maxTotal Maximum total items (default: 50)
     * @returns {string} HTML string for the examples section
     */
    window.SB_SEO_ExamplesDisplay.renderExamples = function(config) {
        if (!config || !config.items || !Array.isArray(config.items) || config.items.length === 0) {
            return '';
        }

        var items = config.items;
        var maxVisible = config.maxVisible || 5;
        var maxTotal = config.maxTotal || 50;
        var title = config.title || 'Examples';
        var renderItem = config.renderItem || function(item) { return '<li>' + JSON.stringify(item) + '</li>'; };

        // Limit items to maxTotal
        if (items.length > maxTotal) {
            items = items.slice(0, maxTotal);
        }

        var html = '<div class="sb-examples-list">';
        html += '<strong>' + window.SB_SEO_ExamplesDisplay.escapeHtml(title) + ':</strong>';
        html += '<ul>';

        // Render all items, hiding those beyond maxVisible
        items.forEach(function(item, index) {
            var itemHtml = renderItem(item);
            if (index >= maxVisible) {
                html += '<li class="sb-examples-hidden" style="display: none;">';
            } else {
                html += '<li>';
            }
            html += itemHtml;
            html += '</li>';
        });

        html += '</ul>';

        // Add toggle button if there are more than maxVisible items
        if (items.length > maxVisible) {
            var toggleId = 'sb-examples-toggle-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            html += '<button type="button" class="sb-examples-toggle" data-toggle-id="' + toggleId + '" data-expanded="false">';
            html += 'Click to see all (' + items.length + ' total)';
            html += '</button>';
        }

        html += '</div>';

        return html;
    };

    /**
     * Render broken image example
     *
     * @since 6.1.26
     * @param {Object} item Broken image item with url, error, line, and context
     * @returns {string} HTML string for the item content (without <li> tags)
     */
    window.SB_SEO_ExamplesDisplay.renderBrokenImage = function(item) {
        var html = '';
        var url = (item.url || '').toString();
        var error = (item.error || '').toString();
        var line = item.line;
        var context = item.context;
        
        // Line number
        if (line) {
            html += '<span class="sb-line-number">Line ' + line + ':</span> ';
        }
        
        if (url) {
            var escapedUrl = window.SB_SEO_ExamplesDisplay.escapeHtml(url);
            html += '<a href="' + escapedUrl + '" target="_blank" rel="noopener">' + escapedUrl + '</a>';
        }
        
        if (error) {
            var escapedError = window.SB_SEO_ExamplesDisplay.escapeHtml(error);
            html += ' <em>(' + escapedError + ')</em>';
        }
        
        // Context snippet
        if (context) {
            var escapedContext = window.SB_SEO_ExamplesDisplay.escapeHtml(context);
            html += '<div class="sb-context-snippet"><code>' + escapedContext + '</code></div>';
        }
        
        return html;
    };

    /**
     * Render image without dimensions example
     *
     * @since 6.1.26
     * @param {Object} item Image item with html, url, line, and context
     * @returns {string} HTML string for the item content (without <li> tags)
     */
    window.SB_SEO_ExamplesDisplay.renderImageWithoutDimensions = function(item) {
        var html = '';
        var line = item.line;
        var context = item.context;
        
        // Line number
        if (line) {
            html += '<span class="sb-line-number">Line ' + line + ':</span> ';
        }
        
        if (item.html) {
            var escapedHtml = window.SB_SEO_ExamplesDisplay.escapeHtml(item.html);
            html += '<code class="sb-image-html">' + escapedHtml + '</code>';
        }
        
        if (item.url) {
            var escapedUrl = window.SB_SEO_ExamplesDisplay.escapeHtml(item.url);
            html += ' <a href="' + escapedUrl + '" target="_blank" rel="noopener">' + escapedUrl + '</a>';
        }
        
        // Context snippet
        if (context) {
            var escapedContext = window.SB_SEO_ExamplesDisplay.escapeHtml(context);
            html += '<div class="sb-context-snippet"><code>' + escapedContext + '</code></div>';
        }
        
        return html;
    };

    /**
     * Render image without alt text example
     *
     * @since 6.1.26
     * @param {Object} item Image item with html, url, line, and context
     * @returns {string} HTML string for the item content (without <li> tags)
     */
    window.SB_SEO_ExamplesDisplay.renderImageWithoutAltText = function(item) {
        var html = '';
        var line = item.line;
        var context = item.context;
        
        // Line number
        if (line) {
            html += '<span class="sb-line-number">Line ' + line + ':</span> ';
        }
        
        if (item.html) {
            var escapedHtml = window.SB_SEO_ExamplesDisplay.escapeHtml(item.html);
            html += '<code class="sb-image-html">' + escapedHtml + '</code>';
        }
        
        if (item.url) {
            var escapedUrl = window.SB_SEO_ExamplesDisplay.escapeHtml(item.url);
            html += ' <a href="' + escapedUrl + '" target="_blank" rel="noopener">' + escapedUrl + '</a>';
        }
        
        // Context snippet
        if (context) {
            var escapedContext = window.SB_SEO_ExamplesDisplay.escapeHtml(context);
            html += '<div class="sb-context-snippet"><code>' + escapedContext + '</code></div>';
        }
        
        return html;
    };

    /**
     * Render external image example
     *
     * @since 6.1.26
     * @param {Object} item Image item with html, url, line, and context
     * @returns {string} HTML string for the item content (without <li> tags)
     */
    window.SB_SEO_ExamplesDisplay.renderExternalImage = function(item) {
        var html = '';
        var line = item.line;
        var context = item.context;
        
        // Line number
        if (line) {
            html += '<span class="sb-line-number">Line ' + line + ':</span> ';
        }
        
        if (item.html) {
            var escapedHtml = window.SB_SEO_ExamplesDisplay.escapeHtml(item.html);
            html += '<code class="sb-image-html">' + escapedHtml + '</code>';
        }
        
        if (item.url) {
            var escapedUrl = window.SB_SEO_ExamplesDisplay.escapeHtml(item.url);
            html += ' <a href="' + escapedUrl + '" target="_blank" rel="noopener">' + escapedUrl + '</a>';
        }
        
        // Context snippet
        if (context) {
            var escapedContext = window.SB_SEO_ExamplesDisplay.escapeHtml(context);
            html += '<div class="sb-context-snippet"><code>' + escapedContext + '</code></div>';
        }
        
        return html;
    };

    /**
     * Render long paragraph example
     *
     * @since 6.1.26
     * @param {Object} item Paragraph item with preview, word_count, line, and context
     * @returns {string} HTML string for the item content (without <li> tags)
     */
    window.SB_SEO_ExamplesDisplay.renderLongParagraph = function(item) {
        var html = '';
        var line = item.line;
        var context = item.context;
        
        // Line number
        if (line) {
            html += '<span class="sb-line-number">Line ' + line + ':</span> ';
        }
        
        if (item.preview) {
            var escapedPreview = window.SB_SEO_ExamplesDisplay.escapeHtml(item.preview);
            html += '<span class="sb-paragraph-preview">' + escapedPreview + '</span>';
        }
        
        if (item.word_count) {
            html += ' <em>(' + item.word_count + ' words)</em>';
        }
        
        // Context snippet
        if (context) {
            var escapedContext = window.SB_SEO_ExamplesDisplay.escapeHtml(context);
            html += '<div class="sb-context-snippet"><code>' + escapedContext + '</code></div>';
        }
        
        return html;
    };

    /**
     * Render GSC keyword item with stats
     *
     * @since 7.0.0
     * @param {object} item Keyword object with query, clicks, impressions, position, ctr
     * @returns {string} HTML string
     */
    window.SB_SEO_ExamplesDisplay.renderGSCKeyword = function(item) {
        var html = '';
        var query = item.query || '';
        var clicks = item.clicks || 0;
        var impressions = item.impressions || 0;
        var position = item.position ? Math.round(item.position * 10) / 10 : 'N/A';
        var ctr = item.ctr ? (Math.round(item.ctr * 100) / 100).toFixed(2) + '%' : 'N/A';
        
        if (query) {
            var escapedQuery = window.SB_SEO_ExamplesDisplay.escapeHtml(query);
            html += '<strong>' + escapedQuery + '</strong>';
            html += ' <span style="color: #646970; font-size: 12px;">';
            html += 'Clicks: ' + clicks + ' | ';
            html += 'Impressions: ' + impressions + ' | ';
            html += 'Position: ' + position;
            if (item.ctr !== undefined) {
                html += ' | CTR: ' + ctr;
            }
            html += '</span>';
        }
        return html;
    };

    /**
     * Render GSC content freshness keyword (declining traffic)
     *
     * @since 7.0.0
     * @param {object} item Keyword object with query, decline_percentage, recent/previous impressions
     * @returns {string} HTML string
     */
    window.SB_SEO_ExamplesDisplay.renderGSCContentFreshness = function(item) {
        var html = '';
        var query = item.query || '';
        var decline = item.decline_percentage || 0;
        var recentImpressions = item.recent_impressions || 0;
        var previousImpressions = item.previous_impressions || 0;
        
        if (query) {
            var escapedQuery = window.SB_SEO_ExamplesDisplay.escapeHtml(query);
            html += '<strong>' + escapedQuery + '</strong>';
            html += ' <span style="color: #d63638; font-size: 12px;">';
            html += 'Decline: ' + decline + '%';
            html += ' (' + recentImpressions + ' → ' + previousImpressions + ' impressions)';
            html += '</span>';
        }
        return html;
    };

    /**
     * Escape HTML to prevent XSS
     *
     * @since 6.1.26
     * @param {string} text Text to escape
     * @returns {string} Escaped text
     */
    window.SB_SEO_ExamplesDisplay.escapeHtml = function(text) {
        if (typeof text !== 'string') {
            text = String(text);
        }
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    /**
     * Initialize toggle functionality for examples
     * Should be called after examples are rendered in the DOM
     *
     * @since 6.1.26
     */
    window.SB_SEO_ExamplesDisplay.initToggles = function() {
        jQuery(document).off('click', '.sb-examples-toggle').on('click', '.sb-examples-toggle', function(e) {
            e.preventDefault();
            var $button = jQuery(this);
            var isExpanded = $button.data('expanded') === true;
            var $hiddenItems = $button.closest('.sb-examples-list').find('.sb-examples-hidden');
            var $list = $button.closest('.sb-examples-list').find('ul');
            var totalCount = $list.find('li').length;

            if (isExpanded) {
                // Collapse
                $hiddenItems.slideUp(300);
                $button.data('expanded', false);
                $button.text('Click to see all (' + totalCount + ' total)');
            } else {
                // Expand
                $hiddenItems.slideDown(300);
                $button.data('expanded', true);
                $button.text('Click to see less');
            }
        });
    };

    // Auto-initialize toggles when DOM is ready
    jQuery(document).ready(function() {
        window.SB_SEO_ExamplesDisplay.initToggles();
    });

})();

