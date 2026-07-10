/* global sbdata, jQuery, Chart, sb_gsc_ajax, ajaxurl */
/**!
 * This script handles various jQuery events and AJAX requests for the SEO Booster plugin.
 * 
 * - Hides suggestion list on document click.
 * - Confirms user actions for specific buttons.
 * - Submits the email form for weekly email updates.
 * - Validates email addresses before submission.
 * - Disables form inputs and shows a spinner during the AJAX request.
 */

jQuery(document).on("click", function () {
    jQuery(".sugglist").hide();
});

// Enables the import button when user has selected a site.
jQuery(document).on('change', '.sb2authenticate #choosecont select', function () {
    var selectedSite = jQuery(this).val();
    var $submitButton = jQuery('#seobooster2selectsite');

    if (selectedSite !== '') {
        $submitButton.prop('disabled', false);
    } else {
        $submitButton.prop('disabled', true);
    }
});


jQuery(document).on('click', '#submit_dbempty, #submit_gsc_change_site, #submit_allempty, #submit_reset_log', function (e) {
    e.preventDefault();
    var $btn = jQuery(this);
    var $form = $btn.closest('form');
    window.SBModal.confirm(sbdata.strings.areYouSure).then(function (confirmed) {
        if (!confirmed || !$form.length) {
            return;
        }

        var btnName = $btn.attr('name');
        if (btnName) {
            $form.find('input[type="hidden"][data-sb-confirmed-action="1"]').remove();
            $form.append(
                jQuery('<input>', {
                    type: 'hidden',
                    name: btnName,
                    value: $btn.val() || '1',
                    'data-sb-confirmed-action': '1'
                })
            );
        }

        if (typeof $form[0].requestSubmit === 'function') {
            $form[0].requestSubmit($btn[0]);
        } else {
            $form[0].submit();
        }
    });
});

