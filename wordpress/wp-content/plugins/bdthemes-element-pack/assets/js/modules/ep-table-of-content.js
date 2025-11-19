/**
 * Start table of content widget script
 */

( function( $, elementor ) {

	'use strict';

	var widgetTableOfContent = function( $scope, $ ) {

		var $tableOfContent = $scope.find( '.bdt-table-of-content' );
				
        if ( ! $tableOfContent.length ) {
            return;
        }

        $($tableOfContent).tocify($tableOfContent.data('settings'));

        // Handle incoming hash URLs only if hash navigation is enabled
        var settings = $tableOfContent.data('settings');
        if (settings && settings.hashNavigation) {
            handleHashOnLoad($tableOfContent);
        }

	};

    function handleHashOnLoad($tableOfContent) {
        var hash = window.location.hash;
        if (hash && hash.length > 1) {
            setTimeout(function() {
                var target = $('[name="' + hash.substring(1) + '"]');
                if (target.length) {
                    // Get scroll offset from TOC settings
                    var settings = $tableOfContent.data('settings');
                    var scrollOffset = settings ? (settings.scrollTo || 0) : 0;
                    
                    $('html, body').animate({
                        scrollTop: target.offset().top - scrollOffset
                    }, 800);
                }
            }, 1500);
        }
    }

	jQuery(window).on('elementor/frontend/init', function() {
		elementorFrontend.hooks.addAction( 'frontend/element_ready/bdt-table-of-content.default', widgetTableOfContent );
	});

}( jQuery, window.elementorFrontend ) );

/**
 * End table of content widget script
 */


