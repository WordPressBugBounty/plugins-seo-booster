/**
 * SEO Booster Tools — llms.txt settings.
 *
 * @package SEOBooster
 */
(function ($) {
    'use strict';

    if (typeof sbToolsLlms === 'undefined') {
        return;
    }

    var aiSuggestions = [];
    var aiSuggestedIntro = '';

    function collectPostTypes() {
        var postTypes = [];
        $('input[name="post_types[]"]:checked').each(function () {
            postTypes.push($(this).val());
        });
        return postTypes;
    }

    function collectDirectoryRules() {
        var rules = {};
        $('select[name^="directory_rules"]').each(function () {
            var name = $(this).attr('name');
            var match = name.match(/directory_rules\[([^\]]+)\]/);
            if (match) {
                rules[match[1]] = $(this).val();
            }
        });
        return rules;
    }

    function collectPinnedIds() {
        var ids = [];
        $('.sb-llms-pinned-input').each(function () {
            ids.push($(this).val());
        });
        return ids;
    }

    function serializeSavePayload() {
        var payload = {
            action: 'sb_tools_save_llms_settings',
            nonce: sbToolsLlms.nonce,
            enabled: $('input[name="enabled"]').is(':checked') ? 1 : 0,
            robots_llms: $('input[name="robots_llms"]').is(':checked') ? 1 : 0,
            head_links: $('input[name="head_links"]').is(':checked') ? 1 : 0,
            http_headers: $('input[name="http_headers"]').is(':checked') ? 1 : 0,
            intro: $('#sb-llms-intro').val(),
            max_links: $('#sb-llms-max').val(),
            cache_ttl: $('#sb-llms-cache-ttl').val(),
            include_faq: $('input[name="include_faq"]').is(':checked') ? 1 : 0,
            faq_max_items: $('#sb-llms-faq-max').val(),
        };

        var serialized = $.param(payload);
        collectPostTypes().forEach(function (type) {
            serialized += '&post_types[]=' + encodeURIComponent(type);
        });
        collectPinnedIds().forEach(function (id) {
            serialized += '&pinned_post_ids[]=' + encodeURIComponent(id);
        });
        $.each(collectDirectoryRules(), function (dir, status) {
            serialized += '&directory_rules[' + encodeURIComponent(dir) + ']=' + encodeURIComponent(status);
        });

        return serialized;
    }

    function setPinned(postId, pinned) {
        var $input = $('.sb-llms-pinned-input[data-post-id="' + postId + '"]');
        var $button = $('.sb-llms-pin-toggle[data-post-id="' + postId + '"]');

        if (pinned) {
            if (!$input.length) {
                $('#sb-tools-llms-form').append(
                    $('<input>', {
                        type: 'hidden',
                        name: 'pinned_post_ids[]',
                        value: postId,
                        class: 'sb-llms-pinned-input',
                        'data-post-id': postId,
                    })
                );
            }
            if ($button.length) {
                $button.attr('data-pinned', '1').text(sbToolsLlms.strings.unpin);
            }
        } else {
            $input.remove();
            if ($button.length) {
                $button.attr('data-pinned', '0').text(sbToolsLlms.strings.pin);
            }
        }
    }

    function renderAiSuggestions(data) {
        var $wrap = $('#sb-tools-llms-ai-results');
        aiSuggestions = data.suggestions || [];
        aiSuggestedIntro = data.intro || '';

        if (!aiSuggestedIntro && !aiSuggestions.length) {
            $wrap.hide().empty();
            return;
        }

        var html = '';
        if (aiSuggestedIntro) {
            html += '<p><strong>' + sbToolsLlms.strings.suggested_intro + ':</strong></p>';
            html += '<p class="description sb-llms-ai-intro">' + $('<div/>').text(aiSuggestedIntro).html() + '</p>';
        }

        if (aiSuggestions.length) {
            html += '<p><strong>' + sbToolsLlms.strings.suggested_pages + ':</strong></p><ul>';
            aiSuggestions.forEach(function (item) {
                html += '<li><label><input type="checkbox" class="sb-llms-ai-pick" value="' + item.post_id + '" checked="checked" /> ';
                html += $('<div/>').text(item.title).html();
                if (item.reason) {
                    html += ' — <span class="description">' + $('<div/>').text(item.reason).html() + '</span>';
                }
                html += '</label></li>';
            });
            html += '</ul>';
            html += '<p><button type="button" class="button button-secondary" id="sb-tools-llms-ai-apply">' + sbToolsLlms.strings.apply_selected + '</button></p>';
        }

        $wrap.html(html).show();
    }

    $('#sb-tools-llms-form').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: sbToolsLlms.ajax_url,
            method: 'POST',
            data: serializeSavePayload(),
        }).done(function (response) {
            if (response.success) {
                if (response.data.preview) {
                    $('#sb-tools-llms-preview').text(response.data.preview);
                }
                window.SBTools.alert(response.data.message || sbToolsLlms.strings.saved, { tone: 'success' });
            } else {
                window.SBTools.alert((response.data && response.data.message) || sbToolsLlms.strings.error, { tone: 'error' });
            }
        }).fail(function () {
            window.SBTools.alert(sbToolsLlms.strings.error, { tone: 'error' });
        });
    });

    $('#sb-tools-llms-download').on('click', function () {
        var form = $('<form>', {
            method: 'POST',
            action: sbToolsLlms.ajax_url,
            target: '_blank',
        });
        form.append($('<input>', { type: 'hidden', name: 'action', value: 'sb_tools_download_llms' }));
        form.append($('<input>', { type: 'hidden', name: 'nonce', value: sbToolsLlms.nonce }));
        $('body').append(form);
        form.trigger('submit');
        form.remove();
    });

    $(document).on('click', '.sb-llms-pin-toggle', function () {
        var postId = String($(this).data('post-id'));
        var pinned = $(this).attr('data-pinned') === '1';
        setPinned(postId, !pinned);
        window.SBTools.alert(pinned ? sbToolsLlms.strings.unpinned : sbToolsLlms.strings.pinned, { tone: 'success' });
    });

    $('#sb-tools-llms-ai-suggest').on('click', function () {
        if (!sbToolsLlms.ai_available) {
            window.SBTools.alert(sbToolsLlms.ai_unavailable_message, { tone: 'warning' });
            return;
        }

        var $btn = $(this).prop('disabled', true);

        $.post(sbToolsLlms.ajax_url, {
            action: 'sb_tools_llms_ai_suggest',
            nonce: sbToolsLlms.nonce,
        }).done(function (response) {
            if (response.success) {
                renderAiSuggestions(response.data || {});
            } else {
                window.SBTools.alert((response.data && response.data.message) || sbToolsLlms.strings.ai_error, { tone: 'error' });
            }
        }).fail(function () {
            window.SBTools.alert(sbToolsLlms.strings.ai_error, { tone: 'error' });
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '#sb-tools-llms-ai-apply', function () {
        if (aiSuggestedIntro) {
            $('#sb-llms-intro').val(aiSuggestedIntro);
        }

        $('.sb-llms-ai-pick:checked').each(function () {
            setPinned(String($(this).val()), true);
        });

        window.SBTools.alert(sbToolsLlms.strings.ai_applied, { tone: 'success' });
    });
})(jQuery);
