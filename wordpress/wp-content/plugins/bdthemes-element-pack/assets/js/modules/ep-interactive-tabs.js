/**
 * Start interactive tabs widget script
 */

 (function($, elementor) {

    'use strict';

    var widgetInteractiveTabs = function($scope, $) {

        var $slider = $scope.find('.bdt-interactive-tabs-content'),
            $tabs   = $scope.find('.bdt-interactive-tabs');

        if (!$slider.length) {
            return;
        }

        var $sliderContainer = $slider.find('.swiper-carousel'),
            $settings = $slider.data('settings'),
            $swiperId = $($settings.id).find('.swiper-carousel');

            const Swiper = elementorFrontend.utils.swiper;
            initSwiper();
            async function initSwiper() {
                var swiper = await new Swiper($swiperId, $settings);
                if ($settings.pauseOnHover) {
                    $($sliderContainer).hover(function () {
                        (this).swiper.autoplay.stop();
                    }, function () {
                        (this).swiper.autoplay.start();
                    });
                }

                // start video stop
                var stopVideos = function () {
                    var videos = document.querySelectorAll($settings.id + ' .bdt-interactive-tabs-iframe');
                    Array.prototype.forEach.call(videos, function (video) {
                        // Store the current source
                        var src = video.src;
                        // Clear the source
                        video.src = '';
                        // Remove any autoplay parameters from the URL
                        src = src.replace(/autoplay=1|autoplay=true/gi, 'autoplay=0');
                        // Set the modified source back
                        video.src = src;
                    });
                };
                // end video stop

                $tabs.find('.bdt-interactive-tabs-item:first').addClass('bdt-active');

                swiper.on('slideChange', function () {
                    $tabs.find('.bdt-interactive-tabs-item').removeClass('bdt-active');
                    $tabs.find('.bdt-interactive-tabs-item').eq(swiper.realIndex).addClass('bdt-active');
                    stopVideos();
                });

                $tabs.find('.bdt-interactive-tabs-wrap .bdt-interactive-tabs-item[data-slide]').on('click', function (e) {
                    e.preventDefault();
                    var slideno = $(this).data('slide');
                    stopVideos();
                    swiper.slideTo(slideno + 1);
                });
            };
    };
    
    jQuery(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/bdt-interactive-tabs.default', widgetInteractiveTabs);
    });

}(jQuery, window.elementorFrontend));

/**
 * End interactive tabs widget script
 */

