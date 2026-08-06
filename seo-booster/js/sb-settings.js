/**
 * SEO Booster Settings Page JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    var botBlockStatsLoading = false;
    var botBlockStatsObserver = null;
    var dbStatsLoading = false;

    function formatVisitCountsCaption(days) {
        var template = (sbSettings.strings && sbSettings.strings.visitCountsLoaded) || 'Visit counts for the last %d days.';
        return template.replace('%d', String(days));
    }

    function applyVisitedOnlyFilter() {
        var visitedOnly = $('#sb-bot-visited-only').is(':checked');
        $('#sb-toggle-bot-list .sb-bot-block-row').each(function() {
            var $row = $(this);
            var visits = parseInt($row.attr('data-visits'), 10) || 0;
            var blocked = $row.attr('data-blocked') === '1';
            var show = !visitedOnly || visits > 0 || blocked;
            $row.toggleClass('is-filtered-out', !show);
        });
    }

    function sortBotBlockRows() {
        var $rowsWrap = $('#sb-toggle-bot-list .sb-bot-block-rows');
        if (!$rowsWrap.length) {
            return;
        }
        var $rows = $rowsWrap.children('.sb-bot-block-row').get();
        $rows.sort(function(a, b) {
            var visitsA = parseInt($(a).attr('data-visits'), 10) || 0;
            var visitsB = parseInt($(b).attr('data-visits'), 10) || 0;
            if (visitsB !== visitsA) {
                return visitsB - visitsA;
            }
            var nameA = ($(a).attr('data-bot-name') || '').toLowerCase();
            var nameB = ($(b).attr('data-bot-name') || '').toLowerCase();
            if (nameA < nameB) {
                return -1;
            }
            if (nameA > nameB) {
                return 1;
            }
            return 0;
        });
        $.each($rows, function(_, row) {
            $rowsWrap.append(row);
        });
    }

    function disconnectBotBlockStatsObserver() {
        if (botBlockStatsObserver) {
            botBlockStatsObserver.disconnect();
            botBlockStatsObserver = null;
        }
    }

    function enrichBotBlockStats(bots, days) {
        var emDash = (sbSettings.strings && sbSettings.strings.emDash) || '-';
        $('#sb-toggle-bot-list .sb-bot-block-row').each(function() {
            var $row = $(this);
            var name = $row.attr('data-bot-name');
            var data = bots && bots[name] ? bots[name] : null;
            var visits = data ? (parseInt(data.visits, 10) || 0) : 0;
            var lastAgo = data && data.last_seen_ago ? data.last_seen_ago : emDash;
            $row.attr('data-visits', String(visits));
            $row.find('[data-role="visits"]').text(visits.toLocaleString());
            $row.find('[data-role="last-seen"]').text(visits > 0 && lastAgo ? lastAgo : emDash);
        });
        sortBotBlockRows();
        applyVisitedOnlyFilter();
        $('#sb-bot-block-stats-caption').text(formatVisitCountsCaption(days));
        $('#sb-toggle-bot-list').attr('data-stats-loaded', '1');
        $('#sb-bot-visited-only').prop('disabled', false);
        disconnectBotBlockStatsObserver();
    }

    function loadBotBlockStats() {
        var $list = $('#sb-toggle-bot-list');
        if (!$list.length || $list.attr('data-stats-loaded') === '1' || botBlockStatsLoading) {
            return;
        }
        if (!$('#ai-llm-tab').hasClass('sb-tab-active')) {
            return;
        }
        if (!sbSettings.botBlockStatsNonce) {
            return;
        }

        botBlockStatsLoading = true;
        $('#sb-bot-block-stats-caption').text((sbSettings.strings && sbSettings.strings.loadingVisitCounts) || 'Loading visit counts…');

        $.post(sbSettings.ajaxurl, {
            action: 'sb_ai_bot_block_stats',
            security: sbSettings.botBlockStatsNonce
        }).done(function(response) {
            if (response && response.success && response.data) {
                enrichBotBlockStats(response.data.bots || {}, response.data.days || 90);
            } else {
                $('#sb-bot-block-stats-caption').text((sbSettings.strings && sbSettings.strings.visitCountsError) || 'Could not load visit counts.');
                window.setTimeout(loadBotBlockStats, 2500);
            }
        }).fail(function() {
            $('#sb-bot-block-stats-caption').text((sbSettings.strings && sbSettings.strings.visitCountsError) || 'Could not load visit counts.');
            window.setTimeout(loadBotBlockStats, 2500);
        }).always(function() {
            botBlockStatsLoading = false;
        });
    }

    function setupBotBlockStatsObserver() {
        var list = document.getElementById('sb-toggle-bot-list');
        if (!list || botBlockStatsObserver) {
            return;
        }
        if (!sbSettings.botBlockStatsNonce) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            loadBotBlockStats();
            return;
        }

        botBlockStatsObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    loadBotBlockStats();
                }
            });
        }, { root: null, rootMargin: '80px', threshold: 0.01 });

        botBlockStatsObserver.observe(list);
    }

    function maybeStartBotBlockStats() {
        if (!$('#ai-llm-tab').hasClass('sb-tab-active')) {
            return;
        }
        setupBotBlockStatsObserver();
    }

    function loadDbStats() {
        var $container = $('#sb-db-stats-container');
        if (!$container.length || $container.attr('data-loaded') === '1' || dbStatsLoading) {
            return;
        }
        if (!$('#stats-tab').hasClass('sb-tab-active')) {
            return;
        }

        dbStatsLoading = true;
        $container.html('<p class="description">' + ((sbSettings.strings && sbSettings.strings.loadingDbStats) || 'Loading database statistics…') + '</p>');

        $.post(sbSettings.ajaxurl, {
            action: 'sb_settings_db_stats',
            security: sbSettings.dbStatsNonce
        }).done(function(response) {
            if (response && response.success && response.data && response.data.html) {
                $container.html(response.data.html).attr('data-loaded', '1');
            } else {
                $container.html('<p class="description">' + ((sbSettings.strings && sbSettings.strings.dbStatsError) || 'Could not load database statistics.') + '</p>');
            }
        }).fail(function() {
            $container.html('<p class="description">' + ((sbSettings.strings && sbSettings.strings.dbStatsError) || 'Could not load database statistics.') + '</p>');
        }).always(function() {
            dbStatsLoading = false;
        });
    }
    
    // Tab Navigation - Only for our custom tabs, not Freemius tabs
    function activateSettingsTab(tabSlug, updateHash) {
        var $tab = $('.sb-seo-tabs .nav-tab[data-tab="' + tabSlug + '"]:not(.fs-tab)');
        var $panel = $('#' + tabSlug + '-tab');

        if (!$tab.length || !$panel.length) {
            return false;
        }

        $('.sb-seo-tabs .nav-tab[data-tab]:not(.fs-tab)').removeClass('nav-tab-active');
        $('.sb-tab-content').removeClass('sb-tab-active');
        $tab.addClass('nav-tab-active');
        $panel.addClass('sb-tab-active');

        if (updateHash) {
            var nextUrl = window.location.pathname + window.location.search + '#' + tabSlug;
            if (window.history.replaceState) {
                window.history.replaceState(null, '', nextUrl);
            } else {
                window.location.hash = '#' + tabSlug;
            }
        }

        if (tabSlug === 'ai-llm') {
            maybeStartBotBlockStats();
        }
        if (tabSlug === 'stats') {
            loadDbStats();
        }

        return true;
    }

    $('.sb-seo-tabs .nav-tab[data-tab]:not(.fs-tab)').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        activateSettingsTab($(this).data('tab'), true);
    });

    $(document).on('change', '#sb-bot-visited-only', function() {
        applyVisitedOnlyFilter();
    });

    $(document).on('change', '#sb-toggle-bot-list .sb-bot-block-row input[type="checkbox"]', function() {
        var $row = $(this).closest('.sb-bot-block-row');
        $row.attr('data-blocked', $(this).is(':checked') ? '1' : '0');
        applyVisitedOnlyFilter();
    });

    // Restore tab after form submission (?current_tab=...)
    var urlParams = new URLSearchParams(window.location.search);
    var savedTab = urlParams.get('current_tab');
    var tabActivated = false;

    if (savedTab && activateSettingsTab(savedTab, true)) {
        tabActivated = true;
    }

    if (!tabActivated && window.location.hash) {
        var hash = window.location.hash.substring(1);
        if (activateSettingsTab(hash, false)) {
            tabActivated = true;
        }
    }

    // Default tab when no hash / unknown hash / bare settings URL
    if (!tabActivated) {
        activateSettingsTab('ai-llm', true);
    }

    // Retention FYI link uses #stats — ensure tab switch when clicked from same page
    $(document).on('click', 'a[href*="page=sb2_settings#stats"]', function(e) {
        if (window.location.search.indexOf('page=sb2_settings') === -1 && window.location.href.indexOf('page=sb2_settings') === -1) {
            return;
        }
        e.preventDefault();
        activateSettingsTab('stats', true);
    });
    
    // Content Types Collapsible Functionality
    $('.sb-content-type-header').on('click', function(e) {
        e.preventDefault();
        
        var header = $(this);
        var targetId = header.data('target');
        var content = $('#' + targetId);
        
        // Toggle active class on header
        header.toggleClass('active');
        
        // Toggle content visibility
        content.toggleClass('active');
    });
    
    // Import functionality
    $('.sb-import-section button').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var importType = button.attr('id').replace('import-', '');
        var statusDiv = $('#' + importType + '-import-status');
        var progressDiv = $('#' + importType + '-import-progress');
        
        // Disable button and show loading
        button.prop('disabled', true).text('Importing...');
        statusDiv.html('<p style="color: #0073aa;">Starting import...</p>');
        progressDiv.html('<div class="progress-bar"><div class="progress-fill" style="width: 0%;"></div></div>');
        
        // Simulate import process (replace with actual AJAX call)
        var progress = 0;
        var interval = setInterval(function() {
            progress += 10;
            $('.progress-fill').css('width', progress + '%');
            
            if (progress >= 100) {
                clearInterval(interval);
                button.prop('disabled', false).text('Import from ' + importType.charAt(0).toUpperCase() + importType.slice(1));
                statusDiv.html('<p style="color: #46b450;">Import completed successfully!</p>');
                progressDiv.html('');
            }
        }, 200);
    });
    
    // Save form and return to same tab
    $('#seobooster_settings').on('submit', function(e) {
        var currentTab = $('.sb-seo-tabs .nav-tab[data-tab].nav-tab-active').data('tab');
        if (currentTab) {
            // Add hidden field to preserve current tab
            $('<input>').attr({
                type: 'hidden',
                name: 'current_tab',
                value: currentTab
            }).appendTo(this);
        }
    });
    
    // Image selection functionality
    $('.sb-seo-select-image').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var targetField = button.data('target');
        var targetInput = $('#' + targetField);
        
        // Create media frame
        var frame = wp.media({
            title: 'Select Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });
        
        // When image is selected
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            targetInput.val(attachment.url);
            
            // Update preview
            var preview = targetInput.closest('td').find('.sb-seo-image-preview');
            if (preview.length) {
                preview.find('img').attr('src', attachment.url);
            } else {
                targetInput.closest('td').append('<div class="sb-seo-image-preview"><img src="' + attachment.url + '" alt="Image Preview" style="max-width: 200px; height: auto; margin-top: 10px;"></div>');
            }
        });
        
        // Open media frame
        frame.open();
    });
    
    // Variable inserter functionality
    $('.sb-variable-inserter-btn').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var targetField = $button.data('target');
        var fieldType = $button.data('type');
        var $target = $('#' + targetField);
        
        // Define available variables for each field type
        var variables = {
            title: [
                {code: '{title}', name: 'Page Title', description: 'The title of the current page'},
                {code: '{site_name}', name: 'Site Name', description: 'Your website name'},
                {code: '{separator}', name: 'Separator', description: 'The separator character'},
                {code: '{description}', name: 'Description', description: 'Page description'},
                {code: '{excerpt}', name: 'Excerpt', description: 'Auto-generated or manual excerpt'},
                {code: '{excerpt_only}', name: 'Manual Excerpt Only', description: 'Only manual excerpt'}
            ],
            description: [
                {code: '{title}', name: 'Page Title', description: 'The title of the current page'},
                {code: '{site_name}', name: 'Site Name', description: 'Your website name'},
                {code: '{description}', name: 'Description', description: 'Page description'},
                {code: '{excerpt}', name: 'Excerpt', description: 'Auto-generated or manual excerpt'},
                {code: '{excerpt_only}', name: 'Manual Excerpt Only', description: 'Only manual excerpt'}
            ]
        };
        
        var fieldVariables = variables[fieldType] || [];
        
        // Remove existing dropdown
        $('.sb-variable-dropdown').remove();
        
        // Create dropdown
        var $dropdown = $('<div class="sb-variable-dropdown"></div>');
        
        fieldVariables.forEach(function(variable) {
            var $option = $('<div class="sb-variable-option"></div>');
            $option.html('<code>' + variable.code + '</code> <strong>' + variable.name + '</strong><br><small>' + variable.description + '</small>');
            $option.data('variable', variable.code);
            $dropdown.append($option);
        });
        
        // Position dropdown
        var buttonOffset = $button.offset();
        $dropdown.css({
            top: buttonOffset.top + $button.outerHeight() + 5,
            left: buttonOffset.left
        });
        
        $('body').append($dropdown);
        
        // Handle variable selection
        $dropdown.on('click', '.sb-variable-option', function() {
            var variable = $(this).data('variable');
            var currentValue = $target.val();
            var cursorPos = $target.prop('selectionStart') || currentValue.length;
            
            var newValue = currentValue.substring(0, cursorPos) + variable + currentValue.substring(cursorPos);
            $target.val(newValue);
            $target.focus();
            
            // Set cursor position after the inserted variable
            var newCursorPos = cursorPos + variable.length;
            $target.prop('selectionStart', newCursorPos);
            $target.prop('selectionEnd', newCursorPos);
            
            $dropdown.remove();
        });
        
        // Close dropdown when clicking outside
        $(document).on('click.variableInserter', function(e) {
            if (!$(e.target).closest('.sb-variable-inserter-btn, .sb-variable-dropdown').length) {
                $dropdown.remove();
                $(document).off('click.variableInserter');
            }
        });
    });
    
    // Flush rewrite rules functionality
    $('#flush-rewrite-rules').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text(sbSettings.strings.flushing);
        
        $.post(sbSettings.ajaxurl, {
            action: 'flush_rewrite_rules',
            nonce: sbSettings.flushNonce
        }, function(response) {
            if (response.success) {
                $button.text(sbSettings.strings.flushed).removeClass('button').addClass('button button-success');
                setTimeout(function() {
                    $button.text(originalText).removeClass('button-success').addClass('button').prop('disabled', false);
                }, 2000);
            } else {
                $button.text(sbSettings.strings.error).removeClass('button').addClass('button button-secondary');
                setTimeout(function() {
                    $button.text(originalText).removeClass('button-secondary').addClass('button').prop('disabled', false);
                }, 2000);
            }
        });
    });
    
    // GSC Import functionality
    $('#manual_update_ajax').on('click', function() {
        var $button = $(this);
        var $statusContainer = $('#settings-import-status');
        var $progress = $('#settings-import-progress');
        var $error = $('#settings-import-error');
        
        // Reset UI
        $statusContainer.html('');
        $progress.html('');
        $error.hide();
        
        // Disable button and show loading
        $button.prop('disabled', true).text(sbSettings.strings.importing);
        
        // Get selected site
        var selectedSite = sbSettings.gscSelectedSite;
        if (!selectedSite) {
            $error.html(sbSettings.strings.noGscSite).show();
            $button.prop('disabled', false).text(sbSettings.strings.importGscData);
            return;
        }
        
        // Start import process
        var startTime = new Date().getTime();
        var totalDays = 30;
        var processedDays = 0;
        
        function sendRequest(step, site, days) {
            $.ajax({
                url: sbSettings.ajaxurl,
                method: 'POST',
                data: {
                    action: 'sb_gsc_import_data',
                    step: step,
                    site_url: site,
                    days: days,
                    nonce: sbSettings.gscImportNonce
                },
                success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var progress = Math.min((step * 10), 100);
                    
                    // Update progress
                    $progress.html('<div class="progress-bar"><div class="progress-fill" style="width: ' + progress + '%;"></div></div>');
                    
                    // Update status with detailed information
                    var statusMessage = '<div class="notice notice-info">' +
                        '<p><strong>Import Progress:</strong></p>' +
                        '<p>Unique Keywords Imported: ' + escapeHtml(data.total_keywords) + '</p>' +
                        '<p>Total Entries Processed: ' + escapeHtml(data.total_entries) + '</p>' +
                        '<p>Last Import Keyword: <code>' + escapeHtml(data.last_keyword) + '</code></p>' +
                        '<p>Last Batch Time: ' + escapeHtml(Number(data.time).toFixed(2)) + ' seconds</p>' +
                        '<p>' + escapeHtml(data.message) + '</p>' +
                        '</div>';
                    
                    $statusContainer.html(statusMessage);
                    
                    if (data.more_results) {
                        // Continue with next step
                        setTimeout(function() {
                            sendRequest(data.next_step, site, days);
                        }, 1000);
                    } else {
                        // Import complete
                        var endTime = new Date().getTime();
                        var elapsedTime = calculateElapsedTime(startTime, endTime);
                        
                        $statusContainer.html('<div class="notice notice-success"><p><strong>Import completed successfully!</strong> (' + elapsedTime + ')</p></div>');
                        $progress.html('<div class="progress-bar"><div class="progress-fill" style="width: 100%;"></div></div>');
                        $button.prop('disabled', false).text('Import GSC Data');
                    }
                } else {
                    $error.html('<div class="notice notice-error"><p>Error: ' + escapeHtml(response.data) + '</p></div>').show();
                    $button.prop('disabled', false).text('Import GSC Data');
                }
            },
            error: function() {
                $error.html('<div class="notice notice-error"><p>An error occurred while processing the request.</p></div>').show();
                $button.prop('disabled', false).text('Import GSC Data');
            }
        });
        }
        
        function calculateElapsedTime(startTime, endTime) {
            var elapsed = Math.floor((endTime - startTime) / 1000);
            var minutes = Math.floor(elapsed / 60);
            var seconds = elapsed % 60;
            return (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        }
        
        // Get time range
        var timeRange = $('#gsc-time-range').val() || 30;
        
        // Start the import
        sendRequest(0, selectedSite, timeRange);
    });
    
    // AI Provider radio button handling (accept WordPress / wordpress form values).
    $('input[name="seobooster_ai_provider"]').on('change', function() {
        var selectedProvider = String($(this).val() || '').toLowerCase();
        $('#wordpress-connectors-settings, #seobooster-credits-register, #seobooster-credits-balance-row').hide();
        if (selectedProvider === 'wordpress') {
            $('#wordpress-connectors-settings').show();
        } else if (selectedProvider === 'seobooster') {
            $('#seobooster-credits-register, #seobooster-credits-balance-row').show();
        }
    });

    $(document).on('click', '.sb-oauth-start', function (e) {
        e.preventDefault();
        var $btn = $(this).prop('disabled', true);
        $.post(sbSettings.ajaxurl, {
            action: 'sb_oauth_prepare',
            nonce: sbSettings.oauthNonce,
            destination: $btn.data('sb-oauth-destination') || 'settings'
        })
            .done(function (res) {
                if (res && res.success && res.data && res.data.auth_url) {
                    window.location.href = res.data.auth_url;
                    return;
                }
                $btn.prop('disabled', false);
                var connectErr = (sbSettings.strings && sbSettings.strings.connectError) || 'Could not start Google authentication.';
                if (window.SBModal && typeof window.SBModal.alert === 'function') {
                    window.SBModal.alert(connectErr);
                }
            })
            .fail(function () {
                $btn.prop('disabled', false);
                var connectErr = (sbSettings.strings && sbSettings.strings.connectError) || 'Could not start Google authentication.';
                if (window.SBModal && typeof window.SBModal.alert === 'function') {
                    window.SBModal.alert(connectErr);
                }
            });
    });

});
