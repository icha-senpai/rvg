/**
 * Start marker widget script
 */

( function( $, elementor ) {

	'use strict';

	var widgetMarker = function( $scope, $ ) {

		var $marker = $scope.find( '.bdt-marker-wrapper' );

        if ( ! $marker.length ) {
            return;
        }

		var $tooltips = $marker.find('.bdt-tippy-tooltip'),
			widgetID = $scope.data('id');
		
		$tooltips.each( function( index ) {
			var $tooltip = $(this);
			var alwaysShow = $tooltip.attr('data-always-show') === 'true';
			var triggerType = $tooltip.attr('data-tippy-trigger') || 'mouseenter focus';
			
			var tippyOptions = {
				allowHTML: true,
				theme: 'bdt-tippy-' + widgetID,
				interactive: true,
				trigger: alwaysShow ? 'manual' : triggerType,
				showOnCreate: false, // Changed from alwaysShow to false to prevent immediate show
				hideOnClick: false
			};
			
			var tippyInstance = tippy(this, tippyOptions);
			
			// Handle always show with delay to prevent CSS class issues
			if (alwaysShow) {
				// Add a longer delay to ensure all CSS classes and animations are properly applied
				setTimeout(function() {
					// Check if element is in viewport before showing
					if (isElementInViewport($tooltip[0])) {
						// Additional check to ensure element is fully rendered
						if ($tooltip.is(':visible')) {
							tippyInstance.show();
						}
					}
				}, 500); // Increased to 500ms delay for better compatibility
			}
			
			// Handle different trigger types
			if (triggerType === 'click' || triggerType === 'mouseenter focus') {
				var tooltipStatus = alwaysShow;
				
				$(this).on('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					
					tooltipStatus = !tooltipStatus;
					
					if (tooltipStatus) {
						tippyInstance.show();
					} else {
						tippyInstance.hide();
					}
				});
			}
		});

		// Helper function to check if element is in viewport
		function isElementInViewport(el) {
			if (typeof el.getBoundingClientRect !== 'function') {
				return true; // Fallback for older browsers
			}
			
			var rect = el.getBoundingClientRect();
			return (
				rect.top >= 0 &&
				rect.left >= 0 &&
				rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
				rect.right <= (window.innerWidth || document.documentElement.clientWidth)
			);
		}

		// Handle scroll events for always-show tooltips
		if ($tooltips.filter('[data-always-show="true"]').length > 0) {
			var scrollHandler = function() {
				$tooltips.filter('[data-always-show="true"]').each(function() {
					var $tooltip = $(this);
					var tippyInstance = $tooltip[0]._tippy;
					
					if (tippyInstance && isElementInViewport(this)) {
						if (!tippyInstance.state.isVisible) {
							tippyInstance.show();
						}
					} else if (tippyInstance && tippyInstance.state.isVisible) {
						tippyInstance.hide();
					}
				});
			};
			
			// Throttle scroll events for better performance
			var scrollTimeout;
			$(window).on('scroll', function() {
				if (scrollTimeout) {
					clearTimeout(scrollTimeout);
				}
				scrollTimeout = setTimeout(scrollHandler, 100);
			});
			
			// Initial check on load with multiple attempts to ensure proper initialization
			setTimeout(scrollHandler, 500);
			setTimeout(scrollHandler, 1000);
			setTimeout(scrollHandler, 1500);
		}

	};


	jQuery(window).on('elementor/frontend/init', function() {
		elementorFrontend.hooks.addAction( 'frontend/element_ready/bdt-marker.default', widgetMarker );
	});

}( jQuery, window.elementorFrontend ) );

/**
 * End marker widget script
 */

