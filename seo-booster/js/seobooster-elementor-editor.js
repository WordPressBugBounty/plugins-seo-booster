/* global jQuery, elementor, seoboosterData, open_floating_window */

// Add a floating SEO Booster button when Elementor is active
jQuery(document).ready(function() {
    
    // Check if Elementor is active
    if (typeof elementor !== 'undefined') {
        
        // Create the floating button
        const floatingButton = document.createElement('div');
        floatingButton.id = 'seo-booster-floating-btn';
        
        // Check if plugin icon URL is available from PHP
        let iconHtml = '';
        if (typeof seoboosterData !== 'undefined' && seoboosterData.pluginIconUrl) {
            iconHtml = `<div class="seo-booster-icon"><img src="${seoboosterData.pluginIconUrl}" width="20" height="20" alt="SEO Booster" style="width: 20px; height: 20px;"></div>`;
        } else {
            // Fallback to Elementor icon
            iconHtml = `<div class="seo-booster-icon"><i class="eicon-search"></i></div>`;
        }
        
        floatingButton.innerHTML = `
            ${iconHtml}
            <div class="seo-booster-text">SEO Booster</div>
        `;
        
        // Style the floating button
        floatingButton.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #556068;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            z-index: 99999;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            font-family: Roboto, Arial, Helvetica, sans-serif;
            font-size: 14px;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            user-select: none;
        `;
        
        // Style the icon
        const iconElement = floatingButton.querySelector('.seo-booster-icon');
        if (typeof seoboosterData !== 'undefined' && seoboosterData.pluginIconUrl) {
            // Style for image icon
            iconElement.style.cssText = `
                margin-right: 8px;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
            `;
        } else {
            // Style for fallback icon
            iconElement.style.cssText = `
                margin-right: 8px;
                color: #71d7f7;
                font-size: 16px;
            `;
        }
        
        // Add hover effect
        floatingButton.addEventListener('mouseover', function() {
            this.style.backgroundColor = '#444c54';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.4)';
        });
        
        floatingButton.addEventListener('mouseout', function() {
            this.style.backgroundColor = '#556068';
            this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.3)';
        });
        
        // Add click event
        floatingButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check if we have the open_floating_window function from your adminbar.js
            if (typeof open_floating_window === 'function') {
                open_floating_window();
            } else {
                alert('SEO Booster activated! The floating window function is not available.');
            }
        });
        
        // Add the button to the body
        document.body.appendChild(floatingButton);
        
        // Make sure the button stays on top
        function ensureButtonVisibility() {
            // Check if button was removed from DOM
            if (!document.getElementById('seo-booster-floating-btn')) {
                document.body.appendChild(floatingButton);
            }
            
            // Make sure it has the highest z-index
            const highestZIndex = Math.max(
                ...Array.from(document.querySelectorAll('body *'))
                    .map(el => parseFloat(window.getComputedStyle(el).zIndex))
                    .filter(zIndex => !isNaN(zIndex))
            );
            
            if (highestZIndex >= floatingButton.style.zIndex) {
                floatingButton.style.zIndex = highestZIndex + 1;
            }
        }
        
        // Check button visibility periodically
        setInterval(ensureButtonVisibility, 2000);
        
        // Also check when Elementor events happen
        if (elementor) {
            elementor.on('panel:init', ensureButtonVisibility);
            elementor.channels.editor.on('section:activated', ensureButtonVisibility);
            elementor.on('preview:loaded', ensureButtonVisibility);
        }
    }
});

// Additional hooks for Elementor initialization
jQuery(window).on('elementor/frontend/init', function() {
    
    // Hook into Elementor's editor
    elementor.on('panel:init', function() {
    });
});