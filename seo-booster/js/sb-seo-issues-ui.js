/* global sb_seo_issues,jQuery:true */
/**
 * SEO Issues UI Functions
 * Handles URL expansion, view toggling, and issue type display
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */

(function($) {
    'use strict';

    // Extend the SB_SEO_Issues object with UI functions
    if (typeof window.SB_SEO_Issues === 'undefined') {
        window.SB_SEO_Issues = {};
    }

    /**
     * Expand URL issues to show details.
     *
     * @since 6.1.26
     * @param int urlId URL ID.
     * @return void
     */
    window.SB_SEO_Issues.expandUrlIssues = function(urlId) {
        var $row = $('tr.sb-url-row[data-url-id="' + urlId + '"]');
        var $detailRow = $row.next('.sb-url-detail-row');
        
        // If already expanded, collapse it
        if ($detailRow.length > 0 && $detailRow.is(':visible')) {
            $detailRow.slideUp(300, function() {
                $(this).remove();
            });
            $row.find('.sb-url-expand .dashicons').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            return;
        }
        
        // Show loading state
        $row.find('.sb-url-expand .dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        
        // Make AJAX call to get issues
        $.ajax({
            url: sb_seo_issues.ajaxurl,
            type: 'POST',
            data: {
                action: 'sb_get_url_issues',
                nonce: sb_seo_issues.nonce,
                url_id: urlId
            },
            success: function(response) {
                if (response.success && response.data.issues) {
                    window.SB_SEO_Issues.displayUrlIssues($row, response.data.issues, urlId);
                } else {
                    window.SB_SEO_Issues.showError(response.data.message || 'Failed to load issues');
                    $row.find('.sb-url-expand .dashicons').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                }
            },
            error: function(xhr, status, error) {
                window.SB_SEO_Issues.showError('Network error occurred: ' + error);
                $row.find('.sb-url-expand .dashicons').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            }
        });
    };

    /**
     * Display URL issues in an expandable row.
     *
     * @since 6.1.26
     * @param object $row jQuery row element.
     * @param object issues Issues grouped by severity.
     * @param int urlId URL ID.
     * @return void
     */
    window.SB_SEO_Issues.displayUrlIssues = function($row, issues, urlId) {
        var $detailRow = $row.next('.sb-url-detail-row');
        
        // Remove existing detail row if present
        if ($detailRow.length > 0) {
            $detailRow.remove();
        }
        
        // Create detail row
        var $newRow = $('<tr class="sb-url-detail-row"><td colspan="3"></td></tr>');
        var $cell = $newRow.find('td');
        
        var html = '<div class="sb-url-issues-inline">';
        
        // Severity order and icons
        var severities = ['critical', 'high', 'medium', 'low'];
        var severityIcons = {
            'critical': 'dashicons-warning',
            'high': 'dashicons-dismiss',
            'medium': 'dashicons-info',
            'low': 'dashicons-lightbulb'
        };
        var severityColors = {
            'critical': '#dc3545',
            'high': '#fd7e14',
            'medium': '#ffc107',
            'low': '#28a745'
        };
        
        // Collect all issues in a flat array with their severity
        var allIssues = [];
        severities.forEach(function(severity) {
            if (issues[severity] && issues[severity].length > 0) {
                issues[severity].forEach(function(issue) {
                    allIssues.push({
                        issue: issue,
                        severity: severity,
                        icon: severityIcons[severity],
                        color: severityColors[severity]
                    });
                });
            }
        });
        
        if (allIssues.length === 0) {
            html += '<p>' + sb_seo_issues.strings.no_recent + '</p>';
        } else {
            // Render all issues in a flat list
            allIssues.forEach(function(item) {
                var issue = item.issue;
                var isFixed = issue.user_status === 'fixed';
                html += '<div class="sb-analysis-item' + (isFixed ? ' sb-issue-fixed' : '') + '">';
                html += '<input type="checkbox" class="sb-issue-checkbox" data-id="' + issue.id + '"' + (isFixed ? ' checked' : '') + ' />';
                html += '<span class="sb-severity-icon dashicons ' + item.icon + '" style="color: ' + item.color + ';"></span>';
                html += '<div class="sb-issue-content-wrapper">';
                html += '<div class="sb-issue-message">' + window.SB_SEO_Issues.escapeHtml(issue.message) + '</div>';
                
                // Display extra_data if it exists
                if (issue.extra_data) {
                    try {
                        var extraData = typeof issue.extra_data === 'string' ? JSON.parse(issue.extra_data) : issue.extra_data;
                        
                        if (extraData.used_by && extraData.used_by.length > 0) {
                            html += '<div class="sb-extra-data">';
                            html += '<strong>Used by:</strong> ';
                            var usedByLinks = [];
                            extraData.used_by.forEach(function(usedItem) {
                                if (usedItem.url && usedItem.title) {
                                    usedByLinks.push('<a href="' + window.SB_SEO_Issues.escapeHtml(usedItem.url) + '">' + window.SB_SEO_Issues.escapeHtml(usedItem.title) + '</a>');
                                } else if (usedItem.title) {
                                    usedByLinks.push(window.SB_SEO_Issues.escapeHtml(usedItem.title));
                                }
                            });
                            html += usedByLinks.join(', ');
                            html += '</div>';
                        }
                    } catch (e) {
                        // If JSON parsing fails, ignore extra_data
                    }
                }
                
                html += '</div>';
                html += '</div>';
            });
        }
        
        html += '</div>';
        
        $cell.html(html);
        $row.after($newRow);
        $newRow.hide().slideDown(300);
    };

    /**
     * Escape HTML to prevent XSS.
     *
     * @since 6.1.26
     * @param string text Text to escape.
     * @return string Escaped text.
     */
    window.SB_SEO_Issues.escapeHtml = function(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    };

})(jQuery);

