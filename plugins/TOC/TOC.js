(function(win) {

  var Config = {}

  Config.show = "[+]";
  Config.hide = "[-]";
  Config.tocTitle = "Table of contents";
  Config.hideClass = "contenttoc-hide";
  Config.topLevel = 2;

   var TOC = function() {

      // private
      var
      tocWrapper = null,
      hElements = [],
      hLevels = [],
      headingsPatt = null,
      initCfg = function(cfg) {
        if(typeof cfg === 'undefined') return;
        for(var attr in cfg) {
          if(!Config.hasOwnProperty(attr)) continue;
          Config[attr] = cfg[attr];
        }
      },
      createTocWrapper = function() {
        var d = document.getElementsByTagName("div");
        var f = false;
        var i = 0;
        for(; i<d.length; i++) {
          if(!d[i].classList.contains("section")) continue;
          f = true;
          break;
        }
        if(!f) throw "Unable to find div.section";
        var div = document.createElement("div");
        div.className = "list";
        tocWrapper = document.createElement("dl");
        tocWrapper.id = "contenttoc";
        div.appendChild(tocWrapper);
        d[i].parentNode.insertBefore(div,d[i]);
      },
      getHeadings = function(e) {
        var l = e.nodeName.toLowerCase().match(headingsPatt);
        if(l !== null) {
          // TODO check / generate id
          hElements.push(e);
          hLevels.push(parseInt(l[1]));
          return;
        }
        for(var i = 0, len = e.childNodes.length; i < len; i++) {
          getHeadings(e.childNodes[i]);
        }
      },
      createTOC = function(i, ol) {
        var currentLevel = hLevels[i];
        for(i, len = hElements.length; i < len; i++) {
          if(hLevels[i] < currentLevel) break;

          // add all current level headings
          if(hLevels[i] != currentLevel) continue;
          var a = win.document.createElement("a");
          a.innerHTML = hElements[i].innerHTML;
          a.href = "#" + hElements[i].id;
          var li = win.document.createElement("li");
          li.appendChild(a);
          ol.appendChild(li);

          // call itself if next level is 1 higher
          if(hLevels[i+1] != (currentLevel+1)) continue;
          var subOl = win.document.createElement('ol');
          createTOC(i+1, subOl);
          li.appendChild(subOl);
        }
      },
      toggleToc = function(e) {
        var dd = e.parentNode.nextSibling;
        if(dd.classList.contains(Config.hideClass)) {
          dd.classList.remove(Config.hideClass);
          e.innerHTML = Config.hide;
        } else {
          dd.classList.add(Config.hideClass);
          e.innerHTML = Config.show;
        }
      }

      // public
      return {
        init : function(cfg) {
          // create toc
          createTocWrapper();
          initCfg(cfg);
          headingsPatt = new RegExp("h([" + Config.topLevel + "-6])");
          getHeadings(win.document.body);
          var ol = win.document.createElement('ol');
          createTOC(0, ol);

          // insert title and toc
          var dt = document.createElement("dt");
          dt.innerHTML = " " + Config.tocTitle;
          var sw = document.createElement("code");
          sw.className = "contenttoc-switch";
          sw.innerHTML = Config.hide;
          dt.insertBefore(sw,dt.firstChild);
          tocWrapper.appendChild(dt);
          var dd = document.createElement("dd");
          dd.appendChild(ol);
          tocWrapper.appendChild(dd);

          // register and run toggle
          sw.addEventListener("click", function(e){
            toggleToc(e.target);
          });
        }
      }
   };

   var toc = new TOC();
   win.TOC = toc;

})(window);