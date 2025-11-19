/**
 * Start section sticky widget script
 */

(function ($, elementor) {

    'use strict';

    // Function to handle dynamic positioning for sticky elements
    function handleDynamicPositioning($stickyElement) {
        var stickyData = $stickyElement.attr('data-bdt-sticky');

        // Check if dynamic positioning is enabled
        if (!stickyData ) {
            return;
        }

        // Store original position for reference
        var originalOffset = $stickyElement.offset();
        var originalLeft = originalOffset ? originalOffset.left : 0;

        // Function to calculate inset value
        function calculateInsetValue() {
            var elementWidth = $stickyElement.outerWidth();
            var documentWidth = $(document).width();
            var isRTL = $('html').attr('dir') === 'rtl' || $('body').hasClass('rtl');

            return isRTL ?
                Math.max(documentWidth - elementWidth - originalLeft, 0) :
                originalLeft;
        }

        // Function to apply or remove positioning
        function updatePositioning(immediate) {
            if ($stickyElement.hasClass('bdt-active')) {
                $stickyElement.css('inset-inline-start', calculateInsetValue() + 'px');
            } else {
                $stickyElement.css('inset-inline-start', '');
            }
        }

        // Update on window resize
        $(window).on('resize', updatePositioning);

        // Update when sticky becomes active (using MutationObserver)
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    if ($stickyElement.hasClass('bdt-active')) {
                        // Apply positioning immediately to prevent layout shift
                        updatePositioning(true);
                    } else {
                        // Remove positioning when sticky becomes inactive
                        setTimeout(function() {
                            updatePositioning();
                        }, 10);
                    }
                }
            });
        });

        observer.observe($stickyElement[0], {
            attributes: true,
            attributeFilter: ['class']
        });

        // Listen for UIkit sticky events
        $stickyElement.on('active', function () {
            updatePositioning(true);
        });

        $stickyElement.on('inactive', function () {
            $stickyElement.css('inset-inline-start', '');
        });

        // Initial check
        setTimeout(updatePositioning, 100);
    }

    var widgetSectionSticky = function ($scope, $) {
        var $section = $scope;

        // Sticky fixes for inner section
        $section.each(function () {
            var $stickyFound = $(this).find('.elementor-inner-section.bdt-sticky');
            if ($stickyFound.length) {
                $stickyFound.wrap('<div class="bdt-sticky-wrapper"></div>');
            }
        });

        // Handle dynamic positioning for sticky elements
        var $stickyElements = $section.find('[data-bdt-sticky]');
        if ($stickyElements.length === 0) {
            $stickyElements = $('[data-bdt-sticky]');
        }

        $stickyElements.each(function () {
            handleDynamicPositioning($(this));
        });
    };

    // Handle elements that might already be present
    $(document).ready(function () {
        $('[data-bdt-sticky]').each(function () {
            handleDynamicPositioning($(this));
        });
    });

    // Initialize on Elementor frontend
    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction('frontend/element_ready/section', widgetSectionSticky);
    });

}(jQuery, window.elementorFrontend));

/**
 * End section sticky widget script
 */
