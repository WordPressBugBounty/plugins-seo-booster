/**
 * Shared batch preview UI for SEO Booster Tools tabs.
 */
(function ($) {
    'use strict';

    function esc(value) {
        return $('<div>').text(value === undefined || value === null ? '' : value).html();
    }

    function format(template, values) {
        var out = template || '';
        Object.keys(values).forEach(function (key) {
            out = out.split(key).join(values[key]);
        });
        return out;
    }

    window.SBToolsBatchPreview = {
        /**
         * @param {Object} options
         * @param {string} options.prefix DOM id prefix (e.g. sb-tools-gsc-opp).
         * @param {Object} options.strings Localized strings.
         * @param {Function} [options.renderResult] Render success body HTML from payload.
         * @param {Function} [options.renderFailed] Render failed item label from item object.
         * @return {Object}
         */
        create: function (options) {
            var prefix = options.prefix;
            var strings = options.strings || {};
            var renderResult = options.renderResult || function () {
                return '';
            };
            var renderFailed = options.renderFailed || function (item) {
                return esc(item.message || strings.error || 'Error');
            };

            var $preview = $('#' + prefix + '-preview');
            var $progress = $('#' + prefix + '-preview-progress');
            var $cancel = $('#' + prefix + '-cancel-batch');
            var $tabWarning = $('#' + prefix + '-tab-warning');
            var $completed = $('#' + prefix + '-preview-completed');
            var $title = $('#' + prefix + '-preview-title');
            var $body = $('#' + prefix + '-preview-body');
            var $error = $('#' + prefix + '-preview-error');
            var $failedSection = $('#' + prefix + '-preview-failed');
            var $failedList = $('#' + prefix + '-preview-failed-list');
            var $complete = $('#' + prefix + '-complete');

            return {
                start: function () {
                    $failedList.empty();
                    $failedSection.prop('hidden', true);
                    $completed.hide();
                    $error.hide().text('');
                    $complete.hide().removeClass('notice-success notice-warning notice-error');
                    $preview.show().addClass('is-batch-active');
                    $tabWarning.prop('hidden', false);
                    $cancel.prop('hidden', false);
                    $progress.text('');
                },

                setProgress: function (current, total) {
                    $progress.text(
                        format(strings.processing || 'Processing %1$d of %2$d…', {
                            '%1$d': current,
                            '%2$d': total,
                        })
                    );
                },

                showResult: function (payload) {
                    var title = payload.post_title || payload.keyword || payload.title || '';
                    if (payload.edit_url && title) {
                        $title.html('<a href="' + esc(payload.edit_url) + '">' + esc(title) + '</a>');
                    } else if (title) {
                        $title.text(title);
                    } else {
                        $title.text('');
                    }

                    $body.html(renderResult(payload));

                    if (payload.skipped && payload.message) {
                        $error.text(payload.message).show();
                    } else {
                        $error.hide().text('');
                    }

                    $completed.show();
                },

                addFailed: function (item) {
                    $failedList.append('<li>' + renderFailed(item) + '</li>');
                    $failedSection.prop('hidden', false);
                },

                mergeFailedItems: function (items) {
                    if (!items || !items.length) {
                        return;
                    }
                    items.forEach(function (item) {
                        this.addFailed(item);
                    }.bind(this));
                },

                finish: function (status, wasCancelled) {
                    $cancel.prop('hidden', true);
                    $preview.removeClass('is-batch-active');

                    if (status && status.failed_items && status.failed_items.length) {
                        this.mergeFailedItems(status.failed_items);
                    }

                    var processed = status ? (status.processed || 0) : 0;
                    var failed = status ? (status.failed || 0) : 0;
                    var tpl = wasCancelled
                        ? (strings.batch_cancelled || strings.batch_complete)
                        : strings.batch_complete;

                    if (tpl) {
                        $complete
                            .removeClass('notice-success notice-warning notice-error')
                            .addClass(wasCancelled ? 'notice-warning' : 'notice-success')
                            .text(format(tpl, {
                                '%1$d': processed,
                                '%2$d': failed,
                                '%3$d': failed,
                            }))
                            .show();
                    }
                },

                reset: function () {
                    $preview.hide().removeClass('is-batch-active');
                    $complete.hide();
                },

                getCancelButton: function () {
                    return $cancel;
                },
            };
        },
    };
})(jQuery);
