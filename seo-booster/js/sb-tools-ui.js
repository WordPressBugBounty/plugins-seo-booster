/**
 * SEO Booster Tools — shared UI helpers.
 *
 * Renders a consistent "content" cell for every Tools results table: the page
 * slug is shown, the full title appears on hover, and WordPress-style row
 * actions (Edit, View, Dismiss) reveal on hover.
 */
(function ($) {
    'use strict';

    window.SBTools = window.SBTools || {};

    var strings = (window.sbToolsUi && window.sbToolsUi.strings) || {};

    function esc(value) {
        return $('<div>').text(value === undefined || value === null ? '' : value).html();
    }

    function escAttr(value) {
        return esc(value).replace(/"/g, '&quot;');
    }

    function slugFromUrl(url) {
        if (!url) {
            return '';
        }
        var path = url;
        try {
            path = new URL(url, window.location.origin).pathname;
        } catch (ignore) {
            // Fall back to the raw string when parsing fails.
        }
        path = String(path).replace(/\/+$/, '');
        var segments = path.split('/').filter(Boolean);
        return segments.length ? segments[segments.length - 1] : '/';
    }

    /**
     * Build the inner HTML for a primary content cell.
     *
     * @param {Object} opts
     * @param {string} [opts.title]   Full post title (shown on hover).
     * @param {string} [opts.editUrl] Edit link (primary link + Edit action).
     * @param {string} [opts.viewUrl] Live URL (slug source + View action).
     * @param {string} [opts.slug]    Explicit slug; derived from viewUrl when absent.
     * @param {boolean} [opts.dismissable=true] Whether to render the Dismiss action.
     * @return {string}
     */
    window.SBTools.contentCellInner = function (opts) {
        opts = opts || {};
        var title = opts.title || '';
        var editUrl = opts.editUrl || '';
        var viewUrl = opts.viewUrl || '';
        var slug = opts.slug || slugFromUrl(viewUrl || editUrl);
        var slugDisplay = slug
            ? '/' + String(slug).replace(/^\//, '')
            : (viewUrl || title || '');
        var primaryHref = editUrl || viewUrl || '#';
        var titleAttr = title ? ' title="' + escAttr(title) + '"' : '';

        var label = '<strong><a href="' + escAttr(primaryHref) + '"' + titleAttr + '>' +
            esc(slugDisplay) + '</a></strong>';

        var actions = [];
        if (editUrl) {
            actions.push('<span class="edit"><a href="' + escAttr(editUrl) + '">' +
                esc(strings.edit || 'Edit') + '</a></span>');
        }
        if (viewUrl) {
            actions.push('<span class="view"><a href="' + escAttr(viewUrl) +
                '" target="_blank" rel="noopener noreferrer">' +
                esc(strings.view || 'View') + '</a></span>');
        }
        if (opts.dismissable !== false) {
            actions.push('<span class="trash"><a href="#" class="sb-tools-row-dismiss">' +
                esc(strings.dismiss || 'Dismiss') + '</a></span>');
        }

        var rowActions = '';
        if (actions.length) {
            rowActions = '<div class="row-actions">' +
                actions.join('<span class="sb-tools-row-action-sep"> | </span>') +
                '</div>';
        }

        return label + rowActions;
    };

    /**
     * Delegate the Dismiss row action for a results table body.
     *
     * @param {string} bodySelector jQuery selector for the <tbody>.
     * @param {Function} onDismiss  Called with the jQuery <tr> being removed.
     */
    window.SBTools.bindDismiss = function (bodySelector, onDismiss) {
        $(document).on('click', bodySelector + ' .sb-tools-row-dismiss', function (event) {
            event.preventDefault();
            var $row = $(this).closest('tr');
            if (typeof onDismiss === 'function') {
                onDismiss($row);
            } else {
                $row.remove();
            }
        });
    };

    if (window.SBModal) {
        window.SBTools.alert = window.SBModal.alert;
        window.SBTools.confirm = window.SBModal.confirm;
    }
})(jQuery);
