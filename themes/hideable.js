(function(win) {

  if(win.Hideable) return;

  var Config = {}

  Config.expand = "[+]";
  Config.collapse = "[–]";
  Config.hideableClass = "hideable";
  Config.hideClass = "hide";
  Config.noHideClass = "nohide";
  Config.hiddenClass = "hidden";

   var Hideable = function() {

    var
    inited = false,
    initCfg = function(cfg) {
      if(typeof cfg === 'undefined') return;
      for(var attr in cfg) {
        if(!Config.hasOwnProperty(attr)) continue;
        Config[attr] = cfg[attr];
      }
    },
    getHideables = function() {
      if (document.querySelectorAll) return document.querySelectorAll("." + Config.hideableClass);
      var hideables = [];
      var allElements = document.getElementsByTagName("*");
      for(var i = 0; i < allElements.length; i++) {
        if(allElements[i].classList.contains(Config.hideableClass)) hideables.push(allElements[i]);
      }
      return hideables;
    },

    appendStyle = function() {
      var css = '/* hideables.js */'
        + ' .hide { display: none !important; }'
        + ' a.switch { text-decoration: none;'
        + ' border: none !important;'
        + ' font-family: "Emilbus Mono", "Lucida Console", monospace;'
        + ' font-weight: bold }';
      var style = document.getElementsByTagName('style')[0];
      if(style == undefined) {
        var head = document.head || document.getElementsByTagName('head')[0];
        style = document.createElement('style');
        style.type = 'text/css';
        head.appendChild(style);
      }
      style.appendChild(document.createTextNode(css));
    },

    toggleHideables = function() {
      var hideables = getHideables();
      for(var i = 0; i < hideables.length; i++) {
        var firstElement = hideables[i].children[0];
        var link = document.createElement("a");
        link.href = "Javascript:void(0);";
        link.innerHTML = Config.collapse;
        link.classList.add("switch")
        link.addEventListener("click",toggle,false);
        firstElement.innerHTML = " " + firstElement.innerHTML;
        firstElement.insertBefore(link,firstElement.firstChild);
        if(hideables[i].classList.contains(Config.noHideClass)) continue;
        hideables[i].classList.add(Config.noHideClass);
        toggleElement(link);
      }
    }

    function toggle(e) {
      toggleElement(e.target);
      e.preventDefault();
    }

    function toggleElement(link) {
      var e = link.parentNode.parentNode;
      if(e.classList.contains(Config.noHideClass)) {
        e.classList.remove(Config.noHideClass);
        e.classList.add(Config.hiddenClass);
      } else {
        e.classList.remove(Config.hiddenClass);
        e.classList.add(Config.noHideClass);
      }
      for(var i = 0; i < e.childNodes.length; i++) {
        var ch = e.childNodes[i];
        if(ch.nodeType != 1) continue;
        if(ch == link.parentNode) continue;
        if(ch.classList.contains(Config.hideClass)) {
          ch.classList.remove(Config.hideClass);
          link.innerHTML = Config.collapse;
        }
        else {
          ch.classList.add(Config.hideClass);
          link.innerHTML = Config.expand;
        }
      }
    }

    // public
    return {
      init : function(cfg) {
        initCfg(cfg);
        appendStyle();
        toggleHideables();
        inited = true;
      },
      isInit : function() { return inited; }
    }
  };

   var hideable = new Hideable();
   win.Hideable = hideable;

})(window);