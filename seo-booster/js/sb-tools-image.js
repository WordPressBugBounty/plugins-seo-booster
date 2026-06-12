/**
 * SEO Booster Tools — Image metadata scanner and batch preview
 *
 * @package SEOBooster
 */
(function ($) {
    'use strict';

    if (typeof sbToolsImage === 'undefined') {
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
        attachmentContext: {},
        activeProcessToken: null,
        processingStartedAt: null,
        processingTimerInterval: null,
        batchStartedAt: null,
        originalDocumentTitle: null,
        titleRestoreTimeout: null,
        lastFailedItems: [],
    };

    var leaveTabHandler = null;
    var FAILED_DISPLAY_LIMIT = 20;

    var issueLabels = {
        empty_alt: 'Empty alt text',
        empty_title: 'Empty title',
        empty_caption: 'Empty caption',
        empty_description: 'Empty description',
    };

    var fieldLabels = {
        title: sbToolsImage.strings.title,
        alt_text: sbToolsImage.strings.alt_text,
        caption: sbToolsImage.strings.caption,
        description: sbToolsImage.strings.description,
    };

    function getScanFilters() {
        var filters = [];
        $('input[name="scan_filter"]:checked').each(function () {
            filters.push($(this).val());
        });
        return filters;
    }

    function getApplyFields() {
        var fields = {
            title: false,
            alt_text: false,
            caption: false,
            description: false,
        };
        $('input[name="apply_field"]:checked').each(function () {
            var key = $(this).val();
            if (fields.hasOwnProperty(key)) {
                fields[key] = true;
            }
        });
        return fields;
    }

    function hasApplyField(fields) {
        var k;
        for (k in fields) {
            if (fields.hasOwnProperty(k) && fields[k]) {
                return true;
            }
        }
        return false;
    }

    function extractErrorMessage(response, xhr) {
        var msg = '';

        if (response) {
            if (response.data && typeof response.data.message === 'string' && response.data.message !== '') {
                msg = response.data.message;
            } else if (typeof response.data === 'string' && response.data !== '') {
                msg = response.data;
            } else if (typeof response.message === 'string' && response.message !== '') {
                msg = response.message;
            }
        }

        if (!msg && xhr) {
            if (xhr.responseJSON) {
                if (xhr.responseJSON.data && typeof xhr.responseJSON.data.message === 'string') {
                    msg = xhr.responseJSON.data.message;
                } else if (typeof xhr.responseJSON.message === 'string') {
                    msg = xhr.responseJSON.message;
                } else if (typeof xhr.responseJSON.data === 'string') {
                    msg = xhr.responseJSON.data;
                }
            }
            if (!msg && xhr.status) {
                msg = String(xhr.status) + (xhr.statusText ? ' ' + xhr.statusText : '');
            }
        }

        return msg || sbToolsImage.strings.error;
    }

    function formatElapsedSeconds(ms) {
        return (ms / 1000).toFixed(1);
    }

    function formatDurationMinSec(ms) {
        var totalSeconds = Math.max(0, Math.round(ms / 1000));
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds % 60;

        return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    }

    function formatEtaMinSec(ms) {
        var roundedSeconds = Math.ceil(Math.max(0, ms) / 1000 / 30) * 30;

        if (roundedSeconds < 60) {
            return sbToolsImage.strings.eta_under_one_min || '< 1 min';
        }

        return formatDurationMinSec(roundedSeconds * 1000);
    }

    function clearBatchEta() {
        $('#sb-tools-preview-eta').text('').prop('hidden', true);
    }

    function updateBatchEta(doneCount, total) {
        var $eta = $('#sb-tools-preview-eta');

        if (!state.isRunning || doneCount < 2 || doneCount >= total || !state.batchStartedAt) {
            clearBatchEta();
            return;
        }

        var elapsed = getBatchElapsedMs();
        var avgMs = elapsed / doneCount;
        var remainingMs = (total - doneCount) * avgMs;
        var etaText = sbToolsImage.strings.eta_remaining.replace(
            '%s',
            formatEtaMinSec(remainingMs)
        );

        $eta.text(etaText).prop('hidden', false);
    }

    function onBeforeUnload(event) {
        if (!state.isRunning) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
        return '';
    }

    function bindLeaveTabWarning() {
        if (!leaveTabHandler) {
            leaveTabHandler = onBeforeUnload;
            window.addEventListener('beforeunload', leaveTabHandler);
        }

        $('#sb-tools-tab-warning').prop('hidden', false);
    }

    function unbindLeaveTabWarning() {
        if (leaveTabHandler) {
            window.removeEventListener('beforeunload', leaveTabHandler);
            leaveTabHandler = null;
        }

        $('#sb-tools-tab-warning').prop('hidden', true);
    }

    function hideFailedSummary() {
        state.lastFailedItems = [];
        $('#sb-tools-preview-failed').prop('hidden', true);
        $('#sb-tools-preview-failed-list').empty();
        $('#sb-tools-retry-failed').prop('hidden', true);
    }

    function renderFailedSummary(items, totalFailed) {
        items = items || [];
        totalFailed = totalFailed || items.length;

        if (totalFailed <= 0) {
            hideFailedSummary();
            return;
        }

        state.lastFailedItems = items.slice();
        var $list = $('#sb-tools-preview-failed-list');
        $list.empty();

        var toShow = state.lastFailedItems.slice(0, FAILED_DISPLAY_LIMIT);
        toShow.forEach(function (item) {
            var id = parseInt(item.attachment_id, 10) || 0;
            var title = item.image_title || ('#' + id);
            var message = item.message || sbToolsImage.strings.error;
            var editUrl = item.edit_url || '#';
            var thumb = item.thumb_url
                ? '<img src="' + escapeAttr(item.thumb_url) + '" alt="" />'
                : '';

            $list.append(
                '<li class="sb-tools-preview-failed-item">' +
                    (thumb ? '<span class="sb-tools-preview-failed-thumb">' + thumb + '</span>' : '') +
                    '<span class="sb-tools-preview-failed-meta">' +
                        '<strong><a href="' + escapeAttr(editUrl) + '">' + escapeHtml(title) + '</a></strong>' +
                        '<span class="sb-tools-preview-failed-message">' + escapeHtml(message) + '</span>' +
                    '</span>' +
                '</li>'
            );
        });

        if (totalFailed > FAILED_DISPLAY_LIMIT) {
            $list.append(
                '<li class="sb-tools-preview-failed-more">' +
                    escapeHtml(
                        sbToolsImage.strings.failed_and_more.replace(
                            '%d',
                            String(totalFailed - FAILED_DISPLAY_LIMIT)
                        )
                    ) +
                '</li>'
            );
        }

        $('#sb-tools-preview-failed').prop('hidden', false);
        $('#sb-tools-retry-failed')
            .text(sbToolsImage.strings.retry_failed.replace('%d', String(totalFailed)))
            .prop('hidden', state.isRunning);
    }

    function getFailedAttachmentIds() {
        var ids = [];
        state.lastFailedItems.forEach(function (item) {
            var id = parseInt(item.attachment_id, 10);
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function retryFailedBatch() {
        var failedIds = getFailedAttachmentIds();
        if (failedIds.length === 0) {
            return;
        }

        hideFailedSummary();
        startBatchRequest({
            processScope: 'selected',
            attachmentIds: failedIds,
        });
    }

    function normalizeFailedItems(data) {
        var items = data.failed_items || [];
        var totalFailed = data.failed || items.length;

        if (items.length === 0 && data.failed_ids && data.failed_ids.length) {
            items = data.failed_ids.map(function (id) {
                return {
                    attachment_id: id,
                    message: sbToolsImage.strings.error,
                    image_title: '#' + id,
                    thumb_url: '',
                    edit_url: '',
                };
            });
        }

        return {
            items: items,
            totalFailed: totalFailed,
        };
    }

    function getBatchElapsedMs() {
        if (!state.batchStartedAt) {
            return 0;
        }
        return Date.now() - state.batchStartedAt;
    }

    function captureDocumentTitle() {
        if (!state.originalDocumentTitle) {
            state.originalDocumentTitle = document.title;
        }
    }

    function clearTitleRestoreTimeout() {
        if (state.titleRestoreTimeout) {
            clearTimeout(state.titleRestoreTimeout);
            state.titleRestoreTimeout = null;
        }
    }

    function setDocumentTitleStatus(statusText) {
        captureDocumentTitle();
        document.title = statusText + ' \u2014 ' + state.originalDocumentTitle;
    }

    function restoreDocumentTitle(delayMs) {
        clearTitleRestoreTimeout();

        if (!state.originalDocumentTitle) {
            return;
        }

        if (!delayMs) {
            document.title = state.originalDocumentTitle;
            return;
        }

        state.titleRestoreTimeout = setTimeout(function () {
            document.title = state.originalDocumentTitle;
            state.titleRestoreTimeout = null;
        }, delayMs);
    }

    function updateDocumentTitleProgress(current, total) {
        if (!state.isRunning) {
            return;
        }

        var status = sbToolsImage.strings.title_processing
            .replace('%1$d', String(Math.min(current, total)))
            .replace('%2$d', String(total));
        setDocumentTitleStatus(status);
    }

    function updateDocumentTitleComplete(processed, failed, wasCancelled) {
        var status;

        if (wasCancelled) {
            status = sbToolsImage.strings.title_cancelled.replace(
                '%d',
                String(processed || 0)
            );
        } else {
            status = sbToolsImage.strings.title_complete
                .replace('%1$d', String(processed || 0))
                .replace('%2$d', String(failed || 0));
        }

        setDocumentTitleStatus(status);
        restoreDocumentTitle(60000);
    }

    function updateDocumentTitleScanning() {
        setDocumentTitleStatus(sbToolsImage.strings.scanning);
    }

    function stopProcessingTimer() {
        if (state.processingTimerInterval) {
            clearInterval(state.processingTimerInterval);
            state.processingTimerInterval = null;
        }
    }

    function getProcessingElapsedMs() {
        if (!state.processingStartedAt) {
            return 0;
        }
        return Date.now() - state.processingStartedAt;
    }

    function updateProcessingTimerDisplay() {
        var elapsed = formatElapsedSeconds(getProcessingElapsedMs());
        $('#sb-tools-preview-processing-timer').text(
            sbToolsImage.strings.timer_elapsed.replace('%s', elapsed)
        );
    }

    function startProcessingTimer() {
        stopProcessingTimer();
        state.processingStartedAt = Date.now();
        updateProcessingTimerDisplay();
        state.processingTimerInterval = setInterval(updateProcessingTimerDisplay, 250);
    }

    function finalizeProcessingTimer(isError) {
        var elapsedMs = getProcessingElapsedMs();
        stopProcessingTimer();
        state.processingStartedAt = null;

        var elapsed = formatElapsedSeconds(elapsedMs);
        var tpl = isError ? sbToolsImage.strings.timer_failed : sbToolsImage.strings.timer_completed;
        $('#sb-tools-preview-completed-timer').text(tpl.replace('%s', elapsed));
        $('#sb-tools-preview-processing-timer').text('');
    }

    function showProcessActions() {
        $('#sb-tools-process-actions').prop('hidden', false);
    }

    function hideProcessActions() {
        $('#sb-tools-process-actions').prop('hidden', true);
    }

    function hideResults() {
        $('#sb-tools-results').hide().removeClass('is-rescanning');
    }

    function showResultsIfAvailable() {
        if (state.scanItems.length > 0 || state.totalFound > 0) {
            $('#sb-tools-results').show();
        }
    }

    function resetResultsForRescan() {
        state.scanItems = [];
        state.totalFound = 0;

        $('#sb-tools-results-body').html(
            '<tr class="sb-tools-results-placeholder"><td colspan="3">' +
                escapeHtml(sbToolsImage.strings.updating_results) +
                '</td></tr>'
        );
        $('#sb-tools-displaying-num').text('');
        setSelectAllChecked(false);
        hideProcessActions();
        updateProcessButtons();
        $('#sb-tools-results').addClass('is-rescanning').show();
    }

    function setScanLoading(on) {
        $('#sb-tools-scan-btn').prop('disabled', on);
        $('.sb-tools-scan-spinner').toggleClass('is-active', on);
    }

    function setCancelButtonVisible(on) {
        $('#sb-tools-cancel-batch-preview')
            .prop('hidden', !on)
            .prop('disabled', false)
            .text(sbToolsImage.strings.cancel);
    }

    function setBatchUiRunning(on) {
        state.isRunning = on;
        $('#sb-tools-scan-btn, #sb-tools-process-selected, #sb-tools-process-all-matching').prop('disabled', on);
        $('input[name="scan_filter"], input[name="apply_field"], #sb-tools-select-all-header, #sb-tools-select-all-footer').prop('disabled', on);

        if (on) {
            showProcessActions();
            setCancelButtonVisible(true);
            bindLeaveTabWarning();
            $('#sb-tools-retry-failed').prop('hidden', true);
        } else {
            setCancelButtonVisible(false);
            unbindLeaveTabWarning();
            updateProcessButtons();
        }
    }

    function showBatchCompleteMessage(processed, failed, wasCancelled) {
        var duration = formatDurationMinSec(getBatchElapsedMs());
        var tpl = wasCancelled
            ? sbToolsImage.strings.batch_cancelled
            : sbToolsImage.strings.batch_complete;
        var msg = tpl
            .replace('%1$s', duration)
            .replace('%2$d', String(processed || 0))
            .replace('%3$d', String(failed || 0));
        var progressClass = wasCancelled
            ? 'sb-tools-preview-progress--cancelled'
            : 'sb-tools-preview-progress--complete';

        $('#sb-tools-preview-progress')
            .text(msg)
            .removeClass('sb-tools-preview-progress--complete sb-tools-preview-progress--cancelled')
            .addClass(progressClass);
        $('#sb-tools-complete')
            .toggleClass('notice-warning', wasCancelled)
            .toggleClass('notice-success', !wasCancelled)
            .text(msg)
            .show();

        clearBatchEta();
        updateDocumentTitleComplete(processed, failed, wasCancelled);
    }

    function updateProcessAllMatchingLabels() {
        var count = state.totalFound || 0;
        var label = sbToolsImage.strings.process_all_matching.replace('%d', String(count));
        $('#sb-tools-process-all-matching').text(label);
    }

    function updateProcessButtons() {
        var aiOk = !!sbToolsImage.ai_available;
        var selected = $('.sb-tools-row-check:checked').length;
        var disableSelected = !aiOk || state.isRunning || selected === 0;
        var disableAllMatching = !aiOk || state.isRunning || state.totalFound === 0;

        $('#sb-tools-process-selected').prop('disabled', disableSelected);
        $('#sb-tools-process-all-matching').prop('disabled', disableAllMatching);
        updateProcessAllMatchingLabels();
    }

    function runScan(options) {
        options = options || {};
        var filters = getScanFilters();
        if (filters.length === 0) {
            window.alert(sbToolsImage.strings.select_filter);
            return;
        }

        setScanLoading(true);
        hideProcessActions();
        if (!options.keepCompleteNotice) {
            clearTitleRestoreTimeout();
            $('#sb-tools-complete').hide();
            updateDocumentTitleScanning();
        }

        $.ajax({
            url: sbToolsImage.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_tools_scan_images',
                nonce: sbToolsImage.nonce,
                filters: filters,
            },
            success: function (response) {
                setScanLoading(false);
                $('#sb-tools-results').removeClass('is-rescanning');
                if (!state.isRunning && !options.keepCompleteNotice) {
                    restoreDocumentTitle();
                }
                if (response.success && response.data) {
                    state.scanItems = response.data.items || [];
                    state.totalFound = response.data.total_found || 0;
                    renderResults(response.data);
                } else {
                    showResultsScanError();
                    window.alert(extractErrorMessage(response));
                }
            },
            error: function (xhr) {
                setScanLoading(false);
                $('#sb-tools-results').removeClass('is-rescanning');
                if (!state.isRunning && !options.keepCompleteNotice) {
                    restoreDocumentTitle();
                }
                showResultsScanError();
                window.alert(extractErrorMessage(null, xhr));
            },
        });
    }

    function buildResultsRow(item) {
        var issuesText = (item.issues || [])
            .map(function (issue) {
                return issueLabels[issue] || issue;
            })
            .join(', ');

        var editUrl = item.edit_url || '#';
        var viewUrl = item.view_url || '#';
        var displayTitle = item.title || item.filename || '#' + item.id;
        var filename = item.filename || displayTitle;
        var thumbHtml = '';

        if (item.thumb_url) {
            thumbHtml =
                '<span class="media-icon image-icon">' +
                '<img src="' + escapeAttr(item.thumb_url) + '" alt="" /></span>';
        }

        var rowActions = '';
        if (item.edit_url) {
            rowActions +=
                '<span class="edit"><a href="' +
                escapeAttr(editUrl) +
                '">' +
                escapeHtml(sbToolsImage.strings.edit) +
                '</a> | </span>';
        }
        if (item.view_url) {
            rowActions +=
                '<span class="view"><a href="' +
                escapeAttr(viewUrl) +
                '" target="_blank" rel="noopener noreferrer">' +
                escapeHtml(sbToolsImage.strings.view) +
                '</a></span>';
        }

        return (
            '<tr data-attachment-id="' +
            item.id +
            '">' +
            '<th scope="row" class="check-column">' +
            '<input type="checkbox" class="sb-tools-row-check" value="' +
            item.id +
            '" /></th>' +
            '<td class="title column-title has-row-actions column-primary" data-colname="' +
            escapeAttr(sbToolsImage.strings.file_col || 'File') +
            '">' +
            '<strong class="' +
            (thumbHtml ? 'has-media-icon' : '') +
            '">' +
            '<a class="row-title" href="' +
            escapeAttr(editUrl) +
            '">' +
            thumbHtml +
            escapeHtml(displayTitle) +
            '</a></strong>' +
            '<p class="filename">' +
            '<span class="screen-reader-text">' +
            escapeHtml(sbToolsImage.strings.filename_label || 'File name:') +
            '</span>' +
            escapeHtml(filename) +
            '</p>' +
            (rowActions
                ? '<div class="row-actions">' + rowActions + '</div>'
                : '') +
            '<button type="button" class="toggle-row">' +
            '<span class="screen-reader-text">' +
            escapeHtml(sbToolsImage.strings.toggle_row || 'Show more details') +
            '</span></button>' +
            '</td>' +
            '<td class="issues column-issues" data-colname="' +
            escapeAttr(sbToolsImage.strings.issues_col || 'Issues') +
            '">' +
            escapeHtml(issuesText || sbToolsImage.strings.empty) +
            '</td>' +
            '</tr>'
        );
    }

    function showResultsScanError() {
        $('#sb-tools-results-body').html(
            '<tr class="sb-tools-results-placeholder"><td colspan="3">' +
                escapeHtml(sbToolsImage.strings.scan_refresh_failed) +
                '</td></tr>'
        );
        $('#sb-tools-displaying-num').text('');
        hideProcessActions();
    }

    function renderResults(data) {
        var $body = $('#sb-tools-results-body');
        $body.empty();

        if (!data.items || data.items.length === 0) {
            $body.append(
                '<tr><td colspan="3">' + escapeHtml(sbToolsImage.strings.no_results) + '</td></tr>'
            );
        } else {
            data.items.forEach(function (item) {
                $body.append(buildResultsRow(item));
            });
        }

        $('#sb-tools-results').show();

        var displayingText = formatDisplayingNum(data);
        $('#sb-tools-displaying-num').text(displayingText);

        setSelectAllChecked(false);
        showProcessActions();
        $('.sb-tools-process-hint').prop('hidden', true);
        updateProcessButtons();
    }

    function formatDisplayingNum(data) {
        var total = data.total_found || 0;
        var previewLimit = data.preview_limit || 50;

        if (total === 0) {
            return sbToolsImage.strings.matching_many.replace('%d', '0');
        }
        if (total === 1) {
            return sbToolsImage.strings.matching_one.replace('%d', '1');
        }
        if (total <= previewLimit) {
            return sbToolsImage.strings.matching_many.replace('%d', String(total));
        }
        return sbToolsImage.strings.preview_sample
            .replace('%1$d', String(previewLimit))
            .replace('%2$d', String(total));
    }

    function setSelectAllChecked(checked) {
        $('#sb-tools-select-all-header, #sb-tools-select-all-footer').prop(
            'checked',
            checked
        );
    }

    function getSelectedIds() {
        var ids = [];
        $('.sb-tools-row-check:checked').each(function () {
            ids.push(parseInt($(this).val(), 10));
        });
        return ids;
    }

    function startBatch(attachmentIds) {
        startBatchRequest({
            processScope: 'selected',
            attachmentIds: attachmentIds,
        });
    }

    function startBatchAllMatching() {
        if (!sbToolsImage.ai_available) {
            window.alert(
                sbToolsImage.ai_unavailable_message || sbToolsImage.strings.ai_disabled
            );
            return;
        }

        var applyFields = getApplyFields();
        if (!hasApplyField(applyFields)) {
            window.alert(sbToolsImage.strings.select_apply);
            return;
        }

        var filters = getScanFilters();
        if (filters.length === 0) {
            window.alert(sbToolsImage.strings.select_filter);
            return;
        }

        if (state.totalFound === 0) {
            window.alert(sbToolsImage.strings.no_results);
            return;
        }

        var confirmMsg = sbToolsImage.strings.confirm_process_all.replace(
            '%d',
            String(state.totalFound)
        );
        if (!window.confirm(confirmMsg)) {
            return;
        }

        startBatchRequest({
            processScope: 'all_matching',
            filters: filters,
        });
    }

    function startBatchRequest(options) {
        options = options || {};
        var processScope = options.processScope || 'selected';
        var attachmentIds = options.attachmentIds || [];

        if (!sbToolsImage.ai_available) {
            window.alert(
                sbToolsImage.ai_unavailable_message || sbToolsImage.strings.ai_disabled
            );
            return;
        }

        var applyFields = getApplyFields();
        if (!hasApplyField(applyFields)) {
            window.alert(sbToolsImage.strings.select_apply);
            return;
        }

        if (processScope !== 'all_matching') {
            if (!attachmentIds || attachmentIds.length === 0) {
                window.alert(sbToolsImage.strings.select_images);
                return;
            }
        }

        state.applyFields = applyFields;
        state.isCancelled = false;
        state.batchStartedAt = null;
        clearTitleRestoreTimeout();
        captureDocumentTitle();
        $('#sb-tools-complete').hide().removeClass('notice-warning').addClass('notice-success');

        openBatchPreview();

        if (processScope !== 'all_matching' && attachmentIds.length > 0) {
            cacheAttachmentContexts(attachmentIds);
            updatePreviewProgress(1, attachmentIds.length);
            showProcessingCard(attachmentIds[0]);
        } else {
            updatePreviewProgress(0, state.totalFound);
            $('#sb-tools-preview-processing-filename').text(sbToolsImage.strings.processing.replace('%1$d', '0').replace('%2$d', String(state.totalFound)));
        }

        var postData = {
            action: 'sb_tools_start_batch',
            nonce: sbToolsImage.nonce,
            apply_fields: applyFields,
            process_scope: processScope,
            filters: options.filters || getScanFilters(),
        };

        if (processScope !== 'all_matching') {
            postData.attachment_ids = attachmentIds;
        }

        $.ajax({
            url: sbToolsImage.ajax_url,
            type: 'POST',
            data: postData,
            success: function (response) {
                if (response.success && response.data) {
                    state.batchId = response.data.batch_id;
                    state.queue = response.data.attachment_ids.slice();
                    state.queueIndex = 0;
                    state.batchStartedAt = Date.now();
                    cacheAttachmentContexts(state.queue);
                    setBatchUiRunning(true);
                    updatePreviewProgress(0, state.queue.length);
                    processNextInQueue();
                } else {
                    closeBatchPreview();
                    window.alert(extractErrorMessage(response));
                }
            },
            error: function (xhr) {
                closeBatchPreview();
                window.alert(extractErrorMessage(null, xhr));
            },
        });
    }

    function processNextInQueue() {
        if (state.isCancelled || !state.batchId) {
            finishBatch();
            return;
        }

        if (state.queueIndex >= state.queue.length) {
            finishBatch();
            return;
        }

        var attachmentId = state.queue[state.queueIndex];
        state.queueIndex += 1;

        var processToken = 'proc_' + attachmentId + '_' + Date.now();
        state.activeProcessToken = processToken;

        updatePreviewProgress(state.queueIndex, state.queue.length);
        showProcessingCard(attachmentId);

        state.activeRequest = $.ajax({
            url: sbToolsImage.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_tools_process_image',
                batch_id: state.batchId,
                attachment_id: attachmentId,
                nonce: sbToolsImage.nonce,
            },
            timeout: 120000,
            success: function (response) {
                if (state.isCancelled || state.activeProcessToken !== processToken) {
                    return;
                }
                if (response.success && response.data) {
                    showCompletedCard(response.data, false);
                    processNextInQueue();
                } else {
                    var errData = response.data || {};
                    var ctx = getAttachmentContext(attachmentId);
                    showCompletedCard(
                        {
                            attachment_id: attachmentId,
                            image_title: errData.image_title || ctx.image_title,
                            thumb_url: errData.thumb_url || ctx.thumb_url,
                            before: ctx.before,
                            after: {},
                            apply_fields: state.applyFields,
                            message: extractErrorMessage(response),
                            progress: errData.progress,
                        },
                        true
                    );
                    processNextInQueue();
                }
            },
            error: function (xhr, status) {
                if (state.isCancelled || status === 'abort' || state.activeProcessToken !== processToken) {
                    return;
                }
                var errCtx = getAttachmentContext(attachmentId);
                showCompletedCard(
                    {
                        attachment_id: attachmentId,
                        image_title: errCtx.image_title,
                        thumb_url: errCtx.thumb_url,
                        before: errCtx.before,
                        after: {},
                        apply_fields: state.applyFields,
                        message: extractErrorMessage(null, xhr),
                    },
                    true
                );
                processNextInQueue();
            },
            complete: function () {
                state.activeRequest = null;
            },
        });
    }

    function updatePreviewProgress(current, total) {
        var doneCount = Math.min(current, total);
        var text = sbToolsImage.strings.processing
            .replace('%1$d', String(doneCount))
            .replace('%2$d', String(total));
        $('#sb-tools-preview-progress')
            .removeClass('sb-tools-preview-progress--complete sb-tools-preview-progress--cancelled')
            .text(text);
        updateDocumentTitleProgress(doneCount, total);
        updateBatchEta(doneCount, total);
    }

    function openBatchPreview() {
        var $preview = $('#sb-tools-preview');
        var $inner = $preview.find('.sb-tools-preview-inner');

        $inner.removeClass('sb-tools-preview-fade-out sb-tools-preview-fade-in').css({
            opacity: '',
            transition: '',
        });

        $preview
            .show()
            .addClass('is-batch-active')
            .removeClass('is-batch-complete is-batch-complete-cancelled');
        $('#sb-tools-preview-progress')
            .removeClass('sb-tools-preview-progress--complete sb-tools-preview-progress--cancelled')
            .text('');
        clearBatchEta();
        hideFailedSummary();
        hideResults();
        resetCompletedPreview();
        unbindLeaveTabWarning();
        $('#sb-tools-preview-processing').show().css({ display: '', opacity: 1, visibility: 'visible' });
    }

    function closeBatchPreview() {
        stopProcessingTimer();
        state.processingStartedAt = null;
        restoreDocumentTitle();
        unbindLeaveTabWarning();
        clearBatchEta();
        hideFailedSummary();
        $('#sb-tools-preview').hide().removeClass('is-batch-active is-batch-complete is-batch-complete-cancelled');
        showResultsIfAvailable();
        resetCompletedPreview();
        $('#sb-tools-preview-processing').show();
        $('#sb-tools-preview-processing-timer').text('');
        $('#sb-tools-preview-completed-timer').text('');
        state.attachmentContext = {};
        state.activeProcessToken = null;
    }

    function resetCompletedPreview() {
        $('#sb-tools-preview-completed').hide().removeClass('is-error');
        $('#sb-tools-preview-completed-error').hide().text('');
        $('#sb-tools-preview-completed-timer').text('');
        $('#sb-tools-preview-skip').prop('hidden', true);
        $('#sb-tools-preview-completed-img').attr('src', '').css({ visibility: 'hidden' });
    }

    function cacheAttachmentContexts(attachmentIds) {
        state.attachmentContext = {};
        attachmentIds.forEach(function (id) {
            var intId = parseInt(id, 10);
            if (intId > 0) {
                state.attachmentContext[intId] = lookupAttachmentContext(intId);
            }
        });
    }

    function getAttachmentContext(attachmentId) {
        var id = parseInt(attachmentId, 10);
        if (state.attachmentContext[id]) {
            return state.attachmentContext[id];
        }
        var ctx = lookupAttachmentContext(id);
        state.attachmentContext[id] = ctx;
        return ctx;
    }

    function lookupAttachmentContext(id) {
        var i;
        for (i = 0; i < state.scanItems.length; i++) {
            if (state.scanItems[i].id === id) {
                var item = state.scanItems[i];
                return {
                    image_title: item.filename || item.title || '#' + id,
                    thumb_url: item.thumb_url || '',
                    before: {
                        title: item.title || '',
                        alt_text: item.meta && item.meta.alt_text ? item.meta.alt_text : '',
                        caption: item.meta && item.meta.caption ? item.meta.caption : '',
                        description: item.meta && item.meta.description ? item.meta.description : '',
                    },
                };
            }
        }

        var $row = $('tr[data-attachment-id="' + id + '"]');
        return {
            image_title:
                $row.find('.column-title .row-title').text() ||
                $row.find('.column-title strong').text() ||
                '#' + id,
            thumb_url: $row.find('.column-title .media-icon img').attr('src') || '',
            before: {},
        };
    }

    function setPreviewThumb(imgSelector, url) {
        var $img = $(imgSelector);
        if (!url) {
            return;
        }
        $img.attr('src', url).css({ display: 'block', visibility: 'visible', opacity: 1 });
    }

    function showProcessingCard(attachmentId) {
        var ctx = getAttachmentContext(attachmentId);

        $('#sb-tools-preview-processing-filename').text(ctx.image_title);
        setPreviewThumb('#sb-tools-preview-processing-img', ctx.thumb_url);
        renderPreviewFields(
            '#sb-tools-preview-processing-before-list',
            ctx.before,
            state.applyFields,
            true
        );

        $('#sb-tools-preview-processing')
            .show()
            .css({ display: 'block', opacity: 1, visibility: 'visible' });
        $('#sb-tools-preview-processing .sb-tools-preview-spinner').addClass('is-active');
        startProcessingTimer();
    }

    function showCompletedCard(data, isError) {
        finalizeProcessingTimer(isError);

        var $card = $('#sb-tools-preview-completed');

        $card.toggleClass('is-error', !!isError).show();

        var errorText = isError ? data.message || sbToolsImage.strings.error : '';
        if (isError && errorText && sbToolsImage.strings.metadata_not_saved
            && errorText.indexOf(sbToolsImage.strings.metadata_not_saved) === -1
            && errorText.toLowerCase().indexOf('no metadata was saved') === -1) {
            errorText += ' ' + sbToolsImage.strings.metadata_not_saved;
        }

        $('#sb-tools-preview-completed-error')
            .toggle(!!isError)
            .text(errorText);
        $('#sb-tools-preview-skip').prop('hidden', !isError);

        var filename = data.image_title || '#' + (data.attachment_id || '');
        $('#sb-tools-preview-completed-filename').text(filename);

        var thumb = data.thumb_url || data.image_source_url || getAttachmentContext(data.attachment_id).thumb_url;
        setPreviewThumb('#sb-tools-preview-completed-img', thumb);

        var timerText = $('#sb-tools-preview-completed-timer').text();
        if (!isError && data.image_size && sbToolsImage.strings.image_source_size) {
            var sizeLabel = sbToolsImage.strings.image_source_size.replace('%s', data.image_size);
            if (timerText) {
                timerText += ' · ' + sizeLabel;
            } else {
                timerText = sizeLabel;
            }
            $('#sb-tools-preview-completed-timer').text(timerText);
        }

        renderPreviewFields(
            '#sb-tools-preview-completed-before-list',
            data.before || {},
            data.apply_fields || state.applyFields,
            true
        );
        renderPreviewFields(
            '#sb-tools-preview-completed-after-list',
            data.after || {},
            data.apply_fields || state.applyFields,
            false
        );

        if (data.progress) {
            updatePreviewProgress(
                (data.progress.processed || 0) + (data.progress.failed || 0),
                data.progress.total || state.queue.length
            );
        }
    }

    function renderPreviewFields(listSelector, values, applyFields, isBefore) {
        var $list = $(listSelector);
        $list.empty();

        var fields = ['title', 'alt_text', 'caption', 'description'];
        fields.forEach(function (key) {
            if (!applyFields || !applyFields[key]) {
                return;
            }
            var val = values[key];
            if (val === undefined || val === null || val === '') {
                val = sbToolsImage.strings.empty;
            }
            $list.append(
                '<dt>' + escapeHtml(fieldLabels[key] || key) + '</dt>' +
                '<dd>' + escapeHtml(String(val)) + '</dd>'
            );
        });

        if ($list.children().length === 0 && isBefore) {
            $list.append('<dd>' + escapeHtml(sbToolsImage.strings.empty) + '</dd>');
        }
    }

    function finishBatch() {
        var wasCancelled = state.isCancelled;
        var batchId = state.batchId;
        var fallbackProcessed = state.queueIndex;

        setBatchUiRunning(false);
        state.activeProcessToken = null;
        stopProcessingTimer();
        state.processingStartedAt = null;

        $('#sb-tools-preview-processing .sb-tools-preview-spinner').removeClass('is-active');
        $('#sb-tools-preview-processing').hide();
        $('#sb-tools-preview-processing-timer').text('');
        $('#sb-tools-preview')
            .removeClass('is-batch-active is-batch-complete-cancelled')
            .addClass('is-batch-complete')
            .toggleClass('is-batch-complete-cancelled', wasCancelled);

        state.batchId = null;
        state.queue = [];
        state.attachmentContext = {};
        state.isCancelled = false;

        if (!batchId) {
            state.batchStartedAt = null;
            showResultsIfAvailable();
            return;
        }

        resetResultsForRescan();

        $.ajax({
            url: sbToolsImage.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_tools_batch_status',
                batch_id: batchId,
                nonce: sbToolsImage.nonce,
            },
            success: function (response) {
                if (response.success && response.data) {
                    var d = response.data;
                    showBatchCompleteMessage(d.processed || 0, d.failed || 0, wasCancelled);
                    if ((d.failed || 0) > 0) {
                        var failedData = normalizeFailedItems(d);
                        renderFailedSummary(failedData.items, failedData.totalFailed);
                    }
                    return;
                }
                showBatchCompleteMessage(fallbackProcessed, 0, wasCancelled);
            },
            error: function () {
                showBatchCompleteMessage(fallbackProcessed, 0, wasCancelled);
            },
            complete: function () {
                state.batchStartedAt = null;
                runScan({ keepCompleteNotice: true });
            },
        });
    }

    function cancelBatch() {
        state.isCancelled = true;
        stopProcessingTimer();
        state.processingStartedAt = null;
        if (state.activeRequest) {
            state.activeRequest.abort();
            state.activeRequest = null;
        }
        if (!state.batchId) {
            finishBatch();
            return;
        }

        $('#sb-tools-cancel-batch-preview')
            .prop('disabled', true)
            .text(sbToolsImage.strings.cancelling);

        $.ajax({
            url: sbToolsImage.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_tools_cancel_batch',
                batch_id: state.batchId,
                nonce: sbToolsImage.nonce,
            },
            complete: function () {
                finishBatch();
            },
        });
    }

    function escapeHtml(str) {
        return $('<div/>').text(str).html();
    }

    function escapeAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    $(function () {
        captureDocumentTitle();

        $('#sb-tools-scan-btn').on('click', function () {
            runScan();
        });

        $('#sb-tools-select-all-header, #sb-tools-select-all-footer').on(
            'change',
            function () {
                var checked = $(this).is(':checked');
                $('.sb-tools-row-check').prop('checked', checked);
                setSelectAllChecked(checked);
                updateProcessButtons();
            }
        );

        $(document).on('change', '.sb-tools-row-check', updateProcessButtons);

        $('#sb-tools-process-selected').on('click', function () {
            startBatch(getSelectedIds());
        });

        $('#sb-tools-process-all-matching').on('click', function () {
            startBatchAllMatching();
        });

        $('#sb-tools-cancel-batch-preview').on('click', cancelBatch);

        $('#sb-tools-retry-failed').on('click', retryFailedBatch);

        $('#sb-tools-preview-skip').on('click', function () {
            state.activeProcessToken = null;
            stopProcessingTimer();
            state.processingStartedAt = null;
            if (state.activeRequest) {
                state.activeRequest.abort();
                state.activeRequest = null;
            }
            processNextInQueue();
        });
    });
})(jQuery);
