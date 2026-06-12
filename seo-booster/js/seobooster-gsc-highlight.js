/* global jQuery, seobooster_gsc_highlight */
/**
 * SEO Booster GSC Keyword Highlighting
 * 
 * @package SEO_Booster
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Configuration
    const CONFIG = {
        highlightClass: 'seo-booster-gsc-highlight',
        highlightActiveClass: 'seo-booster-gsc-highlight-active',
        tooltipClass: 'seo-booster-gsc-tooltip',
        maxKeywords: 500, // Limit to prevent performance issues
        minKeywordLength: 3,
        excludedSelectors: [
            'script', 'style', 'noscript', 'iframe', 'object', 'embed',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', // Exclude headings
            'a', 'code', 'pre', 'kbd', 'samp', 'var', // Exclude existing links and code
            '.seo-booster-gsc-highlight', // Prevent double highlighting
            '[data-sbfb="1"]' // Exclude existing autolinks
        ]
    };

    // State management
    let state = {
        keywords: [],
        isHighlighting: false,
        tooltip: null,
        highlightedElements: []
    };

    /**
     * Initialize GSC keyword highlighting
     */
    function init() {
        
        // Check if highlighting is requested
        if (!isHighlightingRequested()) {
            return;
        }


        // Create tooltip element
        createTooltip();
        
        // Fetch and highlight keywords
        fetchAndHighlightKeywords();
        
        // Add event listeners
        addEventListeners();
    }

    /**
     * Check if GSC highlighting is requested via URL parameter
     */
    function isHighlightingRequested() {
        const urlParams = new URLSearchParams(window.location.search);
        const isRequested = urlParams.get('seobooster_showgsc') === '1';

        return isRequested;
    }

    /**
     * Create tooltip element for keyword information
     */
    function createTooltip() {
        state.tooltip = $('<div>', {
            class: CONFIG.tooltipClass,
            css: {
                position: 'fixed',
                display: 'none',
                background: 'rgba(0, 0, 0, 0.9)',
                color: '#fff',
                padding: '8px 12px',
                borderRadius: '4px',
                fontSize: '12px',
                zIndex: 999999,
                pointerEvents: 'none',
                maxWidth: '300px',
                boxShadow: '0 2px 8px rgba(0, 0, 0, 0.3)'
            }
        }).appendTo('body');
    }

    /**
     * Fetch GSC keywords from server and highlight them
     */
    function fetchAndHighlightKeywords() {

        const nonce = (seobooster_gsc_highlight && seobooster_gsc_highlight.nonce) || '';
        const ajaxUrl = (seobooster_gsc_highlight && seobooster_gsc_highlight.ajax_url) || '/wp-admin/admin-ajax.php';
        const permalink = (seobooster_gsc_highlight && seobooster_gsc_highlight.current_permalink) || window.location.href;
        
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'sb_get_gsc_keywords_for_highlighting',
                _wpnonce: nonce,
                page_url: permalink
            },
            success: function(response) {
                
                if (response.success && response.data && response.data.keywords) {
                    state.keywords = response.data.keywords.slice(0, CONFIG.maxKeywords);
                    highlightKeywords();
                    state.isHighlighting = true;
                } 
            },
            error: function(xhr, status, error) {
            }
        });
    }

    /**
     * Highlight keywords in the page content
     */
    function highlightKeywords() {
   

        // Sort keywords by length (longest first) to handle overlaps properly
        const sortedKeywords = [...state.keywords].sort((a, b) => {
            const keywordA = a.query || a.keyword || '';
            const keywordB = b.query || b.keyword || '';
            return keywordB.length - keywordA.length;
        });
        
        // Process each text node in the document
        let processedNodes = 0;
        walkTextNodes(document.body, function(textNode) {
            if (shouldProcessNode(textNode)) {
                const highlightsApplied = highlightKeywordsInTextNode(textNode, sortedKeywords);
                if (highlightsApplied) {
                    processedNodes++;
                }
            }
        });
        
        
        // If we didn't find many highlights, try a more aggressive approach
        if (state.highlightedElements.length < sortedKeywords.length * 0.3) { // Less than 30% of keywords highlighted
            highlightKeywordsAggressively(sortedKeywords);
        }
    }

    /**
     * Walk through all text nodes in the DOM
     */
    function walkTextNodes(node, callback) {
        if (node.nodeType === Node.TEXT_NODE) {
            callback(node);
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            // Skip excluded elements
            if (isExcludedElement(node)) {
                return;
            }
            
            // Process child nodes
            const children = Array.from(node.childNodes);
            children.forEach(child => walkTextNodes(child, callback));
        }
    }

    /**
     * Check if an element should be excluded from highlighting
     */
    function isExcludedElement(element) {
        if (!element) {
            return false;
        }
        
        const tagName = element.tagName.toLowerCase();
        const className = element.className || '';
        
        // Convert className to string if it's a DOMTokenList
        const classNameString = typeof className === 'string' ? className : className.toString();
        

        
        // Check tag name exclusions
        if (CONFIG.excludedSelectors.includes(tagName)) {
            return true;
        }
        
        // Check class exclusions
        for (const selector of CONFIG.excludedSelectors) {
            if (selector.startsWith('.') && classNameString.includes(selector.substring(1))) {
                return true;
            }
        }
        
        // Check if element already has highlighting
        if (element.classList.contains(CONFIG.highlightClass)) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if a text node should be processed
     */
    function shouldProcessNode(textNode) {
        const text = textNode.textContent.trim();
        
        // Skip empty or very short text
        if (text.length < CONFIG.minKeywordLength) {
            return false;
        }
        
        // Skip if parent is excluded
        if (textNode.parentElement && isExcludedElement(textNode.parentElement)) {
            return false;
        }
        
        // Skip if parent already contains highlighted elements (but allow if it's the same text node)
        if (textNode.parentElement && textNode.parentElement.querySelector('.' + CONFIG.highlightClass)) {
            // Check if the highlighted elements are from this same text node
            const highlightedInParent = textNode.parentElement.querySelectorAll('.' + CONFIG.highlightClass);
            let hasOtherHighlights = false;
            highlightedInParent.forEach(el => {
                if (el.textContent !== text) {
                    hasOtherHighlights = true;
                }
            });
            if (hasOtherHighlights) {
                return false;
            }
        }
        
        // Skip if this text node is inside an already highlighted element
        let parent = textNode.parentElement;
        while (parent) {
            if (parent.classList && parent.classList.contains(CONFIG.highlightClass)) {
                return false;
            }
            parent = parent.parentElement;
        }
        
        return true;
    }

    /**
     * Highlight keywords in a specific text node
     */
    function highlightKeywordsInTextNode(textNode, keywords) {
        const text = textNode.textContent;
  
        
        let modifiedText = text;
        let offset = 0;
        const highlights = [];
        
        // Find all keyword matches in this text node
        for (const keywordData of keywords) {
            // Handle both 'query' and 'keyword' properties from PHP response
            const keyword = keywordData.query || keywordData.keyword;
            if (!keyword) {
                continue;
            }
            

            // Try multiple regex patterns to catch all variations
            const regexPatterns = [
                // 1. Exact match with word boundaries (most specific)
                new RegExp('\\b' + escapeRegExp(keyword) + '\\b', 'gi'),
                // 2. Exact match without word boundaries (for phrases that might be part of longer text)
                new RegExp(escapeRegExp(keyword), 'gi'),
                // 3. Match with optional quotes around the keyword
                new RegExp('["\']?' + escapeRegExp(keyword) + '["\']?', 'gi'),
                // 4. Match with quotes and optional spaces
                new RegExp('["\']\\s*' + escapeRegExp(keyword) + '\\s*["\']', 'gi'),
                // 5. Case-insensitive match with word boundaries
                new RegExp('\\b' + escapeRegExp(keyword.toLowerCase()) + '\\b', 'gi'),
                // 6. Case-insensitive match without word boundaries
                new RegExp(escapeRegExp(keyword.toLowerCase()), 'gi'),
                // 7. Match with various quote styles
                new RegExp('["\']' + escapeRegExp(keyword) + '["\']', 'gi'),
                // 8. Match with smart quotes
                new RegExp('["\u201C\u201D]' + escapeRegExp(keyword) + '["\u201C\u201D]', 'gi')
            ];
            
            // Add space/hyphen equivalence patterns if keyword contains spaces
            if (keyword.includes(' ')) {
                const hyphenatedKeyword = keyword.replace(/\s+/g, '-');
                const spaceHyphenPatterns = [
                    // Hyphenated version with word boundaries
                    new RegExp('\\b' + escapeRegExp(hyphenatedKeyword) + '\\b', 'gi'),
                    // Hyphenated version without word boundaries
                    new RegExp(escapeRegExp(hyphenatedKeyword), 'gi'),
                    // Hyphenated version with quotes
                    new RegExp('["\']?' + escapeRegExp(hyphenatedKeyword) + '["\']?', 'gi')
                ];
                regexPatterns.push(...spaceHyphenPatterns);
 
            }
            
            // Add space/hyphen equivalence patterns if keyword contains hyphens
            if (keyword.includes('-')) {
                const spacedKeyword = keyword.replace(/-+/g, ' ');
                const hyphenSpacePatterns = [
                    // Spaced version with word boundaries
                    new RegExp('\\b' + escapeRegExp(spacedKeyword) + '\\b', 'gi'),
                    // Spaced version without word boundaries
                    new RegExp(escapeRegExp(spacedKeyword), 'gi'),
                    // Spaced version with quotes
                    new RegExp('["\']?' + escapeRegExp(spacedKeyword) + '["\']?', 'gi')
                ];
                regexPatterns.push(...hyphenSpacePatterns);
  
            }
            
            let foundMatches = false;
            let totalMatchesFound = 0;
            
            for (let i = 0; i < regexPatterns.length; i++) {
                const regex = regexPatterns[i];
                let match;
                
      
                
                while ((match = regex.exec(text)) !== null) {
                    totalMatchesFound++;
                    const start = match.index;
                    const end = start + match[0].length;
                    
                    // Clean the matched text (remove quotes if present)
                    const cleanMatchText = match[0].replace(/^["']|["']$/g, '');
      
                    // Check if this match overlaps with existing highlights
                    const overlaps = highlights.some(h => {
                        // Check for true overlaps (one keyword completely contains or is contained within another)
                        const thisContainsOther = start <= h.start && end >= h.end;
                        const otherContainsThis = h.start <= start && h.end >= end;
                        
                        // Check for partial overlaps (keywords share some characters)
                        const partialOverlap = (start < h.end && end > h.start);
                        
                        // Allow adjacent keywords (touching but not overlapping)
                        const isAdjacent = (start === h.end || end === h.start);
                        
                        // Only consider it an overlap if there's actual character overlap, not just adjacency
                        return thisContainsOther || otherContainsThis || partialOverlap;
                    });
                    
                    if (!overlaps) {
                        foundMatches = true;
                        highlights.push({
                            start: start,
                            end: end,
                            keyword: keyword,
                            keywordData: keywordData,
                            matchText: cleanMatchText
                        });
       
                    } else {

                    }
                }
            }
            

        }
        
        
        // Sort highlights by start position
        highlights.sort((a, b) => a.start - b.start);
        
        // Apply highlights
        if (highlights.length > 0) {
            const fragment = document.createDocumentFragment();
            let lastIndex = 0;
            
            highlights.forEach(highlight => {
                // Add text before highlight
                if (highlight.start > lastIndex) {
                    const beforeText = text.substring(lastIndex, highlight.start);
                    if (beforeText.length > 0) {
                        fragment.appendChild(document.createTextNode(beforeText));
                    }
                }
                
                // Create highlight element
                const highlightElement = document.createElement('span');
                highlightElement.className = CONFIG.highlightClass;
                highlightElement.setAttribute('data-keyword-id', highlight.keywordData.id || '');
                highlightElement.setAttribute('data-keyword', highlight.keyword);
                highlightElement.setAttribute('data-impressions', highlight.keywordData.total_impressions || highlight.keywordData.impressions || '0');
                highlightElement.setAttribute('data-clicks', highlight.keywordData.total_clicks || highlight.keywordData.clicks || '0');
                highlightElement.setAttribute('data-position', highlight.keywordData.avg_position || highlight.keywordData.position || '0');
                highlightElement.setAttribute('data-score', highlight.keywordData.score || '0');
                highlightElement.textContent = highlight.matchText;
                
                // Ensure the element is properly created
                if (highlightElement.textContent === highlight.matchText) {
                    fragment.appendChild(highlightElement);
                    state.highlightedElements.push(highlightElement);
                }
                
                lastIndex = highlight.end;
            });
            
            // Add remaining text
            if (lastIndex < text.length) {
                const remainingText = text.substring(lastIndex);
                if (remainingText.length > 0) {
                    fragment.appendChild(document.createTextNode(remainingText));
                }
            }
            
            // Only replace if we have a valid fragment
            if (fragment.childNodes.length > 0) {
                try {
                    textNode.parentNode.replaceChild(fragment, textNode);
                    return true;
                } catch (error) {
                }
            } else {
            }
        }
        return false;
    }

    /**
     * Escape special regex characters
     */
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Normalize text for keyword matching (convert hyphens to spaces)
     */
    function normalizeTextForMatching(text) {
        return text.replace(/-+/g, ' ');
    }

    /**
     * Check if two keywords are equivalent (considering space/hyphen equivalence)
     */
    function areKeywordsEquivalent(keyword1, keyword2) {
        const normalized1 = normalizeTextForMatching(keyword1.toLowerCase());
        const normalized2 = normalizeTextForMatching(keyword2.toLowerCase());
        return normalized1 === normalized2;
    }

    /**
     * Add event listeners for tooltip and interactions
     */
    function addEventListeners() {
        // Mouse events for tooltip
        $(document).on('mouseenter', '.' + CONFIG.highlightClass, function(e) {
            showTooltip(e, this);
        });
        
        $(document).on('mouseleave', '.' + CONFIG.highlightClass, function(e) {
            hideTooltip();
        });
        
        // Click to toggle active state
        $(document).on('click', '.' + CONFIG.highlightClass, function(e) {
            e.preventDefault();
            toggleActiveState(this);
        });
        
        // Keyboard navigation
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                hideTooltip();
                removeAllActiveStates();
            }
        });
    }

    /**
     * Show tooltip with keyword information
     */
    function showTooltip(event, element) {
        const keyword = element.getAttribute('data-keyword');
        const impressions = element.getAttribute('data-impressions');
        const clicks = element.getAttribute('data-clicks');
        const position = element.getAttribute('data-position');
        const score = element.getAttribute('data-score');
        
        const tooltipContent = `
            <strong>${escapeHtml(keyword)}</strong><br>
            Impressions: ${impressions}<br>
            Clicks: ${clicks}<br>
            Position: ${position}<br>
            Score: ${score}
        `;
        
        state.tooltip.html(tooltipContent);
        state.tooltip.show();
        
        // Position tooltip
        const rect = element.getBoundingClientRect();
        const tooltipRect = state.tooltip[0].getBoundingClientRect();
        
        let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        let top = rect.top - tooltipRect.height - 10;
        
        // Adjust if tooltip goes off screen
        if (left < 10) left = 10;
        if (left + tooltipRect.width > window.innerWidth - 10) {
            left = window.innerWidth - tooltipRect.width - 10;
        }
        if (top < 10) {
            top = rect.bottom + 10;
        }
        
        state.tooltip.css({
            left: left + 'px',
            top: top + 'px'
        });
    }

    /**
     * Hide tooltip
     */
    function hideTooltip() {
        state.tooltip.hide();
    }

    /**
     * Toggle active state of a highlighted keyword
     */
    function toggleActiveState(element) {
        const isActive = element.classList.contains(CONFIG.highlightActiveClass);
        
        if (isActive) {
            element.classList.remove(CONFIG.highlightActiveClass);
        } else {
            // Remove active state from all other elements
            removeAllActiveStates();
            // Add active state to current element
            element.classList.add(CONFIG.highlightActiveClass);
        }
    }

    /**
     * Remove active state from all highlighted elements
     */
    function removeAllActiveStates() {
        $('.' + CONFIG.highlightActiveClass).removeClass(CONFIG.highlightActiveClass);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Remove all highlighting
     */
    function removeHighlighting() {
        $('.' + CONFIG.highlightClass).each(function() {
            const textContent = this.textContent;
            const textNode = document.createTextNode(textContent);
            this.parentNode.replaceChild(textNode, this);
        });
        
        state.highlightedElements = [];
        state.isHighlighting = false;
    }

    /**
     * Aggressive keyword highlighting for complex content
     */
    function highlightKeywordsAggressively(keywords) {
        
        // Get all content areas that might contain keywords
        const contentSelectors = [
            '.entry-content',
            '.post-content', 
            '.content',
            'article',
            'main',
            'p',
            'div'
        ];
        
        let totalHighlights = 0;
        
        contentSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                // Skip if element already has highlights
                if (element.querySelector('.' + CONFIG.highlightClass)) {
                    return;
                }
                
                // Skip if element is excluded
                if (isExcludedElement(element)) {
                    return;
                }
                
                const elementText = element.textContent;
                if (elementText.length < 10) return; // Skip very short elements
                
                // Check if this element contains any of our keywords
                let hasKeywords = false;
                keywords.forEach(keywordData => {
                    const keyword = keywordData.query || keywordData.keyword;
                    if (keyword && elementText.toLowerCase().includes(keyword.toLowerCase())) {
                        hasKeywords = true;
                    }
                });
                
                if (hasKeywords) {
  
                    
                    // Process all text nodes in this element
                    walkTextNodes(element, function(textNode) {
                        if (shouldProcessNode(textNode)) {
                            const highlightsApplied = highlightKeywordsInTextNode(textNode, keywords);
                            if (highlightsApplied) {
                                totalHighlights++;
                            }
                        }
                    });
                }
            });
        });

    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        init();
    });

    // Expose functions for external use
    window.SEOBoosterGSCHighlight = {
        init: init,
        removeHighlighting: removeHighlighting,
        isHighlighting: () => state.isHighlighting
    };

})(jQuery); 