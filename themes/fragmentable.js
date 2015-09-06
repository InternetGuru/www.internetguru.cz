(function(win) {

  if(typeof IGCMS === "undefined") throw "IGCMS is not defined";


  Config = {};

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
    }

    return {
      init: function(cfg) {
        IGCMS.initCfg(Config, cfg);
        fireEvents();
      }
    }

  }

  win.IGCMS.Fragmentable = new Fragmentable();

})(window);