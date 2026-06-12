/* global sbAutolinkColumns:true, jQuery:true */
/**
 * SEO Booster Autolink Columns JavaScript
 *
 * Handles toggle functionality for the autolink column
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.22
 */

(function($) {
    'use strict';

    /**
     * Initialize the autolink columns functionality
     */
    function init() {
        // Handle toggle clicks
        $(document).on('change', '.sb-autolink-toggle', handleToggleChange);
        
        // Handle quick edit save
        $(document).on('click', '.save', handleQuickEditSave);
        
        // Handle quick edit open
        $(document).on('click', '.editinline', handleQuickEditOpen);
    }

    /**
     * Handle toggle switch change
     */
    function handleToggleChange() {
        var $toggle = $(this);
        var postId = $toggle.data('post-id');
        var isEnabled = $toggle.is(':checked');
        var $wrapper = $toggle.closest('.sb-autolink-toggle-wrapper');
        var $statusText = $wrapper.find('.sb-autolink-status-text');

        // Disable the toggle during AJAX request
        $toggle.prop('disabled', true);

        // Show loading state
        $statusText.text('Updating...');

        // Send AJAX request
        $.ajax({
            url: sbAutolinkColumns.ajax_url,
            type: 'POST',
            data: {
                action: 'sb_autolink_quick_edit',
                nonce: sbAutolinkColumns.nonce,
                post_id: postId,
                autolink_enabled: isEnabled
            },
            success: function(response) {
                if (response.success) {
                    // Update status text
                    $statusText.text(response.data.status_text);
                    
                    // Show success message
                    showNotice(response.data.message, 'success');
                } else {
                    // Revert toggle state on error
                    $toggle.prop('checked', !isEnabled);
                    $statusText.text(isEnabled ? 'Disabled' : 'Enabled');
                    
                    showNotice(response.data || sbAutolinkColumns.strings.error, 'error');
                }
            },
            error: function() {
                // Revert toggle state on error
                $toggle.prop('checked', !isEnabled);
                $statusText.text(isEnabled ? 'Disabled' : 'Enabled');
                
                showNotice(sbAutolinkColumns.strings.error, 'error');
            },
            complete: function() {
                // Re-enable the toggle
                $toggle.prop('disabled', false);
            }
        });
    }

    /**
     * Handle quick edit open
     */
    function handleQuickEditOpen() {
        var $row = $(this).closest('tr');
        var postId = $row.find('.check-column input[type="checkbox"]').val();
        
        if (postId) {
            // Get current autolink state from the toggle
            var $toggle = $row.find('.sb-autolink-toggle');
            var isEnabled = $toggle.is(':checked');
            
            // Set the checkbox state based on the current post
            setTimeout(function() {
                var $checkbox = $('.quick-edit-row input[name="sbp-autolink"]');
                if ($checkbox.length) {
                    $checkbox.prop('checked', isEnabled);
                }
            }, 100);
        }
    }

    /**
     * Handle quick edit save
     */
    function handleQuickEditSave() {
        var $quickEditRow = $('.quick-edit-row');
        if (!$quickEditRow.length) {
            return;
        }

        var postId = $quickEditRow.find('input[name="post_ID"]').val();
        var autolinkEnabled = $quickEditRow.find('input[name="sbp-autolink"]').is(':checked');

        if (postId) {
            // Update the post meta via AJAX
            $.ajax({
                url: sbAutolinkColumns.ajax_url,
                type: 'POST',
                data: {
                    action: 'sb_autolink_quick_edit',
                    nonce: sbAutolinkColumns.nonce,
                    post_id: postId,
                    autolink_enabled: autolinkEnabled
                },
                success: function(response) {
                    if (response.success) {
                        // Update the toggle state
                        var $toggle = $('input[data-post-id="' + postId + '"]');
                        var $wrapper = $toggle.closest('.sb-autolink-toggle-wrapper');
                        var $statusText = $wrapper.find('.sb-autolink-status-text');
                        
                        $toggle.prop('checked', autolinkEnabled);
                        $statusText.text(response.data.status_text);
                        
                        // Show success message
                        showNotice(response.data.message, 'success');
                    } else {
                        showNotice(response.data || sbAutolinkColumns.strings.error, 'error');
                    }
                },
                error: function() {
                    showNotice(sbAutolinkColumns.strings.error, 'error');
                }
            });
        }
    }

    /**
     * Show a notice message
     */
    function showNotice(message, type) {
        // Remove existing notices
        $('.sb-autolink-notice').remove();
        
        // Create notice element
        var $notice = $('<div class="notice notice-' + type + ' sb-autolink-notice is-dismissible"><p>' + message + '</p></div>');
        
        // Insert after the page title
        $('.wp-header-end').after($notice);
        
        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery); 