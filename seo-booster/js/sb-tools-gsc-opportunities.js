/**
 * SEO Booster Tools — GSC opportunities batch processor.
 */
(function ($) {
    'use strict';

    if (typeof sbToolsGscOpportunities === 'undefined') {
        return;
    }

    var cfg = sbToolsGscOpportunities;
    var preview = window.SBToolsBatchPreview.create({
        prefix: 'sb-tools-gsc-opp',
        strings: cfg.strings,
        renderResult: function (payload) {
            var parts = [];
            var queries = payload.queries && payload.queries.length
                ? payload.queries
                : (payload.query ? [payload.query] : []);
            if (queries.length) {
                parts.push('<p><strong>GSC queries:</strong> ' + escHtml(queries.join(', ')) + '</p>');
            }
            var before = payload.before || {};
            var after = payload.after || {};
            if (before.title !== undefined || after.title !== undefined) {
                parts.push(renderFieldDiff('SEO title', before.title, after.title));
            }
            if (before.description !== undefined || after.description !== undefined) {
                parts.push(renderFieldDiff('Meta description', before.description, after.description));
            }
            return parts.join('');
        },
        renderFailed: function (item) {
            var scanItem = findScanItem(item.post_id || item.item_id);
            var label = scanItem ? scanItem.title : ('Page #' + (item.post_id || item.item_id || ''));
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
        batchStartedAt: null,
    };

    var typeLabels = {
        striking_distance: 'Striking distance',
        low_ctr: 'Low CTR',
        high_impressions_low_clicks: 'High impressions, low clicks',
    };

    function escHtml(value) {
        return $('<div>').text(value === undefined || value === null ? '' : value).html();
    }

    function renderFieldDiff(label, before, after) {
        if ((before || '') === (after || '')) {
            return '';
        }
        return '<div class="sb-tools-preview-text-field">' +
            '<p class="sb-tools-preview-text-field-label"><strong>' + escHtml(label) + '</strong></p>' +
            '<p class="sb-tools-preview-text-before"><span class="sb-tools-preview-label">Before:</span> ' + escHtml(before || '—') + '</p>' +
            '<p class="sb-tools-preview-text-after"><span class="sb-tools-preview-label">After:</span> ' + escHtml(after || '—') + '</p>' +
            '</div>';
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

    function getFilters() {
        var filters = [];
        $('input[name="gsc_opp_filter"]:checked').each(function () {
            filters.push($(this).val());
        });
        return filters;
    }

    function getApplyFields() {
        return {
            title: $('input[name="gsc_opp_apply_field"][value="title"]').is(':checked'),
            description: $('input[name="gsc_opp_apply_field"][value="description"]').is(':checked'),
        };
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

    function getSelectedItems() {
        var items = [];
        $('.sb-tools-gsc-opp-row-cb:checked').each(function () {
            var idx = parseInt($(this).data('index'), 10);
            if (!isNaN(idx) && state.scanItems[idx]) {
                items.push({ post_id: state.scanItems[idx].post_id });
            }
        });
        return items;
    }

    function renderOpportunityTypes(item) {
        var types = item.opportunity_types && item.opportunity_types.length
            ? item.opportunity_types
            : (item.opportunity_type ? [item.opportunity_type] : []);
        return types.map(function (type) {
            return escHtml(typeLabels[type] || type);
        }).join('<br>');
    }

    function renderQueryCluster(item) {
        var queries = (item.queries || []).map(function (q) {
            return q && q.query ? q.query : '';
        }).filter(Boolean);

        if (!queries.length && item.query) {
            queries = [item.query];
        }
        if (!queries.length) {
            return '';
        }

        var shown = queries.slice(0, 3).map(function (q) {
            return escHtml(q);
        }).join('<br>');

        var total = item.query_count || queries.length;
        var extra = total - Math.min(3, queries.length);
        if (extra > 0) {
            shown += '<br><span class="description">' + escHtml('+' + extra + ' more') + '</span>';
        }
        return shown;
    }

    function buildResultsTable(items) {
        var $body = $('#sb-tools-gsc-opp-results-body');
        $body.empty();
        items.forEach(function (item, index) {
            var metrics = 'Impr: ' + (item.impressions || 0) + ', Clicks: ' + (item.clicks || 0) + ', Pos: ' + (item.position || 0);
            var row = $('<tr></tr>');
            row.append('<th scope="row" class="check-column"><input type="checkbox" class="sb-tools-gsc-opp-row-cb" data-index="' + index + '" value="' + item.post_id + '" /></th>');
            row.append('<td class="title column-primary has-row-actions">' + window.SBTools.contentCellInner({
                title: item.title,
                editUrl: item.edit_url,
                viewUrl: item.view_url || item.page_url,
                slug: item.slug,
            }) + '</td>');
            row.append('<td>' + renderOpportunityTypes(item) + '</td>');
            row.append('<td>' + renderQueryCluster(item) + '</td>');
            row.append('<td>' + metrics + '</td>');
            $body.append(row);
        });
        updateSelectionState();
    }

    window.SBTools.bindDismiss('#sb-tools-gsc-opp-results-body', function ($row) {
        var idx = parseInt($row.find('.sb-tools-gsc-opp-row-cb').data('index'), 10);
        if (!isNaN(idx) && state.scanItems[idx]) {
            state.scanItems[idx] = null;
        }
        $row.remove();
        state.totalFound = Math.max(0, state.totalFound - 1);
        var label = state.totalFound === 1 ? cfg.strings.matching_one : cfg.strings.matching_many;
        $('#sb-tools-gsc-opp-displaying-num').text(label.replace('%d', state.totalFound));
        updateSelectionState();
    });

    function updateSelectionState() {
        var selected = $('.sb-tools-gsc-opp-row-cb:checked').length;
        var canProcess = cfg.ai_available && cfg.seo_target && (getApplyFields().title || getApplyFields().description);
        $('#sb-tools-gsc-opp-process-selected').prop('disabled', !canProcess || selected === 0 || state.isRunning);
        $('#sb-tools-gsc-opp-process-all').prop('disabled', !canProcess || state.totalFound === 0 || state.isRunning);
        $('#sb-tools-gsc-opp-process-all').text(cfg.strings.process_all_matching.replace('%d', state.totalFound));
    }

    $('#sb-tools-gsc-opp-scan-btn').on('click', function () {
        var filters = getFilters();
        if (!filters.length) {
            window.SBTools.alert(cfg.strings.select_filter);
            return;
        }
        var $btn = $(this);
        var $spinner = $('.sb-tools-scan-spinner');
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $.post(cfg.ajax_url, {
            action: 'sb_tools_scan_gsc_opportunities',
            nonce: cfg.nonce,
            filters: filters,
        }).done(function (response) {
            if (!response.success) {
                window.SBTools.alert(extractErrorMessage(response), { tone: 'error' });
                return;
            }
            state.scanItems = response.data.items || [];
            state.totalFound = response.data.total_found || 0;
            buildResultsTable(state.scanItems);
            var label = state.totalFound === 1 ? cfg.strings.matching_one : cfg.strings.matching_many;
            $('#sb-tools-gsc-opp-displaying-num').text(label.replace('%d', state.totalFound));
            $('#sb-tools-gsc-opp-results').show();
            $('#sb-tools-gsc-opp-process-actions').prop('hidden', false);
            updateSelectionState();
        }).fail(function (xhr) {
            window.SBTools.alert(extractErrorMessage(null, xhr), { tone: 'error' });
        }).always(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });

    $(document).on('change', '.sb-tools-gsc-opp-row-cb, #sb-tools-gsc-opp-select-all, input[name="gsc_opp_apply_field"]', function () {
        if ($(this).attr('id') === 'sb-tools-gsc-opp-select-all') {
            $('.sb-tools-gsc-opp-row-cb').prop('checked', $(this).is(':checked'));
        }
        updateSelectionState();
    });

    function startBatch(scope) {
        var applyFields = getApplyFields();
        if (!applyFields.title && !applyFields.description) {
            window.SBTools.alert(cfg.strings.select_apply);
            return;
        }
        var items = scope === 'selected' ? getSelectedItems() : [];
        if (scope === 'selected' && !items.length) {
            window.SBTools.alert(cfg.strings.select_items);
            return;
        }
        if (scope === 'all_matching') {
            window.SBTools.confirm(cfg.strings.confirm_process_all.replace('%d', state.totalFound)).then(function (confirmed) {
                if (confirmed) {
                    runBatch(scope, applyFields, items);
                }
            });
            return;
        }
        runBatch(scope, applyFields, items);
    }

    function runBatch(scope, applyFields, items) {
        state.isRunning = true;
        state.isCancelled = false;
        state.queue = [];
        state.queueIndex = 0;
        state.batchStartedAt = Date.now();
        preview.start();
        updateSelectionState();

        $.post(cfg.ajax_url, {
            action: 'sb_tools_gsc_opportunities_start_batch',
            nonce: cfg.nonce,
            process_scope: scope,
            filters: getFilters(),
            apply_fields: applyFields,
            overwrite_existing: $('#sb-tools-gsc-opp-overwrite').is(':checked') ? 1 : 0,
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
            timeout: 120000,
            data: {
                action: 'sb_tools_gsc_opportunities_process',
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
        }).always(function () {
            processNext();
        });
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
            action: 'sb_tools_gsc_opportunities_batch_status',
            nonce: cfg.nonce,
            batch_id: batchId,
        }).done(function (response) {
            if (response.success && response.data) {
                preview.finish(response.data, wasCancelled);
                if ((response.data.processed || 0) > 0 && !wasCancelled) {
                    $.post(cfg.ajax_url, {
                        action: 'sb_tools_gsc_opportunities_finalize_batch',
                        nonce: cfg.nonce,
                        batch_id: batchId,
                    }).done(function () {
                        $('#sb-tools-gsc-opp-revert-wrap').prop('hidden', false);
                    });
                }
            } else {
                preview.finish(null, wasCancelled);
            }
        }).fail(function () {
            preview.finish(null, wasCancelled);
        });
    }

    $('#sb-tools-gsc-opp-process-selected').on('click', function () { startBatch('selected'); });
    $('#sb-tools-gsc-opp-process-all').on('click', function () { startBatch('all_matching'); });
    preview.getCancelButton().on('click', function () {
        state.isCancelled = true;
        if (state.activeRequest && state.activeRequest.abort) {
            state.activeRequest.abort();
        }
        if (state.batchId) {
            $.post(cfg.ajax_url, {
                action: 'sb_tools_gsc_opportunities_cancel_batch',
                nonce: cfg.nonce,
                batch_id: state.batchId,
            }).always(finishBatch);
        } else {
            finishBatch();
        }
    });
    $('#sb-tools-gsc-opp-revert-batch').on('click', function () {
        window.SBTools.confirm(cfg.strings.revert_confirm).then(function (confirmed) {
            if (!confirmed) {
                return;
            }
            $.post(cfg.ajax_url, {
                action: 'sb_tools_gsc_opportunities_revert_batch',
                nonce: cfg.nonce,
            }).done(function (response) {
                window.SBTools.alert(response.success ? (response.data.message || cfg.strings.revert_success) : extractErrorMessage(response), {
                    tone: response.success ? 'success' : 'error',
                });
            });
        });
    });
})(jQuery);
