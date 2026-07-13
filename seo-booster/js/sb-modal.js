/**
 * SEO Booster — styled alert/confirm modal (replaces window.alert / window.confirm).
 */
(function ($) {
    'use strict';

    window.SBModal = window.SBModal || {};

    var strings = (window.sbModalStrings && window.sbModalStrings.strings) || {};
    var modalSeq = 0;
    var openCount = 0;

    function defaultTitleForTone(tone) {
        if (!tone) {
            return '';
        }
        if (tone === 'error') {
            return strings.title_error || 'Error';
        }
        if (tone === 'warning') {
            return strings.title_warning || 'Warning';
        }
        if (tone === 'success') {
            return strings.title_success || 'Success';
        }
        if (tone === 'info') {
            return strings.title_info || '';
        }
        return '';
    }

    function resolveTitle(options) {
        if (options.title) {
            return options.title;
        }
        return defaultTitleForTone(options.tone);
    }

    /**
     * @param {Object} options
     * @return {Object} jQuery promise resolving to true/false.
     */
    function openModal(options) {
        options = options || {};
        var isConfirm = !!options.confirm;
        var closeOnOverlay = options.closeOnOverlay !== false;
        var dfd = $.Deferred();
        var lastFocused = document.activeElement;
        var titleId = 'sb-modal-title-' + (++modalSeq);
        var resolvedTitle = resolveTitle(options);

        var $overlay = $('<div class="sb-modal-overlay"></div>');
        var $modal = $('<div class="sb-modal" tabindex="-1"></div>')
            .attr('role', isConfirm ? 'alertdialog' : 'dialog')
            .attr('aria-modal', 'true');

        if (options.tone) {
            $modal.addClass('sb-modal--' + options.tone);
        }

        if (resolvedTitle) {
            $('<h2 class="sb-modal-title"></h2>')
                .attr('id', titleId)
                .text(resolvedTitle)
                .appendTo($modal);
            $modal.attr('aria-labelledby', titleId);
        }

        $('<div class="sb-modal-body"></div>')
            .text(options.message || '')
            .appendTo($modal);

        var $footer = $('<div class="sb-modal-footer"></div>').appendTo($modal);

        var closed = false;
        var keyNs = 'keydown.sbModal' + modalSeq;
        var $cancel = null;
        var focusables = [];

        function refreshFocusables() {
            focusables = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible').toArray();
        }

        function close(result) {
            if (closed) {
                return;
            }
            closed = true;
            $(document).off(keyNs);
            $overlay.remove();
            openCount = Math.max(0, openCount - 1);
            if (openCount === 0) {
                $('body').removeClass('sb-modal-open');
            }
            if (lastFocused && typeof lastFocused.focus === 'function') {
                try {
                    lastFocused.focus();
                } catch (ignore) {
                    // Element may no longer be focusable.
                }
            }
            dfd.resolve(result);
        }

        if (isConfirm) {
            $cancel = $('<button type="button" class="button sb-modal-cancel"></button>')
                .text(options.cancelLabel || strings.cancel || 'Cancel')
                .on('click', function () { close(false); })
                .appendTo($footer);
        }

        var $ok = $('<button type="button" class="button button-primary sb-modal-ok"></button>')
            .text(options.okLabel || strings.ok || 'OK')
            .on('click', function () { close(true); })
            .appendTo($footer);

        if (closeOnOverlay) {
            $overlay.on('click', function (event) {
                if (event.target === $overlay[0]) {
                    close(false);
                }
            });
        }

        $(document).on(keyNs, function (event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                event.preventDefault();
                close(false);
                return;
            }

            if (event.key === 'Tab' || event.keyCode === 9) {
                refreshFocusables();
                if (!focusables.length) {
                    event.preventDefault();
                    return;
                }
                var first = focusables[0];
                var last = focusables[focusables.length - 1];
                var active = document.activeElement;
                if (event.shiftKey) {
                    if (active === first || !$modal[0].contains(active)) {
                        event.preventDefault();
                        last.focus();
                    }
                } else if (active === last) {
                    event.preventDefault();
                    first.focus();
                }
                return;
            }

            if (event.key === 'Enter' || event.keyCode === 13) {
                var tag = event.target && event.target.tagName ? event.target.tagName.toLowerCase() : '';
                if (tag === 'textarea') {
                    return;
                }
                if ($cancel && event.target === $cancel[0]) {
                    event.preventDefault();
                    close(false);
                    return;
                }
                if (event.target === $ok[0] || !$modal[0].contains(event.target)) {
                    event.preventDefault();
                    close(true);
                }
            }
        });

        $overlay.append($modal);
        $(document.body).append($overlay);
        openCount += 1;
        $('body').addClass('sb-modal-open');
        refreshFocusables();
        $ok.trigger('focus');

        return dfd.promise();
    }

    window.SBModal.alert = function (message, options) {
        options = options || {};
        options.message = message;
        options.confirm = false;
        return openModal(options);
    };

    window.SBModal.confirm = function (message, options) {
        options = options || {};
        options.message = message;
        options.confirm = true;
        return openModal(options);
    };
})(jQuery);
