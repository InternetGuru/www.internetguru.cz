(function(win) {

  if(typeof IGCMS === "undefined") throw "IGCMS is not defined";

  if(IGCMS.Hideable) return;

  var Config = {};

  Config.expand = "►";
  Config.collapse = "▼";
  Config.hideableClass = "hideable";
  Config.hideClass = "hide";
  Config.noHideClass = "nohide";
  Config.hiddenClass = "hidden";
  Config.switchClass = "switch";
  Config.noprintClass = "noprint";
  Config.expandTitle = "Rozbalit";
  Config.collapseTitle = "Sbalit";

   var Hideable = function() {

    var
    inited = false,
    getHideables = function() {
      if (document.querySelectorAll) return document.querySelectorAll("." + Config.hideableClass);
      var hideables = [];
      var allElements = document.getElementsByTagName("*");
      for(var i = 0; i < allElements.length; i++) {
        if(allElements[i].classList.contains(Config.hideableClass)) hideables.push(allElements[i]);
      }
      return hideables;
    },
    toggleHideables = function() {
      var hideables = getHideables();
      for(var i = 0; i < hideables.length; i++) {
        var firstElement = hideables[i].children[0];
        var link = document.createElement("a");
        link.href = "Javascript:void(0);";
        link.title = Config.collapseTitle;
        link.innerHTML = Config.collapse;
        link.classList.add(Config.switchClass);
        link.classList.add(Config.noprintClass);
        link.addEventListener("click", toggle, false);
        firstElement.innerHTML = " " + firstElement.innerHTML;
        firstElement.insertBefore(link, firstElement.firstChild);
        if(hideables[i].classList.contains(Config.noHideClass)) continue;
        hideables[i].classList.add(Config.noHideClass);
        toggleElement(link);
      }
    },

    toggle = function(e) {
      var target = e.target || e.srcElement;
      toggleElement(target);
      e.preventDefault();
    },

    toggleElement = function(link) {
      var e = link.parentNode.parentNode;
      if(e.classList.contains(Config.noHideClass)) {
        e.classList.remove(Config.noHideClass);
        e.classList.add(Config.hiddenClass);
        link.innerHTML = Config.expand;
        link.title = Config.expandTitle;
      } else {
        e.classList.remove(Config.hiddenClass);
        e.classList.add(Config.noHideClass);
        link.innerHTML = Config.collapse;
        link.title = Config.collapseTitle;
      }
      for(var i = 0; i < e.childNodes.length; i++) {
        var ch = e.childNodes[i];
        if(ch.nodeType != 1) continue;
        if(ch == link.parentNode) continue;
        if(ch.classList.contains(Config.noHideClass)) continue;
        if(ch.classList.contains(Config.hideClass)) {
          ch.classList.remove(Config.hideClass);
        } else {
          ch.classList.add(Config.hideClass);
        }
      }
    };

    // public
    return {
      init : function(cfg) {
        if(inited) return;
        IGCMS.initCfg(Config, cfg);
        var css = '/* hideables.js */'
          + ' .hide { display: none !important; }'
          + ' a.' + Config.switchClass + ' { text-decoration: none;'
          + ' border: none !important;'
          + ' font-family: "Emilbus Mono", "Lucida Console", monospace;'
          + ' font-weight: bold }';
        IGCMS.appendStyle(css);
        toggleHideables();
        inited = true;
      },
      isInit : function() { return inited; }
    }
  };

   IGCMS.Hideable = new Hideable();

})(window);
