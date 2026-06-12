/* global seobooster_adminbar:true, WinBox:true, jQuery:true, Tabulator:true, uPlot */

// Create a single global tooltip element that will be reused across all charts
const globalTooltip = document.createElement('div');
globalTooltip.className = 'sb-uplot-tooltip';
document.body.appendChild(globalTooltip);

function getUrlParameter(name) {
	name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
	var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
	var results = regex.exec(location.search);
	return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}


let winboxInstance = null;

function open_floating_window() {
	
	// Check if window exists and is actually visible
	if (winboxInstance && !winboxInstance.closed && document.querySelector('.winbox.modern')) {
		winboxInstance.focus();
	} else {
		// Just nullify the old instance without trying to close it
		winboxInstance = null;
		
		const adminBarHeight = jQuery('#wpadminbar').height() || 0;
		
		winboxInstance = new WinBox({
			title: "SEO Booster",
			class: ["modern", "no-full", "no-max"],
			x: "20px",
			y: (20 + adminBarHeight) + "px",
			width: "90%",
			height: "80%",
			top: adminBarHeight,
			background: "#1e1e1e",
			border: "1px solid #282828",
			max: false,
			modal: false,
			autosize: false,
			root: document.body,
			onclose: function() {
				// Remove the seobooster_showdetails parameter from URL and reload
				var currentUrl = window.location.href;
				var url = new URL(currentUrl);
				url.searchParams.delete('seobooster_showdetails');
				window.location.href = url.toString();
			},
			onresize: function(width, height) {
				this.window.style.transform = 'translate3d(0,0,0)';
			},
			index:999999,
			html: '<div id="seobooster-keywords-table"><div class="sb-loading"><div class="sb-spinner"></div></div></div>'
		});

		// Force window to top after creation
		if (winboxInstance && winboxInstance.window) {
			winboxInstance.focus();
			// Remove from current parent and re-append to body
			document.body.appendChild(winboxInstance.window);
			
			// Add a manual close handler as fallback
			var closeButton = winboxInstance.window.querySelector('.wb-close');
			if (closeButton) {
				closeButton.addEventListener('click', function() {
					setTimeout(function() {
						// Remove the seobooster_showdetails parameter from URL and reload
						var currentUrl = window.location.href;
						var url = new URL(currentUrl);
						url.searchParams.delete('seobooster_showdetails');
						window.location.href = url.toString();
					}, 100);
				});
			}
		}

		jQuery.ajax({
			url: seobooster_adminbar.ajax_url,
			type: 'POST',
			data: {
				action: 'sb_gsc_get_keywords',
				public_url: seobooster_adminbar.public_url,
				content_type: seobooster_adminbar.content_type,
				item_id: seobooster_adminbar.item_id,
				security: seobooster_adminbar.security
			},
			success: function(response) {
				jQuery('.sb-loading').remove();
				
				if (response && response.success === true) {
					if (response.data.status === "no_keywords") {
						// Hide the SVG in the button
						jQuery('.fl-builder-seobooster-button svg').addClass('hidden');
						
						// Create a centered message container
						const noDataMessage = `
							<div class="sb-no-data-message">
								<div class="sb-message-content">
									<svg viewBox="0 0 24 24" width="48" height="48">
										<path fill="currentColor" d="M13 2.05v3.03c3.39.49 6 3.39 6 6.92 0 .9-.18 1.75-.5 2.54l2.54 2.54c.65-1.41 1-2.99 1-4.63 0-5.18-3.95-9.45-9.04-9.95M12 19c-3.87 0-7-3.13-7-7 0-3.53 2.61-6.43 6-6.92V2.05C5.94 2.55 2 6.82 2 12c0 5.52 4.47 10 9.99 10 3.31 0 6.24-1.61 8.06-4.09l-2.6-2.6C16.17 17.64 14.21 19 12 19z"/>
									</svg>
									<h3>${response.data.message}</h3>
									<p>Last refreshed: ${new Date(response.data.last_refreshed).toLocaleString()}</p>
								</div>
							</div>`;
						jQuery('.winbox .wb-body').html(noDataMessage);
					} else if (Array.isArray(response.data.keywords)) {
						// Show the SVG in the button if it was hidden
						jQuery('.fl-builder-seobooster-button svg').removeClass('hidden');
						const keywordCount = response.data.keywords.length;
						winboxInstance.setTitle(`SEO Booster - ${keywordCount} Keywords`);
						initializeKeywordsTable(response.data.keywords);
					}
				} else {
					jQuery('.fl-builder-seobooster-button svg').addClass('hidden');
					jQuery('.winbox .wb-body').html(`<div class="keyword-details">${seobooster_adminbar.text.error}: Invalid data structure</div>`);
				}
			},
			error: function(xhr, status, error) {
				jQuery('.sb-loading').remove();
				jQuery('.winbox .wb-body').html(`<div class="keyword-details">${seobooster_adminbar.text.error}: ${error}</div>`);
			}
		});
	}
}

