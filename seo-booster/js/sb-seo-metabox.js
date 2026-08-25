/* global jQuery, sb_seo_metabox:true */

// Download and analyze full page content - defined globally
function downloadAndAnalyzeFullPage() {
    var objectId = jQuery('#sb-seo-item-id').val();
    var objectType = jQuery('#sb-seo-item-type').val();
    var button = jQuery('#sb-download-and-analyze');
    var originalText = button.html();
    var startTime = Date.now();
    var timerInterval;
    
    if (!objectId) {
        return;
    }

    // Start timer
    function updateTimer() {
        var elapsed = Math.floor((Date.now() - startTime) / 1000);
        var timerText = sb_seo_metabox.strings.loading + ' (' + elapsed + 's)';
        jQuery('#sb-analysis-results').html('<div class="sb-loading">' + timerText + '</div>');
    }

    // Show loading state with timer
    button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + sb_seo_metabox.strings.downloading);
    updateTimer();
    timerInterval = setInterval(updateTimer, 1000);
    jQuery('#sb-score-number').text('-');

    // Make AJAX request
    jQuery.ajax({
        url: sb_seo_metabox.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'sb_seo_download_and_analyze',
            object_id: objectId,
            object_type: objectType,
            force_download: true, // Force redownload when button is clicked
            nonce: sb_seo_metabox.nonce
        },
        success: function(response) {
            clearInterval(timerInterval);
            
            // Handle string response (if double-encoded)
            if (typeof response === 'string') {
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    jQuery('#sb-analysis-results').html('<div class="sb-loading">' + sb_seo_metabox.strings.download_failed + '</div>');
                    return;
                }
            }
            
            if (response && response.success && response.data) {
                // Remove content changed / refresh warnings after successful analysis
                jQuery('#sb-content-changed-warning').remove();
                jQuery('#sb-analysis-refresh-warning').remove();
                if (typeof window.updateFullReviewActionsVisibility === 'function') {
                    window.updateFullReviewActionsVisibility('fresh');
                }
                
                // Check if displayAnalysisResults function exists
                if (typeof window.displayAnalysisResults === 'function') {
                    try {
                        window.displayAnalysisResults(response.data);
                    } catch (e) {
                        jQuery('#sb-analysis-results').html('<div class="sb-loading">Error displaying results. Please refresh the page.</div>');
                    }
                } else {
                    // Function not available yet, wait a bit and try again
                    setTimeout(function() {
                        if (typeof window.displayAnalysisResults === 'function') {
                            window.displayAnalysisResults(response.data);
                        } else {
                            jQuery('#sb-analysis-results').html('<div class="sb-loading">Error: Display function not available. Please refresh the page.</div>');
                        }
                    }, 100);
                }
                
                // Update last download info
                if (response.data.last_download) {
                    jQuery('#sb-last-download-info').text(sb_seo_metabox.strings.last_downloaded + ': ' + response.data.last_download).removeClass('sb-hidden');
                }
            } else {
                var errorMsg = (response && response.data && response.data.message) ? response.data.message : sb_seo_metabox.strings.download_failed;
                jQuery('#sb-analysis-results').html('<div class="sb-loading">' + errorMsg + '</div>');
            }
        },
        error: function(xhr, status, error) {
            clearInterval(timerInterval);
            var errorMsg = sb_seo_metabox.strings.download_failed;
            if (xhr.responseText) {
                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.data && errorResponse.data.message) {
                        errorMsg = errorResponse.data.message;
                    }
                } catch (e) {
                    // Not JSON, use default message
                }
            }
            jQuery('#sb-analysis-results').html('<div class="sb-loading">' + errorMsg + '</div>');
        },
        complete: function() {
            // Re-enable analysis button
            button.prop('disabled', false).html(originalText);
        }
    });
}

// Disable button initially (before document.ready)
jQuery('#sb-download-and-analyze').prop('disabled', true);

