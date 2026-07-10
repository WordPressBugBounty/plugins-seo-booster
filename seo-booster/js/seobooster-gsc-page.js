/* global sbgscdata:true, uPlot, jQuery */
jQuery(document).ready(function($) {
  // Configuration
  const config = {
    batchSize: 15,           // Number of requests to batch together
    batchTimeout: 3000,      // Timeout in ms to wait for batching
    ajaxEndpoint: sbgscdata.ajaxurl, // WordPress AJAX endpoint
    nonce: sbgscdata.nonce
  };

  // State management
  const state = {
    pendingRequests: [],    // Array of pending keyword IDs
    batchTimeout: null,     // Reference to the current batch timeout
    processedElements: new Set(), // Track elements we've already processed
    globalTooltip: null,    // Single tooltip element for all charts
    inProgressRequests: new Set() // Track keyword IDs that are currently being requested
  };

  /**
   * Initialize the chart monitoring system
   */
  function init() {
    // Create a single tooltip element for all charts
    state.globalTooltip = document.createElement('div');
    state.globalTooltip.className = 'sb-uplot-tooltip';
    document.body.appendChild(state.globalTooltip);
    
    // Set up hover event listeners for placeholder elements
    setupHoverListeners();
    
    // Set up mutation observer for more efficient detection
    setupMutationObserver();
  }

  /**
   * Set up hover event listeners for placeholder elements
   */
  function setupHoverListeners() {
    // Use event delegation to handle dynamically added elements
    $(document).on('mouseenter', '.uplot-chart-placeholder', function() {
      const $placeholder = $(this);
      const keywordId = $placeholder.data('sb-kwid');
      
      // Skip if no keyword ID, already processed, or already in progress
      if (!keywordId || 
          state.processedElements.has(keywordId) || 
          state.inProgressRequests.has(keywordId)) {
        return;
      }
      
      // Mark as in progress to avoid duplicate requests
      state.inProgressRequests.add(keywordId);
      
      // Show loading state
      $placeholder.html('<div class="sb-chart-loading"><span class="spinner is-active"></span> Loading chart...</div>');
      
      // Add to pending requests
      state.pendingRequests.push({
        element: $placeholder,
        keywordId: keywordId
      });
      
      // Schedule a batch request
      scheduleBatchRequest();
    });
  }

  /**
   * Set up a mutation observer to detect when new elements are added to the DOM
   */
  function setupMutationObserver() {
    // Debounce function to limit how often we process new elements
    let debounceTimeout = null;
    
    const observer = new MutationObserver((mutations) => {
      // Clear any existing timeout
      if (debounceTimeout) {
        clearTimeout(debounceTimeout);
      }
      
      // Set a new timeout to debounce the processing
      debounceTimeout = setTimeout(() => {
        let hasNewElements = false;
        
        // Check if any of the mutations added new nodes
        mutations.forEach((mutation) => {
          if (mutation.addedNodes.length) {
            // Check if any of the added nodes are our chart elements or contain them
            for (let i = 0; i < mutation.addedNodes.length; i++) {
              const node = mutation.addedNodes[i];
              if (node.nodeType === 1) { // Element node
                if (node.classList && node.classList.contains('uplot-chart-placeholder')) {
                  hasNewElements = true;
                  break;
                }
                // Check if any children are our chart elements
                if (node.querySelector && node.querySelector('.uplot-chart-placeholder')) {
                  hasNewElements = true;
                  break;
                }
              }
            }
          }
        });
        
        if (hasNewElements) {
          // No need to process automatically - hover will trigger loading
        }
      }, 100); // Debounce for 100ms
    });
    
    // Start observing the document with the configured parameters
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }



  /**
   * Schedule a batch request with a timeout to allow for batching
   */
  function scheduleBatchRequest() {
    // Clear any existing timeout
    if (state.batchTimeout) {
      clearTimeout(state.batchTimeout);
    }
    
    // Set a new timeout
    state.batchTimeout = setTimeout(() => {
      sendBatchRequest();
    }, config.batchTimeout);
  }

  /**
   * Send a batch request for all pending keyword IDs
   */
  function sendBatchRequest() {
    // Reset the batch timeout
    state.batchTimeout = null;
    
    // If no pending requests, do nothing
    if (state.pendingRequests.length === 0) return;
    
    // Take up to batchSize requests
    const batchToProcess = state.pendingRequests.splice(0, config.batchSize);
    const keywordIds = batchToProcess.map(req => req.keywordId);
    
    // Show loading state only for elements in this batch
    batchToProcess.forEach(req => {
      req.element.html('<div class="sb-history-loading">Loading...</div>');
    });
    
    // Prepare the AJAX request
    $.ajax({
      url: config.ajaxEndpoint,
      type: 'POST',
      data: {
        action: 'sb_get_keyword_history',
        security: sbgscdata.security,
        keyword_ids: keywordIds,
        clear_cache: (window.location.search.includes('clear_cache=1') ? '1' : '0')
      },
      success: (response) => {
        if (response.success && response.data) {

          // Process each keyword's data
          Object.entries(response.data).forEach(([keywordId, data]) => {
            // Skip debug data
            if (keywordId === '_debug') return;
            
            // Find the corresponding element
            const request = batchToProcess.find(req => req.keywordId.toString() === keywordId);
            if (request) {
              // Clear the content of the div before rendering the chart
              request.element.empty();
              renderChart(request.element, data, request.keywordId);
              
              // Mark as processed and remove from in-progress
              state.processedElements.add(request.keywordId);
              state.inProgressRequests.delete(request.keywordId);
            }
          });
          
          // If there are more pending requests, schedule the next batch
          if (state.pendingRequests.length > 0) {
            scheduleBatchRequest();
          }
        } else {
          // Handle error
          batchToProcess.forEach(req => {
            req.element.html('<div class="sb-error">Error loading data</div>');
            // Remove from in-progress even on error
            state.inProgressRequests.delete(req.keywordId);
          });
          
          // If there are more pending requests, schedule the next batch
          if (state.pendingRequests.length > 0) {
            scheduleBatchRequest();
          }
        }
      },
      error: () => {
        // Handle AJAX error
        batchToProcess.forEach(req => {
          req.element.html('<div class="sb-error">Error loading data</div>');
          // Remove from in-progress even on error
          state.inProgressRequests.delete(req.keywordId);
        });
        
        // If there are more pending requests, schedule the next batch
        if (state.pendingRequests.length > 0) {
          scheduleBatchRequest();
        }
      }
    });
  }

  /**
   * Render the chart with the provided data
   * @param {jQuery} $element - The element to render the chart in
   * @param {Object} data - The chart data
   * @param {number} keywordId - The keyword ID
   */
  function renderChart($element, data, keywordId) {
    // Mark as rendered
    $element.removeClass('uplot-chart-placeholder').addClass('uplot-chart-rendered');
    
    // Check if we have data
    if (!data || data.length === 0) {
      $element.html('<div class="sb-no-data">No data available</div>');
      return;
    }
    
    // Check if this is old data (more than 30 days since last visit)
    const isOldData = data[0] && data[0].days_since_last_visit && data[0].days_since_last_visit > 30;
    const lastVisitText = isOldData && data[0].last_visit_date ? 
      `Last visit: ${Math.round(data[0].days_since_last_visit)} days ago` : '';
    
    // Get metadata about the data
    const isHistorical = data[0] && data[0].is_historical;
    const hasHistoricalData = data[0] && data[0].has_historical_data;
    const totalDays = data[0] && data[0].total_days;
    const firstDate = data[0] && data[0].first_date;
    const lastDate = data[0] && data[0].last_date;
    
    // If we just have a single data point, display its information instead of a chart
    if (data.length === 1) {
      const singlePoint = data[0];
      const date = new Date(singlePoint.date).toLocaleDateString();
      $element.html(`
        <div class="sb-single-data-point">
          <div class="sb-data-date">${date}</div>
          <div class="sb-data-metrics">
            <span class="sb-clicks" title="Clicks">
              <span class="dashicons dashicons-visibility"></span> ${singlePoint.clicks}
            </span>
            <span class="sb-impressions" title="Impressions">
              <span class="dashicons dashicons-yes-alt"></span> ${singlePoint.impressions}
            </span>
            <span class="sb-position" title="Position">
              <span class="dashicons dashicons-arrow-up-alt"></span> ${parseFloat(singlePoint.position).toFixed(1)}
            </span>
          </div>
          ${lastVisitText ? `<div class="sb-last-visit-info">${lastVisitText}</div>` : ''}
        </div>
      `);
      return;
    }

    try {
      // Extract dates and values from history
      const timestamps = data.map(item => new Date(item.date).getTime() / 1000); // Convert to timestamps 
      const impressions = data.map(item => parseInt(item.impressions, 10) || 0);
      const clicks = data.map(item => parseInt(item.clicks, 10) || 0);
      const positions = data.map(item => parseFloat(item.position) || 0);
      
      // Only show the chart if we have valid data
      if (timestamps.length < 2) {
        $element.html('<div class="sb-no-data">Insufficient data</div>' + (lastVisitText ? `<div class="sb-last-visit-info">${lastVisitText}</div>` : ''));
        return;
      }

      // Create chart container with expand/collapse functionality
      const chartContainer = document.createElement('div');
      chartContainer.className = 'sb-chart-container';
      
      // Add historical indicator if using historical data
      if (isHistorical) {
        const historicalIndicator = document.createElement('div');
        historicalIndicator.className = 'sb-historical-indicator';
        historicalIndicator.innerHTML = 'Historical';
        chartContainer.appendChild(historicalIndicator);
        chartContainer.classList.add('sb-expanded');
      }
      
      // Create chart canvas
      const chartCanvas = document.createElement('div');
      chartCanvas.className = 'sb-chart-canvas';
      chartContainer.appendChild(chartCanvas);
      
      // Create expand/collapse button if we have historical data
      if (hasHistoricalData) {
        const expandButton = document.createElement('button');
        expandButton.className = 'sb-expand-chart-btn';
        expandButton.innerHTML = isHistorical ? 'Show recent only' : 'Show full history';
        expandButton.setAttribute('data-keyword-id', keywordId);
        expandButton.setAttribute('data-is-expanded', isHistorical ? 'true' : 'false');
        
        // Add click handler for expand/collapse
        expandButton.addEventListener('click', function() {
          const keywordId = this.getAttribute('data-keyword-id');
          const isExpanded = this.getAttribute('data-is-expanded') === 'true';
          
          if (isExpanded) {
            // Collapse to recent view (120 days)
            loadRecentData($element, keywordId, this);
          } else {
            // Expand to full history
            loadFullHistory($element, keywordId, this);
          }
        });
        
        chartContainer.appendChild(expandButton);
      }
      
      // Add last visit info if it's old data
      if (lastVisitText) {
        const lastVisitDiv = document.createElement('div');
        lastVisitDiv.className = 'sb-last-visit-info';
        lastVisitDiv.innerHTML = lastVisitText;
        chartContainer.appendChild(lastVisitDiv);
      }
      
      // Replace the element content
      $element.empty().append(chartContainer);
      
      // Create the chart
      createChart(chartCanvas, timestamps, impressions, clicks, positions);
      
    } catch (e) {
      $element.html('<div class="sb-error">Chart error</div>' + (lastVisitText ? `<div class="sb-last-visit-info">${lastVisitText}</div>` : ''));
    }
  }

  /**
   * Create a uPlot chart with the given data
   */
  function createChart(canvas, timestamps, impressions, clicks, positions) {
    // Calculate if there's a significant gap (more than 7 days)
    const now = new Date().getTime() / 1000;
    const lastDataPoint = timestamps[timestamps.length - 1];
    const daysSinceLastData = (now - lastDataPoint) / (24 * 60 * 60);
    const hasSignificantGap = daysSinceLastData > 7;
    
    // Data format for uPlot - using original arrays to keep points connected
    const chartData = [
      timestamps,       // x-values (timestamps)
      impressions,      // y-values (series 1 - impressions)
      clicks,           // y-values (series 2 - clicks) 
      positions         // y-values (series 3 - positions)
    ];

    // Create chart options
    const chartOpts = {
      width: canvas.offsetWidth || 400,
      height: 50,       // height in pixels
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
          range: (u, min, max) => {
            // If there's a significant gap, extend the range to show the gap
            if (hasSignificantGap) {
              return [min, now]; // Extend to current time
            }
            return [min, max];
          }
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
    
    // Create the chart
    const chart = new uPlot(chartOpts, chartData, canvas);
    
    // Store the chart instance for potential cleanup
    $(canvas).data('chart', chart);
    
    // Add visual gap indicator if there's a significant gap
    if (hasSignificantGap) {
      addGapIndicator(canvas, chart, lastDataPoint, now, daysSinceLastData);
      // Add old data class to the container
      $(canvas).closest('.sb-chart-container').addClass('sb-old-data');
    }
    
    // Handle hover events for tooltip
    $(canvas).on('mousemove', function(e) {
      // Get cursor index
      var idx = chart.cursor.idx;
      
      // Show tooltip only if we have a valid index
      if (idx !== null) {
        var date = new Date(timestamps[idx] * 1000); // Convert timestamp to milliseconds
        var formattedDate = date.toLocaleDateString();
        var impressions = chartData[1][idx];
        var clicks = chartData[2][idx];
        var position = chartData[3][idx];
        
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
        
        // Update the global tooltip content
        state.globalTooltip.innerHTML = 
          '<div class="sb-tooltip-date">' + formattedDate + '</div>' +
          timeSinceLastData +
          '<div class="sb-tooltip-metrics">' +
            '<span class="sb-impressions">Impressions: ' + impressions + '</span>' +
            '<span class="sb-clicks">Clicks: ' + clicks + '</span>' +
            '<span class="sb-position">Position: ' + position.toFixed(1) + '</span>' +
          '</div>';
        
        // Make sure tooltip is visible
        state.globalTooltip.style.display = 'block';
        
        // Calculate tooltip position relative to the mouse position
        var tooltipX = e.pageX + 10;
        var tooltipY = e.pageY + 10;
        
        // Get tooltip dimensions after it's been populated with content
        var tooltipRect = state.globalTooltip.getBoundingClientRect();
        
        // Adjust horizontal position if tooltip would overflow the right edge
        if (tooltipX + tooltipRect.width > window.innerWidth) {
          tooltipX = e.pageX - tooltipRect.width - 10;
        }
        
        // Adjust vertical position if tooltip would overflow the bottom edge
        if (tooltipY + tooltipRect.height > window.innerHeight) {
          tooltipY = e.pageY - tooltipRect.height - 10;
        }
        
        // Set the tooltip position
        state.globalTooltip.style.left = tooltipX + 'px';
        state.globalTooltip.style.top = tooltipY + 'px';
      } else {
        // Hide tooltip if no valid index
        state.globalTooltip.style.display = 'none';
      }
    });
    
    $(canvas).on('mouseleave', function() {
      // Hide tooltip when mouse leaves the chart
      state.globalTooltip.style.display = 'none';
    });
  }

  /**
   * Add a visual gap indicator to the chart
   */
  function addGapIndicator(canvas, chart, lastDataPoint, now, daysSinceLastData) {
    // Add text indicator
    const gapIndicator = document.createElement('div');
    gapIndicator.className = 'sb-gap-indicator';
    gapIndicator.innerHTML = `Gap: ${Math.round(daysSinceLastData)} days`;
    canvas.parentNode.insertBefore(gapIndicator, canvas);
    
    // Add a visual indicator that the data is old
    const oldDataIndicator = document.createElement('div');
    oldDataIndicator.className = 'sb-old-data-indicator';
    oldDataIndicator.innerHTML = 'Old Data';
    canvas.parentNode.insertBefore(oldDataIndicator, canvas);
  }

  /**
   * Load full historical data for a keyword
   */
  function loadFullHistory($element, keywordId, button) {
    // Show loading state
    button.innerHTML = 'Loading...';
    button.disabled = true;
    
    // Make AJAX request for full history
    $.ajax({
      url: config.ajaxEndpoint,
      type: 'POST',
      data: {
        action: 'sb_get_full_keyword_history',
        keyword_id: keywordId,
        security: sbgscdata.security
      },
      beforeSend: function(xhr, settings) {
      },
      success: function(response) {
        if (response.success && response.data) {
          // Update the chart with full data
          updateChartWithData($element, response.data, true, keywordId);
          button.innerHTML = 'Show recent only';
          button.setAttribute('data-is-expanded', 'true');
        } else {
          button.innerHTML = 'Show full history';
        }
      },
      error: function(xhr, status, error) {
        button.innerHTML = 'Show full history';
      },
      complete: function() {
        button.disabled = false;
      }
    });
  }

  /**
   * Load recent data (120 days) for a keyword
   */
  function loadRecentData($element, keywordId, button) {
    // Show loading state
    button.innerHTML = 'Loading...';
    button.disabled = true;
    
    // Make AJAX request for recent data
    $.ajax({
      url: config.ajaxEndpoint,
      type: 'POST',
      data: {
        action: 'sb_get_keyword_history',
        keyword_ids: [keywordId],
        security: sbgscdata.security
      },
      success: function(response) {
        if (response.success && response.data && response.data[keywordId]) {
          // Update the chart with recent data
          updateChartWithData($element, response.data[keywordId], false, keywordId);
          button.innerHTML = 'Show full history';
          button.setAttribute('data-is-expanded', 'false');
        } else {
          button.innerHTML = 'Show recent only';
        }
      },
      error: function(xhr, status, error) {
        button.innerHTML = 'Show recent only';
      },
      complete: function() {
        button.disabled = false;
      }
    });
  }

  /**
   * Update chart with new data
   */
  function updateChartWithData($element, data, isHistorical, keywordId) {
    // Extract dates and values from history
    const timestamps = data.map(item => new Date(item.date).getTime() / 1000);
    const impressions = data.map(item => parseInt(item.impressions, 10) || 0);
    const clicks = data.map(item => parseInt(item.clicks, 10) || 0);
    const positions = data.map(item => parseFloat(item.position) || 0);
    
    // Find the chart container and canvas
    const chartContainer = $element.find('.sb-chart-container');
    const chartCanvas = chartContainer.find('.sb-chart-canvas')[0];
    
    // Update container state
    if (isHistorical) {
      chartContainer.addClass('sb-expanded');
      
      // Add historical indicator if not present
      if (chartContainer.find('.sb-historical-indicator').length === 0) {
        const historicalIndicator = $('<div class="sb-historical-indicator">Historical</div>');
        chartContainer.prepend(historicalIndicator);
      }
    } else {
      chartContainer.removeClass('sb-expanded');
      chartContainer.find('.sb-historical-indicator').remove();
    }
    
    // Clean up existing chart
    const existingChart = $(chartCanvas).data('chart');
    if (existingChart) {
      existingChart.destroy();
    }
    
    // Clear the canvas
    $(chartCanvas).empty();
    
    // Create new chart
    createChart(chartCanvas, timestamps, impressions, clicks, positions);
    
    // Check if this is old data and add appropriate styling
    const now = new Date().getTime() / 1000;
    const lastDataPoint = timestamps[timestamps.length - 1];
    const daysSinceLastData = (now - lastDataPoint) / (24 * 60 * 60);
    const hasSignificantGap = daysSinceLastData > 7;
    
    if (hasSignificantGap) {
      chartContainer.addClass('sb-old-data');
    } else {
      chartContainer.removeClass('sb-old-data');
    }
    
    // Update last visit info if needed
    const isOldData = data[0] && data[0].days_since_last_visit && data[0].days_since_last_visit > 30;
    const lastVisitText = isOldData && data[0].last_visit_date ? 
      `Last visit: ${Math.round(data[0].days_since_last_visit)} days ago` : '';
    
    // Update or add last visit info
    let lastVisitDiv = chartContainer.find('.sb-last-visit-info');
    if (lastVisitText) {
      if (lastVisitDiv.length) {
        lastVisitDiv.html(lastVisitText);
      } else {
        lastVisitDiv = $('<div class="sb-last-visit-info"></div>').html(lastVisitText);
        chartContainer.append(lastVisitDiv);
      }
    } else {
      lastVisitDiv.remove();
    }
  }

  // Initialize the system
  init();
});
