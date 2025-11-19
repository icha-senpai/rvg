; (function ($, elementor) {
$(window).on('elementor/frontend/init', function () {
    let ModuleHandler = elementorModules.frontend.handlers.Base,
        CursorEffect;

    CursorEffect = ModuleHandler.extend({
        bindEvents: function () {
            this.run();
        },
        getDefaultSettings: function () {
            return {

            };
        },
        onElementChange: debounce(function (prop) {
            if (prop.indexOf('element_pack_cursor_effects_') !== -1) {
                this.run();
            }
        }, 400),

        settings: function (key) {
            return this.getElementSettings('element_pack_cursor_effects_' + key);
        },

        run: function () {
          if (this.settings("show") !== "yes") {
            return;
          }

          // Disable on mobile
          const disableOnMobile = this.settings("disable_on_mobile") === "yes";
          const isMobile = window.innerWidth <= 767;
          if (disableOnMobile && isMobile) {
            return;
          }

          var options = this.getDefaultSettings(),
            elementID = this.$element.data("id"),
            elementContainer = ".elementor-element-" + elementID,
            $element = this.$element,
            cursorStyle = this.settings("style");
          const checkClass = $(elementContainer).find(".bdt-cursor-effects");
          var source = this.settings("source");
          if ($(checkClass).length < 1) {
            if (source === "image") {
              var image = this.settings("image_src.url");
              $(elementContainer).append(
                '<div class="bdt-cursor-effects"><div id="bdt-ep-cursor-ball-effects-' +
                  elementID +
                  '" class="ep-cursor-ball"><img class="bdt-cursor-image"src="' +
                  image +
                  '"></div></div>'
              );
            } else if (source === "icons") {
              var svg = this.settings("icons.value.url");
              var icons = this.settings("icons.value");
              if (svg !== undefined) {
                $(elementContainer).append(
                  '<div class="bdt-cursor-effects"><div id="bdt-ep-cursor-ball-effects-' +
                    elementID +
                    '" class="ep-cursor-ball"><img class="bdt-cursor-image" src="' +
                    svg +
                    '"></img></div></div>'
                );
              } else {
                $(elementContainer).append(
                  '<div class="bdt-cursor-effects"><div id="bdt-ep-cursor-ball-effects-' +
                    elementID +
                    '" class="ep-cursor-ball"><i class="' +
                    icons +
                    ' bdt-cursor-icons"></i></div></div>'
                );
              }
            } else if (source === "text") {
              var text = this.settings("text_label");
              $(elementContainer).append(
                '<div class="bdt-cursor-effects"><div id="bdt-ep-cursor-ball-effects-' +
                  elementID +
                  '" class="ep-cursor-ball"><span class="bdt-cursor-text">' +
                  text +
                  "</span></div></div>"
              );
            } else {
              $(elementContainer).append(
                '<div class="bdt-cursor-effects ' +
                  cursorStyle +
                  '"><div id="bdt-ep-cursor-ball-effects-' +
                  elementID +
                  '" class="ep-cursor-ball"></div><div id="bdt-ep-cursor-circle-effects-' +
                  elementID +
                  '"  class="ep-cursor-circle"></div></div>'
              );
            }
          }
          const cursorBallID =
            "#bdt-ep-cursor-ball-effects-" + this.$element.data("id");
          const cursorBall = document.querySelector(cursorBallID);
          options.models = elementContainer;
          options.speed = 1;
          options.centerMouse = true;
          new Cotton(cursorBall, options);

          if (source === "default") {
            const cursorCircleID =
              "#bdt-ep-cursor-circle-effects-" + this.$element.data("id");
            const cursorCircle = document.querySelector(cursorCircleID);
            options.models = elementContainer;
            options.speed = this.settings("speed")
              ? this.settings("speed.size")
              : 0.725;
            options.centerMouse = true;
            new Cotton(cursorCircle, options);
          }
        }
    });

    // Handle widgets
    elementorFrontend.hooks.addAction('frontend/element_ready/widget', function ($scope) {
        elementorFrontend.elementsHandler.addHandler(CursorEffect, {
            $element: $scope
        });
    });

    // Handle sections
    elementorFrontend.hooks.addAction('frontend/element_ready/section', function ($scope) {
        elementorFrontend.elementsHandler.addHandler(CursorEffect, {
            $element: $scope
        });
    });

    // Handle containers
    elementorFrontend.hooks.addAction('frontend/element_ready/container', function ($scope) {
        elementorFrontend.elementsHandler.addHandler(CursorEffect, {
            $element: $scope
        });
    });
});
})(jQuery, window.elementorFrontend);
