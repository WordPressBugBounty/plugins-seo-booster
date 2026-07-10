/**
 * SEO Booster Tools — Focus keyword bulk setter.
 */
(function ($) {
    'use strict';

    if (typeof sbToolsFocusKeyword === 'undefined') {
        return;
    }

    var cfg = sbToolsFocusKeyword;
    var preview = window.SBToolsBatchPreview.create({
        prefix: 'sb-tools-focus',
        strings: cfg.strings,
        renderResult: function (payload) {
            var before = payload.before || '';
            var after = payload.after || payload.keyword || '';
            if (before && before !== after) {
                return '<p class="sb-tools-preview-text-before"><span class="sb-tools-preview-label">Before:</span> ' + escHtml(before) + '</p>' +
                    '<p class="sb-tools-preview-text-after"><span class="sb-tools-preview-label">After:</span> ' + escHtml(after) + '</p>';
            }
            return '<p><strong>Focus keyword:</strong> ' + escHtml(after) + '</p>';
        },
        renderFailed: function (item) {
            var scanItem = findScanItem(item.item_id || item.query_id);
            var label = item.post_title || (scanItem ? (scanItem.title || scanItem.keyword) : '') || ('Item #' + (item.item_id || item.query_id || ''));
            return escHtml(label) + ': ' + escHtml(item.message || cfg.strings.error);
        },
    });
    var state = {
        totalFound: 0,
        scanItems: [],
        batchId: null,
        queue: [],
        queueIndex: 0,
        isRunning: false,
        isCancelled: false,
        activeRequest: null,
    };

    function escHtml(value) {
        return $('<div>').text(value === undefined || value === null ? '' : value).html();
    }

    function findScanItem(postId) {
        var id = parseInt(postId, 10);
        for (var i = 0; i < state.scanItems.length; i += 1) {
            if (parseInt(state.scanItems[i].post_id, 10) === id) {
                return state.scanItems[i];
            }
        }
        return null;
    }

    function getPostTypes() {
        var types = [];
        $('.sb-tools-focus-keyword input[name="post_type"]:checked').each(function () {
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

    function format(template, values) {
        var out = template;
        Object.keys(values).forEach(function (key) {
            out = out.replace(key, values[key]);
        });
        return out;
    }

    function formatNumber(value) {
        var num = parseInt(value, 10);
        if (isNaN(num)) {
            return '0';
        }
        return num.toLocaleString();
    }

    function formatPosition(value) {
        var num = parseFloat(value);
        if (isNaN(num) || num <= 0) {
            return cfg.strings.no_metrics;
        }
        return num.toFixed(1);
    }

    function normalizeKeyword(value) {
        return String(value || '').trim().toLowerCase();
    }

    function findAlternativeMetrics(keyword, alternatives) {
        var normalized = normalizeKeyword(keyword);
        if (!normalized) {
            return null;
        }
        for (var i = 0; i < alternatives.length; i += 1) {
            if (normalizeKeyword(alternatives[i].keyword) === normalized) {
                return alternatives[i];
            }
        }
        return null;
    }

    function buildMetricsHtml(metrics) {
        if (!metrics) {
            return '<span class="sb-tools-focus-metrics sb-tools-focus-metrics--empty">' +
                escHtml(cfg.strings.custom_keyword) + '</span>';
        }
        return '<span class="sb-tools-focus-metrics">' +
            '<span class="sb-tools-focus-metric">' +
            '<span class="sb-tools-focus-metric-label">' + escHtml(cfg.strings.impressions_short) + '</span> ' +
            formatNumber(metrics.impressions) +
            '</span>' +
            '<span class="sb-tools-focus-metric">' +
            '<span class="sb-tools-focus-metric-label">' + escHtml(cfg.strings.position_short) + '</span> ' +
            formatPosition(metrics.position) +
            '</span>' +
            '</span>';
    }

    function updateRowMetrics($row, keyword, alternatives) {
        var metrics = findAlternativeMetrics(keyword, alternatives);
        $row.find('.sb-tools-focus-metrics-cell').html(buildMetricsHtml(metrics));
        $row.next('tr.sb-tools-focus-kw-detail').find('.sb-tools-focus-kw-pick').each(function () {
            var $pick = $(this);
            var isSelected = normalizeKeyword($pick.data('keyword')) === normalizeKeyword(keyword);
            $pick.closest('.sb-tools-focus-kw-suggestion').toggleClass('is-selected', isSelected);
        });
    }

    function getSelectedItems() {
        var items = [];
        $('.sb-tools-focus-row-cb:checked').each(function () {
            var idx = parseInt($(this).data('index'), 10);
            if (!isNaN(idx) && state.scanItems[idx]) {
                var item = $.extend({}, state.scanItems[idx]);
                var $input = $(this).closest('tr').find('.sb-tools-focus-keyword-input');
                if ($input.length) {
                    item.suggested_keyword = $.trim($input.val());
                }
                items.push(item);
            }
        });
        return items;
    }

    function buildSuggestionsList(alternatives, selectedKeyword) {
        var $list = $('<ul class="sb-tools-focus-kw-suggestions"></ul>');
        alternatives.forEach(function (alt) {
            var isSelected = normalizeKeyword(alt.keyword) === normalizeKeyword(selectedKeyword);
            var $item = $('<li class="sb-tools-focus-kw-suggestion"></li>').toggleClass('is-selected', isSelected);
            var $pick = $('<button type="button" class="sb-tools-focus-kw-pick"></button>')
                .attr('data-keyword', alt.keyword);
            $pick.append('<span class="sb-tools-focus-kw-name">' + escHtml(alt.keyword) + '</span>');
            $pick.append(
                '<span class="sb-tools-focus-kw-badges">' +
                '<span class="sb-tools-focus-badge">' + escHtml(formatNumber(alt.impressions)) + ' ' +
                escHtml(cfg.strings.impressions_short.toLowerCase()) + '</span>' +
                '<span class="sb-tools-focus-badge">' + escHtml(cfg.strings.position_short.toLowerCase()) + '. ' +
                escHtml(formatPosition(alt.position)) + '</span>' +
                '</span>'
            );
            $item.append($pick);
            $list.append($item);
        });
        return $list;
    }

    function buildKeywordEditor(item, index) {
        var alternatives = item.alternatives || [];
        if (!alternatives.length && item.suggested_keyword) {
            alternatives = [{
                keyword: item.suggested_keyword,
                impressions: item.impressions,
                position: item.position,
            }];
        }

        var $editor = $('<div class="sb-tools-focus-kw-editor"></div>').attr('data-index', index);
        var $input = $('<input type="text" class="sb-tools-focus-keyword-input regular-text" />')
            .attr('data-index', index)
            .val(item.suggested_keyword || '')
            .attr('aria-label', cfg.strings.choose_keyword);
        $editor.append($input);

        if (alternatives.length > 1) {
            var toggleLabel = format(cfg.strings.show_suggestions, { '%d': alternatives.length });
            $editor.append(
                '<button type="button" class="button-link sb-tools-focus-suggestions-toggle" aria-expanded="false">' +
                escHtml(toggleLabel) +
                '</button>'
            );
        }

        return {
            $editor: $editor,
            alternatives: alternatives,
        };
    }

    function buildResultsTable(items) {
        var $body = $('#sb-tools-focus-results-body');
        $body.empty();
        items.forEach(function (item, index) {
            var editorParts = buildKeywordEditor(item, index);
            var alternatives = editorParts.alternatives;
            var metrics = findAlternativeMetrics(item.suggested_keyword, alternatives) || {
                impressions: item.impressions,
                position: item.position,
            };

            var row = $('<tr class="sb-tools-focus-row"></tr>');
            row.append('<th scope="row" class="check-column"><input type="checkbox" class="sb-tools-focus-row-cb" data-index="' + index + '" value="' + item.post_id + '" checked /></th>');
            row.append('<td class="title column-primary has-row-actions">' + window.SBTools.contentCellInner({
                title: item.title,
                editUrl: item.edit_url,
                viewUrl: item.view_url,
                slug: item.slug,
            }) + '</td>');

            var $kwCell = $('<td class="sb-tools-focus-kw-cell"></td>');
            $kwCell.append(editorParts.$editor);
            row.append($kwCell);

            var $metricsCell = $('<td class="sb-tools-focus-metrics-cell"></td>');
            $metricsCell.html(buildMetricsHtml(metrics));
            row.append($metricsCell);
            $body.append(row);

            if (alternatives.length > 1) {
                var $detailRow = $('<tr class="sb-tools-focus-kw-detail" hidden></tr>');
                var $detailCell = $('<td colspan="4" class="sb-tools-focus-kw-detail-cell"></td>');
                $detailCell.append(buildSuggestionsList(alternatives, item.suggested_keyword));
                $detailRow.append($detailCell);
                $body.append($detailRow);
            }
        });
        updateSelectionState();
    }

    window.SBTools.bindDismiss('#sb-tools-focus-results-body', function ($row) {
        var idx = parseInt($row.find('.sb-tools-focus-row-cb').data('index'), 10);
        if (!isNaN(idx) && state.scanItems[idx]) {
            state.scanItems[idx] = null;
        }
        if ($row.hasClass('sb-tools-focus-kw-detail')) {
            $row.remove();
            return;
        }
        $row.next('tr.sb-tools-focus-kw-detail').remove();
        $row.remove();
        state.totalFound = Math.max(0, state.totalFound - 1);
        var label = state.totalFound === 1 ? cfg.strings.matching_one : cfg.strings.matching_many;
        $('#sb-tools-focus-displaying-num').text(label.replace('%d', state.totalFound));
        updateSelectionState();
    });

    $(document).on('click', '.sb-tools-focus-suggestions-toggle', function () {
        var $toggle = $(this);
        var $row = $toggle.closest('tr.sb-tools-focus-row');
        var $detail = $row.next('tr.sb-tools-focus-kw-detail');
        var expanded = $toggle.attr('aria-expanded') === 'true';
        $detail.prop('hidden', expanded);
        $toggle.attr('aria-expanded', expanded ? 'false' : 'true');
        $toggle.text(expanded
            ? format(cfg.strings.show_suggestions, { '%d': $detail.find('.sb-tools-focus-kw-suggestion').length })
            : cfg.strings.hide_suggestions);
    });

    $(document).on('click', '.sb-tools-focus-kw-pick', function () {
        var keyword = $(this).data('keyword') || '';
        var $row = $(this).closest('tr.sb-tools-focus-kw-detail').prev('tr.sb-tools-focus-row');
        var idx = parseInt($row.find('.sb-tools-focus-row-cb').data('index'), 10);
        var alternatives = (state.scanItems[idx] && state.scanItems[idx].alternatives) || [];
        $row.find('.sb-tools-focus-keyword-input').val(keyword);
        updateRowMetrics($row, keyword, alternatives);
    });

    $(document).on('input', '.sb-tools-focus-keyword-input', function () {
        var $input = $(this);
        var $row = $input.closest('tr.sb-tools-focus-row');
        var idx = parseInt($input.data('index'), 10);
        var alternatives = (state.scanItems[idx] && state.scanItems[idx].alternatives) || [];
        updateRowMetrics($row, $.trim($input.val()), alternatives);
    });

    function updateSelectionState() {
        var selected = $('.sb-tools-focus-row-cb:checked').length;
        var canProcess = cfg.seo_target;
        $('#sb-tools-focus-process-selected').prop('disabled', !canProcess || selected === 0 || state.isRunning);
        $('#sb-tools-focus-process-all').prop('disabled', !canProcess || state.totalFound === 0 || state.isRunning);
        $('#sb-tools-focus-process-all').text(cfg.strings.process_all_matching.replace('%d', state.totalFound));
    }

    $('#sb-tools-focus-scan-btn').on('click', function () {
        var postTypes = getPostTypes();
        if (!postTypes.length) {
            window.SBTools.alert(cfg.strings.select_post_types);
            return;
        }
        var $btn = $(this);
        var $spinner = $('.sb-tools-scan-spinner');
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $.post(cfg.ajax_url, {
            action: 'sb_tools_scan_focus_keyword',
            nonce: cfg.nonce,
            post_types: postTypes,
        }).done(function (response) {
            if (!response.success) {
                window.SBTools.alert(extractErrorMessage(response), { tone: 'error' });
                return;
            }
            state.scanItems = response.data.items || [];
            state.totalFound = response.data.total_found || 0;
            buildResultsTable(state.scanItems);
            var label = state.totalFound === 1 ? cfg.strings.matching_one : cfg.strings.matching_many;
            $('#sb-tools-focus-displaying-num').text(label.replace('%d', state.totalFound));
            if (response.data.skipped) {
                var s = response.data.skipped;
                $('#sb-tools-focus-skipped-summary').text(
                    cfg.strings.skipped_summary
                        .replace('%1$d', s.has_keyword || 0)
                        .replace('%2$d', s.utility || 0)
                        .replace('%3$d', s.no_demand || 0)
                        .replace('%4$d', s.no_unique || 0)
                ).show();
            }
            $('#sb-tools-focus-results').show();
            $('#sb-tools-focus-process-actions').prop('hidden', false);
            updateSelectionState();
        }).fail(function (xhr) {
            window.SBTools.alert(extractErrorMessage(null, xhr), { tone: 'error' });
        }).always(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });

    $(document).on('change', '.sb-tools-focus-row-cb, #sb-tools-focus-select-all', function () {
        if ($(this).attr('id') === 'sb-tools-focus-select-all') {
            $('.sb-tools-focus-row-cb').prop('checked', $(this).is(':checked'));
        }
        updateSelectionState();
    });

    function startBatch(scope) {
        var items = scope === 'selected' ? getSelectedItems() : [];
        if (scope === 'selected' && !items.length) {
            window.SBTools.alert(cfg.strings.select_items);
            return;
        }
        if (scope === 'all_matching') {
            window.SBTools.confirm(cfg.strings.confirm_process_all.replace('%d', state.totalFound)).then(function (confirmed) {
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
            action: 'sb_tools_focus_keyword_start_batch',
            nonce: cfg.nonce,
            process_scope: scope,
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
            state.queue = response.data.post_ids || [];
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
        var postId = state.queue[state.queueIndex];
        state.queueIndex += 1;
        preview.setProgress(state.queueIndex, state.queue.length);
        state.activeRequest = $.ajax({
            url: cfg.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_tools_focus_keyword_process',
                nonce: cfg.nonce,
                batch_id: state.batchId,
                post_id: postId,
            },
        }).done(function (response) {
            if (response.success && response.data) {
                preview.showResult(response.data);
            } else {
                preview.addFailed({
                    post_id: postId,
                    message: extractErrorMessage(response),
                });
            }
        }).fail(function (xhr) {
            preview.addFailed({
                post_id: postId,
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
            action: 'sb_tools_focus_keyword_batch_status',
            nonce: cfg.nonce,
            batch_id: batchId,
        }).done(function (response) {
            if (response.success && response.data) {
                preview.finish(response.data, wasCancelled);
                if ((response.data.processed || 0) > 0 && !wasCancelled) {
                    $.post(cfg.ajax_url, {
                        action: 'sb_tools_focus_keyword_finalize_batch',
                        nonce: cfg.nonce,
                        batch_id: batchId,
                    }).done(function () {
                        $('#sb-tools-focus-revert-wrap').prop('hidden', false);
                    });
                }
            } else {
                preview.finish(null, wasCancelled);
            }
        }).fail(function () {
            preview.finish(null, wasCancelled);
        });
    }

    $('#sb-tools-focus-process-selected').on('click', function () { startBatch('selected'); });
    $('#sb-tools-focus-process-all').on('click', function () { startBatch('all_matching'); });
    preview.getCancelButton().on('click', function () {
        state.isCancelled = true;
        if (state.activeRequest && state.activeRequest.abort) {
            state.activeRequest.abort();
        }
        if (state.batchId) {
            $.post(cfg.ajax_url, {
                action: 'sb_tools_focus_keyword_cancel_batch',
                nonce: cfg.nonce,
                batch_id: state.batchId,
            }).always(finishBatch);
        } else {
            finishBatch();
        }
    });
    $('#sb-tools-focus-revert-batch').on('click', function () {
        window.SBTools.confirm(cfg.strings.revert_confirm).then(function (confirmed) {
            if (!confirmed) {
                return;
            }
            $.post(cfg.ajax_url, {
                action: 'sb_tools_focus_keyword_revert_batch',
                nonce: cfg.nonce,
            }).done(function (response) {
                window.SBTools.alert(response.success ? response.data.message : extractErrorMessage(response), {
                    tone: response.success ? 'success' : 'error',
                });
            });
        });
    });
})(jQuery);
