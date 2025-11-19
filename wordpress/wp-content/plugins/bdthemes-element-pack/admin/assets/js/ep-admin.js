jQuery(document).ready(function ($) {

    if (jQuery('.wrap').hasClass('element-pack-dashboard')) {

        // modules
        var moduleUsedWidget = jQuery('#element_pack_active_modules_page').find('.ep-used-widget');
        var moduleUsedWidgetCount = jQuery('#element_pack_active_modules_page').find('.ep-options .ep-used').length;
        moduleUsedWidget.text(moduleUsedWidgetCount);
        var moduleUnusedWidget = jQuery('#element_pack_active_modules_page').find('.ep-unused-widget');
        var moduleUnusedWidgetCount = jQuery('#element_pack_active_modules_page').find('.ep-options .ep-unused').length;
        moduleUnusedWidget.text(moduleUnusedWidgetCount);

        // 3rd party
        var thirdPartyUsedWidget = jQuery('#element_pack_third_party_widget_page').find('.ep-used-widget');
        var thirdPartyUsedWidgetCount = jQuery('#element_pack_third_party_widget_page').find('.ep-options .ep-used').length;
        thirdPartyUsedWidget.text(thirdPartyUsedWidgetCount);
        var thirdPartyUnusedWidget = jQuery('#element_pack_third_party_widget_page').find('.ep-unused-widget');
        var thirdPartyUnusedWidgetCount = jQuery('#element_pack_third_party_widget_page').find('.ep-options .ep-unused').length;
        thirdPartyUnusedWidget.text(thirdPartyUnusedWidgetCount);
        
        // Add scroll-to-top functionality for all tab navigation clicks
        jQuery(document).on('click', '.bdt-dashboard-navigation a, .bdt-tab a, .bdt-tab-item, .ep-widget-filter a, .bdt-subnav a', function() {
            // Scroll to top smoothly when any tab or navigation link is clicked
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Handle WordPress admin sub menu clicks
        jQuery(document).on('click', '#adminmenu .wp-submenu a, .toplevel_page_element_pack_options .wp-submenu a', function() {
            var href = jQuery(this).attr('href');
            // Only scroll to top if it's an Element Pack related link
            if (href && (href.includes('element_pack') || href.includes('#'))) {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        });
        
        // Also handle hash change events to scroll to top
        jQuery(window).on('hashchange', function() {
            // Small delay to ensure tab content is loaded before scrolling
            setTimeout(function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }, 100);
        });
    }

    jQuery('.element-pack-notice.notice-error img').css({
        'margin-right': '8px',
        'vertical-align': 'middle'
    });

    /* ===================================
       REMOTE NOTICE
       =================================== */
    
    /**
     * Initialize countdown timers for remote notices
     * This function finds all countdown elements and starts the countdown timer
     */
    function initRemoteNoticeCountdown() {
        // Find all countdown elements on the page
        jQuery('.bdt-notice-countdown').each(function() {
            var $countdown = jQuery(this);
            var $timer = $countdown.find('.countdown-timer');
            var endDate = $countdown.data('end-date');
            var timezone = $countdown.data('timezone');
            
            // Skip if no end date or timer element found
            if (!endDate || !$timer.length) {
                return;
            }
            
            /**
             * Update the countdown display
             * Calculates time remaining and formats it for display
             */
            function updateCountdown() {
                var endTime = new Date(endDate + ' ' + timezone).getTime();
                var now = new Date().getTime();
                var distance = endTime - now;
                
                // If countdown has expired, hide the countdown
                if (distance < 0) {
                    $countdown.hide();
                    return;
                }
                
                // Calculate time units
                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                // Add leading zeros
                days = days < 10 ? "0" + days : days;
                hours = hours < 10 ? "0" + hours : hours; 
                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;
                
                // Build countdown text with wrapped numbers and labels
                var countdownText = "";
                if (days > 0) {
                    countdownText += '<div class="countdown-item"><span class="number">' + days + '</span><span class="label">days</span></div><span class="separator"></span>';
                }
                // Always show hours (even if 00) for consistent layout
                countdownText += '<div class="countdown-item"><span class="number">' + hours + '</span><span class="label">hrs</span></div><span class="separator"></span>';
                
                countdownText += '<div class="countdown-item"><span class="number">' + minutes + '</span><span class="label">min</span></div><span class="separator"></span>';
                
                countdownText += '<div class="countdown-item"><span class="number">' + seconds + '</span><span class="label">sec</span></div>';
                
                // Update the timer display
                $timer.html(countdownText);
            }
            
            // Initial update to show countdown immediately
            updateCountdown();
            
            // Set up interval to update countdown every second
            setInterval(updateCountdown, 1000);
        });
    }
    
    // Initialize countdown on page load
    initRemoteNoticeCountdown();
    
    // Re-initialize countdown when new notices are added (for dynamic content)
    // This ensures countdown works even if notices are loaded after page load
    jQuery(document).on('DOMNodeInserted', '.bdt-notice-countdown', function() {
        initRemoteNoticeCountdown();
    });

    /* ===================================
       END REMOTE NOTICE
       =================================== */

    // Variations swatches
    const variationSwatchesBtn = jQuery(".ep-feature-option-parent");
    const variationDependentOptions = variationSwatchesBtn.length > 0 
        ? variationSwatchesBtn.closest(".ep-option-item").nextAll()
        : jQuery('.ep-option-item[class*="ep-ep_variation_swatches_"]');
    
    const toggleVariationOptions = function() {
        if (variationSwatchesBtn.length > 0 && variationSwatchesBtn.prop("checked")) {
            variationDependentOptions.fadeIn(250);
        } else {
            variationDependentOptions.hide();
        }
    };
    
    toggleVariationOptions();
    
    if (variationSwatchesBtn.length > 0) {
        variationSwatchesBtn.on("change", toggleVariationOptions);
    }
    
    jQuery("#bdt-element_pack_other_settings").on("click", toggleVariationOptions);

    //End Variations swatches

});