jQuery(document).ready(function ($) {


    jQuery(document).on('submit', '#seobooster_email_container form', function (e) {
        e.preventDefault();

        var $form = jQuery(this);
        var $emailInput = $form.find('#seobooster_email');
        var $submitButton = $form.find('input[type="submit"]');
        var $messageContainer = jQuery('#sbweeklyemail_signupmessage');

        // Validate email(s)
        var emails = $emailInput.val().split(',').map(function (email) {
            return email.trim();
        });

        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        var validEmails = emails.filter(function (email) {
            return emailRegex.test(email);
        });

        if (validEmails.length === 0) {
            showMessage(sbdata.strings.pleaseEnterAtLeastOneEmail, 'error');
            return;
        }

        $emailInput.prop('disabled', true);
        $submitButton.prop('disabled', true).addClass('disabled');
        $submitButton.after('<span class="spinner is-active" style="float:none;"></span>');

        var selected_nonce = jQuery('#seobooster_selected_site_nonce').val();

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'weeklyemailsignup',
                nonce: selected_nonce,
                email: validEmails.join(',')
            },
            success: function (response) {
                if (response.success) {
                    $form.hide();
                    $('#seobooster_email_container .innercont').hide();
                    showMessage(response.data, 'success');
                } else {
                    showMessage(response.data || sbdata.strings.errorProcessingRequest, 'error');
                }
            },
            error: function (xhr, status, error) {
                showMessage(sbdata.strings.errorProcessingRequest, 'error');
            },
            complete: function () {
                $emailInput.prop('disabled', false);
                $submitButton.prop('disabled', false).removeClass('disabled');
                $form.find('.spinner').remove();
            }
        });

        function showMessage(message, type) {
            $messageContainer
                .html('<p>' + message + '</p>')
                .removeClass('success error')
                .addClass(type)
                .attr('role', 'alert')
                .slideDown();
        }
    });







    const threshold = 10; // Number of keywords to show initially

    jQuery('.report-section .kwlist,.content-section .kwlist').each(function () {
        const $section = jQuery(this);
        // const $kwlist = $section.find('.kwlist');
        const $toggleButton = $section.find('.toggle-button');
        const $keywords = $section.find('.kw');

        if ($keywords.length > threshold) {
            // Initially hide keywords beyond the threshold
            $keywords.each(function (index) {
                if (index >= threshold) {
                    jQuery(this).hide();
                }
            });

            // Show the toggle button if there are more keywords than the threshold
            $toggleButton.show();

            $toggleButton.on('click', function () {
                if ($kwlist.hasClass('expanded')) {
                    $kwlist.removeClass('expanded');
                    $toggleButton.text(sbdata.strings.showMore);

                    // Hide keywords beyond the threshold with animation
                    $keywords.each(function (index) {
                        if (index >= threshold) {
                            jQuery(this).slideUp('fast');
                        }
                    });
                } else {
                    $kwlist.addClass('expanded');
                    $toggleButton.text(sbdata.strings.showLess);

                    // Show all keywords with animation
                    $keywords.each(function () {
                        jQuery(this).slideDown('slow');
                    });
                }
            });
        } else {
            // Hide the toggle button if there are not more keywords than the threshold
            $toggleButton.hide();
        }
    });








    jQuery("#seobooster2selectsite").on("click", function (e) {
        e.preventDefault();

        var $this = jQuery(this);
        var siteurl = jQuery('select[name="seobooster_selected_site"]').val();

        // Validate that a site is selected
        if (!siteurl || siteurl === "") {
            jQuery("#seobooster-api-error").html(sbdata.strings.pleaseSelectSite || "Please select a site to continue").fadeIn();
            return false;
        }

        var step = 0;
        var startTime = new Date(); // Start the timer

        jQuery('.great-connected, .please-select').slideUp('fast');

        // Hide the timer initially
        jQuery('#elapsed-time').hide();

        $this.prop('disabled', true);
        $this.val(sbdata.strings.pleaseWait);

        jQuery('#seobooster-api-status').html('<span class="spinner is-active"></span>').fadeIn();

        jQuery('select[name="seobooster_selected_site"]').prop('disabled', true);
        jQuery('select[name="seobooster_selected_days"]').prop('disabled', true);
        function sendRequest(step, siteUrl, Days) {
            if (step === 1) {
                jQuery('#seobooster_email_container').slideDown('slow');
            }
            
            
            jQuery.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'sb_gsc_import_data',
                    step: step,
                    site_url: siteUrl,
                    nonce: sb_gsc_ajax.security,
                    days: Days
                },
                success: function (response) {
                    if (response.success) {
                        // Show the timer if it's hidden
                        jQuery('#elapsed-time').fadeIn();
                        
                        // Update alphabetical progress
                        

                        const message = '<span class="spinner is-active"></span>' +
                            sbdata.strings.uniqueKeywordsImported + ': ' + response.data.total_keywords + '<br>' +
                            sbdata.strings.totalEntriesProcessed + ': ' + response.data.total_entries + '<br>' +
                            sbdata.strings.lastImportKeyword + ': <code>' + response.data.last_keyword + '</code><br>' +
                            sbdata.strings.lastBatch + ': ' + response.data.time.toFixed(2) + ' ' + sbdata.strings.seconds + '.<br>' +
                            response.data.message;
                        updateStatus(message);

                        if (response.data.more_results) {
                            // Simple step progression - just increment step
                            var nextStep = step + 1;
                            // If there are more results, send a new request with the next step
                            sendRequest(nextStep, siteUrl, Days);
                        } else {
                            // Import is complete
                            var endTime = new Date(); // End the timer
                            var totalTime = calculateElapsedTime(startTime, endTime);

                            jQuery('#import-status').val(sbdata.strings.completed);
                            const completeMessage = sbdata.strings.importComplete + ' ' + response.data.total_keywords + ' ' + sbdata.strings.uniqueKeywordsImported + '.<br>' +
                                sbdata.strings.totalEntriesProcessed + ': ' + response.data.total_entries + '<br>' +
                                sbdata.strings.totalTime + ': ' + totalTime + '<p><a href="' + sb_gsc_ajax.dashboard_url + '" class="button button-hero button-primary">' + sbdata.strings.goToDashboard + '</a></p>';
                            updateStatus(completeMessage);

                            // Hide the timer after completion
                            jQuery('#elapsed-time').fadeOut();
                            // Show the email container
                            jQuery('#seobooster_email_container').slideDown('slow');
                            jQuery('#seobooster_email_container #seobooster_email').focus();


                        }
                    } else {
                        // Handle error
                        jQuery("#seobooster-api-error").html(response.data).fadeIn();
                        jQuery('#import-status').val(sbdata.strings.retry);
                    }
                },
                error: function () {
                    // Handle AJAX error
                    jQuery("#seobooster-api-error").html(sbdata.strings.errorProcessingRequest).fadeIn();
                    jQuery('#import-status').val(sbdata.strings.retry);
                }
            });
        }

        function updateStatus(message) {
            jQuery('#seobooster-api-status').html(message).fadeIn();
        }

        function calculateElapsedTime(startTime, endTime) {
            var elapsed = Math.floor((endTime - startTime) / 1000); // Elapsed time in seconds
            var minutes = Math.floor(elapsed / 60);
            var seconds = elapsed % 60;
            return (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        }

        function updateTimer() {
            var currentTime = new Date();
            var elapsedTime = calculateElapsedTime(startTime, currentTime);
            jQuery('#elapsed-time').text(sbdata.strings.elapsedTime + ': ' + elapsedTime);
        }

        // Clear any previous errors and start the first request
        jQuery("#seobooster-api-error").fadeOut();
        

        
        updateStatus(sbdata.strings.savingSiteAndLoadingData + '<span class="spinner is-active"></span>');

        var days = jQuery('select[name="seobooster_selected_days"]').val();
        // make sure days is a number and betwenn 1 and 448

        sendRequest(0, siteurl, days);

        // Start the timer update loop
        setInterval(updateTimer, 1000); // Update every second

        // Initial rendering of the timer (hidden initially)
        jQuery('<div id="elapsed-time" style="margin-top: 10px; display: none;">' + sbdata.strings.elapsedTime + ': 00:00</div>').insertAfter('#seobooster-api-status');
     });

    if (jQuery('#seobooster-gsc-chart').length) {
        setupDateRangeSelector();
        fetchChartData();
        
        // Add single resize handler for GSC chart
        let gscResizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(gscResizeTimeout);
            gscResizeTimeout = setTimeout(function() {
                if (chartInstance) {
                    // Force chart to recalculate size
                    chartInstance.resize();
                    // Also try to update the chart to ensure proper rendering
                    chartInstance.update('none');
                }
            }, 100);
        });
        
        // Add ResizeObserver as fallback for better resize detection
        if (window.ResizeObserver) {
            const gscContainer = document.getElementById('sb2canvascont');
            if (gscContainer) {
                const resizeObserver = new ResizeObserver(function() {
                    if (chartInstance) {
                        chartInstance.resize();
                        chartInstance.update('none');
                    }
                });
                resizeObserver.observe(gscContainer);
            }
        }
    }
    function setupDateRangeSelector() {
        const container = document.createElement('div');
        container.style.display = 'flex';
        container.style.justifyContent = 'flex-start'; // Align the content to the left
        container.style.marginBottom = '10px';

        const dateRangeSelector = document.createElement('select');
        dateRangeSelector.id = 'date-range-selector';
        dateRangeSelector.classList.add('regular-text'); // Use the appropriate WordPress class for <select>
        dateRangeSelector.innerHTML = `
			<option value="28_days">${sbdata.strings.last28Days}</option>
			<option value="3_months" selected>${sbdata.strings.last3Months}</option>
			<option value="6_months">${sbdata.strings.last6Months}</option>
			<option value="12_months">${sbdata.strings.last12Months}</option>
			<option value="all">${sbdata.strings.allTime}</option>
	`;

        // Append the select to the container
        container.appendChild(dateRangeSelector);

        // Insert the container before the chart
        document.getElementById('sb2canvascont').insertAdjacentElement('beforebegin', container);

        // Event listener to refresh data when the dropdown changes
        dateRangeSelector.addEventListener('change', function () {
            fetchChartData();
        });

        // Create the refresh button
        const refreshButtonContainer = document.createElement('div');
        refreshButtonContainer.style.textAlign = 'center';
        refreshButtonContainer.style.marginTop = '20px';

        const refreshButton = document.createElement('button');
        refreshButton.innerText = sbdata.strings.refresh;
        refreshButton.classList.add('button', 'button-small', 'button-secondary');

        // Event listener to refresh data when the button is clicked
        refreshButton.addEventListener('click', function () {
            fetchChartData();
        });

        // Append the button to the container
        refreshButtonContainer.appendChild(refreshButton);

        // Insert the refresh button container after the chart
        document.getElementById('sb2canvascont').insertAdjacentElement('afterend', refreshButtonContainer);
    }





    let chartInstance = null; // Variable to hold the chart instance



    function fetchChartData() {
        const loadingIndicator = document.getElementById('loading-indicator');
        const range = document.getElementById('date-range-selector').value;

        // Show loading indicator
        loadingIndicator.style.display = 'block';

        jQuery.ajax({
            url: sbdata.ajaxurl,
            method: 'POST',
            data: {
                action: 'fetch_chart_data',
                nonce: sbdata.nonce,
                range: range // Send the selected date range
            },
            success: function (response) {
                // Hide loading indicator
                loadingIndicator.style.display = 'none';

                if (response.success) {
                    var ctx = document.getElementById('seobooster-gsc-chart').getContext('2d');

                    // If a chart instance already exists, destroy it before creating a new one
                    if (chartInstance) {
                        chartInstance.destroy();
                    }

                    chartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: response.data.labels,
                            datasets: [{
                                label: sbdata.strings.clicks,
                                data: response.data.clicks,
                                borderColor: '#5e97f7',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                fill: false,
                                pointStyle: 'circle',
                                pointRadius: 6, // Increased radius for data points
                                pointHoverRadius: 8, // Increased hover radius
                                cubicInterpolationMode: 'monotone',
                                tension: 0.4
                            }, {
                                label: sbdata.strings.impressions,
                                data: response.data.impressions,
                                borderColor: '#5f35b2',
                                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                                fill: false,
                                pointStyle: 'circle',
                                pointRadius: 6, // Increased radius for data points
                                pointHoverRadius: 8, // Increased hover radius
                                cubicInterpolationMode: 'monotone',
                                tension: 0.4
                            }, {
                                label: sbdata.strings.ctr,
                                data: response.data.ctr,
                                borderColor: '#19897b',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                fill: false,
                                pointRadius: 6,
                                pointHoverRadius: 8
                            }, {
                                label: sbdata.strings.uniqueKeywords,
                                data: response.data.uniqueKeywords,
                                borderColor: '#ff9800',
                                backgroundColor: 'rgba(255, 152, 0, 0.2)',
                                fill: false,
                                pointStyle: 'circle',
                                pointRadius: 6,
                                pointHoverRadius: 8,
                                cubicInterpolationMode: 'monotone',
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: sbdata.strings.seoBoosterDataVisualization
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false
                                }
                            },
                            interaction: {
                                mode: 'nearest',
                                intersect: true
                            },
                            scales: {
                                x: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: sbdata.strings.date
                                    }
                                },
                                y: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: sbdata.strings.value
                                    }
                                }
                            }
                        }
                    });
                    
                } else {
                    const errorMessage = `
									<div style="color: red; font-weight: bold; padding: 10px; border: 1px solid red; background-color: #fdd;">
											${sbdata.strings.errorFetchingData} 
											${sbdata.strings.tryAgainLater} 
											${sbdata.strings.visitTroubleshootingGuide}
											<a href="https://seoboosterpro.com/docs/errors-and-troubleshooting/failed-to-fetch-data/" target="_blank" rel="noopener">
													${sbdata.strings.troubleshootingGuide}
											</a>.
									</div>
							`;
                    document.getElementById('sb2canvascont').outerHTML = errorMessage;
                }
            }
        });
    }













    jQuery('.sparkline').each(function () {
        // Extract the original data from the data attribute
        let rawData = jQuery(this).data('original').split(',').map(Number);

        // Find the maximum and minimum values in the data
        let maxValue = Math.max(...rawData);
        let minValue = Math.min(...rawData);

        // Adjust the data so lower values are higher points, but keep the progression
        let adjustedData = rawData.map(value => maxValue + minValue - value);

        // Render the sparkline with adjusted data
        jQuery(this).sparkline(adjustedData, {
            type: 'line',
            width: '100px',
            height: '20px',
            lineColor: '#6f42c1',
            fillColor: '#32abe2',
            chartRangeMin: 1,
            chartRangeMax: 100,
            tooltipFormatter: function (sp, options, fields) {
                return rawData[fields.x];
            }
        });
    });




    jQuery('.sbp-dismiss-review-notice, .sbp-review-notice .notice-dismiss').on('click', function () {
        if (!jQuery(this).hasClass('sbp-reviewlink')) {
            event.preventDefault();
        }
        jQuery.post(sbdata.ajaxurl, {
            action: 'sbp_dismiss_review',
            nonce: sbdata.nonce
        });
        jQuery('.sbp-review-notice').slideUp().remove();
    });


    jQuery('.suggtitle').click(function (event) {
        event.stopPropagation();

        var $this = jQuery(this).parent().find('.sugglist');

        jQuery(".suggcont .suggestions .sugglist").not($this).hide();
        $this.slideToggle();

    });


    jQuery(".suggtitle").on("click", function (event) {
        event.stopPropagation();
    });


    // ****** Autolink page helpers
    function clearAutolinkFormErrors() {
        jQuery('#addkwresponse').removeClass('hasError').empty();
        jQuery('#sb2_autolink_add form input').removeClass('hasError');
        jQuery('.field-error-msg').remove();
    }

    function showAutolinkFieldError($input, message) {
        $input.addClass('hasError');
        const $field = $input.closest('.sb-autolink-field');
        $field.find('.field-error-msg').remove();
        $field.append('<span class="field-error-msg" role="alert">' + message + '</span>');
    }

    function getAutolinkTableBody() {
        return jQuery('#urls-filter .wp-list-table tbody');
    }

    function insertAutolinkRow(rowHtml) {
        const $tbody = getAutolinkTableBody();
        $tbody.find('tr.no-items').remove();
        const $row = jQuery(rowHtml);
        $row.addClass('sb-autolink-row-new');
        $tbody.prepend($row);
        setTimeout(function () {
            $row.removeClass('sb-autolink-row-new');
        }, 2500);
    }

    function showAutolinkNoItemsRow() {
        const $table = jQuery('#urls-filter .wp-list-table');
        const colCount = $table.find('thead th').length;
        const noItemsText = (typeof sbdata !== 'undefined' && sbdata.strings && sbdata.strings.autolinkNoItems)
            ? sbdata.strings.autolinkNoItems
            : 'No keyword to links made.';
        getAutolinkTableBody().append(
            '<tr class="no-items"><td class="colspanchange" colspan="' + colCount + '">' + noItemsText + '</td></tr>'
        );
    }

    // ****** Add new keyword AJAX
    jQuery("#sb2_autolink_add").submit(function (event) {
        event.preventDefault();
        clearAutolinkFormErrors();
        jQuery('#sb2_autolink_add #submit').prop('disabled', true);

        jQuery('.kwaddspinner').show();
        jQuery("#sb2_autolink_add form #newkeyword").prop('disabled', true);
        jQuery("#sb2_autolink_add form #targeturl").prop('disabled', true);

        jQuery.post(
            sbdata.ajaxurl, {
            'dataType': 'json',
            'action': 'ajax_add_keyword',
            'add-keyword-nonce': jQuery('#_ajax_sb2_add_keyword_nonce').val(),
            'newkeyword': jQuery('#sb2_autolink_add form #newkeyword').val(),
            'targeturl': jQuery('#sb2_autolink_add form #targeturl').val()
        },
            function (response) {
                jQuery('#sb2_autolink_add #submit').prop('disabled', false);
                jQuery('.kwaddspinner').hide();

                if (response.answer) {
                    jQuery('#addkwresponse').html(response.answer);
                }

                if (response.success) {
                    jQuery('#addkwresponse').removeClass('hasError');
                    if (response.newrow) {
                        insertAutolinkRow(response.newrow);
                    }
                    jQuery("#sb2_autolink_add_form #newkeyword").prop('disabled', false).val('').focus();
                    jQuery("#sb2_autolink_add_form #targeturl").prop('disabled', false).val('');
                    return;
                }

                if (response.error === 'malurl') {
                    jQuery('#addkwresponse').addClass('hasError');
                    const $targetUrl = jQuery('#sb2_autolink_add form #targeturl');
                    $targetUrl.prop('disabled', false);
                    showAutolinkFieldError($targetUrl, jQuery('<div>').html(response.answer).text());
                    jQuery("#sb2_autolink_add form #newkeyword").prop('disabled', false);
                }

                if (response.error === 'kwused') {
                    jQuery('#addkwresponse').addClass('hasError');
                    const $keyword = jQuery('#sb2_autolink_add form #newkeyword');
                    $keyword.prop('disabled', false).focus();
                    showAutolinkFieldError($keyword, jQuery('<div>').html(response.answer).text());
                    jQuery("#sb2_autolink_add form #targeturl").prop('disabled', false);
                }
            }
        ).fail(function () {
            jQuery('#sb2_autolink_add #submit').prop('disabled', false);
            jQuery('.kwaddspinner').hide();
            jQuery("#sb2_autolink_add form #newkeyword, #sb2_autolink_add form #targeturl").prop('disabled', false);
            jQuery('#addkwresponse').addClass('hasError').html(
                (typeof sbdata !== 'undefined' && sbdata.strings && sbdata.strings.errorProcessingRequest)
                    ? sbdata.strings.errorProcessingRequest
                    : 'An error occurred while processing the request.'
            );
        });

    });

    // ****** Delete autolink keyword via AJAX
    jQuery(document).on('click', '.sb-delete-keyword', function (event) {
        event.preventDefault();

        const $link = jQuery(this);
        const id = $link.data('id');
        const nonce = $link.data('nonce');
        const confirmMsg = (typeof sbdata !== 'undefined' && sbdata.strings && sbdata.strings.areYouSure)
            ? sbdata.strings.areYouSure
            : 'Are you sure you want to do this?';

        window.SBModal.confirm(confirmMsg).then(function (confirmed) {
            if (!confirmed) {
                return;
            }

            const $row = $link.closest('tr');
            $row.addClass('sb-autolink-row-removing');

            jQuery.post(sbdata.ajaxurl, {
                action: 'sb_delete_keyword',
                id: id,
                nonce: nonce
            }, function (response) {
                if (response.success) {
                    $row.slideUp(300, function () {
                        jQuery(this).remove();
                        const $tbody = getAutolinkTableBody();
                        if ($tbody.find('tr').not('.no-items').length === 0) {
                            showAutolinkNoItemsRow();
                        }
                    });
                    return;
                }

                $row.removeClass('sb-autolink-row-removing');
                window.SBModal.alert(response.data || 'Error deleting keyword', { tone: 'error' });
            }).fail(function () {
                $row.removeClass('sb-autolink-row-removing');
                window.SBModal.alert(
                    (typeof sbdata !== 'undefined' && sbdata.strings && sbdata.strings.errorProcessingRequest)
                        ? sbdata.strings.errorProcessingRequest
                        : 'An error occurred while processing the request.',
                    { tone: 'error' }
                );
            });
        });
    });



    // Enhanced search with debounce
    let searchTimer;
    $('#search_id').on('input', function () {
        clearTimeout(searchTimer);
        const searchInput = $(this);

        searchTimer = setTimeout(function () {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('s', searchInput.val());
            window.location.href = currentUrl.toString();
        }, 500);
    });

    // Make cells editable on double click
    $('.wp-list-table').on('dblclick', '.editable-field', function (e) {
        // Don't trigger if we're already editing
        if ($(this).find('input').length) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        const $field = $(this);
        const currentValue = $field.text();
        const rowId = $field.data('id');
        const isKeyword = $field.hasClass('keyword-field');

        // Create input field within a form to properly handle enter key
        const $input = $('<input type="text" />')
            .val(currentValue)
            .addClass('inline-edit-input');

        // Create action icons
        const $okIcon = $('<span class="ok-icon">✔️</span>');
        const $cancelIcon = $('<span class="cancel-icon">❌</span>');

        // Save original content
        $field.data('original', $field.html())
            .empty()
            .append($input, $okIcon, $cancelIcon);

        // Position cursor at click position within input
        $input.focus();

        // Enable text selection within input
        $input.on('dblclick', function (e) {
            e.stopPropagation();
            // Let default text selection behavior work
        });

        // Prevent form submission on enter
        $input.on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault(); // Prevent form submission
                saveEdit($field, rowId, currentValue, isKeyword);
            } else if (e.key === 'Escape') {
                cancelEdit($field);
            }
        });

        // Handle save on enter and cancel on escape
        $okIcon.on('click', function () {
            saveEdit($field, rowId, currentValue, isKeyword);
        });

        $cancelIcon.on('click', function () {
            cancelEdit($field);
        });

        // Only save on blur if content changed
        $input.on('blur', function () {
            const newValue = $(this).val().trim();
            if (newValue !== currentValue) {
                saveEdit($field, rowId, currentValue, isKeyword);
            } else {
                cancelEdit($field);
            }
        });
    });

    function saveEdit($field, rowId, originalValue, isKeyword) {
        const $input = $field.find('input');
        const newInputValue = $input.val().trim();

        if (newInputValue === originalValue) {
            cancelEdit($field);
            return;
        }

        // Show spinner and disable icons
        const $spinner = $('<span class="spinner is-active"></span>');
        $field.append($spinner);
        $field.find('.ok-icon, .cancel-icon').hide();

        $.ajax({
            url: sbdata.ajaxurl,
            type: 'POST',
            data: {
                action: 'sb_update_keyword',
                nonce: $('#_ajax_sb2_add_keyword_nonce').val(),
                id: rowId,
                [isKeyword ? 'keyword' : 'url']: newInputValue
            },
            success: function (response) {
                if (response.success) {
                    $field.html(newInputValue);
                } else {
                    cancelEdit($field);
                    window.SBModal.alert(response.data || 'Error updating field', { tone: 'error' });
                }
            },
            error: function () {
                cancelEdit($field);
                window.SBModal.alert('Error processing request', { tone: 'error' });
            },
            complete: function () {
                $spinner.remove();
                $field.find('.ok-icon, .cancel-icon').show();
            }
        });
    }

    function cancelEdit($field) {
        $field.html($field.data('original'));
    }
});

// Toggle local domain info details
(function ($) {
    $(document).ready(function () {
        var $toggle = $('.show-local-domain-info');
        var $details = $('.local-domain-details');

        if ($toggle.length && $details.length) {
            $toggle.on('click', function (e) {
                e.preventDefault();
                if ($details.is(':hidden')) {
                    $details.slideDown(200);
                    $(this).text(
                        typeof sbdata !== 'undefined' && sbdata.strings && sbdata.strings.hide_info_text
                            ? sbdata.strings.hide_info_text
                            : 'Hide Information'
                    );
                } else {
                    $details.slideUp(200);
                    $(this).text(
                        typeof sbdata !== 'undefined' && sbdata.strings && sbdata.strings.show_info_text
                            ? sbdata.strings.show_info_text
                            : 'Show More Information'
                    );
                }
            });
        }
    });
})(jQuery);
