/**
 * SEO Booster Tools — Autolink opportunity finder.
 */
(function ($) {
    'use strict';

    if (typeof sbToolsAutolinkOpportunities === 'undefined') {
        return;
    }

    var cfg = sbToolsAutolinkOpportunities;
    var PAGES_PER_PAGE = 25;
    var preview = window.SBToolsBatchPreview.create({
        prefix: 'sb-tools-autolink',
        strings: cfg.strings,
        renderResult: function (payload) {
            if (payload.action === 'enable_autolink') {
                return '<p>' + esc(cfg.strings.enable_autolink_label) + '</p>';
            }
            var target = payload.target_url || '';
            var path = target;
            try {
                path = new URL(target, window.location.origin).pathname;
            } catch (ignore) {
                // Keep full URL when parsing fails.
            }
            return '<p><strong>Rule created:</strong> ' + esc(payload.keyword) + ' → ' + esc(path) + '</p>';
        },
        renderFailed: function (item) {
            var scanItem = findKeywordByQueryId(item.item_id);
            var label = scanItem ? scanItem.keyword : ('Item #' + (item.item_id || ''));
            return esc(label) + ': ' + esc(item.message || cfg.strings.error);
        },
    });
    var state = {
        totalPages: 0,
        totalKeywordRules: 0,
        pageGroups: [],
        scanItems: [],
        scanFilter: 'missing_rules',
        listPage: 1,
        batchId: null,
        queue: [],
        queueIndex: 0,
        isRunning: false,
        isCancelled: false,
        activeRequest: null,
    };

    function findKeywordByQueryId(itemId) {
        var id = parseInt(itemId, 10);
        for (var i = 0; i < state.pageGroups.length; i += 1) {
            var group = state.pageGroups[i];
            var keywords = group.keywords || [];
            for (var j = 0; j < keywords.length; j += 1) {
                if (parseInt(keywords[j].query_id, 10) === id) {
                    return keywords[j];
                }
            }
        }
        for (var k = 0; k < state.scanItems.length; k += 1) {
            var row = state.scanItems[k];
            var rowId = parseInt(row.query_id || row.id || row.post_id, 10);
            if (rowId === id) {
                return row;
            }
        }
        return null;
    }

    function getFilter() {
        return $('input[name="autolink_opp_filter"]:checked').val() || 'missing_rules';
    }

    function getPostTypes() {
        var types = [];
        $('input[name="autolink_post_type"]:checked').each(function () {
            types.push($(this).val());
        });
        return types;
    }

    function extractErrorMessage(response, xhr) {
        if (response && response.data && response.data.message) {
            return response.data.message;
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            return xhr.responseJSON.data.message;
        }
        return cfg.strings.error;
    }

    function esc(value) {
        return $('<div>').text(value === undefined || value === null ? '' : value).html();
    }

    function format(template, values) {
        var out = template;
        Object.keys(values).forEach(function (key) {
            out = out.replace(key, values[key]);
        });
        return out;
    }

    function countKeywordRulesInGroups(groups) {
        var total = 0;
        groups.forEach(function (group) {
            total += (group.keywords || []).length;
        });
        return total;
    }

    function keywordMetricsTooltip(kw) {
        return 'Impr: ' + (kw.impressions || 0) +
            ', Clicks: ' + (kw.clicks || 0) +
            ', Pos: ' + (kw.position || 0);
    }

    function keywordSummary(group) {
        var keywords = group.keywords || [];
        if (!keywords.length) {
            return '';
        }
        var names = keywords.map(function (kw) {
            return kw.keyword;
        });
        var shown = names.slice(0, 3).join(', ');
        if (names.length > 3) {
            shown += ' …';
        }
        var label = keywords.length === 1
            ? cfg.strings.keywords_one
            : cfg.strings.keywords_many;
        return format(label, { '%d': keywords.length }) + ': ' + esc(shown);
    }

    function getSelectedItems() {
        if (state.scanFilter === 'enable_high_traffic') {
            var enableItems = [];
            $('.sb-tools-autolink-row-cb:checked').each(function () {
                var idx = parseInt($(this).data('index'), 10);
                if (!isNaN(idx) && state.scanItems[idx]) {
                    enableItems.push(state.scanItems[idx]);
                }
            });
            return enableItems;
        }

        var items = [];
        $('.sb-tools-autolink-page-cb:checked').each(function () {
            var $pageRow = $(this).closest('tr.sb-tools-autolink-page');
            var idx = parseInt($pageRow.data('index'), 10);
            if (isNaN(idx) || !state.pageGroups[idx]) {
                return;
            }
            var group = state.pageGroups[idx];
            var $detail = $pageRow.next('tr.sb-tools-autolink-kw-detail');
            $detail.find('.sb-tools-autolink-kw-cb:checked').each(function () {
                var kwIdx = parseInt($(this).data('kw-index'), 10);
                var kw = group.keywords[kwIdx];
                if (!kw) {
                    return;
                }
                items.push({
                    query_id: kw.query_id,
                    keyword: kw.keyword,
                    target_url: kw.target_url || group.target_url,
                    post_id: group.post_id,
                });
            });
        });
        return items;
    }

    function buildEnableRows($body, items) {
        items.forEach(function (item, index) {
            var row = $('<tr></tr>');
            row.append('<th scope="row" class="check-column"><input type="checkbox" class="sb-tools-autolink-row-cb" data-index="' + index + '" value="' + item.post_id + '" checked /></th>');
            row.append('<td class="title column-primary has-row-actions">' + window.SBTools.contentCellInner({
                title: item.title,
                editUrl: item.edit_url,
                viewUrl: item.page_url || item.target_url,
                slug: item.target_slug,
            }) + '</td>');
            row.append('<td>' + cfg.strings.enable_autolink_label + '</td>');
            row.append('<td>' + (item.clicks || 0) + ' clicks</td>');
            $body.append(row);
        });
    }

    function buildPageGroupRows($body, groups) {
        var start = (state.listPage - 1) * PAGES_PER_PAGE;
        var slice = groups.slice(start, start + PAGES_PER_PAGE);

        slice.forEach(function (group, sliceIndex) {
            var index = start + sliceIndex;
            var metrics = 'Impr: ' + (group.group_impressions || 0) +
                ', Clicks: ' + (group.group_clicks || 0);
            var candidatesNote = '';
            if (group.total_candidates > group.kept_count) {
                candidatesNote = ' <span class="description">(' +
                    format(cfg.strings.reduced_from, { '%d': group.total_candidates }) + ')</span>';
            }

            var $pageRow = $('<tr class="sb-tools-autolink-page"></tr>').attr('data-index', index);
            $pageRow.append(
                '<th scope="row" class="check-column">' +
                '<input type="checkbox" class="sb-tools-autolink-page-cb" checked />' +
                '</th>'
            );
            $pageRow.append(
                '<td class="title column-primary has-row-actions">' +
                window.SBTools.contentCellInner({
                    title: group.post_title || group.target_path || group.target_url,
                    editUrl: group.edit_url,
                    viewUrl: group.target_url,
                    slug: group.target_slug,
                }) +
                '<button type="button" class="button-link sb-tools-autolink-expand" aria-expanded="false">' +
                esc(cfg.strings.show_keywords) + '</button>' +
                '</td>'
            );
            $pageRow.append('<td class="sb-tools-autolink-kw-summary">' + keywordSummary(group) + candidatesNote + '</td>');
            $pageRow.append('<td>' + metrics + '</td>');

            var kwList = '<ul class="sb-tools-autolink-kw-list">';
            (group.keywords || []).forEach(function (kw, kwIndex) {
                kwList += '<li title="' + esc(keywordMetricsTooltip(kw)) + '">' +
                    '<label><input type="checkbox" class="sb-tools-autolink-kw-cb" data-kw-index="' + kwIndex + '" checked /> ' +
                    esc(kw.keyword) + '</label></li>';
            });
            kwList += '</ul>';

            var $detailRow = $('<tr class="sb-tools-autolink-kw-detail" hidden></tr>');
            $detailRow.append('<td colspan="4" class="sb-tools-autolink-kw-detail-cell">' + kwList + '</td>');

            $body.append($pageRow);
            $body.append($detailRow);
        });
    }

    function buildResultsTable() {
        var $body = $('#sb-tools-autolink-results-body');
        $body.empty();

        if (state.scanFilter === 'enable_high_traffic') {
            buildEnableRows($body, state.scanItems);
        } else {
            buildPageGroupRows($body, state.pageGroups);
        }
        updatePagination();
        updateSelectionState();
    }

    function updateDisplayingNum() {
        if (state.scanFilter === 'enable_high_traffic') {
            var label = state.scanItems.length === 1 ? cfg.strings.matching_one : cfg.strings.matching_many;
            $('#sb-tools-autolink-displaying-num').text(label.replace('%d', state.scanItems.length));
            return;
        }
        var pagesLabel = state.totalPages === 1 ? cfg.strings.pages_one : cfg.strings.pages_many;
        var rulesLabel = state.totalKeywordRules === 1 ? cfg.strings.rules_one : cfg.strings.rules_many;
        $('#sb-tools-autolink-displaying-num').text(
            format(pagesLabel, { '%d': state.totalPages }) +
            ' • ' +
            format(rulesLabel, { '%d': state.totalKeywordRules })
        );
    }

    function updatePagination() {
        var $nav = $('#sb-tools-autolink-pagination');
        if (state.scanFilter !== 'missing_rules' || state.totalPages <= PAGES_PER_PAGE) {
            $nav.prop('hidden', true).empty();
            return;
        }

        var totalListPages = Math.ceil(state.totalPages / PAGES_PER_PAGE);
        $nav.prop('hidden', false);
        $nav.empty();
        $nav.append(
            '<button type="button" class="button" id="sb-tools-autolink-prev"' +
            (state.listPage <= 1 ? ' disabled' : '') + '>' + esc(cfg.strings.prev_page) + '</button>'
        );
        $nav.append(
            '<span class="sb-tools-pagination-status">' +
            format(cfg.strings.page_of, {
                '%1$d': state.listPage,
                '%2$d': totalListPages,
            }) + '</span>'
        );
        $nav.append(
            '<button type="button" class="button" id="sb-tools-autolink-next"' +
            (state.listPage >= totalListPages ? ' disabled' : '') + '>' + esc(cfg.strings.next_page) + '</button>'
        );
    }

    function updateSelectionState() {
        var selectedRules = getSelectedItems().length;
        var applyAllCount = state.scanFilter === 'missing_rules'
            ? state.totalKeywordRules
            : state.scanItems.length;

        $('#sb-tools-autolink-process-selected').prop(
            'disabled',
            !cfg.autolink_on || selectedRules === 0 || state.isRunning
        );
        $('#sb-tools-autolink-process-all').prop(
            'disabled',
            !cfg.autolink_on || applyAllCount === 0 || state.isRunning
        );
        $('#sb-tools-autolink-process-all').text(
            cfg.strings.process_all_matching.replace('%d', applyAllCount)
        );
    }

    $('input[name="autolink_opp_filter"]').on('change', function () {
        $('#sb-tools-autolink-post-types-wrap').toggle(getFilter() === 'enable_high_traffic');
    });

    $('#sb-tools-autolink-scan-btn').on('click', function () {
        var $btn = $(this);
        var $spinner = $('.sb-tools-scan-spinner');
        state.scanFilter = getFilter();
        state.listPage = 1;
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $.post(cfg.ajax_url, {
            action: 'sb_tools_scan_autolink_opportunities',
            nonce: cfg.nonce,
            filter: state.scanFilter,
            post_types: getPostTypes(),
        }).done(function (response) {
            if (!response.success) {
                window.SBTools.alert(extractErrorMessage(response), { tone: 'error' });
                return;
            }
            if (state.scanFilter === 'enable_high_traffic') {
                state.scanItems = response.data.items || [];
                state.pageGroups = [];
                state.totalPages = 0;
                state.totalKeywordRules = 0;
            } else {
                state.pageGroups = response.data.items || [];
                state.scanItems = [];
                state.totalPages = response.data.total_found || state.pageGroups.length;
                state.totalKeywordRules = countKeywordRulesInGroups(state.pageGroups);
            }
            buildResultsTable();
            updateDisplayingNum();
            $('#sb-tools-autolink-results').show();
            $('#sb-tools-autolink-process-actions').prop('hidden', false);
        }).fail(function (xhr) {
            window.SBTools.alert(extractErrorMessage(null, xhr), { tone: 'error' });
        }).always(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });

    $(document).on('click', '#sb-tools-autolink-prev', function () {
        if (state.listPage > 1) {
            state.listPage -= 1;
            buildResultsTable();
        }
    });

    $(document).on('click', '#sb-tools-autolink-next', function () {
        var totalListPages = Math.ceil(state.totalPages / PAGES_PER_PAGE);
        if (state.listPage < totalListPages) {
            state.listPage += 1;
            buildResultsTable();
        }
    });

    $(document).on('click', '.sb-tools-autolink-expand', function (event) {
        event.preventDefault();
        var $btn = $(this);
        var $pageRow = $btn.closest('tr.sb-tools-autolink-page');
        var $detail = $pageRow.next('tr.sb-tools-autolink-kw-detail');
        var expanded = !$detail.prop('hidden');
        $detail.prop('hidden', expanded);
        $btn.attr('aria-expanded', !expanded);
        $btn.text(expanded ? cfg.strings.show_keywords : cfg.strings.hide_keywords);
    });

    $(document).on('change', '#sb-tools-autolink-select-all', function () {
        var checked = $(this).is(':checked');
        if (state.scanFilter === 'enable_high_traffic') {
            $('.sb-tools-autolink-row-cb').prop('checked', checked);
        } else {
            $('.sb-tools-autolink-page-cb').prop('checked', checked);
            $('.sb-tools-autolink-kw-cb').prop('checked', checked);
        }
        updateSelectionState();
    });

    $(document).on('change', '.sb-tools-autolink-page-cb', function () {
        var checked = $(this).is(':checked');
        var $pageRow = $(this).closest('tr.sb-tools-autolink-page');
        $pageRow.next('tr.sb-tools-autolink-kw-detail').find('.sb-tools-autolink-kw-cb').prop('checked', checked);
        updateSelectionState();
    });

    $(document).on('change', '.sb-tools-autolink-kw-cb, .sb-tools-autolink-row-cb', function () {
        updateSelectionState();
    });

    window.SBTools.bindDismiss('#sb-tools-autolink-results-body', function ($row) {
        if ($row.hasClass('sb-tools-autolink-page')) {
            var idx = parseInt($row.data('index'), 10);
            if (!isNaN(idx) && state.pageGroups[idx]) {
                state.totalKeywordRules -= (state.pageGroups[idx].keywords || []).length;
                state.pageGroups[idx] = null;
            }
            $row.next('tr.sb-tools-autolink-kw-detail').remove();
            $row.remove();
            state.totalPages = Math.max(0, state.totalPages - 1);
        } else if (!$row.hasClass('sb-tools-autolink-kw-detail')) {
            var rowIdx = parseInt($row.find('.sb-tools-autolink-row-cb').data('index'), 10);
            if (!isNaN(rowIdx) && state.scanItems[rowIdx]) {
                state.scanItems[rowIdx] = null;
            }
            $row.remove();
        }
        updateDisplayingNum();
        updateSelectionState();
    });

    function startBatch(scope) {
        var items = scope === 'selected' ? getSelectedItems() : [];
        if (scope === 'selected' && !items.length) {
            window.SBTools.alert(cfg.strings.select_items);
            return;
        }
        if (scope === 'all_matching') {
            var count = state.scanFilter === 'missing_rules'
                ? state.totalKeywordRules
                : state.scanItems.length;
            window.SBTools.confirm(cfg.strings.confirm_process_all.replace('%d', count)).then(function (confirmed) {
                if (confirmed) {
                    runBatch(scope, items);
                }
            });
            return;
        }
        runBatch(scope, items);
    }

    function runBatch(scope, items) {
        state.isRunning = true;
        state.isCancelled = false;
        state.queueIndex = 0;
        preview.start();
        updateSelectionState();

        $.post(cfg.ajax_url, {
            action: 'sb_tools_autolink_opportunities_start_batch',
            nonce: cfg.nonce,
            process_scope: scope,
            filter: state.scanFilter,
            post_types: getPostTypes(),
            items: items,
        }).done(function (response) {
            if (!response.success) {
                state.isRunning = false;
                preview.reset();
                window.SBTools.alert(extractErrorMessage(response), { tone: 'error' });
                return;
            }
            state.batchId = response.data.batch_id;
            state.queue = response.data.item_ids || [];
            processNext();
        }).fail(function (xhr) {
            state.isRunning = false;
            preview.reset();
            window.SBTools.alert(extractErrorMessage(null, xhr), { tone: 'error' });
        });
    }

    function processNext() {
        if (state.isCancelled || !state.batchId || state.queueIndex >= state.queue.length) {
            finishBatch();
            return;
        }
        var itemId = state.queue[state.queueIndex];
        state.queueIndex += 1;
        preview.setProgress(state.queueIndex, state.queue.length);
        state.activeRequest = $.ajax({
            url: cfg.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_tools_autolink_opportunities_process',
                nonce: cfg.nonce,
                batch_id: state.batchId,
                item_id: itemId,
            },
        }).done(function (response) {
            if (response.success && response.data) {
                preview.showResult(response.data);
            } else {
                preview.addFailed({
                    item_id: itemId,
                    message: extractErrorMessage(response),
                });
            }
        }).fail(function (xhr) {
            preview.addFailed({
                item_id: itemId,
                message: extractErrorMessage(null, xhr),
            });
        }).always(processNext);
    }

    function finishBatch() {
        var wasCancelled = state.isCancelled;
        var batchId = state.batchId;
        state.isRunning = false;
        state.batchId = null;
        updateSelectionState();
        if (!batchId) {
            preview.reset();
            return;
        }
        $.post(cfg.ajax_url, {
            action: 'sb_tools_autolink_opportunities_batch_status',
            nonce: cfg.nonce,
            batch_id: batchId,
        }).done(function (response) {
            if (response.success && response.data) {
                preview.finish(response.data, wasCancelled);
                if (state.scanFilter === 'missing_rules' && (response.data.processed || 0) > 0 && !wasCancelled) {
                    $.post(cfg.ajax_url, {
                        action: 'sb_tools_autolink_opportunities_finalize_batch',
                        nonce: cfg.nonce,
                        batch_id: batchId,
                    }).done(function () {
                        $('#sb-tools-autolink-revert-wrap').prop('hidden', false);
                    });
                }
            } else {
                preview.finish(null, wasCancelled);
            }
        }).fail(function () {
            preview.finish(null, wasCancelled);
        });
    }

    $('#sb-tools-autolink-process-selected').on('click', function () { startBatch('selected'); });
    $('#sb-tools-autolink-process-all').on('click', function () { startBatch('all_matching'); });
    preview.getCancelButton().on('click', function () {
        state.isCancelled = true;
        if (state.activeRequest && state.activeRequest.abort) {
            state.activeRequest.abort();
        }
        if (state.batchId) {
            $.post(cfg.ajax_url, {
                action: 'sb_tools_autolink_opportunities_cancel_batch',
                nonce: cfg.nonce,
                batch_id: state.batchId,
            }).always(finishBatch);
        } else {
            finishBatch();
        }
    });
    $('#sb-tools-autolink-revert-batch').on('click', function () {
        window.SBTools.confirm(cfg.strings.revert_confirm).then(function (confirmed) {
            if (!confirmed) {
                return;
            }
            $.post(cfg.ajax_url, {
                action: 'sb_tools_autolink_opportunities_revert_batch',
                nonce: cfg.nonce,
            }).done(function (response) {
                window.SBTools.alert(response.success ? response.data.message : extractErrorMessage(response), {
                    tone: response.success ? 'success' : 'error',
                });
            });
        });
    });
})(jQuery);
