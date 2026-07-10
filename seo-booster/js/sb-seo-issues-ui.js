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
                if (response.success && response.data) {
                    window.SB_SEO_Issues.displayUrlIssues($row, response.data, urlId);
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
     * Render a single issue row.
     *
     * @param {object} issue Issue object.
     * @param {string} iconClass Dashicon class.
     * @param {string} color Icon color.
     * @return {string} HTML fragment.
     */
    window.SB_SEO_Issues.renderIssueRow = function(issue, iconClass, color) {
        var html = '';

        html += '<div class="sb-analysis-item">';
        html += '<span class="sb-severity-icon dashicons ' + iconClass + '" style="color: ' + color + ';"></span>';
        html += '<div class="sb-issue-content-wrapper">';
        html += '<div class="sb-issue-message">' + window.SB_SEO_Issues.escapeHtml(issue.message) + '</div>';

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

        return html;
    };

    /**
     * Map DB severity to icon and color for actionable issues.
     *
     * @param {string} severity Severity slug.
     * @return {object} Icon metadata.
     */
    window.SB_SEO_Issues.getActionableStyle = function(severity) {
        var map = {
            critical: { icon: 'dashicons-dismiss', color: '#d63638' },
            error: { icon: 'dashicons-dismiss', color: '#d63638' },
            high: { icon: 'dashicons-warning', color: '#dba617' },
            warning: { icon: 'dashicons-warning', color: '#dba617' },
            medium: { icon: 'dashicons-info', color: '#72aee6' },
            low: { icon: 'dashicons-lightbulb', color: '#00a32a' }
        };

        return map[severity] || { icon: 'dashicons-info', color: '#72aee6' };
    };

    /**
     * Display URL issues in an expandable row.
     *
     * @since 6.1.26
     * @param {object} $row jQuery row element.
     * @param {object} data Bucket data from AJAX.
     * @param {int} urlId URL ID.
     * @return void
     */
    window.SB_SEO_Issues.displayUrlIssues = function($row, data, urlId) {
        var $detailRow = $row.next('.sb-url-detail-row');

        if ($detailRow.length > 0) {
            $detailRow.remove();
        }

        var $newRow = $('<tr class="sb-url-detail-row"><td colspan="3"></td></tr>');
        var $cell = $newRow.find('td');
        var html = '<div class="sb-url-issues-inline">';

        var issues = data.issues || [];
        var opportunities = data.opportunities || [];
        var strings = sb_seo_issues.strings || {};
        var hasContent = issues.length + opportunities.length > 0;

        if (!hasContent) {
            html += '<p>' + (strings.no_recent || 'No recent analysis') + '</p>';
        } else {
            if (issues.length > 0) {
                html += '<div class="sb-url-issues-section">';
                html += '<h4 class="sb-url-section-title">' + (strings.possibilities || 'Issues') + ' (' + issues.length + ')</h4>';
                issues.forEach(function(issue) {
                    var style = window.SB_SEO_Issues.getActionableStyle(issue.severity);
                    html += window.SB_SEO_Issues.renderIssueRow(issue, style.icon, style.color);
                });
                html += '</div>';
            }

            if (opportunities.length > 0) {
                html += '<div class="sb-url-issues-section sb-url-suggestions-section">';
                html += '<h4 class="sb-url-section-title sb-muted-section-title">' + (strings.suggestions || 'Suggestions') + ' (' + opportunities.length + ')</h4>';
                opportunities.forEach(function(issue) {
                    html += window.SB_SEO_Issues.renderIssueRow(issue, 'dashicons-lightbulb', '#646970');
                });
                html += '</div>';
            }
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
        if (typeof text !== 'string') {
            text = String(text || '');
        }
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
