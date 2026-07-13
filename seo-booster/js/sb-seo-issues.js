/* global sb_seo_issues,jQuery:true */
/**
 * SEO Issues Admin Page JavaScript
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */

(function($) {
    'use strict';

    window.SB_SEO_Issues = {
        isAnalyzing: false,
        initialized: false,
        timingMetrics: {
            requestTimes: [],
            lastRequestTime: null,
            startTime: null
        },

        /**
         * Initialize the SEO Issues page.
         */
        init: function() {
            if (this.initialized) {
                return;
            }
            this.bindEvents();
            this.initSearch();
            this.initialized = true;
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            var self = this;

            // Analyze remaining button
            $(document).on('click', '#sb-analyze-remaining', function(e) {
                e.preventDefault();
                self.startAnalysis();
            });

            // Stop analysis button
            $(document).on('click', '#sb-stop-analysis', function(e) {
                e.preventDefault();
                self.stopAnalysis();
            });

            // Sitewide analysis button
            $(document).on('click', '#sb-run-sitewide-analysis', function(e) {
                e.preventDefault();
                self.runSitewideAnalysis();
            });

            // Modal close
            $(document).on('click', '.sb-issues-modal-close, #sb-cancel-analysis', function(e) {
                e.preventDefault();
                self.closeModal();
            });


            // Click outside modal to close
            $(document).on('click', '.sb-issues-modal-overlay', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            // Stats cards toggle
            $(document).on('click', '.sb-stat-card', function() {
                $(this).toggleClass('sb-expanded');
            });

            // URL expansion
            $(document).on('click', '.sb-expand-url, .sb-url-expand', function(e) {
                e.preventDefault();
                var urlId = $(this).data('url-id');
                if (window.SB_SEO_Issues.expandUrlIssues) {
                    window.SB_SEO_Issues.expandUrlIssues(urlId);
                }
            });

            // URL row click to expand
            $(document).on('click', 'tr.sb-url-row', function(e) {
                // Don't expand if clicking on links or buttons
                if ($(e.target).is('a, button, input, select')) {
                    return;
                }
                var urlId = $(this).data('url-id');
                if (window.SB_SEO_Issues.expandUrlIssues) {
                    window.SB_SEO_Issues.expandUrlIssues(urlId);
                }
            });

            // Collapsible sections (metabox style)
            $(document).on('click', '.sb-collapsible-header', function() {
                var $header = $(this);
                var $content = $header.next('.sb-collapsible-content');
                var $icon = $header.find('.sb-collapse-icon');
                
                if ($content.is(':visible')) {
                    $content.slideUp();
                    $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                } else {
                    $content.slideDown();
                    $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                }
            });
        },

        /**
         * Initialize search functionality.
         */
        initSearch: function() {
            var self = this;
            var searchTimeout;

            // Real-time search with debouncing
            $(document).on('input', '#search-input', function() {
                clearTimeout(searchTimeout);
                var searchTerm = $(this).val();

                searchTimeout = setTimeout(function() {
                    if (searchTerm.length >= 2 || searchTerm.length === 0) {
                        self.performSearch(searchTerm);
                    }
                }, 300);
            });

            // Filter dropdowns
            $(document).on('change', '#filter-severity, #filter-status', function() {
                self.applyFilters();
            });
        },

        /**
         * Start analyzing remaining content.
         */
        startAnalysis: function() {
            var self = this;

            if (this.isAnalyzing) {
                return;
            }
            this.isAnalyzing = true;
            
            // Update button states
            $('#sb-analyze-remaining').prop('disabled', true);
            $('#sb-stop-analysis').prop('disabled', false);
            
            this.showInlineProgress();

            // Update timing metrics
            this.timingMetrics.lastRequestTime = Date.now();
            if (!this.timingMetrics.startTime) {
                this.timingMetrics.startTime = Date.now();
            }

            // Disable button and show stop button
            $('#sb-analyze-remaining').prop('disabled', true).text('Analyzing...');
            $('#sb-stop-analysis').show();

            // Show immediate feedback that analysis is starting
            self.showImmediateFeedback();

            // Start immediate analysis
            $.ajax({
                url: sb_seo_issues.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sb_analyze_direct',
                    nonce: sb_seo_issues.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update timing metrics
                        var requestDuration = Date.now() - self.timingMetrics.lastRequestTime;
                        self.timingMetrics.requestTimes.push(requestDuration);

                        // Keep only last 10 requests for average
                        if (self.timingMetrics.requestTimes.length > 10) {
                            self.timingMetrics.requestTimes.shift();
                        }

                        // Calculate average and estimated time
                        var avgTime = self.timingMetrics.requestTimes.reduce(function(a, b) { return a + b; }, 0) / self.timingMetrics.requestTimes.length;
                        var estimatedRemaining = (response.data.remaining * avgTime) / 1000; // in seconds

                        // Update UI with metrics
                        self.updateTimingDisplay(avgTime, estimatedRemaining);

                        // Update current URL display with more details
                        self.updateCurrentUrlDisplay(response.data.current_url, response.data.current_title, response.data);

                        self.updateProgress(response.data);
                        
                        // Continue if more URLs remaining and not stopped
                        if (response.data.remaining > 0 && self.isAnalyzing) {
                            // Update timing metrics for next request
                            self.timingMetrics.lastRequestTime = Date.now();
                            
                            // Wait 1 second after response before sending next request
                            setTimeout(function() {
                                // Check again if still analyzing (user might have stopped it)
                                if (self.isAnalyzing) {
                                    self.continueAnalysis();
                                }
                            }, 1000); // 1 second delay after response
                        } else {
                            self.completeAnalysis();
                        }
                    } else {
                        self.showError(response.data.message || 'Analysis failed');
                        self.completeAnalysis();
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error occurred: ' + error);
                    self.completeAnalysis();
                }
            });
        },

        /**
         * Continue analysis with next batch.
         */
        continueAnalysis: function() {
            var self = this;

            if (!this.isAnalyzing) {
                return;
            }
            
            // Update timing metrics for next request
            this.timingMetrics.lastRequestTime = Date.now();
            
            // Continue with next batch
            $.ajax({
                url: sb_seo_issues.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sb_analyze_direct',
                    nonce: sb_seo_issues.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update timing metrics
                        var requestDuration = Date.now() - self.timingMetrics.lastRequestTime;
                        self.timingMetrics.requestTimes.push(requestDuration);

                        // Keep only last 10 requests for average
                        if (self.timingMetrics.requestTimes.length > 10) {
                            self.timingMetrics.requestTimes.shift();
                        }

                        // Calculate average and estimated time
                        var avgTime = self.timingMetrics.requestTimes.reduce(function(a, b) { return a + b; }, 0) / self.timingMetrics.requestTimes.length;
                        var estimatedRemaining = (response.data.remaining * avgTime) / 1000; // in seconds

                        // Update UI with metrics
                        self.updateTimingDisplay(avgTime, estimatedRemaining);

                        // Update current URL display with more details
                        self.updateCurrentUrlDisplay(response.data.current_url, response.data.current_title, response.data);

                        self.updateProgress(response.data);
                        
                        // Continue if more URLs remaining and not stopped
                        if (response.data.remaining > 0 && self.isAnalyzing) {
                            // Update timing metrics for next request
                            self.timingMetrics.lastRequestTime = Date.now();
                            
                            // Wait 1 second after response before sending next request
                            setTimeout(function() {
                                // Check again if still analyzing (user might have stopped it)
                                if (self.isAnalyzing) {
                                    self.continueAnalysis();
                                }
                            }, 1000); // 1 second delay after response
                        } else {
                            self.completeAnalysis();
                        }
                    } else {
                        self.showError(response.data.message || 'Analysis failed');
                        // Retry after 2 seconds on failure
                        setTimeout(function() {
                            if (self.isAnalyzing) {
                                self.continueAnalysis();
                            }
                        }, 2000);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error occurred: ' + error);
                    // Retry after 3 seconds on network error
                    setTimeout(function() {
                        if (self.isAnalyzing) {
                            self.continueAnalysis();
                        }
                    }, 3000);
                }
            });
        },

        /**
         * Show the progress modal.
         */
        showModal: function() {
            $('#sb-progress-modal').fadeIn(300);
            $('body').addClass('sb-issues-modal-open');
        },

        /**
         * Show inline progress display.
         */
        showInlineProgress: function() {
            var $currentDiv = $('#sb-current-analysis');
            var $stopButton = $('#sb-stop-analysis');
            
            // Hide modal if it's showing
            $('#sb-progress-modal').hide();
            $('body').removeClass('sb-issues-modal-open');
            
            // Show initial progress display
            var $content = $('<div class="sb-inline-progress">');
            $content.append('<div class="sb-progress-header">');
            $content.append('<strong>Analyzing content...</strong>');
            $content.append('</div>');
            
            $content.append('<div class="sb-progress-bar-container">');
            $content.append('<div class="sb-progress-bar">');
            $content.append('<div class="sb-progress-fill" id="sb-progress-fill-inline" style="width: 0%;"></div>');
            $content.append('</div>');
            $content.append('<div class="sb-progress-text" id="sb-progress-text-inline">0%</div>');
            $content.append('</div>');
            
            var $statsContainer = $('<div class="sb-progress-stats"></div>');
            $statsContainer.append('<div class="sb-stat-item"><span class="sb-stat-label">Analyzed:</span><span class="sb-stat-value" id="sb-analyzed-count-inline">0</span></div>');
            $statsContainer.append('<div class="sb-stat-item"><span class="sb-stat-label">Remaining:</span><span class="sb-stat-value" id="sb-remaining-count-inline">0</span></div>');
            $statsContainer.append('<div class="sb-stat-item"><span class="sb-stat-label">Possibilities:</span><span class="sb-stat-value" id="sb-issues-count-inline">0</span></div>');
            $statsContainer.append('<div class="sb-stat-item"><span class="sb-stat-label">Critical:</span><span class="sb-stat-value" id="sb-critical-count-inline">0</span></div>');
            $statsContainer.append('<div class="sb-stat-item"><span class="sb-stat-label">High:</span><span class="sb-stat-value" id="sb-high-count-inline">0</span></div>');
            $content.append($statsContainer);
            
            $content.append('<div class="sb-current-url-display" id="sb-current-url-display">');
            $content.append('<small>Starting analysis...</small>');
            $content.append('</div>');
            
            $content.append('<div class="sb-recent-analysis" id="sb-recent-analysis-inline">');
            $content.append('<h4>Recently Analyzed:</h4>');
            $content.append('<ul id="sb-recent-list"></ul>');
            $content.append('</div>');
            
            $currentDiv.html($content).show();
            
            // Show stop button
            if ($stopButton.length > 0) {
                $stopButton.css('display', 'inline-block');
            }
            
        },

        /**
         * Close the progress modal.
         */
        closeModal: function() {
            $('#sb-progress-modal').fadeOut(300);
            $('body').removeClass('sb-issues-modal-open');
            this.stopProgressTracking();
        },

        /**
         * Run analysis in background.
         */
        runInBackground: function() {
            // Close modal if it's open
            this.closeModal();
            
            // Show inline background notice
            var $currentDiv = $('#sb-current-analysis');
            if ($currentDiv.hasClass('sb-inline-progress')) {
                var $content = $('<div class="sb-background-analysis">');
                $content.append('<div class="notice notice-info">');
                $content.append('<p><strong>Analysis is running in the background.</strong> The page will update automatically.</p>');
                $content.append('</div>');
                $currentDiv.html($content);
            } else {
                this.showNotice('Analysis is running in the background. The page will update automatically.', 'info');
            }
        },

        /**
         * Start progress tracking.
         */
        startProgressTracking: function() {
            // No longer needed - we use sequential processing
        },

        /**
         * Stop progress tracking.
         */
        stopProgressTracking: function() {
            // No longer needed - we use sequential processing
        },

        /**
         * Update progress display.
         */
        updateProgress: function(data) {
            // Clamp percentage between 0 and 100
            var percentage = Math.max(0, Math.min(100, data.progress_percentage || 0));
            
            // Update progress bar (both modal and inline)
            $('#sb-progress-fill').css('width', percentage + '%');
            $('#sb-progress-text').text(Math.round(percentage) + '%');
            $('#sb-progress-fill-inline').css('width', percentage + '%');
            $('#sb-progress-text-inline').text(Math.round(percentage) + '%');
            
            // Update stats (both modal and inline)
            $('#sb-analyzed-count').text(data.analyzed || 0);
            $('#sb-remaining-count').text(data.remaining || 0);
            $('#sb-issues-count').text((data.stats && data.stats.total_issues) || 0);
            $('#sb-analyzed-count-inline').text(data.analyzed || 0);
            $('#sb-remaining-count-inline').text(data.remaining || 0);
            $('#sb-issues-count-inline').text((data.stats && data.stats.total_issues) || 0);
            $('#sb-critical-count-inline').text((data.stats && data.stats.critical) || 0);
            $('#sb-high-count-inline').text((data.stats && data.stats.high) || 0);
            
            // Update current analysis display
            if (data.current_analysis) {
                this.updateCurrentAnalysis(data.current_analysis, data.pending_count, data.is_running);
            } else if (data.current_url) {
                // Use current_url and current_title if available
                this.updateCurrentUrlDisplay(data.current_url, data.current_title, data);
            }
            
            // Update recent analysis list
            this.updateRecentAnalysis(data.recent_analysis || []);
            
            // Update stats cards
            if (data.stats) {
                this.updateStatsCards(data.stats);
            }
        },

        /**
         * Update current analysis display.
         */
        updateCurrentAnalysis: function(currentAnalysis, pendingCount, isRunning) {
            var $currentDiv = $('#sb-current-analysis');
            var $stopButton = $('#sb-stop-analysis');
            var $urlDisplay = $('#sb-current-url-display');
            
            // Check if analysis is actually running by looking at the button state
            var isActuallyRunning = $('#sb-analyze-remaining').text().trim() === 'Analyzing...';
            
            // Only hide stop button if analysis is truly not running
            if (!isActuallyRunning && (!currentAnalysis || !isRunning)) {
                if ($currentDiv.hasClass('sb-inline-progress')) {
                    // Hide inline progress but keep the div for future use
                    $currentDiv.hide();
                } else {
                    $currentDiv.hide();
                }
                $stopButton.css('display', 'none');
                return;
            }
            
            // Ensure stop button is visible during analysis
            if (isActuallyRunning) {
                $stopButton.css('display', 'inline-block');
            }
            
            // Handle case where currentAnalysis is undefined or null
            if (!currentAnalysis) {
                return;
            }
            
            var title = (currentAnalysis.object_id) ? 
                (currentAnalysis.object_type === 'post' ? 'Post' : 'Term') + ' ID: ' + currentAnalysis.object_id :
                'URL Analysis';
            
            // Update inline display if it exists
            if ($urlDisplay.length) {
                var displayText = '<strong>Analyzing URL:</strong><br>';
                displayText += '<span class="sb-current-url">' + (currentAnalysis.url || '') + '</span><br>';
                if (currentAnalysis.started_at) {
                    displayText += '<small>' + title + ' • Started ' + this.timeAgo(currentAnalysis.started_at) + '</small>';
                } else {
                    displayText += '<small>' + title + '</small>';
                }
                
                if (pendingCount > 0) {
                    displayText += '<br><small class="sb-pending-count">' + pendingCount + ' more URLs in queue</small>';
                }
                
                $urlDisplay.html(displayText);
            } else {
                // Fallback to old modal-style display
                var $content = $('<div class="sb-current-analysis-content">');
                $content.append('<strong>Analyzing URL:</strong><br>');
                $content.append('<span class="sb-current-url">' + (currentAnalysis.url || '') + '</span><br>');
                if (currentAnalysis.started_at) {
                    $content.append('<small>' + title + ' • Started ' + this.timeAgo(currentAnalysis.started_at) + '</small>');
                } else {
                    $content.append('<small>' + title + '</small>');
                }
                
                if (pendingCount > 0) {
                    $content.append('<br><small class="sb-pending-count">' + pendingCount + ' more URLs in queue</small>');
                }
                
                $currentDiv.html($content).show();
            }
            
            $stopButton.css('display', 'inline-block');
        },

        /**
         * Update recent analysis list.
         */
        updateRecentAnalysis: function(recentItems) {
            var $list = $('#sb-recent-list');
            $list.empty();
            
            if (recentItems.length === 0) {
                $list.append('<li>' + sb_seo_issues.strings.no_recent + '</li>');
                return;
            }
            
            recentItems.forEach(function(item) {
                var $li = $('<li>');
                $li.append('<strong>' + item.title + '</strong>');
                $li.append('<br><small>' + item.time + '</small>');
                $list.append($li);
            });
        },

        /**
         * Update stats cards.
         */
        updateStatsCards: function(stats) {
            $('.sb-stat-total .sb-stat-number').text(this.formatNumber(stats.total_issues));
            $('.sb-stat-critical .sb-stat-number').text(this.formatNumber(stats.critical));
            $('.sb-stat-high .sb-stat-number').text(this.formatNumber(stats.high));
            $('.sb-stat-medium .sb-stat-number').text(this.formatNumber(stats.medium));
        },


        /**
         * Complete analysis.
         */
        completeAnalysis: function() {
            this.isAnalyzing = false;
            
            // Reset button states
            $('#sb-analyze-remaining').prop('disabled', false).text('Analyze Remaining');
            $('#sb-stop-analysis').hide();
            
            // Update inline display to show completion
            var $currentDiv = $('#sb-current-analysis');
            var $content = $('<div class="sb-analysis-complete">');
            $content.append('<div class="notice notice-success">');
            $content.append('<p><strong>Analysis completed!</strong> All content has been analyzed.</p>');
            $content.append('</div>');
            $currentDiv.html($content);
            
            setTimeout(function() {
                // Reload the page to show updated data
                window.location.reload();
            }, 3000);
        },

        /**
         * Perform search.
         */
        performSearch: function(searchTerm) {
            var self = this;

            $.ajax({
                url: sb_seo_issues.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sb_search_issues',
                    nonce: sb_seo_issues.nonce,
                    search: searchTerm
                },
                success: function(response) {
                    if (response.success) {
                        self.displaySearchResults(response.data.issues);
                    }
                }
            });
        },

        /**
         * Display search results.
         */
        displaySearchResults: function(issues) {
            // This would update the table with filtered results
            // For now, we'll just show a count
            if (issues.length === 0) {
                this.showNotice('No issues found matching your search.', 'info');
            }
        },

        /**
         * Apply filters.
         */
        applyFilters: function() {
            var severity = $('#filter-severity').val();
            var status = $('#filter-status').val();
            
            // Build URL with filters
            var url = new URL(window.location);
            url.searchParams.set('severity', severity);
            url.searchParams.set('status', status);
            
            // Reload page with filters
            window.location.href = url.toString();
        },

        /**
         * Show notice message.
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible">');
            $notice.append('<p>' + message + '</p>');
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },

        /**
         * Show error message.
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Stop analysis.
         */
        stopAnalysis: function() {
            var self = this;

            window.SBModal.confirm('Are you sure you want to stop the analysis? This will cancel all pending analysis tasks.').then(function (confirmed) {
                if (!confirmed) {
                    return;
                }

                self.isAnalyzing = false;
                $('#sb-analyze-remaining').prop('disabled', false).text('Analyze Remaining');
                $('#sb-stop-analysis').hide();
                $('#sb-current-analysis').hide();
                self.showNotice('Analysis stopped by user.', 'info');
            });
        },

        /**
         * Format number with commas.
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * Calculate time ago from timestamp.
         */
        timeAgo: function(timestamp) {
            var now = new Date();
            var time = new Date(timestamp);
            var diff = Math.floor((now - time) / 1000);
            
            if (diff < 60) return diff + ' seconds ago';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            return Math.floor(diff / 86400) + ' days ago';
        },

        /**
         * Update timing display with metrics.
         */
        updateTimingDisplay: function(avgTime, estimatedRemaining) {
            var avgSeconds = (avgTime / 1000).toFixed(1);
            var estMinutes = Math.ceil(estimatedRemaining / 60);
            
            var $timingDiv = $('#sb-timing-metrics');
            if ($timingDiv.length === 0) {
                // Create timing display if it doesn't exist
                $timingDiv = $('<div id="sb-timing-metrics" style="margin-top: 10px; color: #666; font-size: 12px;"></div>');
                $('#sb-current-analysis').append($timingDiv);
            }
            
            $timingDiv.html(
                '<small>Avg: ' + avgSeconds + 's per request | ' +
                'Est. remaining: ' + estMinutes + ' min</small>'
            );
        },

        /**
         * Show immediate feedback when analysis starts.
         */
        showImmediateFeedback: function() {
            var $urlDisplay = $('#sb-current-url-display');
            if ($urlDisplay.length === 0) {
                // Create URL display if it doesn't exist
                $urlDisplay = $('<div class="sb-current-url-display" id="sb-current-url-display"></div>');
                $('#sb-current-analysis').append($urlDisplay);
            }
            
            var displayText = '<strong>Starting analysis...</strong><br>';
            displayText += '<small>Preparing to analyze URLs...</small>';
            
            $urlDisplay.html(displayText);
        },

        /**
         * Update current URL display.
         */
        updateCurrentUrlDisplay: function(currentUrl, currentTitle, responseData) {
            if (!currentUrl) return;
            
            
            var $urlDisplay = $('#sb-current-url-display');
            if ($urlDisplay.length === 0) {
                // Create URL display if it doesn't exist
                $urlDisplay = $('<div class="sb-current-url-display" id="sb-current-url-display"></div>');
                $('#sb-current-analysis').append($urlDisplay);
            }
            
            var displayText = '<strong>Analyzing:</strong><br>';
            displayText += '<span class="sb-current-url">' + currentUrl + '</span><br>';
            if (currentTitle) {
                displayText += '<small>Title: ' + currentTitle + '</small><br>';
            }
            
            // Add more detailed feedback
            if (responseData) {
                displayText += '<small>Status: ';
                if (responseData.processed > 0) {
                    displayText += 'Processed ' + responseData.processed + ' URL(s)';
                }
                if (responseData.stats && responseData.stats.total_issues > 0) {
                    displayText += ' | Found ' + responseData.stats.total_issues + ' issues';
                }
                if (responseData.remaining > 0) {
                    displayText += ' | ' + responseData.remaining + ' remaining';
                }
                displayText += '</small>';
            }
            
            $urlDisplay.html(displayText);
        },

        /**
         * Run sitewide SEO analysis.
         */
        runSitewideAnalysis: function() {
            var self = this;
            var $button = $('#sb-run-sitewide-analysis');
            var $issuesList = $('#sb-sitewide-issues-list');

            // Disable button and show loading state
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + sb_seo_issues.strings.analyzing || 'Analyzing...');

            // Show loading message
            $issuesList.html('<p>' + (sb_seo_issues.strings.analyzing || 'Running sitewide analysis...') + '</p>');

            $.ajax({
                url: sb_seo_issues.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sb_run_sitewide_analysis',
                    nonce: sb_seo_issues.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update the issues list
                        var html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th style="width: 100px;">Severity</th><th>Issue</th></tr></thead><tbody>';
                        
                        if (response.data.results && response.data.results.issues) {
                            response.data.results.issues.forEach(function(issue) {
                                var severityClass = 'sb-severity-' + issue.severity;
                                var severityLabel = issue.severity.charAt(0).toUpperCase() + issue.severity.slice(1);
                                html += '<tr><td><span class="' + severityClass + '" style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">' + severityLabel + '</span></td>';
                                html += '<td>' + issue.message + '</td></tr>';
                            });
                        }

                        // Add improvements
                        if (response.data.results && response.data.results.improvements) {
                            response.data.results.improvements.forEach(function(improvement) {
                                html += '<tr><td><span class="sb-severity-low" style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">Low</span></td>';
                                html += '<td>' + improvement.message + '</td></tr>';
                            });
                        }

                        // Add good practices
                        if (response.data.results && response.data.results.good) {
                            response.data.results.good.forEach(function(good) {
                                html += '<tr><td><span class="sb-severity-good" style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; background: #00a32a; color: #fff;">Good</span></td>';
                                html += '<td>' + good.message + '</td></tr>';
                            });
                        }

                        html += '</tbody></table>';
                        $issuesList.html(html);

                        // Update stats
                        if (response.data.stats) {
                            var stats = response.data.stats;
                            var statsText = stats.total_issues + ' total issues (' + stats.error + ' errors, ' + stats.warning + ' warnings, ' + stats.good + ' good practices)';
                            var $header = $('.sb-sitewide-stats .sb-collapsible-header');
                            var $icon = $header.find('.sb-collapse-icon');
                            var iconClasses = $icon.attr('class');
                            // Replace header content preserving icon
                            $header.html('<span class="' + iconClasses + '"></span> <strong>Sitewide Stats:</strong> ' + statsText);
                        }

                        // Reload the page to show updated sitewide issues from database
                        setTimeout(function() {
                            location.reload();
                        }, 1500);

                        // Show success message
                        self.showNotice(response.data.message || 'Sitewide analysis completed successfully', 'success');
                    } else {
                        self.showNotice(response.data.message || 'Sitewide analysis failed', 'error');
                        $issuesList.html('<p>' + (response.data.message || 'Analysis failed') + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    self.showNotice('Sitewide analysis failed: ' + error, 'error');
                    $issuesList.html('<p>Analysis failed. Please try again.</p>');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + (sb_seo_issues.strings.run_sitewide_analysis || 'Run Sitewide Analysis'));
                }
            });
        }
    };

    // Initialize when document is ready using WordPress standard
    jQuery(document).ready(function($) {
        window.SB_SEO_Issues.init();
    });

})(jQuery);
