/* global jQuery, Tabulator, sbReportData, ajaxurl */
jQuery(document).ready(function($) {
    const $navbar = $('#scroll-navbar');
    const $navbarInner = $navbar.find('.scroll-navbar-inner');
    const $sections = $('.report-section');
    const $backToTop = $('#back-to-top');

     // Create and inject the select element before back-to-top
     const $sectionSelect = $('<select id="section-select"></select>');
     $('#back-to-top').before($sectionSelect);

    // Populate the select with section titles
    $sections.each(function(index) {
        const $heading = $(this).find('.title h2').first(); // Only get the first h2 in each section
        if ($heading.length && $heading.attr('id')) {
            // Clean the text by removing extra whitespace and line breaks
            const headingText = $heading.text().trim().replace(/\s+/g, ' ');
            const $option = $('<option></option>')
                .val($heading.attr('id'))
                .text(headingText);
            $sectionSelect.append($option);
        }
    });

    // Show navbar on scroll
    $(window).on('scroll', function() {
        const firstSectionTop = $sections.first().offset().top;
        if ($(window).scrollTop() > firstSectionTop - $navbar.outerHeight()) {
            $navbar.css('display', 'flex');
        } else {
            $navbar.css('display', 'none');
        }

        // Update selected option based on scroll position
        $sections.each(function() {
            const $section = $(this);
            const sectionTop = $section.offset().top - $navbar.outerHeight() - 60;
            const sectionBottom = sectionTop + $section.outerHeight();
            if ($(window).scrollTop() >= sectionTop && $(window).scrollTop() < sectionBottom) {
                const $heading = $section.find('.title h2');
                if ($heading.length && $heading.attr('id')) {
                    $sectionSelect.val($heading.attr('id'));
                }
            }
        });
    });

    // Scroll to section on select change
    $sectionSelect.on('change', function() {
        const $selectedSection = $('#' + $(this).val());
        if ($selectedSection.length) {
            const offsetTop = $selectedSection.offset().top - $navbar.outerHeight() - 320;
            $('html, body').animate({
                scrollTop: offsetTop
            }, 600);
        } else {
        }
    });

    // Back to top functionality
    $backToTop.on('click', function() {
        $('html, body').animate({
            scrollTop: 0
        }, 500);
    });

    // New table conversion code
    function convertWPListTables(container = null) {
        if (typeof Tabulator === 'undefined') {
            return;
        }

        // Only select tables within the specified container, or all tables if no container specified
        const $tables = container ? $(container).find('.wp-list-table') : $('.wp-list-table');

        $tables.each(function(index) {
            const $table = $(this);
            const tableId = `tabulator-${Date.now()}-${index}`; // Make IDs unique with timestamp
        
            // Create wrapper div and insert it after the table
            const $wrapper = $('<div>', {
                id: tableId,
                class: 'tabulator-wrapper'
            });
            $table.after($wrapper);

            // Create controls container
            const $controls = $('<div>', {
                class: 'tablenav top'
            }).append(
                $('<div>', { class: 'alignleft actions' }).append(
                    $('<input>', {
                        type: 'text',
                        class: 'search-input regular-text',
                        placeholder: 'Search...',
                        style: 'margin-right: 10px;'
                    }),
                    $('<button>', {
                        type: 'button',
                        class: 'button export-csv-button',
                        text: 'Export to CSV',
                        style: 'margin-right: 10px;'
                    })
                ),
                $('<div>', { 
                    class: 'tabulator-results displaying-num',
                    style: 'margin: 0 10px;'
                }),
                $('<div>', { 
                    class: 'tabulator-pagination pagination-links alignright'
                })
            );

            // Insert controls before wrapper
            $wrapper.before($controls);

            // Extract columns from table headers
            const columns = [];
            $table.find('thead th').each(function() {
                const headerText = $(this).text().trim();
                const field = headerText.toLowerCase().replace(/[^a-z0-9]/g, '_');
                
                var columnConfig = {
                    title: headerText,
                    field: field,
                    headerSort: true
                };

                // Special handling for URL and referrer columns
                if (field ==='url' || field === 'referrer' || field === 'page' || field ==='lp' || field === 'side') {
                    columnConfig.formatter = function(cell) {
                        var value = cell.getValue();
                        if (!value) return '';
                        
                        // Get the current site's domain
                        var currentDomain = window.location.hostname;
                        
                        try {
                            var url = new URL(value);
                            // If it's the same domain, show only the path
                            if (url.hostname === currentDomain) {
                                return '<a href="' + value + '" target="_blank">' + url.pathname + '</a>';
                            }
                            // For external links, show full URL
                            return '<a href="' + value + '" target="_blank">' + value + '</a>';
                        } catch(e) {
                            // If URL parsing fails, return original value
                            return value;
                        }
                    };
                }
                // Special handling for competing pages column
                else if (field === 'competing_pages') {
                    columnConfig.formatter = function(cell) {
                        var pages = cell.getValue();
                        if (!Array.isArray(pages)) {
                            try {
                                pages = JSON.parse(pages);
                            } catch(e) {
                                return '';
                            }
                        }
                        
                        return pages.map(function(page) {
                            var inactiveText = page.days_inactive > 30 ? 
                                '<br><span class="inactive-warning">Inactive for ' + page.days_inactive + ' days</span>' : 
                                '';
                            
                            // Format last visit information
                            var lastVisitText = '';
                            if (page.last_visit_date) {
                                var lastVisitDate = new Date(page.last_visit_date);
                                var daysSinceLastVisit = page.days_since_last_visit || 0;
                                lastVisitText = '<br><span class="last-visit-info">Last visit: ' + 
                                    lastVisitDate.toLocaleDateString() + 
                                    ' (' + daysSinceLastVisit + ' days ago)</span>';
                            }
                                
                            return '<div class="competing-page">' +
                                '<a href="' + page.url + '" target="_blank">' + page.url + '</a>' +
                                '<br>Position: ' + parseFloat(page.position).toFixed(1) +
                                '<br>Clicks: ' + parseInt(page.clicks).toLocaleString() +
                                lastVisitText +
                                inactiveText +
                                '</div>';
                        }).join('<hr class="page-separator">');
                    };
                    columnConfig.variableHeight = true;
                }
                // Special handling for numeric columns
                else if (field.includes('clicks') || 
                         field.includes('impressions') || 
                         field.includes('count')) {
                    columnConfig.sorter = "number";
                    columnConfig.formatter = function(cell) {
                        var value = cell.getValue();
                        if (value === undefined || value === null) {
                            return "0";
                        }
                        return typeof value === 'number' ? 
                            value.toLocaleString() : 
                            parseInt(value.replace(/[,%]/g, '') || '0').toLocaleString();
                    };
                }
                // Special handling for CTR columns
                else if (field.includes('ctr')) {
                    columnConfig.sorter = "number";
                    columnConfig.formatter = function(cell) {
                        var value = cell.getValue();
                        if (value === undefined || value === null) {
                            return "0%";
                        }
                        if (typeof value === 'number') {
                            return value.toFixed(2) + '%';
                        }
                        return parseFloat(value.replace(/[%,]/g, '') || '0').toFixed(2) + '%';
                    };
                }

                columns.push(columnConfig);
            });

            // Extract data from table rows
            const data = [];
            $table.find('tbody tr').each(function() {
                const rowData = {};
                $(this).find('td').each(function(index) {
                    if (columns[index]) {
                        const field = columns[index].field;
                        let value = $(this).html().trim();
                        
                        // Special handling for competing_pages - decode HTML entities
                        if (field === 'competing_pages') {
                            value = $('<div/>').html(value).text();
                        }
                        
                        rowData[field] = value;
                    }
                });
                data.push(rowData);
            });

            // Initialize Tabulator
            const table = new Tabulator(`#${tableId}`, {
                data: data,
                columns: columns,
                layout: "fitData",
                height: "auto",
                responsiveLayout: "collapse",
                variableHeight: true,
                pagination: true,
                paginationSize: 10,
                paginationSizeSelector: [10, 25, 50, 100],
                paginationElement: $controls.find('.tabulator-pagination')[0],
                movableColumns: true,
                initialSort: [{column: "total_clicks", dir: "desc"}],
                renderComplete: function() {
                    const totalRows = this.getData().length;
                    $controls.find('.tabulator-results').text(
                        `Showing ${totalRows.toLocaleString()} total results`
                    );
                    // this.redraw(true);
                }
            });

            // Add search functionality
            const $searchInput = $controls.find('.search-input');
            $searchInput.on('keyup', function() {
                const value = $(this).val();
                table.setFilter(function(data) {
                    return Object.keys(data).some(function(key) {
                        const cellValue = String(data[key]).toLowerCase();
                        const searchTerm = value.toLowerCase();
                        return cellValue.includes(searchTerm);
                    });
                });
            });

            // Add CSV export functionality
            const $exportButton = $controls.find('.export-csv-button');
            $exportButton.on('click', function() {
                // Get current filtered data
                const currentData = table.getData('active');
                
                // Ensure we have data to export
                if (!currentData || !currentData.length) {
                    alert('No data to export.');
                    return;
                }
                
                // Create CSV content
                let csv = '';
                
                // Get report name/title from section
                let reportName = 'SEO Report';
                const $section = $wrapper.closest('.report-section');
                if ($section.length) {
                    const sectionTitle = $section.find('.title h2').first().text().trim();
                    if (sectionTitle) {
                        reportName = sectionTitle;
                    }
                }
                
                // Add branding information at the top of the CSV
                const siteName = sbReportData.siteName || document.title.split('-')[0].trim();
                const siteURL = sbReportData.siteURL || window.location.hostname;
                const currentDate = new Date().toLocaleDateString();
                
                // Add branding header to CSV with proper formatting
                // Using empty cells for all columns except first to maintain CSV structure
                const emptyColumns = Array(columns.length - 1).fill('""').join(',');
                
                csv += `"SEO Booster Report - ${siteName}"` + (columns.length > 1 ? `,${emptyColumns}` : '') + '\n';
                csv += `"Report Type: ${reportName}"` + (columns.length > 1 ? `,${emptyColumns}` : '') + '\n';
                csv += `"Website: ${siteURL}"` + (columns.length > 1 ? `,${emptyColumns}` : '') + '\n';
                csv += `"Generated: ${currentDate}"` + (columns.length > 1 ? `,${emptyColumns}` : '') + '\n';
                csv += `"https://seoboosterpro.com"` + (columns.length > 1 ? `,${emptyColumns}` : '') + '\n';
                csv += (columns.length > 0 ? '"",'.repeat(columns.length).slice(0, -1) : '') + '\n'; // Empty line as separator
                
                // Add headers
                const headers = columns.map(column => '"' + column.title.replace(/"/g, '""') + '"');
                csv += headers.join(',') + '\n';
                
                // Add data rows
                currentData.forEach(row => {
                    // Check if this is competing pages data (keyword cannibalization)
                    const hasCompetingPages = row.competing_pages && (
                        typeof row.competing_pages === 'string' ||
                        Array.isArray(row.competing_pages)
                    );
                    
                    if (hasCompetingPages) {
                        // Handle competing pages - expand each page to a separate row
                        let pages = row.competing_pages;
                        
                        // Parse if it's a string
                        if (typeof pages === 'string') {
                            try {
                                pages = JSON.parse(pages);
                            } catch(e) {
                                // If parsing fails, handle as a regular row
                                pages = [];
                            }
                        }
                        
                        // If we have valid pages data, create a row for each page
                        if (Array.isArray(pages) && pages.length > 0) {
                            // For each competing page, create a new row
                            pages.forEach((page, index) => {
                                // Create a copy of the original row data without using spread operator
                                const pageRow = {};
                                for (let key in row) {
                                    if (row.hasOwnProperty(key) && key !== 'competing_pages') {
                                        pageRow[key] = row[key];
                                    }
                                }
                                
                                // Add individual page data as separate columns
                                pageRow.page_url = page.url || '';
                                pageRow.page_position = page.position || '';
                                pageRow.page_clicks = page.clicks || '';
                                pageRow.page_days_inactive = page.days_inactive || '';
                                
                                // Create the CSV row
                                const csvRow = columns.map(column => {
                                    let value;
                                    
                                    // Handle the special columns we've added
                                    if (column.field === 'competing_pages') {
                                        value = page.url || '';
                                    } else if (pageRow[column.field] !== undefined) {
                                        value = pageRow[column.field];
                                    } else {
                                        value = '';
                                    }
                                    
                                    // Remove HTML tags if present
                                    if (typeof value === 'string' && value.includes('<')) {
                                        const temp = document.createElement('div');
                                        temp.innerHTML = value;
                                        value = temp.textContent || temp.innerText || '';
                                    }
                                    
                                    // Format values as needed
                                    if (value === null || value === undefined) {
                                        value = '';
                                    }
                                    
                                    // Escape quotes and wrap in quotes
                                    return '"' + String(value).replace(/"/g, '""') + '"';
                                });
                                
                                csv += csvRow.join(',') + '\n';
                            });
                        } else {
                            // Fallback to standard row handling if pages data isn't valid
                            const csvRow = formatRowForCSV(row, columns);
                            csv += csvRow.join(',') + '\n';
                        }
                    } else {
                        // Standard row handling for non-competing pages data
                        const csvRow = formatRowForCSV(row, columns);
                        csv += csvRow.join(',') + '\n';
                    }
                });
                
                // Helper function to format row data for CSV
                function formatRowForCSV(row, columns) {
                    return columns.map(column => {
                        let value = row[column.field];
                        
                        // Remove HTML tags if present
                        if (typeof value === 'string' && value.includes('<')) {
                            const temp = document.createElement('div');
                            temp.innerHTML = value;
                            value = temp.textContent || temp.innerText || '';
                        }
                        
                        // Format values as needed
                        if (value === null || value === undefined) {
                            value = '';
                        }
                        
                        // Escape quotes and wrap in quotes
                        return '"' + String(value).replace(/"/g, '""') + '"';
                    });
                }
                
                // Create download link
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                
                // Create a link element to trigger the download
                const link = document.createElement('a');
                link.href = url;
                
                // Get table title or use default
                let fileName = 'seobooster-table-export';
                if (reportName !== 'SEO Report') {
                    fileName = reportName.toLowerCase().replace(/[^a-z0-9]/g, '-');
                }
                
                // Add domain name and date to filename
                const domain = sbReportData.siteURL || window.location.hostname.replace(/^www\./, '');
                const dateStr = new Date().toISOString().split('T')[0];
                
                // Create branded filename
                fileName = `seobooster-${domain}-${fileName}-${dateStr}`;
                
                link.setAttribute('download', `${fileName}.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });

            // Only remove original table after Tabulator is fully initialized
            table.on("tableBuilt", function() {
                $table.remove();
            });
        });
    }

    const ReportQueue = {
        queue: [],
        running: [],
        maxConcurrent: 2,
        loadingTimers: {},

        init: function(container) {
            convertWPListTables(container);
        },

        add: function(container) {
            this.updateLoadingState(container, 'waiting');
            this.queue.push(container);
            this.processQueue();
        },

        updateLoadingState: function(container, state, time = 0) {
            if (!container.querySelector('.table-loading')) {
                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'table-loading';
                container.prepend(loadingDiv);
            }
            const loadingEl = container.querySelector('.table-loading');

            switch(state) {
                case 'waiting':
                    loadingEl.innerHTML = `
                        <div class="loading-message"><span class="dashicons dashicons-clock"></span> Waiting...</div>`;
                    break;
                case 'loading':
                    loadingEl.innerHTML = `
                        <div class="loading-message"><span class="dashicons dashicons-update spin"></span> Loading data... <span class="loading-timer">0.0</span> seconds</div>`;
                    const timerEl = loadingEl.querySelector('.loading-timer');
                    const startTime = Date.now();
                    this.loadingTimers[container.dataset.tableId] = setInterval(() => {
                        const elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
                        timerEl.textContent = elapsed;
                    }, 100);
                    break;
                case 'complete':
                    clearInterval(this.loadingTimers[container.dataset.tableId]);
                    delete this.loadingTimers[container.dataset.tableId];
                    loadingEl.remove();
                    break;
            }
        },

        processQueue: function() {
            
            while (this.running.length < this.maxConcurrent && this.queue.length > 0) {
                const container = this.queue.shift();
                if (!this.running.includes(container)) {
                    this.running.push(container);
                    this.loadTableData(container);
                }
            }
        },

        complete: function(container) {
            const index = this.running.indexOf(container);
            if (index > -1) {
                this.running.splice(index, 1);
            }
            this.processQueue();
        },

        loadTableData: function(container) {
            const tableId = container.dataset.tableId;
            const startTime = performance.now();
            
            this.updateLoadingState(container, 'loading');

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sb_get_report_table',
                    nonce: sbReportData.nonce,
                    table_id: tableId
                },
                success: (response) => {
                    const endTime = performance.now();
                    const totalTime = Math.round(endTime - startTime);

                    if (response.success && response.data.html) {
                        // Update loading state and insert table
                        this.updateLoadingState(container, 'complete');
                        
                        // Insert the HTML
                        container.innerHTML = response.data.html;
                        
                        // Add performance info
                        const infoDiv = document.createElement('div');
                        infoDiv.className = 'table-info';
                        infoDiv.innerHTML = `
                            <small>
                                Loaded in ${totalTime}ms 
                                (Server: ${response.data.performance.execution_time}ms | 
                                Network: ${totalTime - response.data.performance.execution_time}ms) | 
                                Cache: ${response.data.performance.cached ? 'HIT' : 'MISS'}
                            </small>
                        `;
                        container.appendChild(infoDiv);

                        // Initialize Tabulator if available
                        if (typeof Tabulator !== 'undefined') {
                            this.init(container);
                        }

                    } else {
                        this.updateLoadingState(container, 'complete');
                        container.innerHTML = `<p class="error">${response.data && response.data.message ? response.data.message : 'Error loading data'}</p>`;
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    this.updateLoadingState(container, 'complete');
                    container.innerHTML = `
                        <p class="error">
                            ${sbReportData.strings.loadError}<br>
                            <small>${textStatus}: ${errorThrown}</small>
                        </p>`;
                },
                complete: () => {
                    // Remove from running array and process next in queue
                    const index = this.running.indexOf(container);
                    if (index > -1) {
                        this.running.splice(index, 1);
                    }

                    // Process next items in queue
                    if (this.queue.length > 0) {
                        setTimeout(() => this.processQueue(), 0);
                    }
                }
            });
        }
    };

    // Modify initializeLazyLoading to be more aggressive
    function initializeLazyLoading() {
        const options = {
            root: null,
            rootMargin: '500px',
            threshold: 0.01
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const tableContainer = entry.target;
                if (!tableContainer.dataset.queued) {
                    tableContainer.dataset.queued = 'true';
                    ReportQueue.add(tableContainer);
                    observer.unobserve(tableContainer);
                }
            });
        }, options);

        document.querySelectorAll('.table-container').forEach(container => {
            if (!container.dataset.queued) {
                observer.observe(container);
            }
        });
    }

    // Initialize lazy loading when document is ready
    $(document).ready(function() {
        initializeLazyLoading();
        
        // Handle cache clear button
        $('#clear-cache-btn').on('click', function() {
            const button = $(this);
            const originalText = button.html();
            
            // Disable button and show loading state
            button.prop('disabled', true);
            button.html('<span class="dashicons dashicons-update" style="margin-right: 5px; animation: spin 1s linear infinite;"></span>Clearing caches...');
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sb_clear_report_cache',
                    nonce: sbReportData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Show success message
                        button.removeClass('button-secondary').addClass('button-primary');
                        button.html('<span class="dashicons dashicons-yes" style="margin-right: 5px;"></span>Cache Cleared!');
                        
                        // Show notification
                        if (response.data && response.data.message) {
                            alert(response.data.message);
                        }
                        
                        // Reset button after 3 seconds
                        setTimeout(() => {
                            button.prop('disabled', false);
                            button.removeClass('button-primary').addClass('button-secondary');
                            button.html(originalText);
                        }, 3000);
                        
                    } else {
                        // Show error
                        button.html('<span class="dashicons dashicons-no" style="margin-right: 5px;"></span>Error');
                        if (response.data && response.data.message) {
                            alert('Error: ' + response.data.message);
                        }
                        
                        // Reset button after 3 seconds
                        setTimeout(() => {
                            button.prop('disabled', false);
                            button.html(originalText);
                        }, 3000);
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    // Show error
                    button.html('<span class="dashicons dashicons-no" style="margin-right: 5px;"></span>Error');
                    alert('Error clearing cache: ' + textStatus);
                    
                    // Reset button after 3 seconds
                    setTimeout(() => {
                        button.prop('disabled', false);
                        button.html(originalText);
                    }, 3000);
                }
            });
        });
    });
});