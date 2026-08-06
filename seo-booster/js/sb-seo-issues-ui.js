/* global sb_seo_issues,jQuery:true */
/**
 * SEO Issues UI Functions
 * Handles URL expansion, finding actions, and issue type display
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
     * Expand URL issues to show details for a specific row.
     *
     * @since 6.1.26
     * @param {number} urlId URL ID.
     * @param {jQuery} [$clickedRow] Optional specific row (Top 10 vs All URLs).
     * @return void
     */
    window.SB_SEO_Issues.expandUrlIssues = function(urlId, $clickedRow) {
        var $row = ($clickedRow && $clickedRow.length)
            ? $clickedRow
            : $('tr.sb-url-row[data-url-id="' + urlId + '"]').first();
        var $detailRow = $row.next('.sb-url-detail-row');
        var $button = $row.find('.sb-expand-url');
        var strings = sb_seo_issues.strings || {};

        // If already expanded, collapse it
        if ($detailRow.length > 0 && $detailRow.is(':visible')) {
            $detailRow.slideUp(300, function() {
                $(this).remove();
            });
            $button.attr('aria-expanded', 'false').text(strings.view_possibilities || 'View possibilities');
            return;
        }

        $button.prop('disabled', true);

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
                    $button.attr('aria-expanded', 'true').text(strings.hide_possibilities || 'Hide possibilities');
                } else {
                    window.SB_SEO_Issues.showError(response.data && response.data.message ? response.data.message : 'Failed to load issues');
                }
            },
            error: function(xhr, status, error) {
                window.SB_SEO_Issues.showError('Network error occurred: ' + error);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    };

    /**
     * Allow only http(s) and relative admin URLs in href attributes.
     *
     * @param {string} url Candidate URL.
     * @return {string} Safe URL or empty string.
     */
    window.SB_SEO_Issues.safeHref = function(url) {
        if (!url || typeof url !== 'string') {
            return '';
        }
        var trimmed = $.trim(url);
        if (!trimmed) {
            return '';
        }
        if (trimmed.charAt(0) === '/' || trimmed.charAt(0) === '#') {
            return trimmed;
        }
        if (/^https?:\/\//i.test(trimmed)) {
            return trimmed;
        }
        return '';
    };

    /**
     * Render a single issue row with actions.
     *
     * @param {object} issue Issue object.
     * @param {string} iconClass Dashicon class.
     * @param {string} color Icon color.
     * @param {string} editUrl Fallback edit URL for the page.
     * @return {string} HTML fragment.
     */
    window.SB_SEO_Issues.renderIssueRow = function(issue, iconClass, color, editUrl) {
        var html = '';
        var strings = sb_seo_issues.strings || {};
        var issueId = issue.id || 0;
        var rowEdit = window.SB_SEO_Issues.safeHref(issue.edit_url || editUrl || '');
        var tool = issue.tool || null;
        var toolUrl = tool && tool.url ? window.SB_SEO_Issues.safeHref(tool.url) : '';

        html += '<div class="sb-analysis-item" data-issue-id="' + issueId + '">';
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
                        var usedHref = window.SB_SEO_Issues.safeHref(usedItem.url || '');
                        if (usedHref && usedItem.title) {
                            usedByLinks.push('<a href="' + window.SB_SEO_Issues.escapeHtml(usedHref) + '">' + window.SB_SEO_Issues.escapeHtml(usedItem.title) + '</a>');
                        } else if (usedItem.title) {
                            usedByLinks.push(window.SB_SEO_Issues.escapeHtml(usedItem.title));
                        }
                    });
                    html += usedByLinks.join(', ');
                    html += '</div>';
                }

                var issueKey = issue.key || issue.issue_key || '';
                if (extraData.redirect_to || (extraData.status_code && (issueKey === 'url_redirected' || issueKey === 'url_unreachable'))) {
                    html += '<div class="sb-extra-data sb-reachability-extra">';
                    if (extraData.status_code) {
                        html += '<span>HTTP ' + window.SB_SEO_Issues.escapeHtml(String(extraData.status_code)) + '</span>';
                    }
                    var redirectHref = window.SB_SEO_Issues.safeHref(extraData.redirect_to || '');
                    if (redirectHref) {
                        html += (extraData.status_code ? ' · ' : '');
                        html += '<a href="' + window.SB_SEO_Issues.escapeHtml(redirectHref) + '" target="_blank" rel="noopener noreferrer">';
                        html += window.SB_SEO_Issues.escapeHtml(extraData.redirect_to);
                        html += '</a>';
                    }
                    html += '</div>';
                }
            } catch (e) {
                // If JSON parsing fails, ignore extra_data
            }
        }

        html += '<div class="sb-issue-actions">';
        if (rowEdit) {
            html += '<a class="button button-small button-primary" href="' + window.SB_SEO_Issues.escapeHtml(rowEdit) + '">';
            html += window.SB_SEO_Issues.escapeHtml(strings.open_in_editor || 'Open in editor');
            html += '</a> ';
        }
        if (toolUrl && tool.label) {
            html += '<a class="button button-small" href="' + window.SB_SEO_Issues.escapeHtml(toolUrl) + '">';
            html += window.SB_SEO_Issues.escapeHtml(tool.label);
            html += '</a> ';
        }
        if (issueId && sb_seo_issues.can_triage) {
            html += '<button type="button" class="button button-small sb-mark-done" data-issue-id="' + issueId + '">';
            html += window.SB_SEO_Issues.escapeHtml(strings.mark_as_done || 'Mark as done');
            html += '</button> ';
            html += '<button type="button" class="button button-small sb-ignore-issue" data-issue-id="' + issueId + '">';
            html += window.SB_SEO_Issues.escapeHtml(strings.ignore || 'Ignore');
            html += '</button>';
        }
        html += '</div>';

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

        var $newRow = $('<tr class="sb-url-detail-row" data-url-id="' + urlId + '"><td colspan="4"></td></tr>');
        var $cell = $newRow.find('td');
        var html = '<div class="sb-url-issues-inline">';

        var issues = data.issues || [];
        var opportunities = data.opportunities || [];
        var strings = sb_seo_issues.strings || {};
        var editUrl = data.edit_url || '';
        var hasContent = issues.length + opportunities.length > 0;

        if (!hasContent) {
            html += '<p>' + (strings.no_recent || 'No recent analysis') + '</p>';
        } else {
            if (issues.length > 0) {
                html += '<div class="sb-url-issues-section">';
                html += '<h4 class="sb-url-section-title">' + (strings.possibilities || 'Issues') + ' (' + issues.length + ')</h4>';
                issues.forEach(function(issue) {
                    var style = window.SB_SEO_Issues.getActionableStyle(issue.severity);
                    html += window.SB_SEO_Issues.renderIssueRow(issue, style.icon, style.color, editUrl);
                });
                html += '</div>';
            }

            if (opportunities.length > 0) {
                html += '<div class="sb-url-issues-section sb-url-suggestions-section">';
                html += '<h4 class="sb-url-section-title sb-muted-section-title">' + (strings.suggestions || 'Suggestions') + ' (' + opportunities.length + ')</h4>';
                opportunities.forEach(function(issue) {
                    html += window.SB_SEO_Issues.renderIssueRow(issue, 'dashicons-lightbulb', '#646970', editUrl);
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
     * Sync issue count badges across duplicate URL rows after a status change.
     *
     * @param {number} urlId URL ID.
     * @param {number} delta Change in active issue count (usually -1 or +1).
     * @return void
     */
    window.SB_SEO_Issues.syncUrlRowCounts = function(urlId, delta) {
        if (!urlId || !delta) {
            return;
        }
        $('tr.sb-url-row[data-url-id="' + urlId + '"]').each(function() {
            var $count = $(this).find('.sb-issue-count, .sb-issues-count').first();
            if (!$count.length) {
                return;
            }
            var current = parseInt($count.text().replace(/[^\d]/g, ''), 10) || 0;
            var next = Math.max(0, current + delta);
            $count.text(next);
        });
    };

    /**
     * Show inline confirmation with Undo after marking done / ignored.
     *
     * @param {jQuery} $item Analysis item.
     * @param {string} status fixed|ignored_permanent.
     * @param {object} cachedIssue Cached issue data for restore.
     * @return void
     */
    window.SB_SEO_Issues.showStatusConfirmation = function($item, status, cachedIssue) {
        var strings = sb_seo_issues.strings || {};
        var label = status === 'fixed'
            ? (strings.marked_as_done || 'Marked as done')
            : (strings.ignored || 'Ignored');
        var issueId = cachedIssue.id || $item.data('issue-id');
        var urlId = $item.closest('.sb-url-detail-row').data('url-id') ||
            $item.closest('.sb-url-detail-row').prev('.sb-url-row').data('url-id');

        var $confirm = $(
            '<div class="sb-issue-status-confirm" data-issue-id="' + issueId + '">' +
            '<span class="sb-issue-status-confirm__label">' + window.SB_SEO_Issues.escapeHtml(label) + '</span> ' +
            '<button type="button" class="button-link sb-undo-issue" data-issue-id="' + issueId + '">' +
            window.SB_SEO_Issues.escapeHtml(strings.undo || 'Undo') +
            '</button></div>'
        );
        $confirm.data('cachedIssue', cachedIssue);
        $confirm.data('urlId', urlId);

        $item.replaceWith($confirm);

        var undoTimer = setTimeout(function() {
            window.SB_SEO_Issues.finalizeStatusRemoval($confirm, urlId);
        }, 8000);

        $confirm.data('undoTimer', undoTimer);
        $confirm.find('.sb-undo-issue').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            clearTimeout(undoTimer);
            window.SB_SEO_Issues.undoIssueStatus(issueId, $confirm, cachedIssue, urlId);
        });
    };

    /**
     * Remove confirmation row and clean up empty detail panels.
     *
     * @param {jQuery} $confirm Confirmation element.
     * @param {number} urlId URL ID.
     * @return void
     */
    window.SB_SEO_Issues.finalizeStatusRemoval = function($confirm, urlId) {
        var strings = sb_seo_issues.strings || {};
        var $detail = $confirm.closest('.sb-url-detail-row');
        var $urlRow = $detail.prev('.sb-url-row');

        $confirm.slideUp(200, function() {
            $(this).remove();
            var remaining = $detail.find('.sb-analysis-item, .sb-issue-status-confirm').length;
            if (remaining === 0) {
                $detail.slideUp(200, function() {
                    $(this).remove();
                });
                $urlRow.find('.sb-expand-url')
                    .attr('aria-expanded', 'false')
                    .text(strings.view_possibilities || 'View possibilities');
            }
        });
    };

    /**
     * Undo a status change (restore to active).
     *
     * @param {number} issueId Issue ID.
     * @param {jQuery} $confirm Confirmation element.
     * @param {object} cachedIssue Cached issue for restore.
     * @param {number} urlId URL ID.
     * @return void
     */
    window.SB_SEO_Issues.undoIssueStatus = function(issueId, $confirm, cachedIssue, urlId) {
        var strings = sb_seo_issues.strings || {};
        var $btn = $confirm.find('.sb-undo-issue');
        $btn.prop('disabled', true);

        $.ajax({
            url: sb_seo_issues.ajaxurl,
            type: 'POST',
            data: {
                action: 'sb_update_issue_status',
                nonce: sb_seo_issues.nonce,
                issue_id: issueId,
                status: 'active'
            },
            success: function(response) {
                if (!response.success) {
                    window.SB_SEO_Issues.showError(
                        (response.data && response.data.message) || (strings.error || 'An error occurred')
                    );
                    $btn.prop('disabled', false);
                    return;
                }

                var style = window.SB_SEO_Issues.getActionableStyle(cachedIssue.severity);
                var restored = window.SB_SEO_Issues.renderIssueRow(
                    cachedIssue,
                    style.icon,
                    style.color,
                    cachedIssue.edit_url || ''
                );
                $confirm.replaceWith(restored);

                if (urlId) {
                    window.SB_SEO_Issues.syncUrlRowCounts(urlId, 1);
                }

                if (response.data && window.SB_SEO_Issues.updateStatCards) {
                    window.SB_SEO_Issues.updateStatCards(response.data.stats, response.data.urls_count);
                }
            },
            error: function() {
                window.SB_SEO_Issues.showError(strings.error || 'An error occurred');
                $btn.prop('disabled', false);
            }
        });
    };

    /**
     * Update issue status via AJAX and show Undo confirmation.
     *
     * @param {number} issueId Issue ID.
     * @param {string} status fixed|ignored_permanent.
     * @param {jQuery} $button Clicked button.
     * @return void
     */
    window.SB_SEO_Issues.updateIssueStatus = function(issueId, status, $button) {
        var strings = sb_seo_issues.strings || {};
        if (!issueId || !sb_seo_issues.can_triage) {
            return;
        }

        var $item = $button.closest('.sb-analysis-item');
        if (!$item.length) {
            $item = $('.sb-analysis-item[data-issue-id="' + issueId + '"]').first();
        }

        var cachedIssue = {
            id: issueId,
            message: $item.find('.sb-issue-message').text(),
            severity: $item.closest('.sb-url-suggestions-section').length ? 'opportunity' : 'medium',
            key: '',
            issue_key: '',
            edit_url: $item.find('.sb-issue-actions a.button-primary').attr('href') || '',
            tool: null
        };

        var $toolLink = $item.find('.sb-issue-actions a.button:not(.button-primary)').first();
        if ($toolLink.length) {
            cachedIssue.tool = {
                url: $toolLink.attr('href'),
                label: $toolLink.text()
            };
        }

        // Prefer severity from icon color class context if available via data attributes later.
        var severityMatch = ($item.find('.sb-severity-icon').attr('style') || '');
        if (severityMatch.indexOf('#d63638') !== -1) {
            cachedIssue.severity = 'critical';
        } else if (severityMatch.indexOf('#dba617') !== -1) {
            cachedIssue.severity = 'high';
        } else if (severityMatch.indexOf('#00a32a') !== -1 || $item.closest('.sb-url-suggestions-section').length) {
            cachedIssue.severity = 'opportunity';
        }

        $button.prop('disabled', true);
        $item.find('.sb-mark-done, .sb-ignore-issue').prop('disabled', true);

        var urlId = $item.closest('.sb-url-detail-row').data('url-id') ||
            $item.closest('.sb-url-detail-row').prev('.sb-url-row').data('url-id');

        $.ajax({
            url: sb_seo_issues.ajaxurl,
            type: 'POST',
            data: {
                action: 'sb_update_issue_status',
                nonce: sb_seo_issues.nonce,
                issue_id: issueId,
                status: status
            },
            success: function(response) {
                if (!response.success) {
                    window.SB_SEO_Issues.showError(
                        (response.data && response.data.message) || (strings.error || 'An error occurred')
                    );
                    $item.find('.sb-mark-done, .sb-ignore-issue').prop('disabled', false);
                    return;
                }

                window.SB_SEO_Issues.showStatusConfirmation($item, status, cachedIssue);

                if (urlId) {
                    window.SB_SEO_Issues.syncUrlRowCounts(urlId, -1);
                }

                if (response.data && window.SB_SEO_Issues.updateStatCards) {
                    window.SB_SEO_Issues.updateStatCards(response.data.stats, response.data.urls_count);
                }
            },
            error: function() {
                window.SB_SEO_Issues.showError(strings.error || 'An error occurred');
                $item.find('.sb-mark-done, .sb-ignore-issue').prop('disabled', false);
            }
        });
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
