/**
 * SEO Booster - Bulk SEO Analysis Status Banner
 * Persistent top banner notification with AJAX polling for bulk SEO analysis status
 *
 * @package SEOBooster
 * @since 7.0.3
 */

(function($) {
    'use strict';
    
    // Ensure jQuery is available
    if (typeof jQuery === 'undefined') {
        return;
    }
    
    // Use jQuery instead of $ to avoid conflicts
    var $ = jQuery;

    var BulkSeoStatusBanner = {
        banner: null,
        batchId: null,
        pollInterval: null,
        autoHideTimer: null,
        isCompleted: false,
        isCancelled: false,
        lastProcessedTitle: '',
        activeAjaxRequest: null,

        /**
         * Initialize the banner system
         */
        init: function() {
            // Check if we're on the right page (post list)
            if (typeof sbBulkSeoStatus === 'undefined') {
                return; // Not on post list page or script not loaded
            }

            // Get batch ID from localized data or localStorage
            this.batchId = sbBulkSeoStatus.batch_id || localStorage.getItem('sb_bulk_seo_batch_id');

            // Check for errors first
            if (sbBulkSeoStatus.error) {
                this.showError(sbBulkSeoStatus.error);
                return;
            }

            // If we have a batch ID, verify it's still active and show banner
            if (this.batchId) {
                // Verify batch is active before showing banner
                this.verifyAndShowBanner();
            } else {
                // No batch ID - clean up localStorage if present
                localStorage.removeItem('sb_bulk_seo_batch_id');
            }
        },

        /**
         * Verify batch is active and show banner if valid
         */
        verifyAndShowBanner: function() {
            if (!this.batchId || typeof sbBulkSeoStatus === 'undefined') {
                return;
            }

            // Check status immediately to verify batch exists
            $.ajax({
                url: sbBulkSeoStatus.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_get_bulk_seo_status',
                    batch_id: this.batchId,
                    nonce: sbBulkSeoStatus.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Check if batch is cancelled or completed
                        if (response.data.cancelled || response.data.completed) {
                            // Batch is done - clean up and don't show banner
                            BulkSeoStatusBanner.cleanup();
                            return;
                        }
                        
                        // Batch is active - show banner and start processing
                        BulkSeoStatusBanner.createBanner();
                        BulkSeoStatusBanner.updateBanner(response.data);
                        
                        // Start processing posts (no polling needed - we process directly)
                        if (response.data.next_post_id && response.data.next_post_id > 0) {
                            BulkSeoStatusBanner.processNextPost(response.data.next_post_id);
                        }
                        
                        // Store in localStorage for persistence
                        localStorage.setItem('sb_bulk_seo_batch_id', BulkSeoStatusBanner.batchId);
                    } else {
                        // Batch not found or completed - clean up
                        BulkSeoStatusBanner.cleanup();
                    }
                },
                error: function() {
                    // On error, assume batch is not active - clean up
                    BulkSeoStatusBanner.cleanup();
                }
            });
        },

        /**
         * Clean up when no active process
         */
        cleanup: function() {
            // Abort any active AJAX request
            if (this.activeAjaxRequest) {
                this.activeAjaxRequest.abort();
                this.activeAjaxRequest = null;
            }
            
            this.stopPolling();
            this.hide();
            localStorage.removeItem('sb_bulk_seo_batch_id');
            this.batchId = null;
            this.isCancelled = false;
        },

        /**
         * Create the banner element
         */
        createBanner: function() {
            if (this.banner) {
                return;
            }

            var bannerHtml = '<div class="sb-bulk-seo-status-banner" id="sb-bulk-seo-status-banner">' +
                '<div class="sb-bulk-seo-status-content">' +
                '<div class="sb-bulk-seo-status-text-wrapper">' +
                '<div class="sb-bulk-seo-status-text" id="sb-bulk-seo-status-text">' + 
                (typeof sbBulkSeoStatus !== 'undefined' ? sbBulkSeoStatus.strings.processing : 'Processing...') +
                '</div>' +
                '<div class="sb-bulk-seo-status-message" id="sb-bulk-seo-status-message" style="display: none;"></div>' +
                '</div>' +
                '<div class="sb-bulk-seo-status-progress">' +
                '<div class="sb-bulk-seo-status-progress-fill" id="sb-bulk-seo-status-progress-fill" style="width: 0%;"></div>' +
                '</div>' +
                '</div>' +
                '<button type="button" class="sb-bulk-seo-status-cancel" id="sb-bulk-seo-status-cancel" style="display: none;">' +
                (typeof sbBulkSeoStatus !== 'undefined' ? sbBulkSeoStatus.strings.cancel : 'Cancel') +
                '</button>' +
                '<button type="button" class="sb-bulk-seo-status-reload" id="sb-bulk-seo-status-reload" style="display: none;">' +
                (typeof sbBulkSeoStatus !== 'undefined' ? sbBulkSeoStatus.strings.reload : 'Reload') +
                '</button>' +
                '<button type="button" class="sb-bulk-seo-status-close" id="sb-bulk-seo-status-close" aria-label="' + 
                (typeof sbBulkSeoStatus !== 'undefined' ? 'Close' : 'Dismiss') + '">&times;</button>' +
                '</div>';

            // Try to place before .wp-header-end, fallback to body prepend
            var $headerEnd = $('.wp-header-end');
            if ($headerEnd.length > 0) {
                $headerEnd.before(bannerHtml);
            } else {
                $('body').prepend(bannerHtml);
            }
            
            this.banner = $('#sb-bulk-seo-status-banner');

            // Close button handler
            $('#sb-bulk-seo-status-close').on('click', function() {
                BulkSeoStatusBanner.hide();
            });

            // Cancel button handler
            $('#sb-bulk-seo-status-cancel').on('click', function() {
                BulkSeoStatusBanner.cancelAnalysis();
            });

            // Reload button handler
            $('#sb-bulk-seo-status-reload').on('click', function() {
                window.location.reload();
            });
        },

        /**
         * Show error banner
         */
        showError: function(errorType) {
            this.createBanner();
            
            var message = '';
            if (typeof sbBulkSeoStatus !== 'undefined' && sbBulkSeoStatus.strings) {
                switch (errorType) {
                    case 'no_posts':
                        message = sbBulkSeoStatus.strings.no_posts;
                        break;
                    case 'no_valid_posts':
                        message = sbBulkSeoStatus.strings.no_valid_posts;
                        break;
                    default:
                        message = sbBulkSeoStatus.strings.unknown_error;
                }
            } else {
                message = 'An error occurred while processing bulk analysis.';
            }

            this.banner.addClass('error');
            $('#sb-bulk-seo-status-text').text(message);
            $('.sb-bulk-seo-status-progress').hide();
        },

        /**
         * Update status from server and process next post if available
         */
        updateStatus: function() {
            if (!this.batchId || typeof sbBulkSeoStatus === 'undefined') {
                this.cleanup();
                return;
            }

            // Don't update if cancelled
            if (this.isCancelled) {
                return;
            }

            $.ajax({
                url: sbBulkSeoStatus.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_get_bulk_seo_status',
                    batch_id: this.batchId,
                    nonce: sbBulkSeoStatus.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Check if batch is cancelled
                        if (response.data.cancelled) {
                            // Batch was cancelled - mark as cancelled and stop processing
                            BulkSeoStatusBanner.isCancelled = true;
                            BulkSeoStatusBanner.stopPolling();
                            BulkSeoStatusBanner.updateBanner(response.data);
                            return;
                        }
                        
                        // Check if completed - if so, update banner and let it handle reload
                        if (response.data.completed) {
                            BulkSeoStatusBanner.updateBanner(response.data);
                            return; // Don't process more posts, banner will reload page
                        }
                        
                        // Update banner with current status
                        BulkSeoStatusBanner.updateBanner(response.data);
                        
                        // Process next post if available and not cancelled
                        if (!BulkSeoStatusBanner.isCancelled && response.data.next_post_id && response.data.next_post_id > 0) {
                            BulkSeoStatusBanner.processNextPost(response.data.next_post_id);
                        }
                    } else {
                        // Batch not found or completed - clean up
                        BulkSeoStatusBanner.cleanup();
                    }
                },
                error: function() {
                    // On error, stop polling to avoid unnecessary requests
                    // User can refresh page if needed
                    BulkSeoStatusBanner.stopPolling();
                }
            });
        },

        /**
         * Process next post via AJAX
         */
        processNextPost: function(postId) {
            if (!this.batchId || typeof sbBulkSeoStatus === 'undefined' || !postId) {
                return;
            }

            // Don't process if cancelled
            if (this.isCancelled) {
                return;
            }

            // Mark as processing
            this.updateBanner({
                processing: 1,
                processed: this.banner.data('processed') || 0,
                queued: this.banner.data('queued') || 0,
                failed: this.banner.data('failed') || 0,
                remaining: (this.banner.data('queued') || 0) - (this.banner.data('processed') || 0) - (this.banner.data('failed') || 0),
                completed: false,
                cancelled: false
            });

            // Store the AJAX request so we can abort it if cancelled
            this.activeAjaxRequest = $.ajax({
                url: sbBulkSeoStatus.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_process_bulk_seo_analysis',
                    batch_id: this.batchId,
                    post_id: postId,
                    nonce: sbBulkSeoStatus.nonce
                },
                timeout: 180000, // 3 minute timeout for SEO analysis
                success: function(response) {
                    // Check if cancelled before processing response
                    if (BulkSeoStatusBanner.isCancelled) {
                        return;
                    }

                    if (response.success) {
                        // Store last processed post title if available
                        if (response.data && response.data.post_title) {
                            BulkSeoStatusBanner.lastProcessedTitle = response.data.post_title;
                        }
                        // Post analyzed successfully - update status and continue
                        BulkSeoStatusBanner.updateStatus();
                    } else {
                        // Post failed - update status and continue with next
                        BulkSeoStatusBanner.updateStatus();
                    }
                },
                error: function(xhr, status, error) {
                    // Don't continue if cancelled
                    if (BulkSeoStatusBanner.isCancelled) {
                        return;
                    }
                    // On error, continue with next post
                    BulkSeoStatusBanner.updateStatus();
                },
                complete: function() {
                    // Clear the active request reference
                    BulkSeoStatusBanner.activeAjaxRequest = null;
                }
            });
        },

        /**
         * Update banner with status data
         */
        updateBanner: function(data) {
            if (!this.banner) {
                this.createBanner();
            }

            // Show banner if hidden
            if (this.banner.hasClass('hidden')) {
                this.banner.removeClass('hidden');
            }

            var processed = data.processed || 0;
            var queued = data.queued || 0;
            var failed = data.failed || 0;
            var remaining = data.remaining || 0;
            var completed = data.completed || false;
            var lastProcessedTitle = data.last_processed_title || this.lastProcessedTitle || '';

            this.isCompleted = completed;
            
            // Update last processed title if provided
            if (data.last_processed_title) {
                this.lastProcessedTitle = data.last_processed_title;
            }

            // Store data in banner for reference
            this.banner.data('processed', processed);
            this.banner.data('queued', queued);
            this.banner.data('failed', failed);

            // Calculate percentage
            var percentage = queued > 0 ? Math.round((processed / queued) * 100) : 0;
            
            // Update progress bar
            $('#sb-bulk-seo-status-progress-fill').css('width', percentage + '%');

            // Check if cancelled
            var isCancelled = data.cancelled || false;

            // Build status text
            var statusText = '';
            if (isCancelled) {
                this.banner.removeClass('error success').addClass('cancelled');
                statusText = sbBulkSeoStatus.strings.cancelled;
                if (processed > 0) {
                    statusText += ' - ' + sbBulkSeoStatus.strings.completed_status.replace('%d', processed) + ' before cancellation';
                }
                
                // Stop polling
                this.stopPolling();
                $('#sb-bulk-seo-status-cancel').hide();
                // Hide message when cancelled
                $('#sb-bulk-seo-status-message').hide();
            } else if (completed) {
                this.banner.removeClass('error success cancelled').addClass('success');
                statusText = sbBulkSeoStatus.strings.completed_status.replace('%d', processed);
                
                if (failed > 0) {
                    statusText += ', ' + sbBulkSeoStatus.strings.failed_status.replace('%d', failed);
                }
                
                // Add last processed post title if available
                if (lastProcessedTitle) {
                    statusText += ' | ' + sbBulkSeoStatus.strings.last_processed.replace('%s', lastProcessedTitle);
                }

                // Stop polling
                this.stopPolling();
                // Hide progress bar and cancel button, show reload button
                $('.sb-bulk-seo-status-progress').hide();
                $('#sb-bulk-seo-status-cancel').hide();
                $('#sb-bulk-seo-status-reload').show();
                
                // Show helpful message with link to SEO Possibilities
                var messageHtml = '';
                if (typeof sbBulkSeoStatus !== 'undefined' && sbBulkSeoStatus.strings) {
                    var seoPossibilitiesUrl = (typeof sbBulkSeoStatus !== 'undefined' && sbBulkSeoStatus.seo_possibilities_url) 
                        ? sbBulkSeoStatus.seo_possibilities_url 
                        : '';
                    
                    if (seoPossibilitiesUrl) {
                        messageHtml = sbBulkSeoStatus.strings.completed_message
                            .replace('%1$s', '<a href="' + seoPossibilitiesUrl + '" class="sb-bulk-seo-status-link">' + sbBulkSeoStatus.strings.seo_possibilities_link + '</a>');
                    } else {
                        messageHtml = sbBulkSeoStatus.strings.completed_message_simple;
                    }
                } else {
                    messageHtml = 'Click the Reload button to see updated scores, or go to SEO Possibilities to see an updated list.';
                }
                
                $('#sb-bulk-seo-status-message').html(messageHtml).show();
            } else {
                this.banner.removeClass('error success cancelled');
                statusText = sbBulkSeoStatus.strings.processing_status
                    .replace('%d', processed)
                    .replace('%d', queued);

                if (failed > 0) {
                    statusText += ', ' + sbBulkSeoStatus.strings.failed_status.replace('%d', failed);
                }

                if (remaining > 0) {
                    statusText += ', ' + sbBulkSeoStatus.strings.remaining_status.replace('%d', remaining);
                }
                
                // Add last processed post title if available
                if (lastProcessedTitle) {
                    statusText += ' | ' + sbBulkSeoStatus.strings.last_processed.replace('%s', lastProcessedTitle);
                }

                // Show cancel button if there are remaining jobs
                if (remaining > 0) {
                    $('#sb-bulk-seo-status-cancel').show();
                } else {
                    $('#sb-bulk-seo-status-cancel').hide();
                }
                
                // Hide message when processing
                $('#sb-bulk-seo-status-message').hide();
            }

            $('#sb-bulk-seo-status-text').text(statusText);

            // Clean up localStorage if completed
            if (completed) {
                localStorage.removeItem('sb_bulk_seo_batch_id');
            }
        },

        /**
         * Start polling for status updates (fallback if processing stops)
         */
        startPolling: function() {
            if (this.pollInterval) {
                return; // Already polling
            }

            // Only poll if we have a batch ID and banner is visible
            if (!this.batchId || !this.banner || !this.banner.is(':visible')) {
                return;
            }

            var pollIntervalMs = (typeof sbBulkSeoStatus !== 'undefined' && sbBulkSeoStatus.poll_interval) 
                ? sbBulkSeoStatus.poll_interval 
                : 30000; // Default 30 seconds

            this.pollInterval = setInterval(function() {
                // Only continue polling if:
                // 1. Not completed
                // 2. Banner is visible
                // 3. We have a batch ID
                if (!BulkSeoStatusBanner.isCompleted && 
                    BulkSeoStatusBanner.banner && 
                    BulkSeoStatusBanner.banner.is(':visible') &&
                    BulkSeoStatusBanner.batchId) {
                    // Check status and resume processing if needed
                    BulkSeoStatusBanner.updateStatus();
                } else {
                    BulkSeoStatusBanner.stopPolling();
                }
            }, pollIntervalMs);
        },

        /**
         * Stop polling
         */
        stopPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        /**
         * Cancel bulk analysis
         */
        cancelAnalysis: function() {
            if (!this.batchId || typeof sbBulkSeoStatus === 'undefined') {
                return;
            }

            // Mark as cancelled immediately to stop any new processing
            this.isCancelled = true;

            // Abort any active AJAX request
            if (this.activeAjaxRequest) {
                this.activeAjaxRequest.abort();
                this.activeAjaxRequest = null;
            }

            // Stop polling
            this.stopPolling();

            // Disable cancel button
            var $cancelBtn = $('#sb-bulk-seo-status-cancel');
            $cancelBtn.prop('disabled', true).text(sbBulkSeoStatus.strings.cancelling);

            $.ajax({
                url: sbBulkSeoStatus.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_cancel_bulk_seo_analysis',
                    batch_id: this.batchId,
                    nonce: sbBulkSeoStatus.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Clean up localStorage immediately
                        localStorage.removeItem('sb_bulk_seo_batch_id');
                        
                        // Update banner to show cancelled state
                        BulkSeoStatusBanner.updateBanner({
                            processed: BulkSeoStatusBanner.banner.data('processed') || 0,
                            queued: BulkSeoStatusBanner.banner.data('queued') || 0,
                            failed: BulkSeoStatusBanner.banner.data('failed') || 0,
                            remaining: 0,
                            completed: false,
                            cancelled: true
                        });
                        
                        // Hide banner after a brief delay to show cancellation message
                        setTimeout(function() {
                            BulkSeoStatusBanner.hide();
                        }, 1500);
                    } else {
                        // Re-enable button on error
                        BulkSeoStatusBanner.isCancelled = false;
                        $cancelBtn.prop('disabled', false).text(sbBulkSeoStatus.strings.cancel);
                        window.SBModal.alert(response.data && response.data.message ? response.data.message : 'Failed to cancel analysis', { tone: 'error' });
                    }
                },
                error: function() {
                    // Re-enable button on error
                    BulkSeoStatusBanner.isCancelled = false;
                    $cancelBtn.prop('disabled', false).text(sbBulkSeoStatus.strings.cancel);
                    window.SBModal.alert('An error occurred while cancelling analysis', { tone: 'error' });
                }
            });
        },

        /**
         * Hide the banner
         */
        hide: function() {
            this.stopPolling();
            
            // Don't clear reload timer if we're about to reload
            // Only clear if we're manually hiding (not auto-reloading)
            if (this.autoHideTimer && !this.isCompleted) {
                clearTimeout(this.autoHideTimer);
                this.autoHideTimer = null;
            }

            if (this.banner) {
                this.banner.addClass('hidden');
            }

            // Clean up localStorage
            localStorage.removeItem('sb_bulk_seo_batch_id');
        }
    };

    // Initialize when DOM is ready (WordPress jQuery compatible)
    jQuery(document).ready(function($) {
        BulkSeoStatusBanner.init();
    });

})(jQuery);

