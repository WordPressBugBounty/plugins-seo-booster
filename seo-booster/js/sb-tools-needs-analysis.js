/**
 * SEO Booster Tools — Needs analysis (Premium).
 *
 * @package SEOBooster
 */
(function ($) {
    'use strict';

    if (typeof sbToolsNeedsAnalysis === 'undefined') {
        return;
    }

    var state = { allIds: [], totalFound: 0 };

    window.SBTools.bindDismiss('#sb-tools-needs-results-body', function ($row) {
        var postId = parseInt($row.data('post-id'), 10);
        if (!isNaN(postId)) {
            state.allIds = state.allIds.filter(function (id) {
                return parseInt(id, 10) !== postId;
            });
        }
        $row.remove();
        state.totalFound = Math.max(0, state.totalFound - 1);
        $('#sb-tools-needs-displaying-num').text(state.totalFound + ' matching');
        $('#sb-tools-needs-queue-all').prop('disabled', state.totalFound === 0);
    });

    $('#sb-tools-needs-scan-btn').on('click', function () {
        var postTypes = [];
        $('input[name="needs_post_type"]:checked').each(function () {
            postTypes.push($(this).val());
        });

        var $btn = $(this);
        var $spinner = $('.sb-tools-scan-spinner');
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(sbToolsNeedsAnalysis.ajax_url, {
            action: 'sb_tools_scan_needs_analysis',
            nonce: sbToolsNeedsAnalysis.nonce,
            filter: $('input[name="needs_filter"]:checked').val(),
            post_types: postTypes,
        }).done(function (response) {
            if (!response.success) {
                window.SBTools.alert((response.data && response.data.message) || sbToolsNeedsAnalysis.strings.error, {
                    title: sbToolsNeedsAnalysis.strings.error,
                    tone: 'error',
                });
                return;
            }
            var data = response.data;
            state.allIds = data.all_ids || [];
            state.totalFound = data.total_found || 0;
            var $body = $('#sb-tools-needs-results-body');
            $body.empty();
            (data.items || []).forEach(function (item) {
                $body.append(
                    '<tr data-post-id="' + (item.id || '') + '">' +
                    '<td class="title column-primary has-row-actions">' + window.SBTools.contentCellInner({
                        title: item.title,
                        editUrl: item.edit_url,
                        viewUrl: item.view_url,
                        slug: item.slug,
                    }) + '</td>' +
                    '<td>' + $('<div>').text(item.status).html() + '</td>' +
                    '<td>' + $('<div>').text(item.issues || '').html() + '</td></tr>'
                );
            });
            $('#sb-tools-needs-displaying-num').text(state.totalFound + ' matching');
            $('#sb-tools-needs-queue-status').hide().text('');
            $('#sb-tools-needs-results').show();
            $('#sb-tools-needs-queue-all').prop('disabled', state.totalFound === 0);
        }).always(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });

    $('#sb-tools-needs-queue-all').on('click', function () {
        if (!state.allIds.length) {
            return;
        }
        window.SBTools.confirm(sbToolsNeedsAnalysis.strings.queue_confirm, {
            title: sbToolsNeedsAnalysis.strings.queue_title,
        }).then(function (confirmed) {
            if (!confirmed) {
                return;
            }
            $.post(sbToolsNeedsAnalysis.ajax_url, {
                action: 'sb_tools_queue_needs_analysis',
                nonce: sbToolsNeedsAnalysis.nonce,
                post_ids: state.allIds,
            }).done(function (response) {
                if (response.success) {
                    var count = (response.data && response.data.total) ? response.data.total : state.allIds.length;
                    $('#sb-tools-needs-queue-status')
                        .text(sbToolsNeedsAnalysis.strings.queue_status.replace('%d', count))
                        .show();
                    window.SBTools.alert(response.data.message || sbToolsNeedsAnalysis.strings.queued, {
                        title: sbToolsNeedsAnalysis.strings.queued_title,
                        tone: 'success',
                    });
                } else {
                    window.SBTools.alert((response.data && response.data.message) || sbToolsNeedsAnalysis.strings.error, {
                        title: sbToolsNeedsAnalysis.strings.error,
                        tone: 'error',
                    });
                }
            });
        });
    });
})(jQuery);