jQuery(document).ready(function($) {
    'use strict';
    
    // Helper function to detect if we're on an attachment page
    function isAttachmentPage() {
        return $('#sb-attachment-id').length > 0 || $('.sb-seo-attachment-metabox').length > 0;
    }

    // Handle exclusion checkbox change
    function handleExclusionCheckbox() {
        var checkbox = $('#sb-exclude-from-analysis');
        if (checkbox.length === 0) {
            return; // Not a post page or checkbox doesn't exist
        }

        var objectId = $('#sb-seo-item-id').val();
        var isExcluded = checkbox.is(':checked');

        // Update UI immediately
        updateExclusionUI(isExcluded);

        // Save preference via AJAX
        $.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sb_seo_toggle_analysis_exclusion',
                post_id: objectId,
                exclude: isExcluded ? 1 : 0,
                nonce: sb_seo_metabox.nonce
            },
            success: function(response) {
                if (response && response.success && response.data) {
                    // Update exclusion reason if provided
                    var exclusionControl = $('.sb-exclusion-control');
                    var reasonP = exclusionControl.find('p:first-of-type');
                    
                    if (response.data.exclusion_reason && isExcluded) {
                        if (reasonP.length === 0 || !reasonP.hasClass('sb-exclusion-reason')) {
                            exclusionControl.append('<p class="sb-exclusion-reason" style="margin: 8px 0 0 0; font-size: 12px; color: #666; font-style: italic;">' + response.data.exclusion_reason + '</p>');
                        } else {
                            reasonP.text(response.data.exclusion_reason);
                        }
                    } else {
                        exclusionControl.find('.sb-exclusion-reason').remove();
                    }
                }
            },
            error: function() {
                // Revert checkbox on error
                checkbox.prop('checked', !isExcluded);
                updateExclusionUI(!isExcluded);
                window.SBModal.alert('Failed to save exclusion preference. Please try again.', { tone: 'error' });
            }
        });
    }

    // Update UI based on exclusion state
    function updateExclusionUI(isExcluded) {
        var analysisResults = $('#sb-analysis-results');
        var downloadButton = $('#sb-download-and-analyze');
        var analysisActions = $('.sb-seo-analysis-actions');
        var scoreHeader = $('.sb-seo-score-header');

        if (isExcluded) {
            // Hide/disable analysis sections
            analysisResults.html('<div class="sb-loading" style="padding: 20px; text-align: center; color: #666;">' + 
                'This page is excluded from SEO analysis. Uncheck the exclusion checkbox above to enable analysis.' + 
                '</div>');
            downloadButton.prop('disabled', true).addClass('disabled');
            scoreHeader.find('#sb-score-number').text('-');
            scoreHeader.find('#sb-score-circle').removeClass('green yellow red');
        } else {
            // Enable analysis sections
            downloadButton.prop('disabled', false).removeClass('disabled');
            // Reload analysis if available
            if (typeof window.loadSavedAnalysis === 'function') {
                window.loadSavedAnalysis();
            }
        }
    }

    // Initialize exclusion checkbox handler
    $('#sb-exclude-from-analysis').on('change', handleExclusionCheckbox);

    // Check initial exclusion state and update UI
    var initialExcluded = $('#sb-exclude-from-analysis').is(':checked');
    if (initialExcluded) {
        updateExclusionUI(true);
    }
    
    // Define displayAnalysisResults function and make it globally accessible
    window.displayAnalysisResults = function(data) {
        // Validate data
        if (!data || typeof data !== 'object') {
            jQuery('#sb-analysis-results').html('<div class="sb-loading">Error: Invalid data received</div>');
            return;
        }
        
        // Update score - handle null vs 0 correctly (0 is a valid score)
        var score = data.score !== null && data.score !== undefined ? data.score : null;
        
        if (score !== null) {
            // Valid score (including 0)
            jQuery('#sb-score-number').text(score);
            
            // Update score circle color
            var scoreCircle = jQuery('#sb-score-circle');
            scoreCircle.removeClass('green yellow red');
            
            if (score >= 80) {
                scoreCircle.addClass('green');
            } else if (score >= 60) {
                scoreCircle.addClass('yellow');
            } else {
                scoreCircle.addClass('red');
            }
        } else {
            // No score available
            jQuery('#sb-score-number').text('-');
            var scoreCircle = jQuery('#sb-score-circle');
            scoreCircle.removeClass('green yellow red');
        }

        // Display timestamp if available
        if (data.metadata && data.metadata.timestamp) {
            var timestamp = data.metadata.timestamp;
            var date = new Date(timestamp * 1000);
            
            // Calculate relative time
            var now = new Date();
            var diffMs = now - date;
            var diffMins = Math.floor(diffMs / 60000);
            var diffHours = Math.floor(diffMs / 3600000);
            var diffDays = Math.floor(diffMs / 86400000);
            
            var relativeTime = '';
            if (diffMins < 1) {
                relativeTime = 'Just now';
            } else if (diffMins < 60) {
                relativeTime = diffMins + ' minute' + (diffMins > 1 ? 's' : '') + ' ago';
            } else if (diffHours < 24) {
                relativeTime = diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
            } else {
                relativeTime = diffDays + ' day' + (diffDays > 1 ? 's' : '') + ' ago';
            }
            
            // Format absolute timestamp using WordPress date/time format
            var absoluteTime = date.toLocaleString();
            
            // Update or create timestamp display
            var timestampHtml = '<small class="sb-analysis-timestamp">' + relativeTime + ' (' + absoluteTime + ')</small>';
            
            // Add to score loading area
            jQuery('#sb-score-loading').html(timestampHtml).show();
        }

        // Display results
        var html = '';
        
        // Combine Issues and Improvements into single "Possibilities" section
        var allPossibilities = [];
        var totalCount = 0;
        
        // Add issues to possibilities
        if (data.issues && data.issues.length > 0) {
            data.issues.forEach(function(issue) {
                allPossibilities.push({
                    type: 'issue',
                    id: issue.id || 0,
                    key: issue.key,
                    severity: issue.severity,
                    message: issue.message,
                    extra_data: issue.extra_data
                });
                totalCount++;
            });
        }
        
        // Add opportunities/improvements to possibilities (prefer opportunities bucket).
        var suggestionItems = (data.opportunities && data.opportunities.length > 0) ? data.opportunities : (data.improvements || []);
        if (suggestionItems.length > 0) {
            suggestionItems.forEach(function(improvement) {
                allPossibilities.push({
                    type: 'improvement',
                    id: improvement.id || 0,
                    key: improvement.key,
                    severity: improvement.severity || 'opportunity',
                    message: improvement.message,
                    extra_data: improvement.extra_data
                });
                totalCount++;
            });
        }
        
        // Possibilities section - combined issues and improvements, expanded by default
        if (allPossibilities.length > 0) {
            html += '<div class="sb-analysis-section">';
            html += '<h4 class="sb-collapsible-header" data-target="sb-possibilities-list">';
            html += '<span class="dashicons dashicons-arrow-down-alt2 sb-collapse-icon"></span>';
            html += '<span class="sb-possibilities-heading-label">' + sb_seo_metabox.strings.issues + ' (<span class="sb-possibilities-count">' + totalCount + '</span>)</span>';
            html += '</h4>';
            html += '<div class="sb-collapsible-content" id="sb-possibilities-list">';
            
            allPossibilities.forEach(function(item) {
                var issueId = parseInt(item.id, 10) || 0;
                var canTriage = !!(sb_seo_metabox.can_triage && issueId > 0);
                var escapedKey = (item.key || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                var escapedSeverity = (item.severity || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

                html += '<div class="sb-analysis-item" data-issue-id="' + issueId + '" data-key="' + escapedKey + '" data-severity="' + escapedSeverity + '">';
                
                // Choose icon based on severity/type
                var iconClass = 'dashicons-warning';
                if (item.type === 'improvement') {
                    iconClass = 'dashicons-lightbulb';
                } else if (item.severity === 'critical') {
                    iconClass = 'dashicons-dismiss';
                } else if (item.severity === 'high') {
                    iconClass = 'dashicons-warning';
                } else if (item.severity === 'medium') {
                    iconClass = 'dashicons-info';
                } else if (item.severity === 'low' || item.severity === 'opportunity') {
                    iconClass = 'dashicons-lightbulb';
                }
                
                html += '<span class="dashicons ' + iconClass + '"></span>';
                
                // Add GSC / AI Readiness labels for categorized possibilities
                var gscLabel = '';
                if (item.key && item.key.indexOf('gsc_') === 0) {
                    gscLabel = '<span class="sb-gsc-label" style="display: inline-block; background: #4285f4; color: white; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 3px; margin-right: 6px; text-transform: uppercase; letter-spacing: 0.5px;">GSC</span>';
                }
                var aiReadinessLabel = '';
                var aiKeys = (typeof sb_seo_metabox !== 'undefined' && sb_seo_metabox.ai_readiness_keys) ? sb_seo_metabox.ai_readiness_keys : [];
                if (item.key && aiKeys.indexOf(item.key) !== -1) {
                    aiReadinessLabel = '<span class="sb-ai-readiness-label-badge" style="display: inline-block; background: #7c3aed; color: white; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 3px; margin-right: 6px; text-transform: uppercase; letter-spacing: 0.5px;">AI</span>';
                }
                
                // Escape HTML in message to prevent XSS
                var messageText = (item.message || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                html += '<div class="sb-analysis-item-body">';
                html += '<div class="sb-analysis-text">' + gscLabel + aiReadinessLabel + messageText + '</div>';
                
                // Display extra data for various issue types
                if (item.extra_data) {
                    // Broken links (refactored to use examples display module)
                    if (item.extra_data.broken_links && item.extra_data.broken_links.length > 0) {
                        html += '<div class="sb-extra-data">';
                        if (typeof window.SB_SEO_ExamplesDisplay !== 'undefined' && window.SB_SEO_ExamplesDisplay.renderExamples) {
                            html += window.SB_SEO_ExamplesDisplay.renderExamples({
                                title: 'Broken links',
                                items: item.extra_data.broken_links,
                                renderItem: function(link) {
                                    var linkUrl = (link.url || '').toString();
                                    var linkError = link.error ? (link.error.toString()) : '';
                                    var html = '';
                                    if (linkUrl) {
                                        var escapedUrl = window.SB_SEO_ExamplesDisplay.escapeHtml(linkUrl);
                                        html += '<a href="' + escapedUrl + '" target="_blank" rel="noopener">' + escapedUrl + '</a>';
                                    }
                                    if (linkError) {
                                        var escapedError = window.SB_SEO_ExamplesDisplay.escapeHtml(linkError);
                                        html += ' <em>(' + escapedError + ')</em>';
                                    }
                                    return html;
                                },
                                maxVisible: 5,
                                maxTotal: 50
                            });
                        } else {
                            // Fallback to original display if module not available
                            html += '<strong>Broken links:</strong><ul>';
                            item.extra_data.broken_links.forEach(function(link) {
                                var linkUrl = (link.url || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                                var linkError = link.error ? (link.error.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')) : '';
                                html += '<li><a href="' + linkUrl + '" target="_blank" rel="noopener">' + linkUrl + '</a>';
                                if (linkError) {
                                    html += ' <em>(' + linkError + ')</em>';
                                }
                                html += '</li>';
                            });
                            html += '</ul>';
                        }
                        html += '</div>';
                    }
                    
                    // Broken images
                    if (item.extra_data.broken_images && item.extra_data.broken_images.length > 0) {
                        html += '<div class="sb-extra-data">';
                        if (typeof window.SB_SEO_ExamplesDisplay !== 'undefined' && window.SB_SEO_ExamplesDisplay.renderExamples) {
                            html += window.SB_SEO_ExamplesDisplay.renderExamples({
                                title: 'Broken images',
                                items: item.extra_data.broken_images,
                                renderItem: window.SB_SEO_ExamplesDisplay.renderBrokenImage,
                                maxVisible: 5,
                                maxTotal: 50
                            });
                        }
                        html += '</div>';
                    }
                    
                    // External images
                    if (item.extra_data.external_images && item.extra_data.external_images.length > 0) {
                        html += '<div class="sb-extra-data">';
                        if (typeof window.SB_SEO_ExamplesDisplay !== 'undefined' && window.SB_SEO_ExamplesDisplay.renderExamples) {
                            html += window.SB_SEO_ExamplesDisplay.renderExamples({
                                title: 'External images',
                                items: item.extra_data.external_images,
                                renderItem: window.SB_SEO_ExamplesDisplay.renderExternalImage,
                                maxVisible: 5,
                                maxTotal: 50
                            });
                        }
                        html += '</div>';
                    }
                    
                    // Images without dimensions
                    if (item.extra_data.images_without_dimensions && item.extra_data.images_without_dimensions.length > 0) {
                        html += '<div class="sb-extra-data">';
                        if (typeof window.SB_SEO_ExamplesDisplay !== 'undefined' && window.SB_SEO_ExamplesDisplay.renderExamples) {
                            html += window.SB_SEO_ExamplesDisplay.renderExamples({
                                title: 'Images without dimensions',
                                items: item.extra_data.images_without_dimensions,
                                renderItem: window.SB_SEO_ExamplesDisplay.renderImageWithoutDimensions,
                                maxVisible: 5,
                                maxTotal: 50
                            });
                        }
                        html += '</div>';
                    }
                    
                    // Images without alt text
                    if (item.extra_data.images_without_alt && item.extra_data.images_without_alt.length > 0) {
                        html += '<div class="sb-extra-data">';
                        if (typeof window.SB_SEO_ExamplesDisplay !== 'undefined' && window.SB_SEO_ExamplesDisplay.renderExamples) {
                            html += window.SB_SEO_ExamplesDisplay.renderExamples({
                                title: 'Images without alt text',
                                items: item.extra_data.images_without_alt,
                                renderItem: window.SB_SEO_ExamplesDisplay.renderImageWithoutAltText,
                                maxVisible: 5,
                                maxTotal: 50
                            });
                        }
                        html += '</div>';
                    }
                    
                    // Images with empty alt text
                    if (item.extra_data.images_with_empty_alt && item.extra_data.images_with_empty_alt.length > 0) {
                        html += '<div class="sb-extra-data">';
                        if (typeof window.SB_SEO_ExamplesDisplay !== 'undefined' && window.SB_SEO_ExamplesDisplay.renderExamples) {
                            html += window.SB_SEO_ExamplesDisplay.renderExamples({
                                title: 'Images with empty alt text',
                                items: item.extra_data.images_with_empty_alt,
                                renderItem: window.SB_SEO_ExamplesDisplay.renderImageWithEmptyAltText,
                                maxVisible: 5,
                                maxTotal: 50
                            });
                        }
                        html += '</div>';
                    }
                    
                    // Long paragraphs
                    if (item.extra_data.long_paragraphs && item.extra_data.long_paragraphs.length > 0) {
                        html += '<div class="sb-extra-data">';
                        if (typeof window.SB_SEO_ExamplesDisplay !== 'undefined' && window.SB_SEO_ExamplesDisplay.renderExamples) {
                            html += window.SB_SEO_ExamplesDisplay.renderExamples({
                                title: 'Long paragraphs',
                                items: item.extra_data.long_paragraphs,
                                renderItem: window.SB_SEO_ExamplesDisplay.renderLongParagraph,
                                maxVisible: 5,
                                maxTotal: 50
                            });
                        }
                        html += '</div>';
                    }
                    
                    // Redirected links (keep original display for now)
                    if (item.extra_data.redirected_links && item.extra_data.redirected_links.length > 0) {
                        html += '<div class="sb-extra-data">';
                        html += '<strong>Redirected links:</strong><ul>';
                        item.extra_data.redirected_links.forEach(function(link) {
                            html += '<li><a href="' + link.url + '" target="_blank" rel="noopener">' + link.url + '</a>';
                            if (link.redirect_to) {
                                html += ' → <a href="' + link.redirect_to + '" target="_blank" rel="noopener">' + link.redirect_to + '</a>';
                            }
                            if (link.status_code) {
                                html += ' <em>(HTTP ' + link.status_code + ')</em>';
                            }
                            html += '</li>';
                        });
                        html += '</ul></div>';
                    }
                    
                    // GSC keywords (standard format)
                    if (item.extra_data.keywords && Array.isArray(item.extra_data.keywords) && item.extra_data.keywords.length > 0) {
                        html += '<div class="sb-extra-data">';
                        if (typeof window.SB_SEO_ExamplesDisplay !== 'undefined' && window.SB_SEO_ExamplesDisplay.renderExamples) {
                            var title = 'Keywords';
                            // Special handling for content freshness
                            if (item.key === 'gsc_content_freshness') {
                                html += window.SB_SEO_ExamplesDisplay.renderExamples({
                                    title: title,
                                    items: item.extra_data.keywords,
                                    renderItem: window.SB_SEO_ExamplesDisplay.renderGSCContentFreshness,
                                    maxVisible: 5,
                                    maxTotal: 20
                                });
                            } else {
                                html += window.SB_SEO_ExamplesDisplay.renderExamples({
                                    title: title,
                                    items: item.extra_data.keywords,
                                    renderItem: window.SB_SEO_ExamplesDisplay.renderGSCKeyword,
                                    maxVisible: 5,
                                    maxTotal: 20
                                });
                            }
                        }
                        html += '</div>';
                        
                        // Show aggregated stats if available
                        if (item.extra_data.aggregated_stats) {
                            var stats = item.extra_data.aggregated_stats;
                            html += '<div class="sb-extra-data" style="margin-top: 8px; padding: 8px; background: #f0f6fc; border-radius: 3px; font-size: 12px;">';
                            html += '<strong>Summary:</strong> ';
                            var statsParts = [];
                            if (stats.total_clicks !== undefined) statsParts.push('Total Clicks: ' + stats.total_clicks);
                            if (stats.total_impressions !== undefined) statsParts.push('Total Impressions: ' + stats.total_impressions);
                            if (stats.avg_position !== undefined) statsParts.push('Avg Position: ' + Math.round(stats.avg_position * 10) / 10);
                            if (stats.avg_ctr !== undefined) statsParts.push('Avg CTR: ' + (Math.round(stats.avg_ctr * 100) / 100).toFixed(2) + '%');
                            if (stats.keyword_count !== undefined) statsParts.push('Keywords: ' + stats.keyword_count);
                            html += statsParts.join(' | ');
                            html += '</div>';
                        }
                    }
                    
                    // GSC keyword cannibalization (special format)
                    if (item.extra_data.cannibalized_keywords && typeof item.extra_data.cannibalized_keywords === 'object') {
                        html += '<div class="sb-extra-data">';
                        html += '<strong>Cannibalized Keywords:</strong><ul>';
                        var cannibalized = item.extra_data.cannibalized_keywords;
                        var currentPage = item.extra_data.current_page || '';
                        
                        for (var query in cannibalized) {
                            if (cannibalized.hasOwnProperty(query)) {
                                var pages = cannibalized[query];
                                var escapedQuery = (query || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                                html += '<li><span class="sb-item-label">' + escapedQuery + '</span><ul>';
                                pages.forEach(function(pageData) {
                                    var pageUrl = pageData.page || '';
                                    var isCurrent = (pageUrl === currentPage);
                                    var escapedUrl = (pageUrl || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                                    var clicks = pageData.clicks !== undefined && pageData.clicks !== null ? pageData.clicks : 0;
                                    var impressions = pageData.impressions !== undefined && pageData.impressions !== null ? pageData.impressions : 0;
                                    var position = pageData.position !== undefined && pageData.position !== null
                                        ? Math.round(pageData.position * 10) / 10
                                        : 'N/A';
                                    html += '<li>';
                                    html += '<span class="sb-item-label">';
                                    if (isCurrent) {
                                        html += '<strong style="color: #2271b1;">[Current Page]</strong> ';
                                    }
                                    html += '<a href="' + escapedUrl + '" target="_blank" rel="noopener">' + escapedUrl + '</a>';
                                    html += '</span>';
                                    html += '<span class="sb-item-metrics">';
                                    html += 'Clicks: ' + clicks + ' · Impr: ' + impressions + ' · Pos: ' + position;
                                    html += '</span>';
                                    html += '</li>';
                                });
                                html += '</ul></li>';
                            }
                        }
                        html += '</ul></div>';
                    }
                }

                if (canTriage) {
                    var strings = sb_seo_metabox.strings || {};
                    html += '<div class="sb-issue-actions">';
                    html += '<button type="button" class="button button-small sb-mark-done" data-issue-id="' + issueId + '">';
                    html += (strings.mark_as_done || 'Mark as done');
                    html += '</button> ';
                    html += '<button type="button" class="button button-small sb-ignore-issue" data-issue-id="' + issueId + '">';
                    html += (strings.ignore || 'Ignore');
                    html += '</button>';
                    html += '</div>';
                }

                html += '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            if (sb_seo_metabox.can_triage && sb_seo_metabox.possibilities_url) {
                var viewLabel = (sb_seo_metabox.strings && sb_seo_metabox.strings.view_in_possibilities) || 'View in Possibilities';
                var possUrl = String(sb_seo_metabox.possibilities_url).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                html += '<p class="sb-metabox-possibilities-link"><a href="' + possUrl + '">' + viewLabel + '</a></p>';
            }
            html += '</div>';
        }

        // Not applicable checks (skipped) - muted, collapsed by default
        if (data.not_applicable && data.not_applicable.length > 0) {
            html += '<div class="sb-analysis-section sb-not-applicable-section">';
            html += '<h4 class="sb-collapsible-header" data-target="sb-not-applicable-list">';
            html += '<span class="dashicons dashicons-arrow-up-alt2 sb-collapse-icon"></span>';
            html += (sb_seo_metabox.strings.not_applicable || 'Not applicable') + ' (' + data.not_applicable.length + ')';
            html += '</h4>';
            html += '<div class="sb-collapsible-content" id="sb-not-applicable-list" style="display: none;">';
            data.not_applicable.forEach(function(item) {
                html += '<div class="sb-analysis-item">';
                html += '<span class="dashicons dashicons-minus" style="color: #888;"></span>';
                var naMessage = (item.message || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                html += '<div class="sb-analysis-text" style="color:#666;">' + naMessage + '</div>';
                html += '</div>';
            });
            html += '</div>';
            html += '</div>';
        }

        // Passed SEO Checks (Good practices) - collapsed by default
        if (data.good && data.good.length > 0) {
            html += '<div class="sb-analysis-section">';
            html += '<h4 class="sb-collapsible-header" data-target="sb-good-practices-list">';
            html += '<span class="dashicons dashicons-arrow-up-alt2 sb-collapse-icon"></span>';
            html += 'Passed SEO Checks (' + data.good.length + ')';
            html += '</h4>';
            html += '<div class="sb-collapsible-content" id="sb-good-practices-list" style="display: none;">';
            data.good.forEach(function(good) {
                html += '<div class="sb-analysis-item">';
                html += '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>';
                
                // Add GSC label for GSC-based checks
                var gscLabel = '';
                if (good.key && good.key.indexOf('gsc_') === 0) {
                    gscLabel = '<span class="sb-gsc-label" style="display: inline-block; background: #4285f4; color: white; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 3px; margin-right: 6px; text-transform: uppercase; letter-spacing: 0.5px;">GSC</span>';
                }
                
                // Escape HTML in message
                var goodMessage = (good.message || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                html += '<div class="sb-analysis-text">' + gscLabel + goodMessage + '</div>';
                html += '</div>';
            });
            html += '</div>';
            html += '</div>';
        }

        if (html === '') {
            // If we have a score but no issues, show a success message
            if (data.score && data.score > 0) {
                html = '<div class="sb-analysis-section">';
                html += '<div class="sb-analysis-item">';
                html += '<span class="dashicons dashicons-yes-alt"></span>';
                html += '<div class="sb-analysis-text">' + sb_seo_metabox.strings.analysis_complete + '</div>';
                html += '</div>';
                html += '</div>';
            } else {
                html = '<div class="sb-loading">' + sb_seo_metabox.strings.no_analysis_results + '</div>';
            }
        }

        jQuery('#sb-analysis-results').html(html);
        
        // Initialize examples display toggles after HTML is inserted
        if (typeof window.SB_SEO_ExamplesDisplay !== 'undefined' && window.SB_SEO_ExamplesDisplay.initToggles) {
            window.SB_SEO_ExamplesDisplay.initToggles();
        }
    };

    /**
     * Escape text for HTML content.
     *
     * @param {*} text Raw text.
     * @return {string}
     */
    function sbMetaboxEscapeHtml(text) {
        return (text == null ? '' : String(text))
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Update the Possibilities (N) heading count in the metabox.
     *
     * @param {number} delta Change to apply.
     * @return {void}
     */
    function sbMetaboxSyncPossibilitiesCount(delta) {
        var $count = jQuery('#sb-analysis-results .sb-possibilities-count').first();
        if (!$count.length) {
            return;
        }
        var current = parseInt($count.text().replace(/[^\d]/g, ''), 10) || 0;
        $count.text(Math.max(0, current + delta));
    }

    /**
     * Show a short triage error near analysis results.
     *
     * @param {string} message Error message.
     * @return {void}
     */
    function sbMetaboxShowTriageError(message) {
        jQuery('#sb-analysis-results .sb-metabox-triage-error').remove();
        var $notice = jQuery('<div class="notice notice-error inline sb-metabox-triage-error"><p></p></div>');
        $notice.find('p').text(message == null ? '' : String(message));
        jQuery('#sb-analysis-results').prepend($notice);
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                jQuery(this).remove();
            });
        }, 8000);
    }

    /**
     * Soft-remove confirmation row after Undo window expires.
     *
     * @param {jQuery} $confirm Confirmation element.
     * @return {void}
     */
    function sbMetaboxFinalizeStatusRemoval($confirm) {
        $confirm.slideUp(200, function() {
            jQuery(this).remove();
            var remaining = jQuery('#sb-possibilities-list .sb-analysis-item, #sb-possibilities-list .sb-issue-status-confirm').length;
            if (remaining === 0) {
                var $section = jQuery('#sb-possibilities-list').closest('.sb-analysis-section');
                $section.find('.sb-possibilities-count').text('0');
            }
        });
    }

    /**
     * Restore an issue row after Undo.
     *
     * @param {number} issueId Issue ID.
     * @param {jQuery} $confirm Confirmation element.
     * @param {jQuery} $cachedItem Cached original item HTML.
     * @return {void}
     */
    function sbMetaboxUndoIssueStatus(issueId, $confirm, $cachedItem) {
        var strings = sb_seo_metabox.strings || {};
        var $btn = $confirm.find('.sb-undo-issue');
        $btn.prop('disabled', true);

        jQuery.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_update_issue_status',
                nonce: sb_seo_metabox.issues_nonce,
                issue_id: issueId,
                status: 'active'
            },
            success: function(response) {
                if (!response || !response.success) {
                    sbMetaboxShowTriageError((response && response.data && response.data.message) || strings.triage_error || 'Could not update this possibility. Please try again.');
                    $btn.prop('disabled', false);
                    return;
                }
                $confirm.replaceWith($cachedItem);
                sbMetaboxSyncPossibilitiesCount(1);
            },
            error: function() {
                sbMetaboxShowTriageError(strings.triage_error || 'Could not update this possibility. Please try again.');
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * Mark done / Ignore via shared Possibilities AJAX.
     *
     * @param {number} issueId Issue ID.
     * @param {string} status fixed|ignored_permanent.
     * @param {jQuery} $button Clicked button.
     * @return {void}
     */
    function sbMetaboxUpdateIssueStatus(issueId, status, $button) {
        var strings = sb_seo_metabox.strings || {};
        if (!issueId || !sb_seo_metabox.can_triage) {
            return;
        }

        var $item = $button.closest('.sb-analysis-item');
        if (!$item.length) {
            return;
        }

        var $cachedItem = $item.clone(true, true);
        $button.prop('disabled', true);
        $item.find('.sb-mark-done, .sb-ignore-issue').prop('disabled', true);

        jQuery.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_update_issue_status',
                nonce: sb_seo_metabox.issues_nonce,
                issue_id: issueId,
                status: status
            },
            success: function(response) {
                if (!response || !response.success) {
                    sbMetaboxShowTriageError((response && response.data && response.data.message) || strings.triage_error || 'Could not update this possibility. Please try again.');
                    $item.find('.sb-mark-done, .sb-ignore-issue').prop('disabled', false);
                    return;
                }

                var label = status === 'fixed'
                    ? (strings.marked_as_done || 'Marked as done')
                    : (strings.ignored || 'Ignored');
                var $confirm = jQuery(
                    '<div class="sb-issue-status-confirm" data-issue-id="' + issueId + '">' +
                    '<span class="sb-issue-status-confirm__label">' + sbMetaboxEscapeHtml(label) + '</span> ' +
                    '<button type="button" class="button-link sb-undo-issue" data-issue-id="' + issueId + '">' +
                    sbMetaboxEscapeHtml(strings.undo || 'Undo') +
                    '</button></div>'
                );

                $item.replaceWith($confirm);
                sbMetaboxSyncPossibilitiesCount(-1);

                var undoTimer = setTimeout(function() {
                    sbMetaboxFinalizeStatusRemoval($confirm);
                }, 8000);

                $confirm.find('.sb-undo-issue').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    clearTimeout(undoTimer);
                    sbMetaboxUndoIssueStatus(issueId, $confirm, $cachedItem);
                });
            },
            error: function() {
                sbMetaboxShowTriageError(strings.triage_error || 'Could not update this possibility. Please try again.');
                $item.find('.sb-mark-done, .sb-ignore-issue').prop('disabled', false);
            }
        });
    }

    jQuery(document).on('click', '#sb-analysis-results .sb-mark-done', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var issueId = parseInt(jQuery(this).data('issue-id'), 10) || 0;
        sbMetaboxUpdateIssueStatus(issueId, 'fixed', jQuery(this));
    });

    jQuery(document).on('click', '#sb-analysis-results .sb-ignore-issue', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var issueId = parseInt(jQuery(this).data('issue-id'), 10) || 0;
        sbMetaboxUpdateIssueStatus(issueId, 'ignored_permanent', jQuery(this));
    });
    
    // Error and success message functions
    function showError(message) {
        hideMessages();
        var errorDiv = $('<div class="sb-error-message" style="background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 4px; display: flex; align-items: center;"></div>');
        errorDiv.append('<span class="dashicons dashicons-warning" style="margin-right: 8px;"></span>');
        if (message && message.jquery) {
            errorDiv.append($('<span class="sb-message-text"></span>').append(message));
        } else {
            errorDiv.append($('<span class="sb-message-text"></span>').text(message == null ? '' : String(message)));
        }
        errorDiv.append('<button type="button" class="sb-close-message" style="margin-left: auto; background: none; border: none; color: #721c24; cursor: pointer; font-size: 16px;">&times;</button>');
        $('#sb-get-llm-suggestions').after(errorDiv);
        
        // Auto-hide after 10 seconds
        setTimeout(function() {
            errorDiv.fadeOut(300, function() {
                $(this).remove();
            });
        }, 10000);
    }
    
    function showSuccess(message) {
        hideMessages();
        var successDiv = $('<div class="sb-success-message" style="background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border: 1px solid #c3e6cb; border-radius: 4px; display: flex; align-items: center;"></div>');
        successDiv.append('<span class="dashicons dashicons-yes-alt" style="margin-right: 8px;"></span>');
        successDiv.append($('<span class="sb-message-text"></span>').text(message == null ? '' : String(message)));
        successDiv.append('<button type="button" class="sb-close-message" style="margin-left: auto; background: none; border: none; color: #155724; cursor: pointer; font-size: 16px;">&times;</button>');
        $('#sb-get-llm-suggestions').after(successDiv);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            successDiv.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Show notification for image generation (supports Enter/ESC keys)
    function showImageNotification(message, type) {
        type = type || 'success';
        var isError = type === 'error';
        var bgColor = isError ? '#f8d7da' : '#d4edda';
        var textColor = isError ? '#721c24' : '#155724';
        var borderColor = isError ? '#f5c6cb' : '#c3e6cb';
        var icon = isError ? 'dashicons-warning' : 'dashicons-yes-alt';
        
        // Remove any existing notifications
        $('.sb-image-notification').remove();
        
        var notification = $('<div class="sb-image-notification" style="position: fixed; top: 32px; right: 20px; z-index: 100000; background: ' + bgColor + '; color: ' + textColor + '; padding: 12px 16px; border: 1px solid ' + borderColor + '; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); max-width: 400px; display: flex; align-items: center; gap: 10px;"></div>');
        notification.append($('<span class="dashicons ' + icon + '" style="font-size: 20px; width: 20px; height: 20px;"></span>'));
        notification.append($('<span style="flex: 1;"></span>').text(message == null ? '' : String(message)));
        notification.append($('<button type="button" class="sb-close-notification" style="background: none; border: none; color: ' + textColor + '; cursor: pointer; font-size: 18px; line-height: 1; padding: 0; width: 20px; height: 20px;">&times;</button>'));
        
        $('body').append(notification);
        
        // Focus the notification for keyboard access
        notification.attr('tabindex', '-1').focus();
        
        // Close handler
        var closeNotification = function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        };
        
        // Click to close
        notification.find('.sb-close-notification').on('click', closeNotification);
        
        // Keyboard handlers (Enter or ESC to close)
        notification.on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === 'Escape' || e.keyCode === 13 || e.keyCode === 27) {
                e.preventDefault();
                closeNotification();
            }
        });
        
        // Global keyboard handler for ESC
        var escHandler = function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                if ($('.sb-image-notification').length) {
                    e.preventDefault();
                    closeNotification();
                    $(document).off('keydown', escHandler);
                }
            }
        };
        $(document).on('keydown', escHandler);
        
        // Auto-hide after 5 seconds (success) or 10 seconds (error)
        var autoHideDelay = isError ? 10000 : 5000;
        setTimeout(function() {
            closeNotification();
            $(document).off('keydown', escHandler);
        }, autoHideDelay);
    }
    
    function hideMessages() {
        $('.sb-error-message, .sb-success-message').remove();
    }
    
    // Close message handler
    $(document).on('click', '.sb-close-message', function() {
        $(this).parent().fadeOut(300, function() {
            $(this).remove();
        });
    });

    var aiTabHydrated = false;

    /**
     * Load saved AI suggestions + assistant history once, when the AI tools tab is first opened.
     * Avoids AJAX on every editor load when the user never visits the tab.
     */
    function hydrateAiTab() {
        if (aiTabHydrated || isAttachmentPage()) {
            return;
        }
        aiTabHydrated = true;

        loadSavedLLMSuggestions();
        loadAssistantHistory();

        if (sb_seo_metabox.saved_comprehensive_analysis && typeof displayComprehensiveAnalysis === 'function') {
            displayComprehensiveAnalysis(sb_seo_metabox.saved_comprehensive_analysis);
        }
    }

    // Section tabs (Analysis / AI tools / Keywords)
    $('#sb-seo-metabox').on('click', '.sb-seo-tab', function() {
        var $metabox = $('#sb-seo-metabox');
        var $tab = $(this);
        var tab = $tab.data('tab');

        $metabox.find('.sb-seo-tab').removeClass('active').attr('aria-selected', 'false');
        $tab.addClass('active').attr('aria-selected', 'true');

        $metabox.find('.sb-seo-tab-panel').removeClass('active');
        $metabox.find('#sb-seo-' + tab).addClass('active');

        if (tab === 'ai') {
            hydrateAiTab();
        }

        // Tabulator needs a redraw after the Keywords panel becomes visible again.
        if (tab === 'keywords' && window.sbGscKeywordsTable && typeof window.sbGscKeywordsTable.redraw === 'function') {
            window.setTimeout(function() {
                window.sbGscKeywordsTable.redraw(true);
            }, 50);
        }
    });

    // Character counting with range-based color indicators
    function updateCharacterCount() {
        var titleElement = $('#sb_seo_title');
        var descElement = $('#sb_seo_description');
        var titleCountElement = $('#title-count');
        var descCountElement = $('#description-count');
        
        // Check if elements exist before accessing them
        if (titleElement.length && titleCountElement.length) {
            var titleLength = titleElement.val() ? titleElement.val().length : 0;
            titleCountElement.text(titleLength + ' characters');
            
            // Range-based color coding for title
            if (titleLength >= 60) {
                titleCountElement.css('color', '#d63638'); // Red for long titles
            } else if (titleLength >= 30) {
                titleCountElement.css('color', '#00a32a'); // Green for optimal range
            } else if (titleLength >= 10) {
                titleCountElement.css('color', '#dba617'); // Yellow for short titles
            } else {
                titleCountElement.css('color', '#666'); // Gray for very short
            }
        }
        
        if (descElement.length && descCountElement.length) {
            var descLength = descElement.val() ? descElement.val().length : 0;
            descCountElement.text(descLength + ' characters');
            
            // Range-based color coding for description
            if (descLength >= 160) {
                descCountElement.css('color', '#d63638'); // Red for long descriptions
            } else if (descLength >= 120) {
                descCountElement.css('color', '#00a32a'); // Green for optimal range
            } else if (descLength >= 50) {
                descCountElement.css('color', '#dba617'); // Yellow for short descriptions
            } else {
                descCountElement.css('color', '#666'); // Gray for very short
            }
        }
    }

    // Update character counts on input
    $('#sb_seo_title, #sb_seo_description').on('input', updateCharacterCount);
    
    // Initial count
    updateCharacterCount();



    // LLM SEO Suggestions
    var llmData = {
        language: null
    };

    function updateAiPanelVisibility() {
        var $activity = $('#sb-ai-activity-zone');
        var $results = $('#sb-ai-results-zone');
        var $loading = $('.sb-llm-loading');
        var $recent = $('#sb-recent-requests-wrap');
        var hasResults = $('#sb-llm-suggestions-display').children().length > 0
            || ($('#sb-comprehensive-analysis-display').length && $('#sb-comprehensive-analysis-display').children().length > 0);
        var hasRecentRows = $recent.length && $recent.find('tbody tr').length > 0;
        var loadingVisible = $loading.length && !$loading.hasClass('sb-hidden') && $loading.is(':visible');
        var progressVisible = $('#sb-ai-progress').length && $('#sb-ai-progress').is(':visible');
        var activityVisible = loadingVisible || hasRecentRows || progressVisible;

        if ($activity.length) {
            $activity.toggleClass('sb-hidden', !activityVisible);
        }
        if ($results.length) {
            $results.toggleClass('sb-hidden', !hasResults);
        }
    }

    function showAiLoading() {
        $('#sb-ai-activity-zone').removeClass('sb-hidden');
        $('.sb-llm-loading').removeClass('sb-hidden').show();
        updateAiPanelVisibility();
    }

    function hideAiLoading() {
        $('.sb-llm-loading').addClass('sb-hidden').hide();
        updateAiPanelVisibility();
    }

    // Load saved suggestions on page load
    function loadSavedLLMSuggestions() {
        if (isAttachmentPage()) {
            return;
        }

        var postId = $('#post_ID').val() || (sb_seo_metabox && sb_seo_metabox.post_id) || '';
        if (!postId) return;

        // Check if AI is disabled
        var $disabledSection = $('.sb-llm-disabled');
        if ($disabledSection.length > 0) {
            return; // Don't load anything if AI is disabled
        }

        $.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_seo_get_llm_suggestions',
                post_id: postId,
                nonce: sb_seo_metabox.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var $btn = $('#sb-get-llm-suggestions');
                    $btn.prop('disabled', false).html(getGenerateSuggestionsButtonHtml());

                    // Display suggestions if available
                    if (response.data.titles && response.data.descriptions) {
                        displayLLMResults(response.data);
                        updateLanguageDisplay(response.data.language);
                    } else {
                        updateAiPanelVisibility();
                    }
                }
            },
            error: function() {
                // On error, still enable button with default message
                var $btn = $('#sb-get-llm-suggestions');
                $btn.prop('disabled', false).html(getGenerateSuggestionsButtonHtml());
                updateAiPanelVisibility();
            }
        });
    }

    function formatAssistantAnswer(text) {
        var escaped = escapeHtml(text || '');
        var parts = escaped.split('\n');
        var out = [];
        var listItems = [];
        function flushList() {
            if (listItems.length) {
                out.push('<ul class="sb-ai-assistant-bullets">' + listItems.join('') + '</ul>');
                listItems = [];
            }
        }
        parts.forEach(function(rawLine) {
            var line = rawLine.trim();
            if (!line) {
                flushList();
                return;
            }
            if (/^[-*]\s+/.test(line)) {
                listItems.push('<li>' + line.replace(/^[-*]\s+/, '') + '</li>');
            } else {
                flushList();
                out.push('<p>' + line + '</p>');
            }
        });
        flushList();
        return out.join('') || '<p>' + escaped + '</p>';
    }

    function renderAssistantActions(actions, canUsePro) {
        var $wrap = $('#sb-ai-assistant-actions');
        $wrap.empty();
        if (!actions || !actions.length) {
            return;
        }

        var s = sb_seo_metabox.strings || {};
        var canPro = !!canUsePro;
        $wrap.append('<h5 class="sb-ai-assistant-actions-heading">' + escapeHtml(s.assistant_proposed || 'Proposed actions') + '</h5>');

        actions.forEach(function(action) {
            if (!action || !action.type) {
                return;
            }
            var label = action.label || action.type;
            var typeLabel = action.type === 'set_focus_keyword'
                ? 'Focus keyword'
                : (action.type === 'create_autolink' ? 'Autolink' : action.type);
            var $card = $('<div class="sb-ai-assistant-action-card"></div>');
            var $main = $('<div class="sb-ai-assistant-action-main"></div>');
            $main.append('<span class="sb-ai-assistant-action-badge">' + escapeHtml(typeLabel) + '</span>');
            $main.append('<span class="sb-ai-assistant-action-label">' + escapeHtml(label) + '</span>');
            $card.append($main);

            var $footer = $('<div class="sb-ai-assistant-action-footer"></div>');
            if (canPro) {
                var $btn = $('<button type="button" class="button button-small button-primary sb-ai-assistant-apply-btn"></button>');
                $btn.text(s.assistant_apply || 'Apply');
                $btn.attr('data-action-type', action.type);
                $btn.attr('data-payload', JSON.stringify(action.payload || {}));
                $footer.append($btn);
            } else {
                var $locked = $('<span class="sb-ai-assistant-pro-lock"></span>');
                $locked.append('<span class="sb-ai-assistant-pro-badge">' + escapeHtml(s.assistant_pro_required || 'Pro') + '</span>');
                $locked.append('<span class="sb-ai-assistant-pro-text">' + escapeHtml(s.assistant_pro_teaser || 'Applying actions requires SEO Booster Pro.') + '</span>');
                $locked.append(
                    $('<a></a>')
                        .attr('href', sb_seo_metabox.pro_upgrade_url || '')
                        .attr('target', '_blank')
                        .attr('rel', 'noopener noreferrer')
                        .text(s.upgrade || 'Upgrade')
                );
                $footer.append($locked);
            }
            $card.append($footer);
            $wrap.append($card);
        });
    }

    function displayAssistantResult(data, fromHistory) {
        if (!data || !data.answer) {
            return;
        }
        var s = sb_seo_metabox.strings || {};
        var $result = $('#sb-ai-assistant-result');
        var $echo = $('#sb-ai-assistant-question-echo');
        var $answer = $('#sb-ai-assistant-answer');

        if (data.question) {
            var prefix = fromHistory ? (s.assistant_previous || 'Previous answer') : (s.assistant_you_asked || 'You asked:');
            $echo.html('<strong>' + escapeHtml(prefix) + '</strong> ' + escapeHtml(data.question));
            $echo.removeClass('sb-hidden');
        } else {
            $echo.empty().addClass('sb-hidden');
        }

        $answer.html(formatAssistantAnswer(data.answer));
        renderAssistantActions(data.actions || [], data.can_use_pro_actions !== undefined ? data.can_use_pro_actions : sb_seo_metabox.can_use_pro_actions);
        $result.removeClass('sb-hidden');
        $('#sb-ai-assistant-history-hint').addClass('sb-hidden');

        if (!fromHistory && data.titles && data.titles.length && data.descriptions && data.descriptions.length) {
            displayLLMResults({
                titles: data.titles,
                descriptions: data.descriptions,
                language: data.language || ''
            }, true);
            if (data.language) {
                updateLanguageDisplay(data.language);
            }
        }

        updateAiPanelVisibility();
    }

    function loadAssistantHistory() {
        if (isAttachmentPage()) {
            return;
        }
        var postId = $('#post_ID').val() || (sb_seo_metabox && sb_seo_metabox.post_id) || '';
        if (!postId) {
            return;
        }
        if ($('#sb-ai-assistant').length === 0) {
            return;
        }

        $.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_seo_get_ai_assistant_history',
                post_id: postId,
                nonce: sb_seo_metabox.nonce
            },
            success: function(response) {
                if (!response.success || !response.data || !response.data.last_turn) {
                    return;
                }
                var turn = response.data.last_turn;
                displayAssistantResult({
                    question: turn.q,
                    answer: turn.answer,
                    actions: turn.actions || [],
                    titles: turn.titles || [],
                    descriptions: turn.descriptions || [],
                    language: response.data.language || '',
                    can_use_pro_actions: response.data.can_use_pro_actions
                }, true);
            }
        });
    }

    function askAssistant(question) {
        var s = sb_seo_metabox.strings || {};
        question = (question || '').trim();
        if (!question) {
            window.SBModal.alert(s.assistant_empty || 'Please enter a question.', { tone: 'warning' });
            return;
        }

        if (sb_seo_metabox.ai_provider !== 'WordPress') {
            window.SBModal.alert(
                'Ask requires WordPress Connectors. Choose WordPress Connectors under Settings → AI/LLM.',
                { tone: 'warning' }
            );
            return;
        }

        var postId = $('#post_ID').val() || (sb_seo_metabox && sb_seo_metabox.post_id) || '';
        if (!postId) {
            return;
        }

        var $askBtn = $('#sb-ai-assistant-ask');
        var $loading = $('#sb-ai-assistant-loading');
        $askBtn.prop('disabled', true);
        $loading.removeClass('sb-hidden');

        $.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_seo_ai_assistant_ask',
                post_id: postId,
                question: question,
                nonce: sb_seo_metabox.nonce
            },
            success: function(response) {
                $loading.addClass('sb-hidden');
                $askBtn.prop('disabled', false);
                if (!response.success || !response.data) {
                    var msg = (response.data && response.data.message) ? response.data.message : (s.assistant_error || 'Could not get an answer.');
                    window.SBModal.alert(msg, { tone: 'error' });
                    return;
                }
                var data = response.data;
                data.question = question;
                displayAssistantResult(data, false);
            },
            error: function() {
                $loading.addClass('sb-hidden');
                $askBtn.prop('disabled', false);
                window.SBModal.alert(s.assistant_error || 'Could not get an answer.', { tone: 'error' });
            }
        });
    }

    $(document).on('click', '.sb-ai-assistant-chip', function() {
        var q = $(this).data('question') || $(this).text();
        $('#sb-ai-assistant-input').val(q);
        askAssistant(q);
    });

    $(document).on('click', '#sb-ai-assistant-ask', function() {
        askAssistant($('#sb-ai-assistant-input').val());
    });

    $(document).on('keydown', '#sb-ai-assistant-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            askAssistant($(this).val());
        }
    });

    $(document).on('click', '.sb-ai-assistant-apply-btn', function() {
        var $btn = $(this);
        var actionType = $btn.attr('data-action-type');
        var payload;
        try {
            payload = JSON.parse($btn.attr('data-payload') || '{}');
        } catch (err) {
            payload = {};
        }
        var s = sb_seo_metabox.strings || {};
        var postId = $('#post_ID').val() || (sb_seo_metabox && sb_seo_metabox.post_id) || '';

        function doApply() {
            $btn.prop('disabled', true);
            $.ajax({
                url: sb_seo_metabox.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_seo_ai_assistant_apply_action',
                    post_id: postId,
                    action_type: actionType,
                    payload: JSON.stringify(payload),
                    nonce: sb_seo_metabox.nonce
                },
                success: function(response) {
                    $btn.prop('disabled', false);
                    if (!response.success) {
                        var msg = (response.data && response.data.message) ? response.data.message : (s.assistant_error || 'Error');
                        window.SBModal.alert(msg, { tone: 'error' });
                        return;
                    }
                    $btn.text(s.assistant_applied || 'Applied.').prop('disabled', true);
                    // Mirror focus keyword into the active SEO plugin UI when possible.
                    if (actionType === 'set_focus_keyword' && payload.keyword) {
                        var $applyProxy = $('<button type="button" class="sb-llm-apply-btn" data-field="focus_keyword"></button>');
                        $applyProxy.attr('data-value', payload.keyword);
                        $applyProxy.trigger('click');
                    }
                    window.SBModal.alert(response.data.message || (s.assistant_applied || 'Applied.'), { tone: 'success' });
                },
                error: function() {
                    $btn.prop('disabled', false);
                    window.SBModal.alert(s.assistant_error || 'Error', { tone: 'error' });
                }
            });
        }

        if (actionType === 'create_autolink') {
            window.SBModal.confirm(s.assistant_apply_confirm || 'Create this automatic internal link?', { tone: 'warning' }).then(function(ok) {
                if (ok) {
                    doApply();
                }
            });
        } else {
            doApply();
        }
    });

    // Display LLM results
    function displayLLMResults(data, isNewGeneration = false) {
        if (!data.titles || !data.descriptions) return;
        
        // Create the display structure if it doesn't exist
        var $displayArea = $('#sb-llm-suggestions-display');
        if ($displayArea.find('.sb-llm-results').length === 0) {
            $displayArea.html(`
                <div class="sb-llm-results sb-hidden">
                    <div class="sb-llm-results-header sb-llm-toggle-header">
                        <h4>Generated AI SEO Suggestions</h4>
						<span class="sb-llm-toggle-arrow">
                            <span class="dashicons dashicons-arrow-down"></span>
                        </span>
                    </div>
                    <div class="sb-llm-results-content">
                        <div class="sb-llm-results-intro" style="margin-bottom: 20px; padding: 12px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 2px;">
                            <p style="margin: 0; font-size: 13px; color: #1d2327;">
                                <strong>What you received:</strong> The AI analyzed your content and generated 7 optimized SEO title suggestions and 7 meta description suggestions. 
                                If you have set a focus keyword, it has been included in the suggestions following SEO best practices. 
                                You can select and copy any suggestion text directly, or use the "Copy" button. Click "Use" to apply a suggestion to your SEO fields.
                            </p>
                        </div>
                        <div class="sb-llm-previous-indicator" style="display: none; margin-bottom: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffb900; border-radius: 2px;">
                            <p style="margin: 0; font-size: 12px; color: #1d2327;">
                                <span class="dashicons dashicons-clock" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;"></span>
                                <strong>Previously Generated Suggestions</strong> - These suggestions are saved and available for easy copy-paste.
                            </p>
                        </div>
                        <div class="sb-llm-titles">
                            <h5>Title Suggestions</h5>
                            <p style="font-size: 12px; color: #646970; margin: 0 0 10px 0;">Each title is optimized for SEO (55-80 characters) and designed to improve click-through rates.</p>
                            <ul id="sb-llm-titles-list"></ul>
                        </div>
                        <div class="sb-llm-descriptions">
                            <h5>Meta Description Suggestions</h5>
                            <p style="font-size: 12px; color: #646970; margin: 0 0 10px 0;">Each description is optimized for SEO (150-175 characters) and designed to encourage clicks from search results.</p>
                            <ul id="sb-llm-descriptions-list"></ul>
                        </div>
                    </div>
                </div>
            `);
        }
        
        // Display titles - append new suggestions instead of replacing
        var $titlesList = $('#sb-llm-titles-list');
        if (!isNewGeneration) {
            $titlesList.empty(); // Clear only when loading saved suggestions
            // Show "Previously Generated" indicator for saved suggestions
            $('.sb-llm-previous-indicator').show();
        } else {
            // Hide "Previously Generated" indicator for new suggestions
            $('.sb-llm-previous-indicator').hide();
        }
        
        // Add separator for new generations
        if (isNewGeneration && $titlesList.children().length > 0) {
            var $separator = $('<li class="sb-llm-separator">');
            $separator.html('<div class="sb-llm-separator-line"><span>New Suggestions</span></div>');
            $titlesList.append($separator);
        }
        
        data.titles.forEach(function(title) {
            var $li = $('<li class="sb-llm-suggestion-item">');
            $li.html(
                '<span class="sb-llm-text">' + escapeHtml(title) + '</span>' +
                '<div class="sb-llm-actions">' +
                    '<button class="button button-small sb-llm-copy-btn">Copy</button>' +
                    '<button class="button button-small button-primary sb-llm-apply-btn" ' +
                           'data-field="title" data-value="' + escapeHtml(title) + '">Use</button>' +
                '</div>'
            );
            $titlesList.append($li);
        });
        
        // Display descriptions - append new suggestions instead of replacing
        var $descriptionsList = $('#sb-llm-descriptions-list');
        if (!isNewGeneration) {
            $descriptionsList.empty(); // Clear only when loading saved suggestions
        }
        
        // Add separator for new generations
        if (isNewGeneration && $descriptionsList.children().length > 0) {
            var $separator = $('<li class="sb-llm-separator">');
            $separator.html('<div class="sb-llm-separator-line"><span>New Suggestions</span></div>');
            $descriptionsList.append($separator);
        }
        
        data.descriptions.forEach(function(description) {
            var $li = $('<li class="sb-llm-suggestion-item">');
            $li.html(
                '<span class="sb-llm-text">' + escapeHtml(description) + '</span>' +
                '<div class="sb-llm-actions">' +
                    '<button class="button button-small sb-llm-copy-btn">Copy</button>' +
                    '<button class="button button-small button-primary sb-llm-apply-btn" ' +
                           'data-field="description" data-value="' + escapeHtml(description) + '">Use</button>' +
                '</div>'
            );
            $descriptionsList.append($li);
        });
        
        // Show results box
        var $results = $('.sb-llm-results');
        var $content = $results.find('.sb-llm-results-content');
        var $arrow = $results.find('.sb-llm-toggle-header .dashicons');

        $results.removeClass('sb-hidden');

        // Expand for new generations; keep collapsed when restoring saved suggestions.
        if (isNewGeneration) {
            $results.addClass('expanded');
            $content.show();
            $arrow.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
        } else {
            $results.removeClass('expanded');
            $content.hide();
            $arrow.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
        }

        updateAiPanelVisibility();

        if (isNewGeneration && $results[0]) {
            $results[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    // Update language display (language is auto-detected; label is rendered in PHP)
    function updateLanguageDisplay(language) {
        llmData.language = language;
    }

    // Escape HTML for safe display
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function getGenerateSuggestionsButtonHtml() {
        var $btn = $('#sb-get-llm-suggestions');
        var s = (sb_seo_metabox && sb_seo_metabox.strings) || {};
        var type = ($btn.data('request-type') || 'seo_suggestions').toString();
        if (type === 'product_seo') {
            return '<span class="dashicons dashicons-cart"></span> ' + (s.generate_product_seo || 'Generate Product SEO');
        }
        return '<span class="dashicons dashicons-lightbulb"></span> ' + (s.generate_suggestions || 'Generate title & meta ideas');
    }

    function displayComprehensiveAnalysis(data) {
        var $container = $('#sb-comprehensive-analysis-display');
        if (!$container.length) {
            return;
        }
        var html = '<div class="sb-comprehensive-analysis">';
        html += '<h4>Comprehensive SEO Analysis</h4>';

        if (data.overall_score !== undefined) {
            var scoreClass = data.overall_score >= 70 ? 'sb-score-good' : (data.overall_score >= 40 ? 'sb-score-warn' : 'sb-score-bad');
            html += '<p class="sb-overall-score-p">Overall Score: <strong class="' + scoreClass + '">' + data.overall_score + '/100</strong></p>';
        }

        if (data.priority_issues && data.priority_issues.length) {
            html += '<h5>Priority Issues</h5><ul>';
            data.priority_issues.forEach(function(item) {
                var badgeClass = item.impact === 'high' ? 'sb-badge-high' : (item.impact === 'medium' ? 'sb-badge-medium' : 'sb-badge-low');
                html += '<li><span class="sb-badge ' + badgeClass + '">' + escapeHtml(item.impact) + '</span> ' + escapeHtml(item.issue) + '<br><small class="sb-muted">Fix: ' + escapeHtml(item.fix) + '</small></li>';
            });
            html += '</ul>';
        }

        if (data.content_gaps && data.content_gaps.length) {
            html += '<h5>Content Gaps</h5><ul>';
            data.content_gaps.forEach(function(item) {
                html += '<li><strong>' + escapeHtml(item.topic) + '</strong> &mdash; ' + escapeHtml(item.reason) + '<br><small class="sb-muted">Suggestion: ' + escapeHtml(item.suggestion) + '</small></li>';
            });
            html += '</ul>';
        }

        if (data.internal_linking && data.internal_linking.length) {
            html += '<h5>Internal Linking Suggestions</h5><ul>';
            data.internal_linking.forEach(function(item) {
                html += '<li>Link text: "<strong>' + escapeHtml(item.anchor_text) + '</strong>" &rarr; ' + escapeHtml(item.target_description) + '<br><small class="sb-muted">Where: ' + escapeHtml(item.context) + '</small></li>';
            });
            html += '</ul>';
        }

        if (data.keyword_opportunities && data.keyword_opportunities.length) {
            html += '<h5>Keyword Opportunities</h5><ul>';
            data.keyword_opportunities.forEach(function(item) {
                html += '<li><strong>' + escapeHtml(item.keyword) + '</strong> (potential: ' + escapeHtml(item.potential) + ') &mdash; ' + escapeHtml(item.action) + '</li>';
            });
            html += '</ul>';
        }

        if (data.quick_wins && data.quick_wins.length) {
            html += '<h5>Quick Wins</h5><ol>';
            data.quick_wins.forEach(function(item) {
                html += '<li>' + escapeHtml(item) + '</li>';
            });
            html += '</ol>';
        }

        html += '</div>';
        $container.html(html);
        updateAiPanelVisibility();
    }

    function updateCreditsDisplay(balance) {
        if (typeof balance === 'number' || typeof balance === 'string') {
            var numBalance = Number(balance);
            var formatted = numBalance.toLocaleString();
            $('#sb-credits-inline-count').text(formatted);
            $('#sb-credits-balance-value').text(formatted);
            if (sb_seo_metabox.credits) {
                sb_seo_metabox.credits.balance = numBalance;
            }
            checkLowBalance(numBalance);
        }
    }

    var _lastNudgeLevel = null;
    function checkLowBalance(balance) {
        var $notice = $('#sb-low-balance-notice');
        var thresholds = [
            { level: 5, msg: 'You have only ' + balance + ' credits left! Buy more to continue using AI features.', cls: 'notice-error' },
            { level: 10, msg: 'Credits running low (' + balance + ' remaining). Consider topping up soon.', cls: 'notice-warning' },
            { level: 20, msg: 'Heads up: ' + balance + ' credits remaining.', cls: 'notice-info' }
        ];

        var matched = null;
        for (var i = 0; i < thresholds.length; i++) {
            if (balance <= thresholds[i].level && balance > 0) {
                matched = thresholds[i];
                break;
            }
        }

        if (!matched) {
            $notice.remove();
            _lastNudgeLevel = null;
            return;
        }

        if (_lastNudgeLevel === matched.level) return;
        _lastNudgeLevel = matched.level;

        $notice.remove();
        var buyUrl = (sb_seo_metabox.credits && sb_seo_metabox.credits.buy_url) || '#';
        var html = '<div id="sb-low-balance-notice" class="notice ' + matched.cls + ' is-dismissible sb-low-balance-notice-wrap">' +
            '<p>' + escapeHtml(matched.msg) + ' <a href="' + buyUrl + '">' + 'Buy credits' + '</a></p>' +
            '<button type="button" class="notice-dismiss" onclick="this.parentElement.remove()"><span class="screen-reader-text">Dismiss</span></button></div>';
        $('#sb-seo-metabox .sb-seo-analysis-section').prepend(html);
    }

    function startGeneration(requestType) {
        var postId = $('#post_ID').val();
        var $btn = $('#sb-get-llm-suggestions');
        var $loading = $('.sb-llm-loading');
        var $statusMessage = $loading.find('.sb-llm-status-message');
        var $statusStep = $loading.find('.sb-llm-status-step');
        var $timerText = $loading.find('.sb-llm-timer-text');

        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> ' + ((sb_seo_metabox.strings && sb_seo_metabox.strings.generating) || 'Generating...'));

        showAiLoading();
        updateStatusMessage('analyzing', $statusMessage, $statusStep);

        var countup = 0;
        var countupInterval = setInterval(function() {
            countup++;
            var minutes = Math.floor(countup / 60);
            var seconds = countup % 60;
            var timeText = minutes > 0 ? minutes + 'm ' + seconds + 's' : seconds + 's';
            $timerText.text('Elapsed time: ' + timeText);
        }, 1000);

        var provider = ($btn.data('provider') || sb_seo_metabox.ai_provider || '').toString().toLowerCase();
        var type = requestType || $btn.data('request-type') || 'seo_suggestions';

        function finishGeneration() {
            clearInterval(countupInterval);
            $btn.prop('disabled', false);
            $btn.html(originalHtml);
        }

        if (provider === 'seobooster') {
            startCreditsGeneration(postId, $loading, $statusMessage, $statusStep, countupInterval, finishGeneration, type);
        } else if (provider === 'wordpress') {
            startWPConnectorGeneration(postId, $loading, $statusMessage, $statusStep, countupInterval, finishGeneration);
        } else {
            finishGeneration();
            hideAiLoading();
            showError('AI provider not configured. Please configure your AI provider in SEO Booster Settings.');
        }
    }

    function startWPConnectorGeneration(postId, $loading, $statusMessage, $statusStep, countupInterval, finishCb) {
        setTimeout(function() { updateStatusMessage('condensing', $statusMessage, $statusStep); }, 500);
        setTimeout(function() { updateStatusMessage('sending', $statusMessage, $statusStep); }, 1500);
        setTimeout(function() { updateStatusMessage('generating', $statusMessage, $statusStep); }, 2500);

        $.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_seo_generate_wp_connector_suggestions',
                post_id: postId,
                nonce: sb_seo_metabox.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatusMessage('complete', $statusMessage, $statusStep);
                    setTimeout(function() {
                        hideAiLoading();
                        displayLLMResults(response.data, true);
                        updateLanguageDisplay(response.data.language);
                    }, 500);
                } else {
                    hideAiLoading();
                    showError(response.data.message || 'An error occurred while generating suggestions.');
                }
            },
            error: function() {
                hideAiLoading();
                showError('Failed to generate suggestions. Please try again.');
            },
            complete: finishCb
        });
    }

    function startCreditsGeneration(postId, $loading, $statusMessage, $statusStep, countupInterval, finishCb, requestType) {
        setTimeout(function() { updateStatusMessage('condensing', $statusMessage, $statusStep); }, 500);
        setTimeout(function() { updateStatusMessage('sending', $statusMessage, $statusStep); }, 1500);

        var ajaxAction = 'sb_seo_generate_credits_suggestions';
        if (requestType === 'product_seo') {
            ajaxAction = 'sb_seo_generate_product_seo';
        } else if (requestType === 'comprehensive_seo_analysis') {
            ajaxAction = 'sb_seo_generate_comprehensive_analysis';
        }

        $.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            data: {
                action: ajaxAction,
                post_id: postId,
                nonce: sb_seo_metabox.nonce
            },
            success: function(response) {
                if (response.success && response.data.request_id) {
                    updateCreditsDisplay(response.data.credits_remaining);
                    updateStatusMessage('queued', $statusMessage, $statusStep);
                    var rType = response.data.request_type || requestType || 'seo_suggestions';
                    $.post(sb_seo_metabox.ajax_url, {
                        action: 'sb_seo_save_pending_request',
                        post_id: postId,
                        request_id: response.data.request_id,
                        request_type: rType,
                        nonce: sb_seo_metabox.nonce
                    }).done(function() {
                        if (typeof loadRecentRequests === 'function') loadRecentRequests();
                    });
                    pollCreditsRequest(response.data.request_id, postId, $loading, $statusMessage, $statusStep, countupInterval, finishCb, rType);
                } else {
                    hideAiLoading();
                    finishCb();
                    if (response.data && response.data.insufficient_credits) {
                        var $creditsMsg = $('<span></span>')
                            .text('Insufficient credits (' + response.data.credits_remaining + ' remaining). ')
                            .append($('<a></a>').attr('href', response.data.buy_url || '#').text('Purchase more credits'));
                        showError($creditsMsg);
                    } else {
                        showError(response.data.message || 'Failed to submit request.');
                    }
                }
            },
            error: function() {
                hideAiLoading();
                finishCb();
                showError('Failed to submit credits request. Please try again.');
            }
        });
    }

    function pollCreditsRequest(requestId, postId, $loading, $statusMessage, $statusStep, countupInterval, finishCb, requestType) {
        var pollCount = 0;
        var maxPolls = 15;   // 15 * 20s = 5 min max
        var pollInterval = 20000; // 20s: server pings site via callback; we only poll every 20s to pick up result
        var firstPollDelay = 20000; // Don't poll until 20 seconds have passed
        var lastPollTime = 0;
        var $lastCheckedSpan = $loading.find('.sb-llm-last-checked-text');
        var lastCheckedInterval = null;

        function clearPending() {
            $.post(sb_seo_metabox.ajax_url, {
                action: 'sb_seo_clear_pending_request',
                post_id: postId,
                nonce: sb_seo_metabox.nonce
            });
        }

        function updateLastChecked() {
            if (lastPollTime <= 0) {
                $lastCheckedSpan.text('');
                return;
            }
            var secs = Math.floor((Date.now() - lastPollTime) / 1000);
            $lastCheckedSpan.text('Last checked: ' + secs + 's ago');
        }

        function doPoll() {
            pollCount++;

            if (pollCount > maxPolls) {
                if (lastCheckedInterval) clearInterval(lastCheckedInterval);
                hideAiLoading();
                finishCb();
                showError('Request timed out (5 min). Your credits have been preserved. Please try again.');
                clearPending();
                return;
            }

            $.ajax({
                url: sb_seo_metabox.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_seo_poll_credits_request',
                    request_id: requestId,
                    post_id: postId,
                    nonce: sb_seo_metabox.nonce
                },
                success: function(response) {
                    lastPollTime = Date.now();
                    updateLastChecked();
                    if (!lastCheckedInterval) {
                        lastCheckedInterval = setInterval(updateLastChecked, 1000);
                    }

                    if (!response.success) {
                        if (lastCheckedInterval) clearInterval(lastCheckedInterval);
                        hideAiLoading();
                        finishCb();
                        showError(response.data.message || 'Error checking request status.');
                        clearPending();
                        return;
                    }

                    var status = response.data.status;
                    if (response.data.credits_remaining !== undefined) {
                        updateCreditsDisplay(response.data.credits_remaining);
                    }

                    if (status === 'completed' && response.data.data) {
                        if (lastCheckedInterval) clearInterval(lastCheckedInterval);
                        updateStatusMessage('complete', $statusMessage, $statusStep);
                        clearPending();
                        setTimeout(function() {
                            hideAiLoading();
                            finishCb();
                            if (requestType === 'comprehensive_seo_analysis') {
                                displayComprehensiveAnalysis(response.data.data);
                            } else {
                                displayLLMResults(response.data.data, true);
                            }
                        }, 500);
                    } else if (status === 'failed') {
                        if (lastCheckedInterval) clearInterval(lastCheckedInterval);
                        clearPending();
                        hideAiLoading();
                        finishCb();
                        showError('AI processing failed: ' + (response.data.error || 'Unknown error') + '. Credits refunded.');
                    } else {
                        if (status === 'processing') {
                            updateStatusMessage('generating', $statusMessage, $statusStep);
                        }
                        $checkBtn.prop('disabled', false);
                        setTimeout(doPoll, pollInterval);
                    }
                },
                error: function() {
                    lastPollTime = Date.now();
                    updateLastChecked();
                    if (!lastCheckedInterval) lastCheckedInterval = setInterval(updateLastChecked, 1000);
                    $checkBtn.prop('disabled', false);
                    setTimeout(doPoll, pollInterval);
                }
            });
        }

        // Add "Check status" button that does one poll immediately
        var $checkBtn = $('<button type="button" class="button" style="margin-top:8px;">Check status</button>');
        $loading.find('.sb-llm-status-details').append($checkBtn);
        $checkBtn.on('click', function() {
            $checkBtn.prop('disabled', true);
            doPoll();
        });

        // First poll after 20s; then every 20s (server may callback sooner; next poll will pick it up)
        setTimeout(doPoll, firstPollDelay);
    }

    // Update status message based on stage
    function updateStatusMessage(stage, $statusMessage, $statusStep) {
        var messages = {
            'analyzing': {
                main: 'Analyzing your content...',
                step: 'Reading post content, categories, tags, and focus keywords'
            },
            'condensing': {
                main: 'Condensing content...',
                step: 'Preparing content summary for AI analysis'
            },
            'sending': {
                main: 'Sending to AI...',
                step: 'Submitting request to AI service'
            },
            'queued': {
                main: 'Request submitted...',
                step: 'Waiting in queue for AI processing (usually 30–90 seconds)'
            },
            'generating': {
                main: 'AI is generating suggestions...',
                step: 'Creating optimized SEO titles and meta descriptions (usually 30–90 seconds)'
            },
            'complete': {
                main: 'Suggestions generated successfully!',
                step: 'Your AI-powered SEO suggestions are ready'
            }
        };

        var msg = messages[stage] || messages['generating'];
        $statusMessage.text(msg.main);
        $statusStep.text(msg.step);
    }

    // Generate button click
    $('#sb-get-llm-suggestions').on('click', function() {
        startGeneration();
    });

    // Comprehensive SEO analysis button
    $('#sb-comprehensive-analysis').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        startGeneration('comprehensive_seo_analysis');
    });

    // Check low balance on page load
    if (sb_seo_metabox.credits && sb_seo_metabox.credits.balance !== undefined) {
        checkLowBalance(Number(sb_seo_metabox.credits.balance));
    }

    // Recent requests: load statuses and show list (with Retry for failed/queued)
    function getRequestTypeLabel(type) {
        var s = sb_seo_metabox.strings || {};
        if (type === 'product_seo') return s.request_type_product || 'Product SEO';
        if (type === 'comprehensive_seo_analysis') return s.request_type_analysis || 'Full analysis';
        if (type === 'image_analysis') return s.request_type_image || 'Image descriptions';
        return s.request_type_seo || 'SEO suggestions';
    }
    function getStatusLabel(status) {
        var s = sb_seo_metabox.strings || {};
        var key = 'request_status_' + (status || 'queued');
        return s[key] || status || '-';
    }
    function loadRecentRequests() {
        var postId = sb_seo_metabox.post_id;
        if (!postId) return;
        var $wrap = $('#sb-recent-requests-wrap');
        var $list = $('#sb-recent-requests-list');
        $.post(sb_seo_metabox.ajax_url, {
            action: 'sb_seo_get_recent_request_statuses',
            post_id: postId,
            nonce: sb_seo_metabox.nonce
        }).done(function(response) {
            if (!response.success || !response.data) {
                $wrap.addClass('sb-hidden');
                updateAiPanelVisibility();
                return;
            }
            var statuses = response.data.statuses || [];
            if (!statuses.length) {
                $wrap.addClass('sb-hidden');
                updateAiPanelVisibility();
                return;
            }
            $wrap.removeClass('sb-hidden');
            var html = '<table class="widefat striped" style="margin:0;"><thead><tr><th>Type</th><th>Status</th><th>Time</th><th></th></tr></thead><tbody>';
            statuses.forEach(function(row) {
                var typeLabel = getRequestTypeLabel(row.type);
                var statusLabel = getStatusLabel(row.status);
                var timeStr = row.created ? (function() {
                    var d = new Date(row.created * 1000);
                    return isNaN(d.getTime()) ? '-' : d.toLocaleString();
                }()) : '-';
                var retryHtml = (row.status === 'failed' || row.status === 'queued') 
                    ? '<button type="button" class="button button-small sb-recent-retry-btn" data-request-id="' + (row.id || '').replace(/"/g, '&quot;') + '">' + (sb_seo_metabox.strings.retry || 'Retry') + '</button>'
                    : '';
                html += '<tr><td>' + typeLabel + '</td><td>' + statusLabel + (row.error ? ' <span style="color:#b32d2e;">(' + (row.error || '').slice(0, 40) + ')</span>' : '') + '</td><td>' + timeStr + '</td><td>' + retryHtml + '</td></tr>';
            });
            html += '</tbody></table>';
            $list.html(html);
            updateAiPanelVisibility();
        }).fail(function() {
            $wrap.addClass('sb-hidden');
            updateAiPanelVisibility();
        });
    }
    if (sb_seo_metabox.post_id && $('#sb-recent-requests-wrap').length) {
        var shouldLoadRecent = sb_seo_metabox.ai_provider === 'seobooster'
            && (sb_seo_metabox.has_recent_request_ids || sb_seo_metabox.pending_request_id);
        if (shouldLoadRecent) {
            loadRecentRequests();
        }
    }

    $(document).on('click', '.sb-recent-retry-btn', function() {
        var requestId = $(this).data('request-id');
        if (!requestId) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text('…');
        $.post(sb_seo_metabox.ajax_url, {
            action: 'sb_seo_retry_request',
            request_id: requestId,
            nonce: sb_seo_metabox.nonce
        }).done(function(response) {
            if (response.success) {
                loadRecentRequests();
            } else {
                $btn.prop('disabled', false).text(sb_seo_metabox.strings.retry || 'Retry');
                window.SBModal.alert(response.data && response.data.message ? response.data.message : 'Retry failed', { tone: 'error' });
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(sb_seo_metabox.strings.retry || 'Retry');
            window.SBModal.alert('Retry failed', { tone: 'error' });
        });
    });

    // Restore "Check status" when this post has a pending credits request (e.g. after page reload)
    if (sb_seo_metabox.pending_request_id && sb_seo_metabox.post_id) {
        var $btn = $('#sb-get-llm-suggestions');
        var $loading = $('.sb-llm-loading');
        var $statusMessage = $loading.find('.sb-llm-status-message');
        var $statusStep = $loading.find('.sb-llm-status-step');
        var $timerText = $loading.find('.sb-llm-timer-text');
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> ' + ((sb_seo_metabox.strings && sb_seo_metabox.strings.generating) || 'Generating...'));
        showAiLoading();
        updateStatusMessage('queued', $statusMessage, $statusStep);
        var countup = 0;
        var countupInterval = setInterval(function() {
            countup++;
            var minutes = Math.floor(countup / 60);
            var seconds = countup % 60;
            $timerText.text('Elapsed time: ' + (minutes > 0 ? minutes + 'm ' + seconds + 's' : seconds + 's'));
        }, 1000);
        function finishGeneration() {
            clearInterval(countupInterval);
            $btn.prop('disabled', false).html(originalHtml);
        }
        pollCreditsRequest(
            sb_seo_metabox.pending_request_id,
            parseInt(sb_seo_metabox.post_id, 10),
            $loading,
            $statusMessage,
            $statusStep,
            countupInterval,
            finishGeneration,
            sb_seo_metabox.pending_request_type || 'seo_suggestions'
        );
    }

    // Copy to clipboard
    $(document).on('click', '.sb-llm-copy-btn', function() {
        var text = $(this).closest('.sb-llm-suggestion-item').find('.sb-llm-text').text();
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                var $btn = $(this);
                $btn.text('Copied!').prop('disabled', true);
                setTimeout(function() {
                    $btn.text('Copy').prop('disabled', false);
                }, 2000);
            }.bind(this));
        } else {
            // Fallback for older browsers
            var textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            var $btn = $(this);
            $btn.text('Copied!').prop('disabled', true);
            setTimeout(function() {
                $btn.text('Copy').prop('disabled', false);
            }, 2000);
        }
    });

    // Apply suggestion
    $(document).on('click', '.sb-llm-apply-btn', function() {
        var field = $(this).data('field');
        var value = $(this).data('value');
        // Normalize title casing for certain languages (e.g., Danish sentence case)
        if (field === 'title') {
            var lang = llmData.language || '';
            value = normalizeTitleForLanguage(value, lang);
        }
        var $btn = $(this);
        var originalText = $btn.text();
        
        // Disable button and show loading
        $btn.text('Applying...').prop('disabled', true);
        
        // Get active SEO plugin info
        $.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_seo_get_active_plugin',
                nonce: sb_seo_metabox.nonce
            },
            success: function(response) {
                if (response.success && response.data.plugin) {
                    // Apply to the detected SEO plugin's field
                    var fieldSelector = response.data.fields[field];
                    var applied = false;

					// Prefer hidden/legacy inputs provided by detected plugin (Yoast, SEOPress, AIOSEO, TSF, etc.)
					var domSelectors = [];
					if (fieldSelector) {
						domSelectors.push(fieldSelector);
					}
					if (response.data.plugin === 'aioseo') {
						if (field === 'title') {
							domSelectors.push('#aioseo-title', '#aioseo_title', 'input[name="aioseo-title"]');
						} else if (field === 'description') {
							domSelectors.push('#aioseo-description', '#aioseo_description', 'textarea[name="aioseo-description"]');
						} else if (field === 'focus_keyword') {
							domSelectors.push('#aioseo-keyphrase', '#aioseo_keywords', 'input[name="aioseo-keyphrase"]');
						}
					} else if (response.data.plugin === 'seoframework') {
						if (field === 'title') {
							domSelectors.push('#autodescription-title', '#tsf_title');
						} else if (field === 'description') {
							domSelectors.push('#autodescription-description', '#tsf_description');
						}
					} else if (response.data.plugin === 'seopress') {
						if (field === 'title') {
							domSelectors.push('#seopress_titles_title');
						} else if (field === 'description') {
							domSelectors.push('#seopress_titles_desc');
						} else if (field === 'focus_keyword') {
							domSelectors.push('#seopress_analysis_target_kw');
						}
					}

					for (var s = 0; s < domSelectors.length; s++) {
						if (!domSelectors[s] || !$(domSelectors[s]).length) {
							continue;
						}
						var el = $(domSelectors[s]).get(0);
						if (el) {
							el.value = value;
							try {
								el.dispatchEvent(new Event('input', { bubbles: true }));
								el.dispatchEvent(new Event('change', { bubbles: true }));
								el.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
							} catch (e) {
								$(el).trigger('input').trigger('change').trigger('keyup');
							}
							applied = true;
							break;
						}
					}

					// Try to access Rank Math WordPress data store (works even without DOM inputs)
					var rankMathUpdated = false;
					if (response.data.plugin === 'rankmath') {
						try {
							if (window.wp && window.wp.data && window.wp.data.select && window.wp.data.dispatch) {
								var rankMathStore = window.wp.data.select('rank-math');
								var rankMathDispatch = window.wp.data.dispatch('rank-math');
								
								if (rankMathStore && rankMathDispatch) {
									// Update Rank Math via WordPress data store
									if (field === 'title') {
										if (typeof rankMathDispatch.updateTitle === 'function') {
											rankMathDispatch.updateTitle(value);
											// Also update SERP preview for title
											if (typeof rankMathDispatch.updateSerpTitle === 'function') {
												rankMathDispatch.updateSerpTitle(value);
											}
											rankMathUpdated = true;
										}
									} else if (field === 'description') {
										if (typeof rankMathDispatch.updateDescription === 'function') {
											rankMathDispatch.updateDescription(value);
											// Also update SERP preview for description
											if (typeof rankMathDispatch.updateSerpDescription === 'function') {
												rankMathDispatch.updateSerpDescription(value);
											}
											rankMathUpdated = true;
										}
									} else if (field === 'focus_keyword') {
										if (typeof rankMathDispatch.updateFocusKeyword === 'function') {
											rankMathDispatch.updateFocusKeyword(value);
											rankMathUpdated = true;
										} else if (typeof rankMathDispatch.setFocusKeyword === 'function') {
											rankMathDispatch.setFocusKeyword(value);
											rankMathUpdated = true;
										}
									}
									
									// Trigger Rank Math UI refresh
									if (rankMathUpdated && typeof rankMathDispatch.refreshResults === 'function') {
										rankMathDispatch.refreshResults();
									}
								}
							}
						} catch (e) {
							// Rank Math store access failed, continue with other methods
						}
					}
					
					// Try AIOSEO block editor data store when DOM apply did not run.
					var aioseoUpdated = false;
					if (response.data.plugin === 'aioseo' && !applied) {
						try {
							if (window.wp && window.wp.data && window.wp.data.dispatch) {
								var aioseoDispatch = window.wp.data.dispatch('aioseo/editor');
								if (aioseoDispatch) {
									if (field === 'title' && typeof aioseoDispatch.setTitle === 'function') {
										aioseoDispatch.setTitle(value);
										aioseoUpdated = true;
									} else if (field === 'description' && typeof aioseoDispatch.setDescription === 'function') {
										aioseoDispatch.setDescription(value);
										aioseoUpdated = true;
									} else if (field === 'focus_keyword' && typeof aioseoDispatch.setKeyphrase === 'function') {
										aioseoDispatch.setKeyphrase(value);
										aioseoUpdated = true;
									}
								}
							}
						} catch (e) {
							// AIOSEO store access failed, continue with other methods
						}
					}

					var uiUpdated = applied || rankMathUpdated || aioseoUpdated;

					if (uiUpdated) {
						var reduxUpdated = false;
						
						// Try to access Yoast Redux store via WordPress data API
						if (response.data.plugin === 'yoast') {
							try {
								if (window.wp && window.wp.data && window.wp.data.select && window.wp.data.dispatch) {
									var storeName = 'yoast-seo/editor';
									var store = window.wp.data.select(storeName);
									var dispatch = window.wp.data.dispatch(storeName);
									
									if (store && dispatch) {
										// Try to update via Redux store
										if (field === 'title') {
											if (typeof dispatch.updateData === 'function') {
												dispatch.updateData({ title: value });
												reduxUpdated = true;
											} else if (typeof dispatch.setSeoTitle === 'function') {
												dispatch.setSeoTitle(value);
												reduxUpdated = true;
											}
										} else if (field === 'description') {
											if (typeof dispatch.updateData === 'function') {
												dispatch.updateData({ description: value });
												reduxUpdated = true;
											} else if (typeof dispatch.setMetaDescription === 'function') {
												dispatch.setMetaDescription(value);
												reduxUpdated = true;
											}
										} else if (field === 'focus_keyword') {
											if (typeof dispatch.updateData === 'function') {
												dispatch.updateData({ keyword: value });
												reduxUpdated = true;
											} else if (typeof dispatch.setFocusKeyword === 'function') {
												dispatch.setFocusKeyword(value);
												reduxUpdated = true;
											}
										}
									}
								}
							} catch (e) {
								// Redux store access failed, continue with other methods
							}
						}
						
						// Try to update Yoast UI immediately (classic metabox - fallback)
						if (!reduxUpdated && !rankMathUpdated && response.data.plugin === 'yoast') {
							try {
								if (typeof window !== 'undefined' && window.YoastSEO && YoastSEO.app) {
									if (YoastSEO.app.snippetPreview) {
										if (field === 'title' && typeof YoastSEO.app.snippetPreview.setTitle === 'function') {
											YoastSEO.app.snippetPreview.setTitle(value);
										}
										if (field === 'description' && typeof YoastSEO.app.snippetPreview.setMetaDescription === 'function') {
											YoastSEO.app.snippetPreview.setMetaDescription(value);
										}
									}
									if (typeof YoastSEO.app.refresh === 'function') {
										YoastSEO.app.refresh();
									}
								}
								// Fire Yoast refresh event for listeners
								if (typeof jQuery !== 'undefined' && typeof jQuery(document).trigger === 'function') {
									jQuery(document).trigger('YoastSEO:refresh');
								}
							} catch (e) { /* no-op */ }
						}
					}

					var $metabox = $('#sb-seo-metabox');
					var objectId = $metabox.data('object-id') || $metabox.attr('data-object-id') || $('#post_ID').val() || (sb_seo_metabox && sb_seo_metabox.post_id) || '';
					var objectType = $metabox.data('object-type') || $metabox.attr('data-object-type') || 'post';
					if (!$metabox.length && $('#tag_ID').length) {
						objectId = $('#tag_ID').val();
						objectType = 'term';
					}

					var fieldName = field === 'title' ? 'SEO Title' : (field === 'description' ? 'Meta Description' : 'Focus Keyword');

					function showApplyFeedback(persisted, persistError) {
						$btn.text('✓ Applied').addClass('button-success');
						var saveMessage;
						if (persisted) {
							saveMessage = 'Saved to ' + response.data.name + '. The value is stored in your SEO plugin.';
						} else if (persistError) {
							saveMessage = 'Updated in the editor, but server save failed: ' + persistError + '. Save the post to keep changes.';
						} else if (uiUpdated) {
							saveMessage = 'Updated in the editor. Save the post or term to keep changes.';
						} else {
							saveMessage = 'Could not update the SEO plugin field in the editor.';
						}
						var $notice = $('<div class="sb-llm-applied-notice notice notice-success is-dismissible" style="margin: 10px 0; padding: 10px 15px;">' +
							'<p style="margin: 0;"><strong>✓ Value applied to ' + response.data.name + ' ' + fieldName + '</strong><br>' +
							'<small>' + saveMessage + '</small></p>' +
							'<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
							'</div>');
						var $noticeAnchor = $('#sb-ai-results-zone');
						if (!$noticeAnchor.length) {
							$noticeAnchor = $('#sb-llm-suggestions-display');
						}
						$noticeAnchor.before($notice);
						setTimeout(function() {
							$notice.fadeOut(300, function() {
								$(this).remove();
							});
						}, 8000);
						$notice.on('click', '.notice-dismiss', function() {
							$notice.fadeOut(300, function() {
								$(this).remove();
							});
						});
						setTimeout(function() {
							$btn.text(originalText).prop('disabled', false).removeClass('button-success');
						}, 3000);
					}

					if (objectId) {
						$.ajax({
							url: sb_seo_metabox.ajax_url,
							type: 'POST',
							data: {
								action: 'sb_seo_apply_seo_field',
								nonce: sb_seo_metabox.nonce,
								object_id: objectId,
								object_type: objectType,
								field: field,
								value: value
							},
							success: function(persistResponse) {
								showApplyFeedback(!!(persistResponse.success && persistResponse.data && persistResponse.data.persisted), '');
							},
							error: function(xhr) {
								var errMsg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : '';
								showApplyFeedback(false, errMsg);
							}
						});
					} else if (uiUpdated) {
						showApplyFeedback(false, '');
                    } else {
                        // Fallback to SEO Booster fields
                        $('#sb_seo_' + field).val(value).trigger('input');
                        updateCharacterCount();
                        
                        $btn.text('✓ Applied (Fallback)').addClass('button-success');
                        setTimeout(function() {
                            $btn.text(originalText).prop('disabled', false).removeClass('button-success');
                        }, 3000);
                    }
                } else {
                    // No compatible plugin detected, show message
                    $btn.text('No SEO Plugin').addClass('button-error');
                    setTimeout(function() {
                        $btn.text(originalText).prop('disabled', false).removeClass('button-error');
                    }, 3000);
                }
            },
            error: function() {
                // Fallback to SEO Booster fields
                $('#sb_seo_' + field).val(value).trigger('input');
                updateCharacterCount();
                
                $btn.text('✓ Applied (Fallback)').addClass('button-success');
                setTimeout(function() {
                    $btn.text(originalText).prop('disabled', false).removeClass('button-success');
                }, 3000);
            }
        });
    });

    function normalizeTitleForLanguage(title, language) {
        if (!title) return title;
        // Handle markdown heading prefix like "# "
        var prefix = '';
        var trimmed = title;
        var match = title.match(/^(#+\s+)/);
        if (match) {
            prefix = match[1];
            trimmed = title.substring(prefix.length);
        }
        if ((language || '').toLowerCase().indexOf('da') === 0) {
            var lower = trimmed.toLowerCase();
            // Capitalize first non-space character
            lower = lower.replace(/^(\s*)(\S)/, function(_, s, c){ return s + c.toUpperCase(); });
            return prefix + lower;
        }
        return title;
    }

    // Toggle collapsible results - make entire header clickable
    $(document).on('click', '.sb-llm-toggle-header', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $header = $(this);
        var $results = $header.closest('.sb-llm-results');
        var $content = $header.next('.sb-llm-results-content');
        var $arrow = $header.find('.dashicons');
        
        if ($results.hasClass('expanded')) {
            $results.removeClass('expanded');
            $content.slideUp();
            $arrow.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
        } else {
            $results.addClass('expanded');
            $content.slideDown();
            $arrow.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
        }
    });

    // Saved suggestions / assistant history hydrate on first AI tools tab open (see hydrateAiTab).
    // Credits pending poll still starts on ready so in-flight requests are not abandoned.
    if (!isAttachmentPage() && $('#sb-seo-ai').hasClass('active')) {
        hydrateAiTab();
    }

    updateAiPanelVisibility();

    // Toggle switch functionality for SEO settings
    $('.sb-seo-toggle-switch input').on('change', function() {
        var $toggle = $(this);
        var $status = $toggle.closest('.sb-seo-toggle-item').find('.sb-seo-toggle-status');
        
        if ($toggle.is(':checked')) {
            $status.text('Noindex').css({
                'background': '#d63638',
                'color': 'white'
            });
        } else {
            $status.text('Index').css({
                'background': '#00a32a',
                'color': 'white'
            });
        }
    });


    function readinessItemClass(item) {
        if (item.pass) {
            return 'is-pass';
        }
        if (item.unknown) {
            return 'is-unknown';
        }
        return 'is-fail';
    }

    function readinessItemSymbol(item) {
        if (item.pass) {
            return '✓';
        }
        if (item.unknown) {
            return '-';
        }
        return '○';
    }

    function buildChecklistHtml(items, showPoints) {
        if (!items || !items.length) {
            var notAnalyzed = (sb_seo_metabox.strings && sb_seo_metabox.strings.readiness_not_analyzed) || 'Not analyzed yet.';
            return '<li class="is-unknown"><span class="sb-ai-readiness-status">-</span><span class="sb-ai-readiness-label">' + notAnalyzed + '</span></li>';
        }
        return items.map(function (item) {
            var pointsHtml = showPoints && item.points ? '<span class="sb-ai-readiness-points">+' + item.points + '</span>' : '';
            return '<li class="' + readinessItemClass(item) + '" data-key="' + (item.key || '') + '">' +
                '<span class="sb-ai-readiness-status">' + readinessItemSymbol(item) + '</span>' +
                '<span class="sb-ai-readiness-label">' + (item.label || '') + '</span>' +
                pointsHtml +
            '</li>';
        }).join('');
    }

    function renderReadinessSections(view) {
        if (!view || !jQuery('#sb-ai-readiness-section').length) {
            return;
        }
        var grade = view.grade || {};
        var summaryText = (view.score || 0) + ' / ' + (view.max || 0) + ' · ' + (grade.label || '');
        jQuery('.sb-ai-readiness-summary-meta')
            .text(summaryText)
            .css('color', grade.color || '#646970');
        jQuery('#sb-ai-readiness-checklist').html(buildChecklistHtml(view.details || [], true));
        jQuery('#sb-ai-readiness-sitewide').html(buildChecklistHtml(view.sitewide || [], false));
    }

    function updateFullReviewActionsVisibility() {
        var $wrap = jQuery('#sb-seo-full-review-actions');
        if (!$wrap.length) {
            return;
        }

        $wrap.removeClass('sb-hidden');
        $wrap.find('.sb-seo-analysis-buttons, .sb-full-review-help, h3').show();
    }
    window.updateFullReviewActionsVisibility = updateFullReviewActionsVisibility;

    /**
     * Read the live focus keyword from SEO Booster fields or the active SEO plugin UI.
     * Rank Math often keeps the value in its data store until the post is saved.
     */
    function getCurrentFocusKeyword() {
        var fromSb = $('#sb_seo_focus_keyword').val();
        if (fromSb && String(fromSb).trim() !== '') {
            return String(fromSb).trim().split(',')[0].trim();
        }

        var seoPlugin = (typeof sb_seo_metabox !== 'undefined' && sb_seo_metabox.seo_plugin) ? sb_seo_metabox.seo_plugin : {};
        var pluginSlug = seoPlugin.plugin || '';
        var fields = seoPlugin.fields || {};

        if (pluginSlug === 'rankmath' && window.wp && wp.data && typeof wp.data.select === 'function') {
            try {
                var store = wp.data.select('rank-math');
                if (store) {
                    var kw = '';
                    if (typeof store.getFocusKeyword === 'function') {
                        kw = store.getFocusKeyword();
                    } else if (typeof store.getFocusKeywords === 'function') {
                        var list = store.getFocusKeywords();
                        if (Array.isArray(list) && list.length) {
                            var first = list[0];
                            if (typeof first === 'string') {
                                kw = first;
                            } else if (first && typeof first === 'object') {
                                kw = first.value || first.id || first.keyword || '';
                            }
                        } else if (typeof list === 'string') {
                            kw = list;
                        }
                    }
                    if (kw && String(kw).trim() !== '') {
                        return String(kw).trim().split(',')[0].trim();
                    }
                }
            } catch (e) {
                // Fall through to DOM selectors.
            }
        }

        var selectors = [];
        if (fields.focus_keyword) {
            selectors.push(fields.focus_keyword);
        }
        if (pluginSlug === 'rankmath') {
            selectors.push('#rank_math_focus_keyword', 'input[name="rank_math_focus_keyword"]');
        } else if (pluginSlug === 'yoast') {
            selectors.push('#yoast_wpseo_focuskw', 'input[name="yoast_wpseo_focuskw"]');
        } else if (pluginSlug === 'seopress') {
            selectors.push('#seopress_analysis_target_kw', 'input[name="seopress_analysis_target_kw"]');
        } else if (pluginSlug === 'aioseo') {
            selectors.push('#aioseo-keyphrase', '#aioseo_keywords', 'input[name="aioseo-keyphrase"]');
        }

        for (var i = 0; i < selectors.length; i++) {
            var $el = $(selectors[i]);
            if (!$el.length) {
                continue;
            }
            var val = $el.val();
            if (val && String(val).trim() !== '') {
                return String(val).trim().split(',')[0].trim();
            }
        }

        // Rank Math Tagify / chip UI (classic editor).
        var $tag = $('.rank-math-focus-keyword .tagify__tag-text, .rank-math-focus-keyword .rank-math-tag').first();
        if ($tag.length) {
            var tagText = $.trim($tag.text());
            if (tagText) {
                return tagText;
            }
        }

        return '';
    }

    // Load existing analysis on page load
    function loadExistingAnalysis() {
        var objectId = $('#sb-seo-item-id').val();
        var objectType = $('#sb-seo-item-type').val();
        
        if (!objectId) {
            showNotAnalyzedMessage();
            return;
        }

        // Get current input field values
        var currentTitle = $('#sb_seo_title').val();
        var currentDescription = $('#sb_seo_description').val();
        var currentFocusKeyword = getCurrentFocusKeyword();

        // Make AJAX request to get saved analysis results
        $.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_seo_get_saved_analysis',
                object_id: objectId,
                object_type: objectType,
                current_title: currentTitle,
                current_description: currentDescription,
                current_focus_keyword: currentFocusKeyword,
                nonce: sb_seo_metabox.nonce
            },
            success: function(response) {
                $('#sb-score-loading').hide();
                
                // Check if excluded
                if (response.success && response.data && response.data.excluded) {
                    updateExclusionUI(true);
                    return;
                }
                
                // Check if we have a valid score (score can be 0, which is valid, so check for null explicitly)
                var hasScore = response.data && response.data.score !== null && response.data.score !== undefined;
                var hasIssues = response.data && response.data.issues && response.data.issues.length > 0;
                
                if (response.success && response.data && (hasScore || hasIssues)) {
                    // Check if content has changed
                    var contentChanged = response.data.content_changed || false;
                    
                    // Check if analysis needs refresh (missing checks)
                    var needsRefresh = response.data.needs_refresh || false;
                    var refreshReason = response.data.refresh_reason || '';
                    
                    // Display saved results - handle the actual format returned by sb_seo_get_saved_analysis
                    var resultsData = {
                        score: response.data.score !== null && response.data.score !== undefined ? response.data.score : null,
                        issues: response.data.issues || [],
                        improvements: response.data.improvements || [],
                        opportunities: response.data.opportunities || [],
                        good: response.data.good || [],
                        not_applicable: response.data.not_applicable || [],
                        content_changed: contentChanged,
                        needs_refresh: needsRefresh,
                        refresh_reason: refreshReason,
                        metadata: response.data.metadata || null,
                        db_metadata: response.data.db_metadata || null
                    };
                    
                    // Show warning banner if content has changed
                    if (contentChanged) {
                        showContentChangedWarning();
                        updateFullReviewActionsVisibility('warning');
                    } else {
                        // Remove any existing warning
                        $('#sb-content-changed-warning').remove();
                    }
                    
                    // Show refresh warning if analysis is missing checks
                    if (needsRefresh) {
                        showRefreshWarning(refreshReason);
                        updateFullReviewActionsVisibility('warning');
                    } else {
                        // Remove any existing refresh warning
                        $('#sb-analysis-refresh-warning').remove();
                    }

                    if (!contentChanged && !needsRefresh) {
                        updateFullReviewActionsVisibility('fresh');
                    }
                    
                    window.displayAnalysisResults(resultsData);
                } else {
                    // No saved results, show "not analyzed yet" message
                    showNotAnalyzedMessage();
                }
            },
            error: function() {
                $('#sb-score-loading').hide();
                // On error, show "not analyzed yet" message
                showNotAnalyzedMessage();
            }
        });
    }

    // SEO Analysis functionality
    function runSEOAnalysis() {
        var objectId = $('#sb-seo-item-id').val();
        var objectType = $('#sb-seo-item-type').val();
        
        if (!objectId) {
            return;
        }

        // Get current input field values
        var currentTitle = $('#sb_seo_title').val();
        var currentDescription = $('#sb_seo_description').val();
        var currentFocusKeyword = getCurrentFocusKeyword();

        // Disable analysis button during request
        $('#sb-download-and-analyze').prop('disabled', true);

        // Show loading state with timer
        var startTime = Date.now();
        var timerInterval = setInterval(function() {
            var elapsed = Math.floor((Date.now() - startTime) / 1000);
            $('#sb-analysis-results').html('<div class="sb-loading">' + sb_seo_metabox.strings.loading + ' (' + elapsed + 's)</div>');
        }, 1000);
        $('#sb-score-number').text('-');

        // Make AJAX request to get saved analysis results
        $.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_seo_get_saved_analysis',
                object_id: objectId,
                object_type: objectType,
                current_title: currentTitle,
                current_description: currentDescription,
                current_focus_keyword: currentFocusKeyword,
                nonce: sb_seo_metabox.nonce
            },
            success: function(response) {
                clearInterval(timerInterval);
                
                if (response.success && response.data && (response.data.results || response.data.score !== undefined)) {
                    // Display saved results - handle both old format (response.data.results) and new format (response.data directly)
                    var resultsData = response.data.results || response.data;
                    displayAnalysisResults(resultsData);
                } else {
                    // No saved results, run new analysis
                    runNewAnalysis();
                }
            },
            error: function() {
                clearInterval(timerInterval);
                // On error, show "not analyzed yet" message
                showNotAnalyzedMessage();
            },
            complete: function() {
                // Re-enable analysis button
                $('#sb-download-and-analyze').prop('disabled', false);
            }
        });
    }

    // Show "not analyzed yet" message
    function showNotAnalyzedMessage() {
        var html = '<div class="sb-not-analyzed">';
        html += '<div class="sb-not-analyzed-icon">';
        html += '<span class="dashicons dashicons-chart-line"></span>';
        html += '</div>';
        html += '<div class="sb-not-analyzed-content">';
        html += '<h4>' + sb_seo_metabox.strings.not_analyzed_title + '</h4>';
        html += '<p>' + sb_seo_metabox.strings.not_analyzed_message + '</p>';
        html += '</div>';
        html += '</div>';
        
        $('#sb-analysis-results').html(html);
        $('#sb-score-number').text('-');
        
        // Reset score circle
        var scoreCircle = $('#sb-score-circle');
        scoreCircle.removeClass('green yellow red');

        $('#sb-content-changed-warning').remove();
        $('#sb-analysis-refresh-warning').remove();
        updateFullReviewActionsVisibility('needed');
    }

    // Show content changed warning
    function showContentChangedWarning() {
        // Remove any existing warning
        $('#sb-content-changed-warning').remove();
        
        var warningHtml = '<div id="sb-content-changed-warning" class="sb-content-changed-warning">';
        warningHtml += '<div class="sb-warning-inner">';
        warningHtml += '<span class="dashicons dashicons-warning"></span>';
        warningHtml += '<span><strong>Content has changed.</strong> Click to reanalyze.</span>';
        warningHtml += '</div>';
        warningHtml += '<button type="button" id="sb-reanalyze-button" class="button button-primary">Reanalyze</button>';
        warningHtml += '</div>';
        
        // Insert warning before analysis results
        $('#sb-analysis-results').before(warningHtml);
        updateFullReviewActionsVisibility('warning');
        
        // Handle reanalyze button click
        $('#sb-reanalyze-button').on('click', function() {
            $(this).prop('disabled', true).text('Analyzing...');
            downloadAndAnalyzeFullPage();
        });
    }

    // Show analysis refresh warning (missing checks)
    function showRefreshWarning(reason) {
        // Remove any existing warning
        $('#sb-analysis-refresh-warning').remove();
        
        var warningHtml = '<div id="sb-analysis-refresh-warning" class="sb-analysis-refresh-warning">';
        warningHtml += '<div class="sb-warning-inner">';
        warningHtml += '<span class="dashicons dashicons-info"></span>';
        warningHtml += '<span><strong>' + (reason || 'Analysis may be incomplete.') + '</strong></span>';
        warningHtml += '</div>';
        warningHtml += '<button type="button" id="sb-refresh-analysis-button" class="button button-primary">Re-run Analysis</button>';
        warningHtml += '</div>';
        
        // Insert warning before analysis results
        $('#sb-analysis-results').before(warningHtml);
        updateFullReviewActionsVisibility('warning');
        
        // Handle refresh button click
        $('#sb-refresh-analysis-button').on('click', function() {
            $(this).prop('disabled', true).text('Analyzing...');
            downloadAndAnalyzeFullPage();
        });
    }

    // Run new analysis (not saved results)
    function runNewAnalysis() {
        var objectId = $('#sb-seo-item-id').val();
        var objectType = $('#sb-seo-item-type').val();
        
        // Get current input field values
        var currentTitle = $('#sb_seo_title').val();
        var currentDescription = $('#sb_seo_description').val();
        var currentFocusKeyword = getCurrentFocusKeyword();
        
        // Disable analysis button during request
        $('#sb-download-and-analyze').prop('disabled', true);
        
        // Start timer
        var startTime = Date.now();
        var timerInterval = setInterval(function() {
            var elapsed = Math.floor((Date.now() - startTime) / 1000);
            $('#sb-analysis-results').html('<div class="sb-loading">' + sb_seo_metabox.strings.loading + ' (' + elapsed + 's)</div>');
        }, 1000);
        
        // Make AJAX request for new analysis
        $.ajax({
            url: sb_seo_metabox.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_seo_analysis',
                object_id: objectId,
                object_type: objectType,
                current_title: currentTitle,
                current_description: currentDescription,
                current_focus_keyword: currentFocusKeyword,
                nonce: sb_seo_metabox.nonce
            },
            success: function(response) {
                clearInterval(timerInterval);
                if (response.success) {
                    // Remove content changed warning after successful analysis
                    jQuery('#sb-content-changed-warning').remove();
                    
                    displayAnalysisResults(response.data);
                } else {
                    jQuery('#sb-analysis-results').html('<div class="sb-loading">' + sb_seo_metabox.strings.analysis_failed + '</div>');
                }
            },
            error: function() {
                clearInterval(timerInterval);
                jQuery('#sb-analysis-results').html('<div class="sb-loading">' + sb_seo_metabox.strings.analysis_failed + '</div>');
            },
            complete: function() {
                // Re-enable analysis button
                jQuery('#sb-download-and-analyze').prop('disabled', false);
            }
        });
    }

    // Download and analyze full page content - function moved to global scope above


    // Run analysis on page load - always load via AJAX for consistency
    // Show loading indicator first
    jQuery('#sb-analysis-results').html('<div class="sb-loading">Checking for old analysis...</div>');
    jQuery('#sb-score-number').text('-');
    // Don't show score loading during initial load - only show timestamp after analysis
    
    // Load existing analysis (skip on attachment pages)
    if (!isAttachmentPage()) {
        loadExistingAnalysis();
    }

    // Run analysis when content changes (debounced)
    var analysisTimeout;
    jQuery('#sb_seo_title, #sb_seo_description, #sb_seo_focus_keyword').on('input', function() {
        clearTimeout(analysisTimeout);
        analysisTimeout = setTimeout(function() {
            runSEOAnalysis();
        }, 1000);
    });

    // Enable analysis button after document is ready
    jQuery('#sb-download-and-analyze').prop('disabled', false);
    
    // Download and analyze full page button
    jQuery('#sb-download-and-analyze').on('click', function() {
        downloadAndAnalyzeFullPage();
    });

    // Collapsible sections functionality
    jQuery(document).on('click', '.sb-collapsible-header', function() {
        var target = jQuery(this).data('target');
        var content = jQuery('#' + target);
        var icon = jQuery(this).find('.sb-collapse-icon');
        
        if (content.is(':visible')) {
            content.slideUp(200);
            icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        } else {
            content.slideDown(200);
            icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        }
    });

    // ===== NEW AI FEATURES =====
    
    // Progress display variables
    var progressTimer = null;
    var progressSeconds = 0;

    // Progress display functions
    function showProgress(message, seconds) {
        jQuery('#sb-ai-progress').show();
        jQuery('.sb-progress-message').text(message);
        progressSeconds = seconds || 0;
        updateTimer();
        
        // Start timer
        if (progressTimer) clearInterval(progressTimer);
        progressTimer = setInterval(function() {
            progressSeconds++;
            updateTimer();
        }, 1000);
    }

    function updateTimer() {
        jQuery('.sb-progress-timer').text('(' + progressSeconds + 's)');
    }

    function hideProgress() {
        jQuery('#sb-ai-progress').hide();
        if (progressTimer) clearInterval(progressTimer);
        progressTimer = null;
    }

    // Image AI Generation Handlers (only if on attachment page)
    if ($('#sb-generate-image-descriptions').length) {
        var $generateImageBtn = $('#sb-generate-image-descriptions');
        var $imageStatus = $('#sb-image-generation-status');
        var $imageResults = $('#sb-image-generation-results');
        var $applyImageBtn = $('#sb-apply-image-content');
        var $restoreImageBtn = $('#sb-restore-image-content');

        // Checkbox change handler - enable/disable and fade input fields
        $(document).on('change', '.sb-field-checkbox', function() {
            var $checkbox = $(this);
            var fieldName = $checkbox.data('field');
            var $input = $('.sb-generated-input[data-field="' + fieldName + '"]');
            var isChecked = $checkbox.is(':checked');
            
            $input.prop('disabled', !isChecked);
            if (isChecked) {
                $input.css('opacity', '1').css('pointer-events', 'auto');
            } else {
                $input.css('opacity', '0.5').css('pointer-events', 'none');
            }
        });

        // Initialize checkbox states on page load
        $('.sb-field-checkbox').each(function() {
            var $checkbox = $(this);
            var fieldName = $checkbox.data('field');
            var $input = $('.sb-generated-input[data-field="' + fieldName + '"]');
            var isChecked = $checkbox.is(':checked');
            
            $input.prop('disabled', !isChecked);
            if (isChecked) {
                $input.css('opacity', '1').css('pointer-events', 'auto');
            } else {
                $input.css('opacity', '0.5').css('pointer-events', 'none');
            }
        });

        // Generate image descriptions
        $generateImageBtn.on('click', function() {
            var attachmentId = $('#sb-attachment-id').val() || $('#post_ID').val();
            if (!attachmentId) {
                showImageNotification('Invalid attachment ID', 'error');
                return;
            }

            var $btn = $(this);
            var originalText = $btn.html();
            
            // Show loading state
            $btn.prop('disabled', true);
            $imageStatus.show().find('.sb-status-message').text('Analyzing image and generating descriptions...');
            $imageResults.hide();

            $.ajax({
                url: sb_seo_metabox.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_seo_generate_image_descriptions',
                    attachment_id: attachmentId,
                    nonce: sb_seo_metabox.nonce
                },
                success: function(response) {
                    $btn.prop('disabled', false).html(originalText);
                    $imageStatus.hide();

                    if (response.success && response.data) {
                        // Populate input fields with generated content
                        $('#sb-generated-title').val(response.data.title || '');
                        $('#sb-generated-alt').val(response.data.alt_text || '');
                        $('#sb-generated-caption').val(response.data.caption || '');
                        $('#sb-generated-description').val(response.data.description || '');
                        
                        // Ensure all checkboxes are checked and inputs enabled
                        $('.sb-field-checkbox').prop('checked', true);
                        $('.sb-generated-input').prop('disabled', false).css('opacity', '1').css('pointer-events', 'auto');
                        
                        $imageResults.show();
                        // Hide previously generated section if it exists
                        $('.sb-previously-generated').hide();
                    } else {
                        showImageNotification(response.data && response.data.message ? response.data.message : 'Failed to generate descriptions', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).html(originalText);
                    $imageStatus.hide();
                    showImageNotification('An error occurred while generating descriptions. Please try again.', 'error');
                }
            });
        });

        // Apply generated content to image
        $applyImageBtn.on('click', function() {
            var attachmentId = $('#sb-attachment-id').val() || $('#post_ID').val();
            if (!attachmentId) {
                showImageNotification('Invalid attachment ID', 'error');
                return;
            }

            // Get values from input fields, only if checkbox is checked
            var title = '';
            var altText = '';
            var caption = '';
            var description = '';
            var dataToSend = {};

            if ($('.sb-field-checkbox[data-field="title"]').is(':checked')) {
                title = $('#sb-generated-title').val();
                if (title) dataToSend.title = title;
            }
            
            if ($('.sb-field-checkbox[data-field="alt_text"]').is(':checked')) {
                altText = $('#sb-generated-alt').val();
                if (altText) dataToSend.alt_text = altText;
            }
            
            if ($('.sb-field-checkbox[data-field="caption"]').is(':checked')) {
                caption = $('#sb-generated-caption').val();
                if (caption) dataToSend.caption = caption;
            }
            
            if ($('.sb-field-checkbox[data-field="description"]').is(':checked')) {
                description = $('#sb-generated-description').val();
                if (description) dataToSend.description = description;
            }

            if (Object.keys(dataToSend).length === 0) {
                showImageNotification('No content selected to apply. Please check at least one field.', 'error');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Applying...');

            // Build AJAX data object
            var ajaxData = {
                action: 'sb_seo_apply_image_content',
                attachment_id: attachmentId,
                nonce: sb_seo_metabox.nonce
            };
            
            // Only include fields that are checked
            if (dataToSend.title !== undefined) ajaxData.title = dataToSend.title;
            if (dataToSend.alt_text !== undefined) ajaxData.alt_text = dataToSend.alt_text;
            if (dataToSend.caption !== undefined) ajaxData.caption = dataToSend.caption;
            if (dataToSend.description !== undefined) ajaxData.description = dataToSend.description;

            // Apply content via AJAX
            $.ajax({
                url: sb_seo_metabox.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    $btn.prop('disabled', false).text('Apply to Image');
                    
                    if (response.success) {
                        // Update form fields to reflect changes
                        if (dataToSend.title) {
                            var $titleField = $('#title, input[name="attachments[' + attachmentId + '][post_title]"]');
                            if ($titleField.length) {
                                $titleField.val(dataToSend.title).trigger('change');
                            }
                        }
                        
                        if (dataToSend.alt_text) {
                            var $altField = $('#attachment_alt, input[name="attachments[' + attachmentId + '][alt]"]');
                            if ($altField.length) {
                                $altField.val(dataToSend.alt_text).trigger('change');
                            }
                        }
                        
                        if (dataToSend.caption) {
                            var $captionField = $('#attachment_caption, textarea[name="attachments[' + attachmentId + '][post_excerpt]"]');
                            if ($captionField.length) {
                                $captionField.val(dataToSend.caption).trigger('change');
                            }
                        }
                        
                        if (dataToSend.description) {
                            var $descField = $('#attachment_description, textarea[name="attachments[' + attachmentId + '][post_content]"]');
                            if ($descField.length) {
                                $descField.val(dataToSend.description).trigger('change');
                            }
                        }
                        
                        // Try block editor update
                        if (window.wp && wp.data && wp.data.dispatch) {
                            try {
                                var updates = {};
                                if (dataToSend.title) updates.title = dataToSend.title;
                                if (dataToSend.alt_text) updates.alt_text = dataToSend.alt_text;
                                if (dataToSend.caption) updates.caption = dataToSend.caption;
                                if (dataToSend.description) updates.description = dataToSend.description;
                                
                                if (Object.keys(updates).length > 0) {
                                    wp.data.dispatch('core').editEntityRecord('postType', 'attachment', parseInt(attachmentId), updates);
                                }
                            } catch (e) {
                            }
                        }
                        
                        showImageNotification('Content applied successfully!');
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        showImageNotification(response.data && response.data.message ? response.data.message : 'Failed to apply content', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Apply to Image');
                    showImageNotification('An error occurred while applying content. Please try again.', 'error');
                }
            });
        });

        // Restore previous content
        $restoreImageBtn.on('click', function() {
            var attachmentId = $('#sb-attachment-id').val() || $('#post_ID').val();
            if (!attachmentId) {
                showImageNotification('Invalid attachment ID', 'error');
                return;
            }

            var $btn = $(this);

            window.SBModal.confirm('Are you sure you want to restore the previous content? This will overwrite the current values.').then(function (confirmed) {
                if (!confirmed) {
                    return;
                }

                $btn.prop('disabled', true).text('Restoring...');

                $.ajax({
                    url: sb_seo_metabox.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sb_seo_restore_image_content',
                        attachment_id: attachmentId,
                        nonce: sb_seo_metabox.nonce
                    },
                success: function(response) {
                    $btn.prop('disabled', false).text('Restore Previous');

                    if (response.success && response.data) {
                        // Update form fields
                        if (response.data.title !== undefined) {
                            var $titleField = $('#title, input[name="attachments[' + attachmentId + '][post_title]"]');
                            if ($titleField.length) {
                                $titleField.val(response.data.title).trigger('change');
                            }
                        }
                        
                        if (response.data.alt_text !== undefined) {
                            var $altField = $('#attachment_alt, input[name="attachments[' + attachmentId + '][alt]"]');
                            if ($altField.length) {
                                $altField.val(response.data.alt_text).trigger('change');
                            }
                        }
                        
                        if (response.data.caption !== undefined) {
                            var $captionField = $('#attachment_caption, textarea[name="attachments[' + attachmentId + '][post_excerpt]"]');
                            if ($captionField.length) {
                                $captionField.val(response.data.caption).trigger('change');
                            }
                        }
                        
                        if (response.data.description !== undefined) {
                            var $descField = $('#attachment_description, textarea[name="attachments[' + attachmentId + '][post_content]"]');
                            if ($descField.length) {
                                $descField.val(response.data.description).trigger('change');
                            }
                        }

                        // Update block editor if available
                        if (window.wp && wp.data && wp.data.dispatch) {
                            try {
                                var updates = {};
                                if (response.data.title !== undefined) updates.title = response.data.title;
                                if (response.data.alt_text !== undefined) updates.alt_text = response.data.alt_text;
                                if (response.data.caption !== undefined) updates.caption = response.data.caption;
                                if (response.data.description !== undefined) updates.description = response.data.description;
                                
                                if (Object.keys(updates).length > 0) {
                                    wp.data.dispatch('core').editEntityRecord('postType', 'attachment', parseInt(attachmentId), updates);
                                }
                            } catch (e) {
                            }
                        }

                        showImageNotification('Previous content restored successfully!');
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        showImageNotification(response.data && response.data.message ? response.data.message : 'Failed to restore content', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Restore Previous');
                    showImageNotification('An error occurred while restoring content. Please try again.', 'error');
                }
            });
            });
        });
    }
});