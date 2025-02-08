/* global jQuery, Tabulator, sbReportData */
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
        } else {
            console.warn(`No valid heading found for section ${index}`, {
                section: this,
                heading: $heading
            });
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
            console.error(`Section with ID "${$(this).val()}" not found.`);
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
            console.error('Tabulator library is not loaded.');
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

                // Special handling for competing pages column
                if (field === 'competing_pages') {
                    columnConfig.formatter = function(cell) {
                        var pages = cell.getValue();
                        if (!Array.isArray(pages)) {
                            try {
                                pages = JSON.parse(pages);
                            } catch(e) {
                                console.error('Failed to parse competing pages:', e);
                                return '';
                            }
                        }
                        
                        return pages.map(function(page) {
                            var inactiveText = page.days_inactive > 30 ? 
                                '<br><span class="inactive-warning">Inactive for ' + page.days_inactive + ' days</span>' : 
                                '';
                                
                            return '<div class="competing-page">' +
                                '<a href="' + page.url + '" target="_blank">' + page.url + '</a>' +
                                '<br>Position: ' + parseFloat(page.position).toFixed(1) +
                                '<br>Clicks: ' + parseInt(page.clicks).toLocaleString() +
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
                        
                        // Special handling for 404 pages data
                        if (container.dataset.tableId === '404-missing-pages') {
                            if (field === 'count') {
                                value = parseInt(value, 10) || 0;
                            } else if (field === 'last_seen' || field === 'first_seen') {
                                const date = new Date(value);
                                if (!isNaN(date)) {
                                    value = date.toLocaleString();
                                }
                            }
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
                layout: "fitColumns",
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
                    this.redraw(true);
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
                    console.error('AJAX Error:', textStatus, errorThrown);
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
    });
});