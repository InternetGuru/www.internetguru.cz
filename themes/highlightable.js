(function (win) {

  if (typeof IGCMS === "undefined") throw "IGCMS is not defined";

  var Config = {};
  Config.cName = "highlightable-active";

  var Highlightable = function () {

    var
    highlighted = null,

    highlight = function () {
      if (highlighted) {
        highlighted.classList.remove(Config.cName);
      }
      var fragment = window.location.href.split('#')[1];
      var el = document.getElementById(fragment);
      if (!el) {
        return;
      }
      el.classList.add(Config.cName);
      highlighted = el;
    };

    return {
      init: function () {
        win.onhashchange = highlight;
        highlight();
      }
    }

  };

  var hl = new Highlightable();
  win.IGCMS.Highlightable = hl;
  hl.init();

})(window);
