/* global sbgscdata:true, uPlot, jQuery */
jQuery(document).ready(function ($) {
  // Configuration
  const config = {
    batchSize: 6, // Keep AJAX payloads small to avoid tab memory spikes
    batchTimeout: 80, // ms — load soon after a cell becomes visible
    maxActiveCharts: 12, // Hard cap on live uPlot instances (Chrome crash guard)
    maxPoints: 90, // Downsample long histories before drawing
    ajaxEndpoint: sbgscdata.ajaxurl,
    nonce: sbgscdata.nonce
  };

  // State management
  const state = {
    pendingRequests: [],
    batchTimeout: null,
    processedElements: new Set(),
    inProgressRequests: new Set(),
    globalTooltip: null,
    dataCache: new Map(), // keywordId -> history rows
    activeCharts: new Map(), // keywordId -> { $element, chart, lastUsed }
    observer: null,
    observed: new WeakSet()
  };

  function init() {
    state.globalTooltip = document.createElement('div');
    state.globalTooltip.className = 'sb-uplot-tooltip';
    document.body.appendChild(state.globalTooltip);

    setupIntersectionObserver();
    observeExistingPlaceholders();
    setupMutationObserver();

    // Hover remains a soft fallback (e.g. keyboard/focus tools) but is no longer required.
    $(document).on('mouseenter focusin', '.uplot-chart-placeholder', function () {
      queuePlaceholder($(this));
    });
  }

  function setupIntersectionObserver() {
    if (typeof IntersectionObserver === 'undefined') {
      return;
    }

    state.observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          const el = entry.target;
          if (!el || !el.getAttribute) {
            return;
          }
          const keywordId = el.getAttribute('data-sb-kwid');
          if (!keywordId) {
            return;
          }

          if (entry.isIntersecting) {
            queuePlaceholder($(el));
            return;
          }

          // Free GPU/canvas when a chart scrolls away and we are over the cap.
          if (state.activeCharts.size >= config.maxActiveCharts) {
            unloadChart(keywordId, true);
          }
        });
      },
      {
        root: null,
        rootMargin: '120px 0px',
        threshold: 0.05
      }
    );
  }

  function observePlaceholder(el) {
    if (!el || !state.observer || state.observed.has(el)) {
      return;
    }
    state.observed.add(el);
    state.observer.observe(el);
  }

  function observeExistingPlaceholders() {
    document.querySelectorAll('.uplot-chart-placeholder[data-sb-kwid]').forEach(observePlaceholder);
  }

  function setupMutationObserver() {
    let debounceTimeout = null;
    const observer = new MutationObserver(function () {
      if (debounceTimeout) {
        clearTimeout(debounceTimeout);
      }
      debounceTimeout = setTimeout(observeExistingPlaceholders, 100);
    });

    const table = document.querySelector('.wp-list-table') || document.body;
    observer.observe(table, { childList: true, subtree: true });
  }

  function queuePlaceholder($placeholder) {
    const keywordId = String($placeholder.data('sb-kwid') || '');
    if (
      !keywordId ||
      state.processedElements.has(keywordId) ||
      state.inProgressRequests.has(keywordId)
    ) {
      touchActive(keywordId);
      return;
    }

    // Instant path: cached data, no network.
    if (state.dataCache.has(keywordId)) {
      state.inProgressRequests.add(keywordId);
      ensureChartSlot();
      renderChart($placeholder, state.dataCache.get(keywordId), keywordId);
      state.processedElements.add(keywordId);
      state.inProgressRequests.delete(keywordId);
      return;
    }

    state.inProgressRequests.add(keywordId);
    $placeholder.html(
      '<div class="sb-chart-loading"><span class="spinner is-active"></span> ' +
        escapeHtml(sbgscdata.strings && sbgscdata.strings.loadingChart ? sbgscdata.strings.loadingChart : 'Loading chart…') +
        '</div>'
    );

    state.pendingRequests.push({
      element: $placeholder,
      keywordId: keywordId
    });

    scheduleBatchRequest();
  }

  function scheduleBatchRequest() {
    if (state.batchTimeout) {
      clearTimeout(state.batchTimeout);
    }
    state.batchTimeout = setTimeout(sendBatchRequest, config.batchTimeout);
  }

  function sendBatchRequest() {
    state.batchTimeout = null;
    if (state.pendingRequests.length === 0) {
      return;
    }

    const batchToProcess = state.pendingRequests.splice(0, config.batchSize);
    const keywordIds = batchToProcess.map(function (req) {
      return req.keywordId;
    });

    batchToProcess.forEach(function (req) {
      req.element.html('<div class="sb-history-loading">Loading…</div>');
    });

    $.ajax({
      url: config.ajaxEndpoint,
      type: 'POST',
      data: {
        action: 'sb_get_keyword_history',
        security: sbgscdata.security,
        keyword_ids: keywordIds,
        clear_cache: window.location.search.includes('clear_cache=1') ? '1' : '0'
      },
      success: function (response) {
        if (response && response.success && response.data) {
          Object.entries(response.data).forEach(function (entry) {
            const keywordId = String(entry[0]);
            const data = entry[1];
            if (keywordId === '_debug') {
              return;
            }
            state.dataCache.set(keywordId, data);
            const request = batchToProcess.find(function (req) {
              return String(req.keywordId) === keywordId;
            });
            if (request) {
              ensureChartSlot();
              request.element.empty();
              renderChart(request.element, data, keywordId);
              state.processedElements.add(keywordId);
              state.inProgressRequests.delete(keywordId);
            }
          });
        } else {
          failBatch(batchToProcess);
        }

        if (state.pendingRequests.length > 0) {
          scheduleBatchRequest();
        }
      },
      error: function () {
        failBatch(batchToProcess);
        if (state.pendingRequests.length > 0) {
          scheduleBatchRequest();
        }
      }
    });
  }

  function failBatch(batchToProcess) {
    batchToProcess.forEach(function (req) {
      req.element.html('<div class="sb-error">Error loading data</div>');
      state.inProgressRequests.delete(req.keywordId);
    });
  }

  function ensureChartSlot() {
    while (state.activeCharts.size >= config.maxActiveCharts) {
      const victimId = pickUnloadCandidate();
      if (!victimId) {
        break;
      }
      unloadChart(victimId, true);
    }
  }

  function pickUnloadCandidate() {
    let oldestId = null;
    let oldestTime = Infinity;
    state.activeCharts.forEach(function (meta, id) {
      const el = meta.$element && meta.$element[0];
      const visible = el && isElementRoughlyVisible(el);
      if (visible) {
        return;
      }
      if (meta.lastUsed < oldestTime) {
        oldestTime = meta.lastUsed;
        oldestId = id;
      }
    });
    if (oldestId) {
      return oldestId;
    }
    // Fall back to least-recently used even if visible (hard memory guard).
    state.activeCharts.forEach(function (meta, id) {
      if (meta.lastUsed < oldestTime) {
        oldestTime = meta.lastUsed;
        oldestId = id;
      }
    });
    return oldestId;
  }

  function isElementRoughlyVisible(el) {
    const rect = el.getBoundingClientRect();
    return rect.bottom > 0 && rect.top < (window.innerHeight || 0) + 40;
  }

  function touchActive(keywordId) {
    const meta = state.activeCharts.get(String(keywordId));
    if (meta) {
      meta.lastUsed = Date.now();
    }
  }

  function unloadChart(keywordId, restorePlaceholder) {
    keywordId = String(keywordId);
    const meta = state.activeCharts.get(keywordId);
    if (!meta) {
      return;
    }

    try {
      if (meta.chart && typeof meta.chart.destroy === 'function') {
        meta.chart.destroy();
      }
    } catch (e) {
      // Soft-fail: never let cleanup kill the tab.
    }

    state.activeCharts.delete(keywordId);
    state.processedElements.delete(keywordId);

    if (restorePlaceholder && meta.$element && meta.$element.length) {
      const $el = meta.$element;
      $el
        .removeClass('uplot-chart-rendered')
        .addClass('uplot-chart-placeholder')
        .attr('data-sb-kwid', keywordId)
        .html(
          '<div class="sb-chart-placeholder">' +
            '<span class="dashicons dashicons-chart-line"></span>' +
            '<span class="sb-placeholder-text">' +
            escapeHtml(
              sbgscdata.strings && sbgscdata.strings.chartPaused
                ? sbgscdata.strings.chartPaused
                : 'Scroll to reload'
            ) +
            '</span></div>'
        );
      state.observed.delete($el[0]);
      observePlaceholder($el[0]);
    }
  }

  function registerActiveChart(keywordId, $element, chart) {
    keywordId = String(keywordId);
    const prev = state.activeCharts.get(keywordId);
    if (prev && prev.chart && prev.chart !== chart) {
      try {
        prev.chart.destroy();
      } catch (e) {
        // ignore
      }
    }
    state.activeCharts.set(keywordId, {
      $element: $element,
      chart: chart || null,
      lastUsed: Date.now()
    });
  }

  function downsampleSeries(timestamps, impressions, clicks, positions, maxPoints) {
    const len = timestamps.length;
    if (len <= maxPoints) {
      return [timestamps, impressions, clicks, positions];
    }
    const outT = [];
    const outI = [];
    const outC = [];
    const outP = [];
    const last = len - 1;
    for (let i = 0; i < maxPoints; i++) {
      const idx = i === maxPoints - 1 ? last : Math.round((i * last) / (maxPoints - 1));
      outT.push(timestamps[idx]);
      outI.push(impressions[idx]);
      outC.push(clicks[idx]);
      outP.push(positions[idx]);
    }
    return [outT, outI, outC, outP];
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  /**
   * Render the chart with the provided data
   */
  function renderChart($element, data, keywordId) {
    $element.removeClass('uplot-chart-placeholder').addClass('uplot-chart-rendered');

    if (!data || data.length === 0) {
      $element.html('<div class="sb-no-data">No data available</div>');
      registerActiveChart(keywordId, $element, null);
      return;
    }

    const isOldData = data[0] && data[0].days_since_last_visit && data[0].days_since_last_visit > 30;
    const lastVisitText =
      isOldData && data[0].last_visit_date
        ? 'Last visit: ' + Math.round(data[0].days_since_last_visit) + ' days ago'
        : '';

    const isHistorical = data[0] && data[0].is_historical;
    const hasHistoricalData = data[0] && data[0].has_historical_data;

    if (data.length === 1) {
      const singlePoint = data[0];
      const date = new Date(singlePoint.date).toLocaleDateString();
      $element.html(
        '<div class="sb-single-data-point">' +
          '<div class="sb-data-date">' +
          escapeHtml(date) +
          '</div>' +
          '<div class="sb-data-metrics">' +
          '<span class="sb-clicks" title="Clicks"><span class="dashicons dashicons-visibility"></span> ' +
          escapeHtml(singlePoint.clicks) +
          '</span>' +
          '<span class="sb-impressions" title="Impressions"><span class="dashicons dashicons-yes-alt"></span> ' +
          escapeHtml(singlePoint.impressions) +
          '</span>' +
          '<span class="sb-position" title="Position"><span class="dashicons dashicons-arrow-up-alt"></span> ' +
          escapeHtml(parseFloat(singlePoint.position).toFixed(1)) +
          '</span>' +
          '</div>' +
          (lastVisitText ? '<div class="sb-last-visit-info">' + escapeHtml(lastVisitText) + '</div>' : '') +
          '</div>'
      );
      registerActiveChart(keywordId, $element, null);
      return;
    }

    try {
      let timestamps = data.map(function (item) {
        return new Date(item.date).getTime() / 1000;
      });
      let impressions = data.map(function (item) {
        return parseInt(item.impressions, 10) || 0;
      });
      let clicks = data.map(function (item) {
        return parseInt(item.clicks, 10) || 0;
      });
      let positions = data.map(function (item) {
        return parseFloat(item.position) || 0;
      });

      if (timestamps.length < 2) {
        $element.html(
          '<div class="sb-no-data">Insufficient data</div>' +
            (lastVisitText ? '<div class="sb-last-visit-info">' + escapeHtml(lastVisitText) + '</div>' : '')
        );
        registerActiveChart(keywordId, $element, null);
        return;
      }

      const downsampled = downsampleSeries(timestamps, impressions, clicks, positions, config.maxPoints);
      timestamps = downsampled[0];
      impressions = downsampled[1];
      clicks = downsampled[2];
      positions = downsampled[3];

      const chartContainer = document.createElement('div');
      chartContainer.className = 'sb-chart-container';

      if (isHistorical) {
        const historicalIndicator = document.createElement('div');
        historicalIndicator.className = 'sb-historical-indicator';
        historicalIndicator.textContent = 'Historical';
        chartContainer.appendChild(historicalIndicator);
        chartContainer.classList.add('sb-expanded');
      }

      const chartCanvas = document.createElement('div');
      chartCanvas.className = 'sb-chart-canvas';
      chartContainer.appendChild(chartCanvas);

      if (hasHistoricalData) {
        const expandButton = document.createElement('button');
        expandButton.className = 'sb-expand-chart-btn';
        expandButton.type = 'button';
        expandButton.textContent = isHistorical ? 'Show recent only' : 'Show full history';
        expandButton.setAttribute('data-keyword-id', keywordId);
        expandButton.setAttribute('data-is-expanded', isHistorical ? 'true' : 'false');
        expandButton.addEventListener('click', function () {
          const kid = this.getAttribute('data-keyword-id');
          const expanded = this.getAttribute('data-is-expanded') === 'true';
          if (expanded) {
            loadRecentData($element, kid, this);
          } else {
            loadFullHistory($element, kid, this);
          }
        });
        chartContainer.appendChild(expandButton);
      }

      if (lastVisitText) {
        const lastVisitDiv = document.createElement('div');
        lastVisitDiv.className = 'sb-last-visit-info';
        lastVisitDiv.textContent = lastVisitText;
        chartContainer.appendChild(lastVisitDiv);
      }

      $element.empty().append(chartContainer);
      const chart = createChart(chartCanvas, timestamps, impressions, clicks, positions);
      registerActiveChart(keywordId, $element, chart);
    } catch (e) {
      $element.html(
        '<div class="sb-error">Chart error</div>' +
          (lastVisitText ? '<div class="sb-last-visit-info">' + escapeHtml(lastVisitText) + '</div>' : '')
      );
      registerActiveChart(keywordId, $element, null);
    }
  }

  function createChart(canvas, timestamps, impressions, clicks, positions) {
    if (typeof uPlot === 'undefined') {
      canvas.innerHTML = '<div class="sb-error">Chart library unavailable</div>';
      return null;
    }

    const now = new Date().getTime() / 1000;
    const lastDataPoint = timestamps[timestamps.length - 1];
    const daysSinceLastData = (now - lastDataPoint) / (24 * 60 * 60);
    const hasSignificantGap = daysSinceLastData > 7;

    const chartData = [timestamps, impressions, clicks, positions];

    const chartOpts = {
      width: canvas.offsetWidth || 400,
      height: 50,
      padding: [5, 0, 0, 0],
      cursor: {
        show: true,
        points: { show: true, size: 6 },
        lock: false,
        focus: { prox: 30 }
      },
      select: { show: false },
      legend: { show: false },
      axes: [{ show: false }, { show: false }],
      scales: {
        x: {
          time: true,
          auto: true,
          range: function (u, min, max) {
            if (hasSignificantGap) {
              return [min, now];
            }
            return [min, max];
          }
        },
        y: {
          auto: true,
          range: function (u, min, max) {
            const padding = (max - min) * 0.1 || 1;
            return [min - padding, max + padding];
          }
        },
        position: {
          auto: true,
          range: function (u, min, max) {
            const padding = (max - min) * 0.1 || 1;
            return [max + padding, min - padding];
          }
        }
      },
      series: [
        {},
        {
          stroke: 'rgba(24, 119, 242, 0.8)',
          width: 1,
          fill: 'rgba(24, 119, 242, 0.1)',
          paths: impressions.some(function (v) {
            return v > 0;
          })
            ? undefined
            : function () {
                return null;
              },
          points: {
            show: timestamps.length <= 40,
            size: 4,
            stroke: 'rgba(24, 119, 242, 1)',
            fill: 'rgba(24, 119, 242, 0.8)'
          }
        },
        {
          stroke: 'rgba(45, 196, 78, 0.8)',
          width: 1,
          fill: 'rgba(45, 196, 78, 0.1)',
          paths: clicks.some(function (v) {
            return v > 0;
          })
            ? undefined
            : function () {
                return null;
              },
          points: {
            show: timestamps.length <= 40,
            size: 4,
            stroke: 'rgba(45, 196, 78, 1)',
            fill: 'rgba(45, 196, 78, 0.8)'
          }
        },
        {
          stroke: 'rgba(242, 120, 24, 0.8)',
          width: 1.5,
          scale: 'position',
          points: {
            show: timestamps.length <= 40,
            size: 4,
            stroke: 'rgba(242, 120, 24, 1)',
            fill: 'rgba(242, 120, 24, 0.8)'
          }
        }
      ]
    };

    let chart;
    try {
      chart = new uPlot(chartOpts, chartData, canvas);
    } catch (e) {
      canvas.innerHTML = '<div class="sb-error">Chart error</div>';
      return null;
    }

    $(canvas).data('chart', chart);

    if (hasSignificantGap) {
      addGapIndicator(canvas, daysSinceLastData);
      $(canvas).closest('.sb-chart-container').addClass('sb-old-data');
    }

    $(canvas).on('mousemove', function (e) {
      const idx = chart.cursor.idx;
      if (idx == null) {
        state.globalTooltip.style.display = 'none';
        return;
      }

      const date = new Date(timestamps[idx] * 1000);
      const formattedDate = date.toLocaleDateString();
      const imp = chartData[1][idx];
      const clk = chartData[2][idx];
      const pos = chartData[3][idx];

      let timeSinceLastData = '';
      if (idx === timestamps.length - 1) {
        const days = Math.floor((now - timestamps[idx]) / (24 * 60 * 60));
        if (days > 0) {
          timeSinceLastData =
            '<div class="sb-time-since">' + days + ' day' + (days !== 1 ? 's' : '') + ' ago</div>';
        }
      }

      state.globalTooltip.innerHTML =
        '<div class="sb-tooltip-date">' +
        escapeHtml(formattedDate) +
        '</div>' +
        timeSinceLastData +
        '<div class="sb-tooltip-metrics">' +
        '<span class="sb-impressions">Impressions: ' +
        escapeHtml(imp) +
        '</span>' +
        '<span class="sb-clicks">Clicks: ' +
        escapeHtml(clk) +
        '</span>' +
        '<span class="sb-position">Position: ' +
        escapeHtml(Number(pos).toFixed(1)) +
        '</span></div>';

      state.globalTooltip.style.display = 'block';
      let tooltipX = e.pageX + 10;
      let tooltipY = e.pageY + 10;
      const tooltipRect = state.globalTooltip.getBoundingClientRect();
      if (tooltipX + tooltipRect.width > window.innerWidth) {
        tooltipX = e.pageX - tooltipRect.width - 10;
      }
      if (tooltipY + tooltipRect.height > window.innerHeight) {
        tooltipY = e.pageY - tooltipRect.height - 10;
      }
      state.globalTooltip.style.left = tooltipX + 'px';
      state.globalTooltip.style.top = tooltipY + 'px';
    });

    $(canvas).on('mouseleave', function () {
      state.globalTooltip.style.display = 'none';
    });

    return chart;
  }

  function addGapIndicator(canvas, daysSinceLastData) {
    const gapIndicator = document.createElement('div');
    gapIndicator.className = 'sb-gap-indicator';
    gapIndicator.textContent = 'Gap: ' + Math.round(daysSinceLastData) + ' days';
    canvas.parentNode.insertBefore(gapIndicator, canvas);

    const oldDataIndicator = document.createElement('div');
    oldDataIndicator.className = 'sb-old-data-indicator';
    oldDataIndicator.textContent = 'Old Data';
    canvas.parentNode.insertBefore(oldDataIndicator, canvas);
  }

  function loadFullHistory($element, keywordId, button) {
    button.innerHTML = 'Loading...';
    button.disabled = true;

    $.ajax({
      url: config.ajaxEndpoint,
      type: 'POST',
      data: {
        action: 'sb_get_full_keyword_history',
        keyword_id: keywordId,
        security: sbgscdata.security
      },
      success: function (response) {
        if (response && response.success && response.data) {
          state.dataCache.set(String(keywordId), response.data);
          updateChartWithData($element, response.data, true, keywordId);
          button.innerHTML = 'Show recent only';
          button.setAttribute('data-is-expanded', 'true');
        } else {
          button.innerHTML = 'Show full history';
        }
      },
      error: function () {
        button.innerHTML = 'Show full history';
      },
      complete: function () {
        button.disabled = false;
      }
    });
  }

  function loadRecentData($element, keywordId, button) {
    button.innerHTML = 'Loading...';
    button.disabled = true;

    $.ajax({
      url: config.ajaxEndpoint,
      type: 'POST',
      data: {
        action: 'sb_get_keyword_history',
        keyword_ids: [keywordId],
        security: sbgscdata.security
      },
      success: function (response) {
        if (response && response.success && response.data && response.data[keywordId]) {
          state.dataCache.set(String(keywordId), response.data[keywordId]);
          updateChartWithData($element, response.data[keywordId], false, keywordId);
          button.innerHTML = 'Show full history';
          button.setAttribute('data-is-expanded', 'false');
        } else {
          button.innerHTML = 'Show recent only';
        }
      },
      error: function () {
        button.innerHTML = 'Show recent only';
      },
      complete: function () {
        button.disabled = false;
      }
    });
  }

  function updateChartWithData($element, data, isHistorical, keywordId) {
    const timestampsRaw = data.map(function (item) {
      return new Date(item.date).getTime() / 1000;
    });
    const impressionsRaw = data.map(function (item) {
      return parseInt(item.impressions, 10) || 0;
    });
    const clicksRaw = data.map(function (item) {
      return parseInt(item.clicks, 10) || 0;
    });
    const positionsRaw = data.map(function (item) {
      return parseFloat(item.position) || 0;
    });

    const downsampled = downsampleSeries(
      timestampsRaw,
      impressionsRaw,
      clicksRaw,
      positionsRaw,
      config.maxPoints
    );
    const timestamps = downsampled[0];
    const impressions = downsampled[1];
    const clicks = downsampled[2];
    const positions = downsampled[3];

    const chartContainer = $element.find('.sb-chart-container');
    const chartCanvas = chartContainer.find('.sb-chart-canvas')[0];
    if (!chartCanvas) {
      renderChart($element, data, keywordId);
      return;
    }

    if (isHistorical) {
      chartContainer.addClass('sb-expanded');
      if (chartContainer.find('.sb-historical-indicator').length === 0) {
        chartContainer.prepend('<div class="sb-historical-indicator">Historical</div>');
      }
    } else {
      chartContainer.removeClass('sb-expanded');
      chartContainer.find('.sb-historical-indicator').remove();
    }

    const existingChart = $(chartCanvas).data('chart');
    if (existingChart && typeof existingChart.destroy === 'function') {
      try {
        existingChart.destroy();
      } catch (e) {
        // soft-fail
      }
    }
    $(chartCanvas).empty();

    const chart = createChart(chartCanvas, timestamps, impressions, clicks, positions);
    registerActiveChart(keywordId, $element, chart);

    const now = new Date().getTime() / 1000;
    const lastDataPoint = timestamps[timestamps.length - 1];
    const daysSinceLastData = (now - lastDataPoint) / (24 * 60 * 60);
    if (daysSinceLastData > 7) {
      chartContainer.addClass('sb-old-data');
    } else {
      chartContainer.removeClass('sb-old-data');
    }

    const isOldData = data[0] && data[0].days_since_last_visit && data[0].days_since_last_visit > 30;
    const lastVisitText =
      isOldData && data[0].last_visit_date
        ? 'Last visit: ' + Math.round(data[0].days_since_last_visit) + ' days ago'
        : '';

    let lastVisitDiv = chartContainer.find('.sb-last-visit-info');
    if (lastVisitText) {
      if (lastVisitDiv.length) {
        lastVisitDiv.text(lastVisitText);
      } else {
        chartContainer.append($('<div class="sb-last-visit-info"></div>').text(lastVisitText));
      }
    } else {
      lastVisitDiv.remove();
    }
  }

  init();
});
