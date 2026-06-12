/**
 * SEO Booster Settings Page JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Tab Navigation - Only for our custom tabs, not Freemius tabs
    $('.sb-seo-tabs .nav-tab[data-tab]:not(.fs-tab)').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var targetTab = $(this).data('tab');
        
        // Remove active class from all our custom tabs and content
        $('.sb-seo-tabs .nav-tab[data-tab]:not(.fs-tab)').removeClass('nav-tab-active');
        $('.sb-tab-content').removeClass('sb-tab-active');
        
        // Add active class to clicked tab and corresponding content
        $(this).addClass('nav-tab-active');
        $('#' + targetTab + '-tab').addClass('sb-tab-active');
        
        // Update URL hash
        window.location.hash = '#' + targetTab;
    });
    
    // Handle initial hash on page load. The default tab (automatic-links) is
    // already rendered active server-side, so only switch if the hash points
    // to a different tab. This avoids a flash/layout jump on initial load.
    var tabActivated = false;
    if (window.location.hash) {
        var hash = window.location.hash.substring(1);
        var targetTab = $('.sb-seo-tabs .nav-tab[data-tab="' + hash + '"]');
        if (targetTab.length) {
            if (!targetTab.hasClass('nav-tab-active')) {
                targetTab.trigger('click');
            }
            tabActivated = true;
        }
    }
    
    // Default to automatic-links tab if no hash is present or hash doesn't match any tab
    if (!tabActivated) {
        var defaultTab = $('.sb-seo-tabs .nav-tab[data-tab="automatic-links"]');
        if (defaultTab.length && !defaultTab.hasClass('nav-tab-active')) {
            defaultTab.trigger('click');
        }
    }
    
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
    
    // Restore tab after form submission
    if (window.location.search.indexOf('current_tab=') > -1) {
        var urlParams = new URLSearchParams(window.location.search);
        var savedTab = urlParams.get('current_tab');
        if (savedTab) {
            var targetTab = $('.sb-seo-tabs .nav-tab[data-tab="' + savedTab + '"]');
            if (targetTab.length) {
                targetTab.trigger('click');
            }
        }
    }
    
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
                        '<p>Unique Keywords Imported: ' + data.total_keywords + '</p>' +
                        '<p>Total Entries Processed: ' + data.total_entries + '</p>' +
                        '<p>Last Import Keyword: <code>' + data.last_keyword + '</code></p>' +
                        '<p>Last Batch Time: ' + data.time.toFixed(2) + ' seconds</p>' +
                        '<p>' + data.message + '</p>' +
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
                    $error.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>').show();
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
    
    // AI Provider radio button handling
    $('input[name="seobooster_ai_provider"]').on('change', function() {
        var selectedProvider = $(this).val();
        $('#wordpress-connectors-settings, #seobooster-credits-register, #seobooster-credits-balance-row').hide();
        if (selectedProvider === 'wordpress') {
            $('#wordpress-connectors-settings').show();
        } else if (selectedProvider === 'seobooster') {
            $('#seobooster-credits-register, #seobooster-credits-balance-row').show();
        }
    });
    


});
