(function(win) {

  var Config = {}
  Config.selectTitle = "Select all";
  Config.selectableClass = "selectable";

   var Selectable = function() {

      var initCfg = function(cfg) {
        if(typeof cfg === 'undefined') return;
        for(var attr in cfg) {
          if(!Config.hasOwnProperty(attr)) continue;
          Config[attr] = cfg[attr];
        }
      },

      getElements = function() {
        if (document.querySelectorAll) return document.querySelectorAll("." + Config.selectableClass);
        var selectables = [];
        var allElements = document.getElementsByTagName("*");
        for(var i = 0; i < allElements.length; i++) {
          if(allElements[i].classList.contains(Config.selectableClass)) selectables.push(allElements[i]);
        }
        return selectables;
      },
      getPreviousElement = function(e) {
        if(e == null) return null;
        var tmp = e.previousSibling;
        while(tmp != null) {
          if(tmp.nodeType == 1) return tmp;
          tmp = tmp.previousSibling;
        }
        return getPreviousElement(e.parentNode);
      },
      createButton = function(e) {
        var i;
        for (i = 0; i < e.length; i++) {
          var button = document.createElement("input");
          button.onclick = (function() {
            var currentI = i;
            var curE = e;
            return function() {
              var d = curE[currentI].style.display;
              curE[currentI].style.display = "block";
              selectText(curE[currentI]);
            }
          })();
          button.type = "button";
          button.value = Config.selectTitle;
          button.style.marginLeft = "0.5em";
          var ul = document.createElement("ul");
          var li = document.createElement("li");
          ul.appendChild(li);
          li.appendChild(button);
          var prevElement = getPreviousElement(e[i]);
          prevElement.parentNode.insertBefore(ul, prevElement.nextSibling);
        }
      },
      selectText = function(text) {
        var doc = document, range, selection;
        if (doc.body.createTextRange) {
          range = document.body.createTextRange();
          range.moveToElementText(text);
          range.select();
        } else if (window.getSelection) {
          selection = window.getSelection();
          range = document.createRange();
          range.selectNodeContents(text);
          selection.removeAllRanges();
          selection.addRange(range);
        }
      };

      // public
      return {
        init : function(cfg) {
          // create toc
          initCfg(cfg);
          var e = getElements();
          createButton(e);
        }
      }
   };

   var selectable = new Selectable();
   win.Selectable = selectable;

})(window);