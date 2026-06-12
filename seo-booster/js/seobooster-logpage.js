/* global jQuery:true, Tabulator:true, sblogdata:true */
jQuery(document).ready(function($) {
    if ($("#seobooster_tabulator.seoboosterlogpage").length) {
        if (typeof Tabulator !== 'undefined') {
            var table = new Tabulator("#seobooster_tabulator.seoboosterlogpage", {
                ajaxURL: sblogdata.ajaxurl,
                ajaxParams: function() {
                    return {
                        _nonce: sblogdata.security,
                        action: 'sb_log_table',
                        search: $("#search-input").val(),
                        page_size: table.getPageSize()
                    };
                },
                layout: "fitDataStretch",
                pagination: true,
                paginationMode: "remote",
                paginationSize: 50,
                paginationInitialPage: 1,
                paginationSizeSelector: [50, 100, 200,500],
                movableColumns: true,
                columns: [
                    {title: sblogdata.strings.timestamp, field: "logtime", sorter: "date", headerSort: true},
                    {title: sblogdata.strings.logEntry, field: "log", headerSort: false, formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const prioText = rowData.prio_text || '';
                        
                        // Replace potentially dangerous characters first
                        const escaped = value
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                            
                        // Only allow specific HTML patterns with limited attributes
                        let formatted = escaped
                            // Allow <code> tags
                            .replace(/&lt;code&gt;(.*?)&lt;\/code&gt;/g, '<code>$1</code>')
                            // Allow <span class="..."> tags
                            .replace(/&lt;span class=&quot;([a-zA-Z0-9-_\s]+)&quot;&gt;(.*?)&lt;\/span&gt;/g, '<span class="$1">$2</span>')
                            // Allow <a href="..." target="_blank"> tags
                            .replace(/&lt;a href=&quot;([^"]+)&quot; target=&quot;_blank&quot;&gt;(.*?)&lt;\/a&gt;/g, '<a href="$1" target="_blank">$2</a>')
                            // Allow <strong> tags
                            .replace(/&lt;strong&gt;(.*?)&lt;\/strong&gt;/g, '<strong>$1</strong>')
                            // Allow <em> tags
                            .replace(/&lt;em&gt;(.*?)&lt;\/em&gt;/g, '<em>$1</em>');
                        
                        // Append status label if priority text exists
                        if (prioText) {
                            const prioClass = 'prio-' + (rowData.prio || 0);
                            formatted += ' <span class="status-label ' + prioClass + '">' + prioText + '</span>';
                        }
                        
                        return formatted;
                    }
                }
                ],
                rowFormatter: function(row) {
                    // Add classes based on the priority ID of the log
                    var data = row.getData();
                    var priorityClass = "prio-" + data.prio; // Use the ID to create class name
                    row.getElement().classList.add(priorityClass);
                },
                ajaxSorting: true, // Enable server-side sorting
                ajaxFiltering: true, // Enable server-side filtering
                ajaxResponse: function(url, params, response) {
                    // Ensure the sorting and pagination work correctly with the server response
                    return {
                        last_page: response.last_page,
                        data: response.data,
                        total: response.total
                    };
                }
            });

            // Apply "button button-small" class to the pagination buttons
            table.on("tableBuilt", function(){
                $(".tabulator-paginator button").addClass("button button-small");
            });

            // Reload data on button click
            $("#refresh-button").addClass("button button-small").on("click", function() {
                table.setData();
            });

            // Trigger search on submit
            $("#search-submit").on("click", function() {
                table.setData();
            });
        } else {
        }
    }
});
