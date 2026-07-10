/**
 * SEO Booster Tools — Bulk meta scanner and batch processor.
 *
 * @package SEOBooster
 */
(function ($) {
    'use strict';

    if (typeof sbToolsMeta === 'undefined') {
        return;
    }

    var state = {
        totalFound: 0,
        scanItems: [],
        batchId: null,
        queue: [],
        queueIndex: 0,
        isRunning: false,
        isCancelled: false,
        activeRequest: null,
        applyFields: {},
        lastFailedItems: [],
        batchStartedAt: null,
        originalDocumentTitle: null,
    };

    var issueLabels = {
        missing_title: 'Missing SEO title',
        missing_description: 'Missing meta description',
        duplicate_title: 'Duplicate SEO title',
        duplicate_description: 'Duplicate meta description',
        missing_keyword: 'Missing focus/GSC keyword in meta',
    };

    function getScanFilters() {
        var filters = [];
        $('input[name="scan_filter"]:checked').each(function () {
            filters.push($(this).val());
        });
        return filters;
    }

    function getPostTypes() {
        var types = [];
        $('input[name="post_type"]:checked').each(function () {
            types.push($(this).val());
        });
        return types;
    }

    function getApplyFields() {
        return {
            title: $('input[name="apply_field"][value="title"]').is(':checked'),
            description: $('input[name="apply_field"][value="description"]').is(':checked'),
        };
    }

    function hasApplyField(fields) {
        return fields.title || fields.description;
    }

    function extractErrorMessage(response, xhr) {
        var msg = '';
        if (response && response.data && typeof response.data.message === 'string') {
            msg = response.data.message;
        } else if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            msg = xhr.responseJSON.data.message;
        }
        return msg || sbToolsMeta.strings.error;
    }

    function formatDurationMinSec(ms) {
        var totalSeconds = Math.max(0, Math.round(ms / 1000));
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds % 60;
        if (minutes > 0) {
            return minutes + ' min ' + seconds + ' s';
        }
        return seconds + ' s';
    }

    function getBatchElapsedMs() {
        return state.batchStartedAt ? Date.now() - state.batchStartedAt : 0;
    }

    function renderMetaList($el, meta) {
        $el.empty();
        $el.append('<dt>' + sbToolsMeta.strings.seo_title + '</dt><dd>' + (meta.seo_title || meta.title || sbToolsMeta.strings.empty) + '</dd>');
        $el.append('<dt>' + sbToolsMeta.strings.seo_description + '</dt><dd>' + (meta.seo_description || meta.description || sbToolsMeta.strings.empty) + '</dd>');
    }

    function showBatchCompleteMessage(processed, failed, wasCancelled) {
        var duration = formatDurationMinSec(getBatchElapsedMs());
        var tpl = wasCancelled
            ? sbToolsMeta.strings.batch_cancelled
            : sbToolsMeta.strings.batch_complete;
        var msg = tpl
            .replace('%1$s', duration)
            .replace('%2$d', String(processed || 0))
            .replace('%3$d', String(failed || 0));
        var progressClass = wasCancelled
            ? 'sb-tools-preview-progress--cancelled'
            : 'sb-tools-preview-progress--complete';

        $('#sb-tools-meta-preview-progress')
            .text(msg)
            .removeClass('sb-tools-preview-progress--complete sb-tools-preview-progress--cancelled')
            .addClass(progressClass);

        $('#sb-tools-meta-complete')
            .toggleClass('notice-warning', wasCancelled)
            .toggleClass('notice-success', !wasCancelled)
            .text(msg)
            .show();

        if (state.originalDocumentTitle) {
            var titleTpl = wasCancelled ? sbToolsMeta.strings.title_cancelled : sbToolsMeta.strings.title_complete;
            document.title = titleTpl
                .replace('%1$d', String(processed || 0))
                .replace('%2$d', String(failed || 0)) + ' — ' + state.originalDocumentTitle;
        }
    }

    function buildResultsTable(items) {
        var $body = $('#sb-tools-meta-results-body');
        $body.empty();
        items.forEach(function (item) {
            var issues = (item.issues || []).map(function (key) {
                return issueLabels[key] || key;
            }).join(', ');
            var row = $('<tr></tr>');
            row.append('<th scope="row" class="check-column"><input type="checkbox" class="sb-tools-meta-row-cb" value="' + item.id + '" /></th>');
            row.append(
                '<td class="column-title column-primary has-row-actions">' +
                window.SBTools.contentCellInner({
                    title: item.title,
                    editUrl: item.edit_url,
                    viewUrl: item.view_url,
                    slug: item.slug,
                }) +
                '</td>'
            );
            row.append('<td class="column-issues">' + $('<div>').text(issues).html() + '</td>');
            $body.append(row);
        });
        updateSelectionState();
    }

    function updateSelectionState() {
        var selected = $('.sb-tools-meta-row-cb:checked').length;
        var canProcess = sbToolsMeta.ai_available && sbToolsMeta.seo_target && hasApplyField(getApplyFields());
        $('#sb-tools-meta-process-selected').prop('disabled', !canProcess || selected === 0 || state.isRunning);
        $('#sb-tools-meta-process-all-matching').prop('disabled', !canProcess || state.totalFound === 0 || state.isRunning);
        $('#sb-tools-meta-process-all-matching').text(
            sbToolsMeta.strings.process_all_matching.replace('%d', state.totalFound)
        );
    }

    function showProcessActions() {
        if (state.totalFound > 0) {
            $('#sb-tools-meta-process-actions').prop('hidden', false);
            updateSelectionState();
        }
    }

    $('#sb-tools-meta-scan-btn').on('click', function () {
        var filters = getScanFilters();
        var postTypes = getPostTypes();
        if (!filters.length) {
            window.SBTools.alert(sbToolsMeta.strings.select_filter);
            return;
        }
        if (!postTypes.length) {
            window.SBTools.alert(sbToolsMeta.strings.select_post_types);
            return;
        }

        var $btn = $(this);
        var $spinner = $('.sb-tools-scan-spinner');
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $('#sb-tools-meta-complete').hide();

        $.post(sbToolsMeta.ajax_url, {
            action: 'sb_tools_scan_meta',
            nonce: sbToolsMeta.nonce,
            filters: filters,
            post_types: postTypes,
        }).done(function (response) {
            if (!response.success) {
                window.SBTools.alert(extractErrorMessage(response), { tone: 'error' });
                return;
            }
            var data = response.data;
            state.scanItems = data.items || [];
            state.totalFound = data.total_found || 0;
            buildResultsTable(state.scanItems);
            var label = state.totalFound === 1 ? sbToolsMeta.strings.matching_one : sbToolsMeta.strings.matching_many;
            $('#sb-tools-meta-displaying-num').text(label.replace('%d', state.totalFound));
            if (state.totalFound > (data.preview_count || 0)) {
                $('#sb-tools-meta-displaying-num').append(
                    ' — ' + sbToolsMeta.strings.preview_sample
                        .replace('%1$d', data.preview_count || 0)
                        .replace('%2$d', state.totalFound)
                );
            }
            $('#sb-tools-meta-results').show();
            showProcessActions();
        }).fail(function (xhr) {
            window.SBTools.alert(extractErrorMessage(null, xhr), { tone: 'error' });
        }).always(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });

    window.SBTools.bindDismiss('#sb-tools-meta-results-body', function ($row) {
        var id = parseInt($row.find('.sb-tools-meta-row-cb').val(), 10);
        if (!isNaN(id)) {
            state.scanItems = state.scanItems.filter(function (entry) {
                return parseInt(entry.id, 10) !== id;
            });
        }
        $row.remove();
        state.totalFound = Math.max(0, state.totalFound - 1);
        var label = state.totalFound === 1 ? sbToolsMeta.strings.matching_one : sbToolsMeta.strings.matching_many;
        $('#sb-tools-meta-displaying-num').text(label.replace('%d', state.totalFound));
        updateSelectionState();
    });

    $(document).on('change', '.sb-tools-meta-row-cb, #sb-tools-meta-select-all-header, input[name="apply_field"]', updateSelectionState);
    $('#sb-tools-meta-select-all-header').on('change', function () {
        $('.sb-tools-meta-row-cb').prop('checked', $(this).is(':checked'));
        updateSelectionState();
    });

    function startBatch(scope) {
        if (!sbToolsMeta.ai_available || !sbToolsMeta.seo_target) {
            return;
        }
        var applyFields = getApplyFields();
        if (!hasApplyField(applyFields)) {
            window.SBTools.alert(sbToolsMeta.strings.select_apply);
            return;
        }

        var postIds = [];
        if (scope === 'selected') {
            $('.sb-tools-meta-row-cb:checked').each(function () {
                postIds.push(parseInt($(this).val(), 10));
            });
            if (!postIds.length) {
                window.SBTools.alert(sbToolsMeta.strings.select_items);
                return;
            }
        } else if (scope === 'all_matching') {
            window.SBTools.confirm(sbToolsMeta.strings.confirm_process_all.replace('%d', state.totalFound)).then(function (confirmed) {
                if (confirmed) {
                    runBatch(scope, applyFields, postIds);
                }
            });
            return;
        }

        runBatch(scope, applyFields, postIds);
    }

    function runBatch(scope, applyFields, postIds) {
        state.isRunning = true;
        state.isCancelled = false;
        state.queue = [];
        state.queueIndex = 0;
        state.lastFailedItems = [];
        state.batchStartedAt = Date.now();
        state.originalDocumentTitle = state.originalDocumentTitle || document.title;
        state.applyFields = applyFields;

        $('#sb-tools-meta-preview-failed-list').empty();
        $('#sb-tools-meta-preview-failed').prop('hidden', true);
        $('#sb-tools-meta-complete').hide();
        $('#sb-tools-meta-preview').show().addClass('is-batch-active').removeClass('is-batch-complete is-batch-complete-cancelled');
        $('#sb-tools-meta-tab-warning').prop('hidden', false);
        $('#sb-tools-meta-cancel-batch').prop('hidden', false);
        $('#sb-tools-meta-preview-progress')
            .removeClass('sb-tools-preview-progress--complete sb-tools-preview-progress--cancelled')
            .text('');
        updateSelectionState();

        $.post(sbToolsMeta.ajax_url, {
            action: 'sb_tools_meta_start_batch',
            nonce: sbToolsMeta.nonce,
            process_scope: scope,
            post_ids: postIds,
            filters: getScanFilters(),
            post_types: getPostTypes(),
            apply_fields: applyFields,
            overwrite_existing: $('#sb-tools-meta-overwrite').is(':checked') ? 1 : 0,
        }).done(function (response) {
            if (!response.success) {
                stopBatch(extractErrorMessage(response));
                return;
            }
            state.batchId = response.data.batch_id;
            state.queue = response.data.post_ids || [];
            processNext();
        }).fail(function (xhr) {
            stopBatch(extractErrorMessage(null, xhr));
        });
    }

    function processNext() {
        if (state.isCancelled || !state.batchId) {
            return;
        }
        if (state.queueIndex >= state.queue.length) {
            finishBatch();
            return;
        }

        var postId = state.queue[state.queueIndex];
        state.queueIndex += 1;
        $('#sb-tools-meta-preview-progress')
            .removeClass('sb-tools-preview-progress--complete sb-tools-preview-progress--cancelled')
            .text(
                sbToolsMeta.strings.processing
                    .replace('%1$d', state.queueIndex)
                    .replace('%2$d', state.queue.length)
            );

        state.activeRequest = $.ajax({
            url: sbToolsMeta.ajax_url,
            type: 'POST',
            timeout: 120000,
            data: {
                action: 'sb_tools_meta_process',
                nonce: sbToolsMeta.nonce,
                batch_id: state.batchId,
                post_id: postId,
            },
        }).done(function (response) {
            if (response.success) {
                showCompletedPreview(response.data);
            } else {
                addFailedItem(response.data || { post_id: postId, message: extractErrorMessage(response) });
            }
            processNext();
        }).fail(function (xhr) {
            addFailedItem({ post_id: postId, message: extractErrorMessage(null, xhr) });
            processNext();
        });
    }

    function showCompletedPreview(data) {
        $('#sb-tools-meta-preview-completed').show();
        $('#sb-tools-meta-preview-title').text(data.post_title || '');
        renderMetaList($('#sb-tools-meta-preview-before-list'), data.before || {});
        renderMetaList($('#sb-tools-meta-preview-after-list'), data.after || {});

        var $error = $('#sb-tools-meta-preview-error');
        if (data.skipped) {
            $error
                .text(data.message || sbToolsMeta.strings.item_skipped)
                .removeClass('sb-tools-preview-error')
                .addClass('sb-tools-preview-skipped')
                .show();
        } else {
            $error.hide().removeClass('sb-tools-preview-skipped');
        }
    }

    function addFailedItem(item) {
        state.lastFailedItems.push(item);
        var $list = $('#sb-tools-meta-preview-failed-list');
        $('#sb-tools-meta-preview-failed').prop('hidden', false);
        $list.append('<li>' + $('<div>').text((item.post_title || item.post_id) + ': ' + (item.message || sbToolsMeta.strings.error)).html() + '</li>');
        $('#sb-tools-meta-retry-failed').prop('hidden', false).text(
            sbToolsMeta.strings.retry_failed.replace('%d', state.lastFailedItems.length)
        );
    }

    function finishBatch() {
        var wasCancelled = state.isCancelled;
        var batchId = state.batchId;
        var fallbackProcessed = Math.max(0, state.queueIndex - (wasCancelled ? 0 : 0));

        state.isRunning = false;
        state.activeRequest = null;
        $('#sb-tools-meta-cancel-batch').prop('hidden', true);
        $('#sb-tools-meta-tab-warning').prop('hidden', true);
        $('#sb-tools-meta-preview')
            .removeClass('is-batch-active')
            .addClass('is-batch-complete')
            .toggleClass('is-batch-complete-cancelled', wasCancelled);
        updateSelectionState();

        if (!batchId) {
            state.batchStartedAt = null;
            return;
        }

        var savedBatchId = batchId;
        state.batchId = null;

        $.post(sbToolsMeta.ajax_url, {
            action: 'sb_tools_meta_batch_status',
            nonce: sbToolsMeta.nonce,
            batch_id: savedBatchId,
        }).done(function (response) {
            if (response.success && response.data) {
                var data = response.data;
                showBatchCompleteMessage(data.processed || 0, data.failed || 0, wasCancelled);
                if ((data.processed || 0) > 0 && !wasCancelled) {
                    $.post(sbToolsMeta.ajax_url, {
                        action: 'sb_tools_meta_finalize_batch',
                        nonce: sbToolsMeta.nonce,
                        batch_id: savedBatchId,
                    }).done(function () {
                        $('#sb-tools-meta-revert-wrap').prop('hidden', false);
                    });
                }
                return;
            }
            showBatchCompleteMessage(fallbackProcessed, 0, wasCancelled);
        }).fail(function () {
            showBatchCompleteMessage(fallbackProcessed, 0, wasCancelled);
        }).always(function () {
            state.batchStartedAt = null;
        });
    }

    function stopBatch(message) {
        state.isRunning = false;
        $('#sb-tools-meta-cancel-batch').prop('hidden', true);
        $('#sb-tools-meta-tab-warning').prop('hidden', true);
        $('#sb-tools-meta-preview').removeClass('is-batch-active');
        updateSelectionState();
        if (message) {
            window.SBTools.alert(message, { tone: 'error' });
        }
    }

    $('#sb-tools-meta-process-selected').on('click', function () {
        startBatch('selected');
    });
    $('#sb-tools-meta-process-all-matching').on('click', function () {
        startBatch('all_matching');
    });

    $('#sb-tools-meta-cancel-batch').on('click', function () {
        if (!state.batchId) {
            return;
        }
        state.isCancelled = true;
        if (state.activeRequest && state.activeRequest.abort) {
            state.activeRequest.abort();
        }
        $.post(sbToolsMeta.ajax_url, {
            action: 'sb_tools_meta_cancel_batch',
            nonce: sbToolsMeta.nonce,
            batch_id: state.batchId,
        }).always(function () {
            finishBatch();
        });
    });

    $('#sb-tools-meta-revert-batch').on('click', function () {
        window.SBTools.confirm(sbToolsMeta.strings.revert_confirm).then(function (confirmed) {
            if (!confirmed) {
                return;
            }
            $.post(sbToolsMeta.ajax_url, {
                action: 'sb_tools_meta_revert_batch',
                nonce: sbToolsMeta.nonce,
            }).done(function (response) {
                if (response.success) {
                    window.SBTools.alert(response.data.message || sbToolsMeta.strings.revert_success, { tone: 'success' });
                    $('#sb-tools-meta-revert-wrap').prop('hidden', true);
                } else {
                    window.SBTools.alert(extractErrorMessage(response), { tone: 'error' });
                }
            }).fail(function (xhr) {
                window.SBTools.alert(extractErrorMessage(null, xhr), { tone: 'error' });
            });
        });
    });

    showProcessActions();
})(jQuery);
