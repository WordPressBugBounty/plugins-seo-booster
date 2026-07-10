/**
 * SEO Booster — styled alert/confirm modal (replaces window.alert / window.confirm).
 */
(function ($) {
    'use strict';

    window.SBModal = window.SBModal || {};

    var strings = (window.sbModalStrings && window.sbModalStrings.strings) || {};
    var modalSeq = 0;

  /**
   * @param {Object} options
   * @return {Object} jQuery promise resolving to true/false.
   */
    function openModal(options) {
        options = options || {};
        var isConfirm = !!options.confirm;
        var dfd = $.Deferred();
        var lastFocused = document.activeElement;
        var titleId = 'sb-modal-title-' + (++modalSeq);

        var $overlay = $('<div class="sb-modal-overlay"></div>');
        var $modal = $('<div class="sb-modal" tabindex="-1"></div>')
            .attr('role', isConfirm ? 'alertdialog' : 'dialog')
            .attr('aria-modal', 'true');

        if (options.tone) {
            $modal.addClass('sb-modal--' + options.tone);
        }

        if (options.title) {
            $('<h2 class="sb-modal-title"></h2>')
                .attr('id', titleId)
                .text(options.title)
                .appendTo($modal);
            $modal.attr('aria-labelledby', titleId);
        }

        $('<div class="sb-modal-body"></div>')
            .text(options.message || '')
            .appendTo($modal);

        var $footer = $('<div class="sb-modal-footer"></div>').appendTo($modal);

        var closed = false;
        var keyNs = 'keydown.sbModal' + modalSeq;

        function close(result) {
            if (closed) {
                return;
            }
            closed = true;
            $(document).off(keyNs);
            $overlay.remove();
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
            $('<button type="button" class="button sb-modal-cancel"></button>')
                .text(options.cancelLabel || strings.cancel || 'Cancel')
                .on('click', function () { close(false); })
                .appendTo($footer);
        }

        var $ok = $('<button type="button" class="button button-primary sb-modal-ok"></button>')
            .text(options.okLabel || strings.ok || 'OK')
            .on('click', function () { close(true); })
            .appendTo($footer);

        $overlay.on('click', function (event) {
            if (event.target === $overlay[0]) {
                close(false);
            }
        });

        $(document).on(keyNs, function (event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                event.preventDefault();
                close(false);
            } else if (event.key === 'Enter' || event.keyCode === 13) {
                event.preventDefault();
                close(true);
            }
        });

        $overlay.append($modal);
        $(document.body).append($overlay);
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