function initializeKeywordsTable(data) {
	// Create controls container with flex layout
	const $controls = jQuery('<div>', {
		class: 'sb-fixed-controls'
	}).append(
		jQuery('<div>', { class: 'sb-controls-wrapper' }).append(
			jQuery('<div>', { class: 'sb-search-wrapper' }).append(
				jQuery('<input>', {
					type: 'text',
					class: 'sb-search-input',
					placeholder: seobooster_adminbar.text.search || 'Search...'
				})
			),
			jQuery('<div>', { class: 'sb-pagination-wrapper' })
		)
	);

	
	jQuery('#seobooster-keywords-table').before($controls);


	// Set up event handler immediately after adding to DOM
	jQuery('.sb-fixed-controls').on('input', '.sb-search-input', function() {
		if (window.keywordsTable) {
			window.keywordsTable.setFilter("query", "like", this.value);
		}
	});

	// Add uPlot chart formatter
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
				container.innerHTML = '<div class="sb-no-data">' + (seobooster_adminbar.text.noDataAvailable || 'No data available') + '</div>';
				return;
			}
			
			// If we just have a single data point, display its information instead of a chart
			if (rowData.history.length === 1) {
				const singlePoint = rowData.history[0];
				const date = new Date(singlePoint.date).toLocaleDateString();
				container.innerHTML = `
					<div class="sb-single-data-point">
						<div class="sb-data-date">${date}</div>
						<div class="sb-data-metrics">
							<span class="sb-clicks" title="${seobooster_adminbar.text.clicks || 'Clicks'}">
								<span class="dashicons dashicons-visibility"></span> ${singlePoint.clicks}
							</span>
							<span class="sb-impressions" title="${seobooster_adminbar.text.impressions || 'Impressions'}">
								<span class="dashicons dashicons-yes-alt"></span> ${singlePoint.impressions}
							</span>
							<span class="sb-position" title="${seobooster_adminbar.text.position || 'Position'}">
								<span class="dashicons dashicons-arrow-up-alt"></span> ${parseFloat(singlePoint.position).toFixed(1)}
							</span>
						</div>
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
					container.innerHTML = '<div class="sb-no-data">' + (seobooster_adminbar.text.insufficientData || 'Not enough data points') + '</div>';
					return;
				}

				// Data format for uPlot
				const data = [
					timestamps,       // x-values (timestamps)
					impressions,      // y-values (series 1 - impressions)
					clicks,           // y-values (series 2 - clicks) 
					positions         // y-values (series 3 - positions)
				];
			
				// Create interactive chart options (with points)
				const interactiveOpts = {
					width: container.clientWidth || 220,  // Use container width or fallback to 220px
					height: 60,       // height in pixels
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
				var interactiveChart = new uPlot(interactiveOpts, data, container);
				window.currentChart = interactiveChart;
				
			} catch (error) {
				container.innerHTML = '<div class="sb-no-data">' + (seobooster_adminbar.text.chartError || 'Error creating chart') + '</div>';
			}
		});
		
		return container;
	};

	// Update column definitions
	const columns = [
		{
			title: seobooster_adminbar.text.query,
			field: "query",
			formatter: function(cell) {
				return `<div class="query-container">${cell.getValue()}</div>`;
			},
			widthGrow: 3
		},
		{
			title: seobooster_adminbar.text.impressions,
			field: "impressions",
			hozAlign: "right",
			sorter: "number",
			formatter: function(cell) {
				return parseInt(cell.getValue()) || 0;
			},
			width: 120
		},
		{
			title: seobooster_adminbar.text.clicks,
			field: "clicks",
			hozAlign: "right",
			sorter: "number",
			formatter: function(cell) {
				return parseInt(cell.getValue()) || 0;
			},
			width: 100
		},
		{
			title: seobooster_adminbar.text.ctr,
			field: "ctr",
			hozAlign: "right",
			sorter: "number",
			formatter: function(cell) {
				return parseFloat(cell.getValue()) || "0.00%";
			},
			width: 100
		},
		{
			title: seobooster_adminbar.text.avg_position,
			field: "position",
			formatter: function(cell) {
				return parseFloat(cell.getValue()).toFixed(1);
			},
			sorter: "number",
			hozAlign: "right",
			width: 100
		},
		{
			title: seobooster_adminbar.text.used,
			field: "position_details",
			formatter: function(cell) {
				return cell.getValue();
			},
			width: 120
		},
		{
			title: seobooster_adminbar.text.trends || "Trends",
			field: "curves",
			formatter: curvesFormatter, 
			headerSort: false,
			hozAlign: "center",
			width: 300,
			resizable: true
		}
	];

	// Initialize table
	window.keywordsTable = new Tabulator("#seobooster-keywords-table", {
		data: data,
		layout: "fitColumns",
		responsiveLayout: "collapse",
		responsiveLayoutCollapseStartOpen: true,
		pagination: "local",
		paginationSize: 50,
		paginationElement: $controls.find('.sb-pagination-wrapper')[0],
		columns: columns.map(function(col) {
			return {
				title: col.title,
				field: col.field,
				formatter: col.formatter,
				hozAlign: col.hozAlign,
				sorter: col.sorter,
				width: col.width,
				widthGrow: col.widthGrow,
				responsive: col.field === "query" ? 0 : 1,
				headerSort: col.headerSort !== undefined ? col.headerSort : true,
				resizable: col.resizable || false
			};
		})
	});

}

jQuery(document).ready(function () {

	// Check if the Beaver Builder is active
	if (jQuery('.fl-builder-bar-actions').length > 0) {
		var button = jQuery('.fl-builder-seobooster-button');
		// var header = jQuery('.site-header');
		button.addClass('fl-builder-button-silent');
		// remove the text inside the button
		button.text('');
		button.append(`<svg viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:bx="https://boxy-svg.com">
			<defs>
			<symbol id="symbol-0" viewBox="0 0 100 100">
			<path d="M 63.332 70.126 L 63.332 70.126 L 63.332 70.126 C 63.332 67.186 62.292 64.896 60.212 63.256 L 60.212 63.256 L 60.212 63.256 C 58.132 61.616 54.475 59.916 49.242 58.156 L 49.242 58.156 L 49.242 58.156 C 44.015 56.403 39.739 54.706 36.412 53.066 L 36.412 53.066 L 36.412 53.066 C 25.612 47.759 20.212 40.466 20.212 31.186 L 20.212 31.186 L 20.212 31.186 C 20.212 26.566 21.555 22.489 24.242 18.956 L 24.242 18.956 L 24.242 18.956 C 26.935 15.423 30.745 12.673 35.672 10.706 L 35.672 10.706 L 35.672 10.706 C 40.599 8.739 46.135 7.756 52.282 7.756 L 52.282 7.756 L 52.282 7.756 C 58.275 7.756 63.649 8.826 68.402 10.966 L 68.402 10.966 L 68.402 10.966 C 73.155 13.106 76.852 16.149 79.492 20.096 L 79.492 20.096 L 79.492 20.096 C 82.125 24.049 83.442 28.566 83.442 33.646 L 83.442 33.646 L 63.392 33.646 L 63.392 33.646 C 63.392 30.246 62.352 27.613 60.272 25.746 L 60.272 25.746 L 60.272 25.746 C 58.192 23.873 55.375 22.936 51.822 22.936 L 51.822 22.936 L 51.822 22.936 C 48.235 22.936 45.402 23.729 43.322 25.316 L 43.322 25.316 L 43.322 25.316 C 41.235 26.896 40.192 28.909 40.192 31.356 L 40.192 31.356 L 40.192 31.356 C 40.192 33.496 41.339 35.433 43.632 37.166 L 43.632 37.166 L 43.632 37.166 C 45.925 38.906 49.955 40.703 55.722 42.556 L 55.722 42.556 L 55.722 42.556 C 61.489 44.403 66.222 46.396 69.922 48.536 L 69.922 48.536 L 69.922 48.536 C 78.935 53.729 83.442 60.889 83.442 70.016 L 83.442 70.016 L 83.442 70.016 C 83.442 77.309 80.692 83.036 75.192 87.196 L 75.192 87.196 L 75.192 87.196 C 69.692 91.363 62.152 93.446 52.572 93.446 L 52.572 93.446 L 52.572 93.446 C 45.812 93.446 39.692 92.233 34.212 89.806 L 34.212 89.806 L 34.212 89.806 C 28.732 87.379 24.609 84.056 21.842 79.836 L 21.842 79.836 L 21.842 79.836 C 19.075 75.616 17.692 70.759 17.692 65.266 L 17.692 65.266 L 37.852 65.266 L 37.852 65.266 C 37.852 69.733 39.005 73.026 41.312 75.146 L 41.312 75.146 L 41.312 75.146 C 43.625 77.259 47.379 78.316 52.572 78.316 L 52.572 78.316 L 52.572 78.316 C 55.892 78.316 58.515 77.603 60.442 76.176 L 60.442 76.176 L 60.442 76.176 C 62.369 74.743 63.332 72.726 63.332 70.126 Z" transform="matrix(1, 0, 0, 1, 0, 0)" style="fill: rgb(130, 135, 140); white-space: pre;" id="s"/>
			</symbol>
			</defs>
			<use width="100" height="100" transform="matrix(4.947808, 0, 0, 4.947808, -20.354914, -11.482257)" xlink:href="#symbol-0"/>
			<path style="paint-order: stroke; fill: rgb(130, 135, 140);" d="M 349.355 16.098 C 333.687 49.355 248.938 171.838 248.938 171.838 C 248.938 171.838 228.3 199.676 236.116 203.927 C 247.584 210.168 267.795 206.135 284.389 206.805 C 309.456 207.816 329.639 205.313 341.68 205.786 C 341.68 205.786 359.942 201.1 363.11 211.672 C 365.18 218.581 354.131 230.067 354.131 230.067 L 105.339 481.212 L 213.627 310.542 C 213.627 310.542 221.796 293.779 216.787 287.127 C 210.653 278.986 186.557 281.117 186.557 281.117 C 186.557 281.117 140.259 279.657 117.109 279.939 C 108.054 280.05 99.5 279.319 99.082 272.877 C 98.532 264.365 100.711 262.353 110.047 252.866 C 188.089 173.584 349.355 16.098 349.355 16.098 Z"/>
			</svg>`);

		jQuery('.fl-builder-seobooster-button svg').css({
			'height': '20px',
			'width': '20px'
		});

		button.on('click', function (e) {
			e.preventDefault();
			open_floating_window();
			jQuery('#seobooster-floating-div').show();

		});
	}

	// Check if the GET parameter "seobooster_showdetails" is set to "true" or "1"
	var showDetailsParam = getUrlParameter('seobooster_showdetails');
	if (showDetailsParam === 'true' || showDetailsParam === '1') {
		open_floating_window();
	}

	// Close button functionality
	jQuery(document).on('click', '.seobooster-close', function () {
		jQuery('#seobooster-floating-div').hide();
	});
});

