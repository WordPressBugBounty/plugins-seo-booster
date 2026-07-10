/**
 * SEO Booster - Media Library Bulk Status Banner
 * Persistent top banner notification with AJAX polling for bulk generation status
 *
 * @package SEOBooster
 * @since 7.0.2
 */

(function($) {
    'use strict';
    
    // Ensure jQuery is available
    if (typeof jQuery === 'undefined') {
        return;
    }
    
    // Use jQuery instead of $ to avoid conflicts
    var $ = jQuery;

    var BulkStatusBanner = {
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
            // Check if we're on the right page (media library)
            if (typeof sbBulkStatus === 'undefined') {
                return; // Not on media library page or script not loaded
            }

            // Get batch ID from localized data or localStorage
            this.batchId = sbBulkStatus.batch_id || localStorage.getItem('sb_bulk_batch_id');

            // Check for errors first
            if (sbBulkStatus.error) {
                this.showError(sbBulkStatus.error);
                return;
            }

            // If we have a batch ID, verify it's still active and show banner
            if (this.batchId) {
                // Verify batch is active before showing banner
                this.verifyAndShowBanner();
            } else {
                // No batch ID - clean up localStorage if present
                localStorage.removeItem('sb_bulk_batch_id');
            }
        },

        /**
         * Verify batch is active and show banner if valid
         */
        verifyAndShowBanner: function() {
            if (!this.batchId || typeof sbBulkStatus === 'undefined') {
                return;
            }

            // Check status immediately to verify batch exists
            $.ajax({
                url: sbBulkStatus.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_get_bulk_status',
                    batch_id: this.batchId,
                    nonce: sbBulkStatus.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Check if batch is cancelled or completed
                        if (response.data.cancelled || response.data.completed) {
                            // Batch is done - clean up and don't show banner
                            BulkStatusBanner.cleanup();
                            return;
                        }
                        
                        // Batch is active - show banner and start processing
                        BulkStatusBanner.createBanner();
                        BulkStatusBanner.updateBanner(response.data);
                        
                        // Start processing images (no polling needed - we process directly)
                        if (response.data.next_attachment_id && response.data.next_attachment_id > 0) {
                            BulkStatusBanner.processNextImage(response.data.next_attachment_id);
                        }
                        
                        // Store in localStorage for persistence
                        localStorage.setItem('sb_bulk_batch_id', BulkStatusBanner.batchId);
                    } else {
                        // Batch not found or completed - clean up
                        BulkStatusBanner.cleanup();
                    }
                },
                error: function() {
                    // On error, assume batch is not active - clean up
                    BulkStatusBanner.cleanup();
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
            localStorage.removeItem('sb_bulk_batch_id');
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

            var bannerHtml = '<div class="sb-bulk-status-banner" id="sb-bulk-status-banner">' +
                '<div class="sb-bulk-status-content">' +
                '<div class="sb-bulk-status-text" id="sb-bulk-status-text">' + 
                (typeof sbBulkStatus !== 'undefined' ? sbBulkStatus.strings.processing : 'Processing...') +
                '</div>' +
                '<div class="sb-bulk-status-progress">' +
                '<div class="sb-bulk-status-progress-fill" id="sb-bulk-status-progress-fill" style="width: 0%;"></div>' +
                '</div>' +
                '</div>' +
                '<button type="button" class="sb-bulk-status-cancel" id="sb-bulk-status-cancel" style="display: none;">' +
                (typeof sbBulkStatus !== 'undefined' ? sbBulkStatus.strings.cancel : 'Cancel') +
                '</button>' +
                '<button type="button" class="sb-bulk-status-reload" id="sb-bulk-status-reload" style="display: none;">' +
                (typeof sbBulkStatus !== 'undefined' ? sbBulkStatus.strings.reload : 'Reload') +
                '</button>' +
                '<button type="button" class="sb-bulk-status-close" id="sb-bulk-status-close" aria-label="' + 
                (typeof sbBulkStatus !== 'undefined' ? 'Close' : 'Dismiss') + '">&times;</button>' +
                '</div>';

            // Try to place before .wp-header-end, fallback to body prepend
            var $headerEnd = $('.wp-header-end');
            if ($headerEnd.length > 0) {
                $headerEnd.before(bannerHtml);
            } else {
                $('body').prepend(bannerHtml);
            }
            
            this.banner = $('#sb-bulk-status-banner');

            // Close button handler
            $('#sb-bulk-status-close').on('click', function() {
                BulkStatusBanner.hide();
            });

            // Cancel button handler
            $('#sb-bulk-status-cancel').on('click', function() {
                BulkStatusBanner.cancelGeneration();
            });

            // Reload button handler
            $('#sb-bulk-status-reload').on('click', function() {
                window.location.reload();
            });
        },

        /**
         * Show error banner
         */
        showError: function(errorType) {
            this.createBanner();
            
            var message = '';
            if (typeof sbBulkStatus !== 'undefined' && sbBulkStatus.strings) {
                switch (errorType) {
                    case 'no_scheduler':
                        message = sbBulkStatus.strings.no_scheduler;
                        break;
                    case 'no_openai':
                    case 'no_ai_provider':
                        message = sbBulkStatus.strings.no_ai_provider || sbBulkStatus.strings.unknown_error;
                        break;
                    case 'no_images':
                        message = sbBulkStatus.strings.no_images;
                        break;
                    default:
                        message = sbBulkStatus.strings.unknown_error;
                }
            } else {
                message = 'An error occurred while processing bulk generation.';
            }

            this.banner.addClass('error');
            $('#sb-bulk-status-text').text(message);
            $('.sb-bulk-status-progress').hide();
        },

        /**
         * Update status from server and process next image if available
         */
        updateStatus: function() {
            if (!this.batchId || typeof sbBulkStatus === 'undefined') {
                this.cleanup();
                return;
            }

            // Don't update if cancelled
            if (this.isCancelled) {
                return;
            }

            $.ajax({
                url: sbBulkStatus.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_get_bulk_status',
                    batch_id: this.batchId,
                    nonce: sbBulkStatus.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Check if batch is cancelled
                        if (response.data.cancelled) {
                            // Batch was cancelled - mark as cancelled and stop processing
                            BulkStatusBanner.isCancelled = true;
                            BulkStatusBanner.stopPolling();
                            BulkStatusBanner.updateBanner(response.data);
                            return;
                        }
                        
                        // Check if completed - if so, update banner and let it handle reload
                        if (response.data.completed) {
                            BulkStatusBanner.updateBanner(response.data);
                            return; // Don't process more images, banner will reload page
                        }
                        
                        // Update banner with current status
                        BulkStatusBanner.updateBanner(response.data);
                        
                        // Process next image if available and not cancelled
                        if (!BulkStatusBanner.isCancelled && response.data.next_attachment_id && response.data.next_attachment_id > 0) {
                            BulkStatusBanner.processNextImage(response.data.next_attachment_id);
                        }
                    } else {
                        // Batch not found or completed - clean up
                        BulkStatusBanner.cleanup();
                    }
                },
                error: function() {
                    // On error, stop polling to avoid unnecessary requests
                    // User can refresh page if needed
                    BulkStatusBanner.stopPolling();
                }
            });
        },

        /**
         * Process next image via AJAX
         */
        processNextImage: function(attachmentId) {
            if (!this.batchId || typeof sbBulkStatus === 'undefined' || !attachmentId) {
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
                url: sbBulkStatus.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_process_bulk_image',
                    batch_id: this.batchId,
                    attachment_id: attachmentId,
                    nonce: sbBulkStatus.nonce
                },
                timeout: 120000, // 2 minute timeout for image processing
                success: function(response) {
                    // Check if cancelled before processing response
                    if (BulkStatusBanner.isCancelled) {
                        return;
                    }

                    if (response.success) {
                        // Store last processed image title if available
                        if (response.data && response.data.image_title) {
                            BulkStatusBanner.lastProcessedTitle = response.data.image_title;
                        }
                        // Image processed successfully - update status and continue
                        BulkStatusBanner.updateStatus();
                    } else {
                        // Image failed - update status and continue with next
                        BulkStatusBanner.updateStatus();
                    }
                },
                error: function(xhr, status, error) {
                    // Don't continue if cancelled
                    if (BulkStatusBanner.isCancelled) {
                        return;
                    }
                    // On error, continue with next image
                    BulkStatusBanner.updateStatus();
                },
                complete: function() {
                    // Clear the active request reference
                    BulkStatusBanner.activeAjaxRequest = null;
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
            $('#sb-bulk-status-progress-fill').css('width', percentage + '%');

            // Check if cancelled
            var isCancelled = data.cancelled || false;

            // Build status text
            var statusText = '';
            if (isCancelled) {
                this.banner.removeClass('error success').addClass('cancelled');
                statusText = sbBulkStatus.strings.cancelled;
                if (processed > 0) {
                    statusText += ' - ' + sbBulkStatus.strings.completed_status.replace('%d', processed) + ' before cancellation';
                }
                
                // Stop polling
                this.stopPolling();
                $('#sb-bulk-status-cancel').hide();
            } else if (completed) {
                this.banner.removeClass('error success cancelled').addClass('success');
                statusText = sbBulkStatus.strings.completed_status.replace('%d', processed);
                
                if (failed > 0) {
                    statusText += ', ' + sbBulkStatus.strings.failed_status.replace('%d', failed);
                }
                
                // Add last processed image title if available
                if (lastProcessedTitle) {
                    statusText += ' | ' + sbBulkStatus.strings.last_processed.replace('%s', lastProcessedTitle);
                }

                // Stop polling
                this.stopPolling();
                // Hide progress bar and cancel button, show reload button
                $('.sb-bulk-status-progress').hide();
                $('#sb-bulk-status-cancel').hide();
                $('#sb-bulk-status-reload').show();
            } else {
                this.banner.removeClass('error success cancelled');
                statusText = sbBulkStatus.strings.processing_status
                    .replace('%d', processed)
                    .replace('%d', queued);

                if (failed > 0) {
                    statusText += ', ' + sbBulkStatus.strings.failed_status.replace('%d', failed);
                }

                if (remaining > 0) {
                    statusText += ', ' + sbBulkStatus.strings.remaining_status.replace('%d', remaining);
                }
                
                // Add last processed image title if available
                if (lastProcessedTitle) {
                    statusText += ' | ' + sbBulkStatus.strings.last_processed.replace('%s', lastProcessedTitle);
                }

                // Show cancel button if there are remaining jobs
                if (remaining > 0) {
                    $('#sb-bulk-status-cancel').show();
                } else {
                    $('#sb-bulk-status-cancel').hide();
                }
            }

            $('#sb-bulk-status-text').text(statusText);

            // Clean up localStorage if completed
            if (completed) {
                localStorage.removeItem('sb_bulk_batch_id');
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

            var pollIntervalMs = (typeof sbBulkStatus !== 'undefined' && sbBulkStatus.poll_interval) 
                ? sbBulkStatus.poll_interval 
                : 30000; // Default 30 seconds

            this.pollInterval = setInterval(function() {
                // Only continue polling if:
                // 1. Not completed
                // 2. Banner is visible
                // 3. We have a batch ID
                if (!BulkStatusBanner.isCompleted && 
                    BulkStatusBanner.banner && 
                    BulkStatusBanner.banner.is(':visible') &&
                    BulkStatusBanner.batchId) {
                    // Check status and resume processing if needed
                    BulkStatusBanner.updateStatus();
                } else {
                    BulkStatusBanner.stopPolling();
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
         * Schedule auto-hide after completion
         */
        scheduleAutoHide: function() {
            // Clear any existing timer
            if (this.autoHideTimer) {
                clearTimeout(this.autoHideTimer);
            }

            // Auto-hide after 10 seconds
            this.autoHideTimer = setTimeout(function() {
                BulkStatusBanner.hide();
            }, 10000);
        },

        /**
         * Cancel bulk generation
         */
        cancelGeneration: function() {
            if (!this.batchId || typeof sbBulkStatus === 'undefined') {
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
            var $cancelBtn = $('#sb-bulk-status-cancel');
            $cancelBtn.prop('disabled', true).text(sbBulkStatus.strings.cancelling);

            $.ajax({
                url: sbBulkStatus.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_cancel_bulk_generation',
                    batch_id: this.batchId,
                    nonce: sbBulkStatus.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Clean up localStorage immediately
                        localStorage.removeItem('sb_bulk_batch_id');
                        
                        // Update banner to show cancelled state
                        BulkStatusBanner.updateBanner({
                            processed: BulkStatusBanner.banner.data('processed') || 0,
                            queued: BulkStatusBanner.banner.data('queued') || 0,
                            failed: BulkStatusBanner.banner.data('failed') || 0,
                            remaining: 0,
                            completed: false,
                            cancelled: true
                        });
                        
                        // Hide banner after a brief delay to show cancellation message
                        setTimeout(function() {
                            BulkStatusBanner.hide();
                        }, 1500);
                    } else {
                        // Re-enable button on error
                        BulkStatusBanner.isCancelled = false;
                        $cancelBtn.prop('disabled', false).text(sbBulkStatus.strings.cancel);
                        window.SBModal.alert(response.data && response.data.message ? response.data.message : 'Failed to cancel generation', { tone: 'error' });
                    }
                },
                error: function() {
                    // Re-enable button on error
                    BulkStatusBanner.isCancelled = false;
                    $cancelBtn.prop('disabled', false).text(sbBulkStatus.strings.cancel);
                    window.SBModal.alert('An error occurred while cancelling generation', { tone: 'error' });
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
            localStorage.removeItem('sb_bulk_batch_id');
        }
    };

    // Initialize when DOM is ready (WordPress jQuery compatible)
    jQuery(document).ready(function($) {
        BulkStatusBanner.init();
    });

})(jQuery);

