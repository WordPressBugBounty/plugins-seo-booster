/* global wp:true, sb_gsc_metabox_data:true, ajaxurl:true, jQuery:true, Tabulator:true */
jQuery(document).ready(function ($) {

    function loadKeywordsData() {
        // Hide existing table controls and refresh button if they exist
        $('#table-controls, #refresh-keywords, #refresh-keyword-analysis, #copy-all').remove();
        $('#sb-gsc-keywords-container').html('<p><span class="spinner is-active" style="margin-left:10px;float:left;"></span> ' + sb_gsc_metabox_data.strings.analyzing + '<span class="sb_timeload"></span></p>');
        var startTime = new Date().getTime();

        // Update the value in ".sb_timeload" with elapsed time
        var timerInterval = setInterval(function() {
            var elapsedTime = Math.floor((new Date().getTime() - startTime) / 1000);
            $('.sb_timeload').text(' (' + elapsedTime + 's)');
        }, 1000);

        $.post(ajaxurl, {
            action: 'sb_gsc_get_keywords',
            post_id: sb_gsc_metabox_data.post_id,
            post_url: sb_gsc_metabox_data.post_url,
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
                                <div id="sb-gsc-keywords-response"></div>
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
                    $('#sb_gsc_keywords #table-controls').after(`
                        <button id="refresh-keywords" class="button">${sb_gsc_metabox_data.strings.refreshKeywords}</button>
                        <button id="copy-all" class="button" style="display:none;">${sb_gsc_metabox_data.strings.copyAll}</button>
                    `);
// <button id="refresh-keyword-analysis" class="button">DEBUG ${sb_gsc_metabox_data.strings.refreshKeywordAnalysis}</button>

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
                            {title: sb_gsc_metabox_data.strings.ctr, field: "ctr", formatter: "html", widthGrow: 1},
                            {title: sb_gsc_metabox_data.strings.position, field: "position", formatter: function(cell) { var value = parseFloat(cell.getValue()); return isNaN(value) ? "" : value.toFixed(2); }, sorter: "number", widthGrow: 1},
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
                            console.error('Failed to copy text: ', err);
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
                                table.addFilter("clicks", "=", "0");
                                break;
                            case "not-used-content":
                                table.addFilter("position_intext", "=", "");
                                break;
                            case "used-content":
                                table.addFilter("position_intext", "=", "1");
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
                            console.error('Failed to copy text: ', err);
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

                    // Add event listener for filter dropdown change
                    $(document).on('change', '#filter-dropdown', function() {
                        var filterValue = $(this).val();
                        // Adjust the filter logic based on the selected filter value
                        if (filterValue === "no-clicks") {
                            table.setFilter("clicks", "=", "0");
                        } else if (filterValue === "not-used-content") {
                            table.setFilter("position_intext", "=", "");
                        } else if (filterValue === "used-content") {
                            table.setFilter("position_intext", "=", "1");
                        } else {
                            table.clearFilter();
                        }

                        // Update the response count after applying the filter
                        updateResponseCount();
                    });

                    // Add event listener for keyword search input
                    $(document).on('input', '#keyword-search', function() {
                        var searchValue = $(this).val();
                        table.setFilter("query", "like", searchValue); // Adjust the field and filter type as needed
                    });
                } else {
                    // Hide spinner and show message if no keywords found
                    $('.spinner.is-active').hide();
                    $('#sb-gsc-keywords-container').html(`<p>${response.data.message}</p>`);
                }
            }
        });
    }

    function refreshKeywordAnalysis() {
        $.post(ajaxurl, {
            action: 'sb_gsc_delete_transients',
            post_id: sb_gsc_metabox_data.post_id,
            security: sb_gsc_metabox_data.security
        }, function(response) {
            if (response.success) {
                loadKeywordsData();
            } else {
                alert(sb_gsc_metabox_data.strings.errorDeletingTransients);
            }
        }).fail(function() {
            alert(sb_gsc_metabox_data.strings.errorDeletingTransients);
        });
    }


    // Initial load
    loadKeywordsData();

    // Event listener for refresh button
    $(document).on('click', '#refresh-keywords', function(e) {
        e.preventDefault();
        loadKeywordsData();
    });

    // Event listener for refresh keyword analysis button
    $(document).on('click', '#refresh-keyword-analysis', function(e) {
        e.preventDefault();
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
            console.error('Failed to copy text: ', err);
            alert(sb_gsc_metabox_data.strings.error + ': ' + err.message);
        });
    });





    
});
