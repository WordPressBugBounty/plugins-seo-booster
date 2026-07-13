/**
 * SEO Booster Tools — Content decay (Premium).
 *
 * @package SEOBooster
 */
(function ($) {
    'use strict';

    if (typeof sbToolsContentDecay === 'undefined') {
        return;
    }

    var cfg = sbToolsContentDecay;
    var state = { allIds: [], totalFound: 0 };

    function escHtml(value) {
        return $('<div>').text(value === undefined || value === null ? '' : value).html();
    }

    function extractErrorMessage(response) {
        if (response && response.data && response.data.message) {
            return response.data.message;
        }
        return cfg.strings.error;
    }

    window.SBTools.bindDismiss('#sb-tools-decay-results-body', function ($row) {
        var postId = parseInt($row.data('post-id'), 10);
        if (!isNaN(postId)) {
            state.allIds = state.allIds.filter(function (id) {
                return parseInt(id, 10) !== postId;
            });
        }
        $row.remove();
        state.totalFound = Math.max(0, state.totalFound - 1);
        $('#sb-tools-decay-displaying-num').text(cfg.strings.matching.replace('%d', state.totalFound));
        $('#sb-tools-decay-queue-all').prop('disabled', state.totalFound === 0);
    });

    $('#sb-tools-decay-scan-btn').on('click', function () {
        if (!cfg.has_gsc) {
            window.SBTools.alert(cfg.strings.need_gsc, { tone: 'error', title: cfg.strings.error });
            return;
        }

        var $btn = $(this);
        var $spinner = $('.sb-tools-content-decay .sb-tools-scan-spinner');
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(cfg.ajax_url, {
            action: 'sb_tools_scan_content_decay',
            nonce: cfg.nonce,
        }).done(function (response) {
            if (!response.success) {
                window.SBTools.alert(extractErrorMessage(response), {
                    title: cfg.strings.error,
                    tone: 'error',
                });
                return;
            }
            var data = response.data;
            state.allIds = data.all_ids || [];
            state.totalFound = data.total_found || 0;
            var $body = $('#sb-tools-decay-results-body');
            $body.empty();
            (data.items || []).forEach(function (item) {
                var analysisParts = [escHtml(item.analysis_status || '')];
                if (item.possibilities_url) {
                    analysisParts.push(
                        '<a href="' + escHtml(item.possibilities_url) + '">' +
                        escHtml(cfg.strings.view_possibilities) +
                        '</a>'
                    );
                }
                $body.append(
                    '<tr data-post-id="' + (item.post_id || '') + '">' +
                    '<td class="title column-primary has-row-actions">' + window.SBTools.contentCellInner({
                        title: item.title,
                        editUrl: item.edit_url,
                        viewUrl: item.view_url || item.page_url,
                        slug: item.slug,
                    }) + '</td>' +
                    '<td>' + escHtml(item.recent_clicks) + ' / ' + escHtml(item.previous_clicks) + '</td>' +
                    '<td>' + escHtml(item.decline_pct) + '%</td>' +
                    '<td>' + analysisParts.join('<br>') + '</td></tr>'
                );
            });
            $('#sb-tools-decay-displaying-num').text(cfg.strings.matching.replace('%d', state.totalFound));
            $('#sb-tools-decay-queue-status').hide().text('');
            $('#sb-tools-decay-results').show();
            $('#sb-tools-decay-queue-all').prop('disabled', state.totalFound === 0);
            if (state.totalFound === 0) {
                window.SBTools.alert(cfg.strings.no_results);
            }
        }).always(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });

    $('#sb-tools-decay-queue-all').on('click', function () {
        if (!state.allIds.length) {
            return;
        }
        window.SBTools.confirm(cfg.strings.queue_confirm, {
            title: cfg.strings.queue_title,
        }).then(function (confirmed) {
            if (!confirmed) {
                return;
            }
            $.post(cfg.ajax_url, {
                action: 'sb_tools_queue_content_decay',
                nonce: cfg.nonce,
                post_ids: state.allIds,
            }).done(function (response) {
                if (response.success) {
                    var count = (response.data && response.data.total) ? response.data.total : state.allIds.length;
                    $('#sb-tools-decay-queue-status')
                        .text(cfg.strings.queue_status.replace('%d', count))
                        .show();
                    window.SBTools.alert(response.data.message || cfg.strings.queued, {
                        title: cfg.strings.queued_title,
                        tone: 'success',
                    });
                } else {
                    window.SBTools.alert(extractErrorMessage(response), {
                        title: cfg.strings.error,
                        tone: 'error',
                    });
                }
            });
        });
    });
})(jQuery);
