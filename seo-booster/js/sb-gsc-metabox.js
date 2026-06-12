/* global wp:true, sb_gsc_metabox_data:true, ajaxurl:true, jQuery:true, Tabulator:true, uPlot */
jQuery(document).ready(function ($) {
    // Create a single global tooltip element that will be reused across all charts
    const globalTooltip = document.createElement('div');
    globalTooltip.className = 'sb-uplot-tooltip';
    document.body.appendChild(globalTooltip);

    // Handle the "Load Data" button click
    $(document).on('click', '#sb-gsc-load-data-btn', function() {
        var $btn = $(this);
        var $container = $('#sb-gsc-load-button-container');
        
        // Disable button and show loading state
        $btn.prop('disabled', true).html('<span class="spinner is-active"></span> ' + sb_gsc_metabox_data.strings.loadingData);
        
        // Load the heavy libraries via AJAX
        $.post(ajaxurl, {
            action: 'sb_gsc_load_libraries',
            security: sb_gsc_metabox_data.security
        }, function(response) {
            if (response.success) {

                // Validate response structure
                if (!response.data || !response.data.styles || !response.data.scripts) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-line"></span> ' + sb_gsc_metabox_data.strings.loadData);
                    alert('Invalid response from server. Please try again.');
                    return;
                }
                
                // Load the CSS files
                if (response.data.styles && typeof response.data.styles === 'object') {
                    Object.keys(response.data.styles).forEach(function(styleKey) {
                        var styleUrl = response.data.styles[styleKey];
                        if (!$('link[href="' + styleUrl + '"]').length) {
                            var link = document.createElement('link');
                            link.rel = 'stylesheet';
                            link.type = 'text/css';
                            link.href = styleUrl;
                            document.head.appendChild(link);
                        }
                    });
                }
                
                // Load the JavaScript files
                var scriptsLoaded = 0;
                var totalScripts = Object.keys(response.data.scripts).length;
                var scriptErrors = [];
                                
                function checkAllScriptsLoaded() {
                    scriptsLoaded++;
                    if (scriptsLoaded >= totalScripts) {
                        if (scriptErrors.length > 0) {
                            // Handle script loading errors
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-line"></span> ' + sb_gsc_metabox_data.strings.loadData);
                            alert('Some libraries failed to load: ' + scriptErrors.join(', '));
                            return;
                        }
                        
                        // Check if Tabulator and uPlot are available
                        if (typeof Tabulator === 'undefined' || typeof uPlot === 'undefined') {
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-line"></span> ' + sb_gsc_metabox_data.strings.loadData);
                            alert('Libraries loaded but not available. Please refresh the page and try again.');
                            return;
                        }
                        
                        // All scripts loaded successfully, now initialize the keyword analysis
                        $container.hide();
                        $('#sb-gsc-keywords-container').show();
                        $('.sb-chart-legend').show();
                        loadKeywordsData();
                    }
                }
                
                // Load each script
                Object.keys(response.data.scripts).forEach(function(scriptKey) {
                    var scriptUrl = response.data.scripts[scriptKey];
                    if (!$('script[src="' + scriptUrl + '"]').length) {
                        var script = document.createElement('script');
                        script.src = scriptUrl;
                        script.onload = function() {
                            checkAllScriptsLoaded();
                        };
                        script.onerror = function() {
                            scriptErrors.push(scriptKey);
                            checkAllScriptsLoaded();
                        };
                        document.head.appendChild(script);
                    } else {
                        checkAllScriptsLoaded();
                    }
                });
            } else {
                // Handle error
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-line"></span> ' + sb_gsc_metabox_data.strings.loadData);
                alert('Error loading libraries: ' + (response.data ? response.data.message : 'Unknown error'));
            }
        }).fail(function(xhr, status, error) {
            // Handle AJAX failure
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-line"></span> ' + sb_gsc_metabox_data.strings.loadData);
            alert('Failed to load libraries. Please try again.');
        });
    });

    function loadKeywordsData() {
        // Hide existing table controls and refresh button if they exist
        $('#table-controls, #refresh-keywords, #copy-all, #reanalyze').remove();

        var $sbTableCont = $('#sbtablecont');
        var $form = $sbTableCont.closest('form#edittag');

        if ($form.length > 0) {
            $sbTableCont.insertAfter($form);
        }
        $('#sb-gsc-keywords-container').html('<p><span class="spinner is-active" style="margin-left:10px;float:left;"></span> ' + sb_gsc_metabox_data.strings.analyzing + '<span class="sb_timeload"></span></p>');
        var startTime = new Date().getTime();

        // Update the value in ".sb_timeload" with elapsed time
        var timerInterval = setInterval(function() {
            var elapsedTime = Math.floor((new Date().getTime() - startTime) / 1000);
            $('.sb_timeload').text(' (' + elapsedTime + 's)');
        }, 1000);

        // Get data from hidden input fields
        var public_url = $('#sb-gsc-public-url').val();
        var content_type = $('#sb-gsc-content-type').val();
        var item_id = $('#sb-gsc-item-id').val();

        $.post(ajaxurl, {
            action: 'sb_gsc_get_keywords',
            public_url: public_url,
            content_type: content_type,
            item_id: item_id,
            security: sb_gsc_metabox_data.security,
            filter: $("#filter-dropdown").val()
        }, function (response) {
            // Clear loading spinner after the response is received
            clearInterval(timerInterval);
            $('.sb_timeload').hide();
            // Existing code for handling the response
            if (response.data.error) {
                $('#sb-gsc-keywords-container').html('<p>' + response.data.message + '</p>');
            } else {
                if (response.data.keywords && response.data.keywords.length > 0) {
                    // Add table controls after the existing content
                    $('#sb-gsc-keywords-container').before(`
                        <div id="table-controls" class="tablenav">
                            <div class="alignleft actions bulkactions">
                                <input type="text" id="keyword-search" placeholder="${sb_gsc_metabox_data.strings.searchPlaceholder}" class="regular-text">
                                <select id="filter-dropdown" class="postform">
                                    <option value="">${sb_gsc_metabox_data.strings.allKeywords}</option>
                                    <option value="no-clicks">${sb_gsc_metabox_data.strings.keywordsNoClicks}</option>
                                    <option value="not-used-content">${sb_gsc_metabox_data.strings.keywordsNotUsedInContent}</option>
                                    <option value="used-content">${sb_gsc_metabox_data.strings.keywordsUsedInContent}</option>
                                </select>
                                <button id="reset-filters" type="button" class="button" style="display:none;">${sb_gsc_metabox_data.strings.resetFilters}</button>

                            </div>
                            <div class="tablenav-pages">
                                <span class="pagination-controls"></span>
                                <span class="paging-input">
                                    <span class="tabulator-page-size"></span>
                                </span>
                            </div>
                        </div>
                    `);

                    // Add refresh and copy all buttons after the table controls
                    $('#table-controls').after(`
                        <button id="refresh-keywords" class="button">${sb_gsc_metabox_data.strings.refresh}</button>
                        <button id="reanalyze" class="button">${sb_gsc_metabox_data.strings.reanalyze}</button>
                        <button id="copy-all" class="button" style="display:none;">${sb_gsc_metabox_data.strings.copyAll}</button>
                        <div id="sb-gsc-keywords-response"></div>
                    `);

                    // Store the Tabulator instance in a variable for easy access
                    var table; // Declare the table variable

                    // Initialize Tabulator
                    table = new Tabulator("#sb-gsc-keywords-container", {
                        data: response.data.keywords,
                        layout: "fitColumns",
                        responsiveLayout: "hide",
                        columns: [
                            {
                                title: '<input type="checkbox" id="select-all-rows" />', 
                                field: "select", 
                                formatter: "rowSelection", 
                                headerSort: false, 
                                width: 30, 
                                hozAlign: "center", 
                                headerHozAlign: "center"
                            },
                            {
                                title: sb_gsc_metabox_data.strings.query, 
                                field: "query", 
                                formatter: function(cell) {
                                    var firstSeen = cell.getRow().getData().first_seen_date;
                                    var lastSeen = cell.getRow().getData().latest_date;
                                    return `<div class="query-container">
                                                <div class="copyicon" data-clipboard-text="${cell.getValue()}">⧉</div>
                                                <span title="${sb_gsc_metabox_data.strings.firstSeen}: ${new Date(firstSeen).toLocaleDateString()}, ${sb_gsc_metabox_data.strings.lastSeen}: ${new Date(lastSeen).toLocaleDateString()}">
                                                    ${cell.getValue()}
                                                </span>
                                            </div>`;
                                },
                                widthGrow: 3
                            },
                            {
                                title: sb_gsc_metabox_data.strings.seenInContent, 
                                field: "position_details", 
                                formatter: function(cell) { 
                                    return cell.getValue() && cell.getValue() !== "0" ? cell.getValue() : ""; 
                                }, 
                                widthGrow: 1
                            },
                            {title: sb_gsc_metabox_data.strings.clicks, field: "clicks", formatter: "html", widthGrow: 1},
                            {title: sb_gsc_metabox_data.strings.impressions, field: "impressions", formatter: "html", sorter: "number", widthGrow: 1},
                            {
                                title: sb_gsc_metabox_data.strings.trends || "Trends",
                                field: "curves",
                                formatter: curvesFormatter, 
                                headerSort: false,
                                hozAlign: "center",
                                width: 300,
                                resizable: true
                            },
                            {title: sb_gsc_metabox_data.strings.autolink, field: "autolink", formatter: "html", widthGrow: 1}
                        ],
                        pagination: true,
                        paginationSize: 50,
                        paginationSizeSelector: [50, 100, 200],
                        paginationElement: document.querySelector(".pagination-controls"),
                        paginationCounter: "pages",
                        paginationButtonCount: 7,
                        ajaxProgressiveLoad: "scroll",
                        tableBuilt: function() {
                            this.element.classList.add("wp-list-table", "widefat", "fixed", "striped");
                        }
                    });

                    function updateResponseCount() {
                        var totalRows = table.getDataCount("active"); // Get the count of rows after filtering
                        var totalKeywords = response.data.keywords.length; // Total keywords from the response
                
                        // Update the UI with the count of filtered results
                        $('#sb-gsc-keywords-response').html(
                            `${sb_gsc_metabox_data.strings.showingKeywords}: ${totalRows} / ${totalKeywords}`
                        );
                    }
                
                    // Custom pagination buttons
                    function updatePagination() {
                        var paginationElement = document.querySelector(".pagination-controls");
                        paginationElement.innerHTML = "";

                        var currentPage = table.getPage();
                        var totalPages = table.getPageMax();

                        // Previous button
                        var prevButton = document.createElement("button");
                        prevButton.className = "tabulator-page button button-small";
                        prevButton.innerHTML = "&laquo;";
                        prevButton.disabled = currentPage === 1;
                        prevButton.addEventListener("click", function() {
                            table.previousPage();
                        });
                        paginationElement.appendChild(prevButton);

                        // Page numbers
                        for (var i = Math.max(1, currentPage - 1); i <= Math.min(totalPages, currentPage + 1); i++) {
                            var pageButton = document.createElement("button");
                            pageButton.className = i === currentPage ? "tabulator-page button button-small current-page" : "tabulator-page button button-small";
                            pageButton.textContent = i;
                            pageButton.addEventListener("click", function(e) {
                                table.setPage(parseInt(e.target.textContent));
                            });
                            paginationElement.appendChild(pageButton);
                        }

                        // Next button
                        var nextButton = document.createElement("button");
                        nextButton.className = "tabulator-page button button-small";
                        nextButton.innerHTML = "&raquo;";
                        nextButton.disabled = currentPage === totalPages;
                        nextButton.addEventListener("click", function() {
                            table.nextPage();
                        });
                        paginationElement.appendChild(nextButton);

                        // Update page size selector
                        var pageSizeSelector = document.querySelector(".tabulator-page-size");
                        if (pageSizeSelector) {
                            pageSizeSelector.innerHTML = "";
                            var select = document.createElement("select");
                            select.className = "postform";
                            [25, 50, 100].forEach(function(size) {
                                var option = document.createElement("option");
                                option.value = size;
                                option.textContent = size + " " + wp.i18n.__('per page', 'seo-booster');
                                if (size === table.getPageSize()) option.selected = true;
                                select.appendChild(option);
                            });
                            select.addEventListener("change", function(e) {
                                table.setPageSize(parseInt(e.target.value));
                            });
                            pageSizeSelector.appendChild(select);
                        }
                    }

                    // Update pagination on table draw and page change
                    table.on("tableBuilt", updatePagination);
                    table.on("pageLoaded", updatePagination);

                    // Remove footer pagination
                    table.on("tableBuilt", function(){
                        var footer = this.element.querySelector(".tabulator-footer");
                        if(footer) {
                            footer.style.display = "none";
                        }
                    });

                    // Clear existing time and total keywords information
                    $('#sb-gsc-keywords-container').parent('.inside').find('.time-info, .total-keywords-info').remove();

                    // Display total number of keywords and time taken, appending to the specific parent of the table
                    $('#sb-gsc-keywords-container').parent('.inside').append(`
                        <p class="time-info">${sb_gsc_metabox_data.strings.time}: ${response.data.time} s.</p>
                        <p class="total-keywords-info">${sb_gsc_metabox_data.strings.totalKeywords}: ${response.data.keywords.length}</p>
                    `);


                    // Show "Copy All" button if rows are selected
                    table.on("rowSelectionChanged", function(data) {
                        if (data.length > 0) {
                            $('#copy-all').show();
                        } else {
                            $('#copy-all').hide();
                        }
                    });

                    // Event listener for "Copy All" button
                    $(document).on('click', '#copy-all', function(e) {
                        e.preventDefault();
                        var selectedData = table.getSelectedData();
                        var textToCopy = selectedData.map(function(row) {
                            return `"${row.query}"`;
                        }).join(', ');
                        navigator.clipboard.writeText(textToCopy).then(function() {
                            alert(sb_gsc_metabox_data.strings.copiedToClipboard + ': ' + textToCopy);
                        }).catch(function(err) {
                        });
                    });

                    // Event listener for "Select All" checkbox
                    $(document).on('change', '#select-all-rows', function() {
                        if (this.checked) {
                            table.selectRow();
                        } else {
                            table.deselectRow();
                        }
                    });

                    // Function to apply both search and dropdown filters
                    function applyFilters() {
                        var searchValue = $('#keyword-search').val();
                        var filterValue = $('#filter-dropdown').val();

                        // Clear all filters first
                        table.clearFilter();

                        // Apply search filter
                        if (searchValue) {
                            table.setFilter("query", "like", searchValue);
                        }

                        // Apply dropdown filter
                        switch (filterValue) {
                            case "no-clicks":
                                table.setFilter("clicks", "=", "0");
                                break;
                            case "not-used-content":
                                table.setFilter("position_intext", "=", "-1");
                                break;
                            case "used-content":
                                table.setFilter("position_intext", "=", "1");
                                break;
                            // No need to add a filter for "all keywords" as it should show everything
                        }

                        // Update the response count after applying the filters
                        updateResponseCount();
                    }

                    // Add event listener for filter dropdown change
                    $(document).on('change', '#filter-dropdown', applyFilters);

                    // Add event listener for keyword search input
                    $(document).on('input', '#keyword-search', applyFilters);

                    // Update the response count on table initialization and after any data change
                    table.on("tableBuilt", updateResponseCount);
                    table.on("dataFiltered", updateResponseCount); // Ensure it updates after filtering
                } else {
                    // Hide spinner and show message if no keywords found
                    $('.spinner.is-active').hide();
                    
                    // Create a more visually appealing and informative "no keywords" message
                    const noKeywordsContainer = document.createElement('div');
                    noKeywordsContainer.className = 'sb-gsc-no-keywords';
                    noKeywordsContainer.innerHTML = `
                        <div class="sb-gsc-no-keywords-content">
                            <div class="sb-gsc-no-keywords-icon">
                                <span class="dashicons dashicons-info"></span>
                            </div>
                            <h3>${sb_gsc_metabox_data.strings.noKeywordsFound}</h3>
                            <p>${sb_gsc_metabox_data.strings.noKeywordsExplanation}</p>
                            <ul>
                                <li>${sb_gsc_metabox_data.strings.noKeywordsReason1}</li>
                                <li>${sb_gsc_metabox_data.strings.noKeywordsReason2}</li>
                            </ul>
                        </div>
                    `;
                    
                    // Replace the container with the no keywords message
                    $('#sb-gsc-keywords-container').html('').append(noKeywordsContainer);
                    return;
                }
            }
        });
    }

    function refreshKeywordAnalysis() {
        // Get values from hidden fields
        var public_url = $('#sb-gsc-public-url').val();
        var content_type = $('#sb-gsc-content-type').val();
        var item_id = $('#sb-gsc-item-id').val();

        $.post(ajaxurl, {
            action: 'sb_gsc_reanalyze_keywords',
            public_url: public_url,
            content_type: content_type,
            item_id: item_id,
            security: sb_gsc_metabox_data.security
        }, function(response) {
 
            if (response.success) {
                if (response.data.message) {
                    jQuery('#sb-gsc-keywords-response').html(response.data.message);
                }
                setTimeout(loadKeywordsData, 4000);
            } else {
                alert(sb_gsc_metabox_data.strings.errorDeletingTransients);
            }
        }).fail(function() {
            alert(sb_gsc_metabox_data.strings.errorDeletingTransients);
        });
    }

    // Check if #sbtablecont exists and is inside a form element
   

    // Note: loadKeywordsData() is now called only when user clicks "Load Data" button

    // Event listener for refresh button
    $(document).on('click', '#refresh-keywords', function(e) {
        e.preventDefault();
        loadKeywordsData();
    });

    // Event listener for reanalyze button
    $(document).on('click', '#reanalyze', function(e) {
        e.preventDefault();
        
        // Show processing message
        $('#reanalyze-message').remove(); // Remove any existing message
        $(this).after('<div id="reanalyze-message" class="reanalyze-message">' + 
            sb_gsc_metabox_data.strings.analysisResetMessage + '</div>');
        
        refreshKeywordAnalysis();
    });

    $(document).on('click', '.seobooster-add-autolink', function (e) {
        e.preventDefault();
        var post_id = $(this).data('postid');
        var query_id = $(this).data('queryid');
        var orgknap = $(this);

        // Confirmation dialog
        if (confirm(wp.i18n.__('Are you sure you want to create internal links using this keyword on other pages, linking to this page?', 'seo-booster'))) {
            orgknap.replaceWith(`<span class="spinner is-active spin-${post_id}"></span>`);

            $.post(ajaxurl, {
                action: 'seobooster_gsc_make_auto_link',
                post_id: post_id,
                query_id: query_id,
                security: sb_gsc_metabox_data.security
            }, function (response) {
                if (response.success) {
                    $(`.spinner.is-active.spin-${post_id}`).replaceWith(`${response.data.message}`);
                } else {
                    $(`.spinner.is-active.spin-${post_id}`).replaceWith(`<p class="error">${wp.i18n.__('Error:', 'seo-booster')} ${response.data.message}</p>`);
                }
            }).fail(function() {
                $(`.spinner.is-active.spin-${post_id}`).replaceWith(`<p class="error">${wp.i18n.__('An error occurred. Please try again.', 'seo-booster')}</p>`);
            });
        }
    });

    // Event listener for copyicon click
    $(document).on('click', '.copyicon', function () {
        var $icon = $(this); // Store a reference to the clicked element
        var textToCopy = $icon.attr('data-clipboard-text');
        
        navigator.clipboard.writeText(textToCopy).then(function() {
            $icon.addClass('copied');
            setTimeout(() => {
                $icon.removeClass('copied');
            }, 3000);
        }).catch(function(err) {
            alert(sb_gsc_metabox_data.strings.error + ': ' + err.message);
        });
    });

    // Add this uPlot curve formatter
    const curvesFormatter = function(cell, formatterParams, onRendered) {
        // Create a container for the chart
        const container = document.createElement('div');
        container.className = 'sb-uplot-container';
        
        // Get data from the row
        const rowData = cell.getRow().getData();
        
        // We'll use onRendered callback to make sure the DOM element exists before creating the chart
        onRendered(function() {
            // Structure data for uPlot (time series format)
            if (!rowData.history || rowData.history.length === 0) {
                container.innerHTML = '<div class="sb-no-data">' + sb_gsc_metabox_data.strings.noDataAvailable + '</div>';
                return;
            }
            
            // Check if this is old data (more than 30 days since last visit)
            const isOldData = rowData.history[0] && rowData.history[0].days_since_last_visit && rowData.history[0].days_since_last_visit > 30;
            const lastVisitText = isOldData && rowData.history[0].last_visit_date ? 
                `<div class="sb-last-visit-info">Last visit: ${Math.round(rowData.history[0].days_since_last_visit)} days ago</div>` : '';
            
            // If we just have a single data point, display its information instead of a chart
            if (rowData.history.length === 1) {
                const singlePoint = rowData.history[0];
                const date = new Date(singlePoint.date).toLocaleDateString();
                container.innerHTML = `
                    <div class="sb-single-data-point">
                        <div class="sb-data-date">${date}</div>
                        <div class="sb-data-metrics">
                            <span class="sb-clicks" title="${sb_gsc_metabox_data.strings.clicks}">
                                <span class="dashicons dashicons-visibility"></span> ${singlePoint.impressions}
                            </span>
                            <span class="sb-impressions" title="${sb_gsc_metabox_data.strings.impressions}">
                                <span class="dashicons dashicons-yes-alt"></span> ${singlePoint.clicks}
                            </span>
                            <span class="sb-position" title="${sb_gsc_metabox_data.strings.position}">
                                <span class="dashicons dashicons-arrow-up-alt"></span> ${parseFloat(singlePoint.position).toFixed(1)}
                            </span>
                        </div>
                        ${lastVisitText}
                    </div>
                `;
                return;
            }
            
            try {
                // Extract dates and values from history
                const timestamps = rowData.history.map(item => new Date(item.date).getTime() / 1000); // Convert to timestamps 
                const impressions = rowData.history.map(item => parseInt(item.impressions, 10) || 0);
                const clicks = rowData.history.map(item => parseInt(item.clicks, 10) || 0);
                const positions = rowData.history.map(item => parseFloat(item.position) || 0);
                
                // Only show the chart if we have valid data
                if (timestamps.length < 2) {
                    container.innerHTML = '<div class="sb-no-data">' + sb_gsc_metabox_data.strings.insufficientData + lastVisitText + '</div>';
                    return;
                }

                // Data format for uPlot - using original arrays to keep points connected
                const data = [
                    timestamps,       // x-values (timestamps)
                    impressions,      // y-values (series 1 - impressions)
                    clicks,           // y-values (series 2 - clicks) 
                    positions         // y-values (series 3 - positions)
                ];

                // Create chart options
                const chartOpts = {
                    width: container.clientWidth || 260,  // Use container width or fallback to 220px
                    height: 70,       // height in pixels
                    padding: [5, 0, 0, 0], // [top, right, bottom, left]
                    cursor: {
                        show: true,
                        points: {
                            show: true,
                            size: 6
                        },
                        lock: false,
                        focus: {
                            prox: 30
                        }
                    },
                    select: {
                        show: false
                    },
                    legend: {
                        show: false
                    },
                    axes: [
                        {
                            show: false
                        },
                        {
                            show: false
                        }
                    ],
                    scales: {
                        x: {
                            time: true,
                            auto: true,
                            range: (u, min, max) => [min, max]
                        },
                        y: {
                            auto: true,
                            range: (u, min, max) => {
                                const padding = (max - min) * 0.1;
                                return [min - padding, max + padding];
                            }
                        },
                        position: {
                            auto: true,
                            range: (u, min, max) => {
                                // Invert position scale (lower position = better)
                                const padding = (max - min) * 0.1;
                                return [max + padding, min - padding];
                            }
                        }
                    },
                    series: [
                        {},
                        {
                            stroke: "rgba(24, 119, 242, 0.8)",  // blue for impressions
                            width: 1,
                            fill: "rgba(24, 119, 242, 0.1)",
                            paths: impressions.some(v => v > 0) ? undefined : u => null,
                            points: {
                                show: true,
                                size: 4,
                                stroke: "rgba(24, 119, 242, 1)",
                                fill: "rgba(24, 119, 242, 0.8)"
                            }
                        },
                        {
                            stroke: "rgba(45, 196, 78, 0.8)",   // green for clicks
                            width: 1,
                            fill: "rgba(45, 196, 78, 0.1)",
                            paths: clicks.some(v => v > 0) ? undefined : u => null,
                            points: {
                                show: true,
                                size: 4,
                                stroke: "rgba(45, 196, 78, 1)",
                                fill: "rgba(45, 196, 78, 0.8)"
                            }
                        },
                        {
                            stroke: "rgba(242, 120, 24, 0.8)",  // orange for position
                            width: 1.5,
                            scale: "position",
                            points: {
                                show: true,
                                size: 4,
                                stroke: "rgba(242, 120, 24, 1)",
                                fill: "rgba(242, 120, 24, 0.8)"
                            }
                        }
                    ]
                };
                
                // Create the chart immediately
                const chart = new uPlot(chartOpts, data, container);
                
                // Add last visit info below the chart if it's old data
                if (lastVisitText) {
                    const lastVisitDiv = document.createElement('div');
                    lastVisitDiv.className = 'sb-last-visit-info';
                    lastVisitDiv.innerHTML = lastVisitText;
                    container.appendChild(lastVisitDiv);
                }
                
                // Handle hover events for tooltip
                container.addEventListener('mousemove', function(e) {
                    var rect = container.getBoundingClientRect();
                    var left = rect.left;
                    var top = rect.top;

                    
                    // Get cursor index
                    var idx = chart.cursor.idx;
                    
                    // Show tooltip only if we have a valid index
                    if (idx !== null) {
                        var date = new Date(timestamps[idx] * 1000); // Convert timestamp to milliseconds
                        var formattedDate = date.toLocaleDateString();
                        var impressions = data[1][idx];
                        var clicks = data[2][idx];
                        var position = data[3][idx];
                        
                        // Add time since last data point if this is the most recent point
                        let timeSinceLastData = '';
                        if (idx === timestamps.length - 1) {
                            const now = new Date().getTime() / 1000;
                            const timeDiff = now - timestamps[idx];
                            const days = Math.floor(timeDiff / (24 * 60 * 60));
                            
                            if (days > 0) {
                                timeSinceLastData = `<div class="sb-time-since">${days} day${days !== 1 ? 's' : ''} ago</div>`;
                            }
                        }
                        
                        globalTooltip.innerHTML = 
                            '<div class="sb-tooltip-date">' + formattedDate + '</div>' +
                            timeSinceLastData +
                            '<div class="sb-tooltip-metrics">' +
                                '<span class="sb-impressions">' + sb_gsc_metabox_data.strings.impressions + ': ' + impressions + '</span>' +
                                '<span class="sb-clicks">' + sb_gsc_metabox_data.strings.clicks + ': ' + clicks + '</span>' +
                                '<span class="sb-position">' + sb_gsc_metabox_data.strings.position + ': ' + position.toFixed(1) + '</span>' +
                            '</div>';
                        
                        globalTooltip.style.display = 'block';
                        
                        // Position tooltip to avoid container boundaries
                        var tooltipRect = globalTooltip.getBoundingClientRect();
                        
                        var tooltipX = e.clientX + 10;
                        var tooltipY = e.clientY + 10;
                        
                        // Adjust horizontal position if tooltip would overflow
                        if (tooltipX + tooltipRect.width > window.innerWidth) {
                            tooltipX = e.clientX - tooltipRect.width - 10;
                        }
                        
                        // Adjust vertical position if tooltip would overflow
                        if (tooltipY + tooltipRect.height > window.innerHeight) {
                            tooltipY = e.clientY - tooltipRect.height - 10;
                        }
                        
                        globalTooltip.style.left = tooltipX + 'px';
                        globalTooltip.style.top = tooltipY + 'px';
                    } else {
                        // Hide tooltip if no valid index
                        globalTooltip.style.display = 'none';
                    }
                });
                
                container.addEventListener('mouseleave', function() {
                    // Hide tooltip when mouse leaves the chart
                    globalTooltip.style.display = 'none';
                });
                
            } catch (e) {
                container.innerHTML = '<div class="sb-error">Chart error</div>' + lastVisitText;
            }
        });
        
        return container;
    };
});
