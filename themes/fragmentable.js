(function(win) {

  require("IGCMS", function () {

    var Config = {};
    Config.ns = "fragmentable";

    var Fragmentable = function () {

      var
        /**
         * Triger changeFragment on document onclick
         */
        fireEvents = function () {
          document.addEventListener("click", changeFragment, false);
        },
        /**
         * Change fragment according to heading id.
         * H1 will remove fragment.
         * @param  {Event} event
         */
        changeFragment = function (event) {
          var h = IGCMS.findParentBySelector(event.target, "h1, h2, h3, h4, h5, h6");
          if (h === null) return;
          if (h.nodeName.toLowerCase() == "h1") {
            win.history.replaceState("", "", win.location.href.split('#')[0]);
          } else {
            win.history.replaceState("", "", "#" + h.id);
          }
          win.dispatchEvent(new HashChangeEvent("hashchange"));
        };

      return {
        /**
         * @param  {Object} cfg custom configuration
         */
        init: function (cfg) {
          IGCMS.initCfg(Config, cfg);
          fireEvents();
        }
      }

    };

    win.IGCMS.Fragmentable = new Fragmentable();
  })
})(window)
