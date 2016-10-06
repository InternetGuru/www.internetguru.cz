(function(win) {

  if(typeof IGCMS === "undefined") throw "IGCMS is not defined";


  var Config = {};
  Config.ns = "fragmentable";

  var Fragmentable = function() {

    var
    /**
     * Triger changeFragment on document onclick
     */
    fireEvents = function() {
      document.addEventListener("click", changeFragment, false);
    },
    /**
     * Change fragment according to heading id.
     * H1 will remove fragment.
     * @param  {Event} event
     */
    changeFragment = function(event) {
      var h = IGCMS.findParentBySelector(event.target, "h1, h2, h3, h4, h5, h6");
      if(h === null) return;
      if(h.nodeName.toLowerCase() == "h1") {
        window.history.replaceState("", "", window.location.href.split('#')[0]);
        return;
      }
      window.history.replaceState("", "", "#"+h.id);
    };

    return {
      /**
       * Works only for body with class=[Config.ns]
       * @param  {Object} cfg custom configuration
       */
      init: function(cfg) {
        if(!document.body.classList.contains(Config.ns)) throw "Element body missing "+Config.ns+" class";
        IGCMS.initCfg(Config, cfg);
        fireEvents();
      }
    }

  };

  win.IGCMS.Fragmentable = new Fragmentable();

})(window);