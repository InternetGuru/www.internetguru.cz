(function(win) {

  var Config = {}

  Config.show = "[+]";
  Config.hide = "[-]";
  Config.tocTitle = "Table of contents";
  Config.tocNS = "contenttoc";

   var TOC = function() {

      // private
      var
      tocWrapper = null,
      hElements = [],
      hLevels = [],
      headingsPatt = null,
      tocRoot = null,
      initCfg = function(cfg) {
        if(typeof cfg === 'undefined') return;
        for(var attr in cfg) {
          if(!Config.hasOwnProperty(attr)) continue;
          Config[attr] = cfg[attr];
        }
      },
      createTocWrapper = function() {
        var d = document.getElementsByTagName("div");
        var i = 0;
        for(; i<d.length; i++) {
          if(!d[i].classList.contains(Config.tocNS)) continue;
          tocRoot = d[i];
          break;
        }
        if(tocRoot == null) throw "Unable to find div." + Config.tocNS;
        var div = document.createElement("div");
        div.className = "list";
        tocWrapper = document.createElement("dl");
        tocWrapper.className = Config.tocNS + "-wrapper";
        div.appendChild(tocWrapper);
        tocRoot.parentNode.insertBefore(div,tocRoot);
      },
      getHeadings = function(e) {
        var l = e.nodeName.toLowerCase().match(headingsPatt);
        if(l !== null) {
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
        if(dd.classList.contains(Config.tocNS + "-hide")) {
          dd.classList.remove(Config.tocNS + "-hide");
          e.innerHTML = Config.hide;
        } else {
          dd.classList.add(Config.tocNS + "-hide");
          e.innerHTML = Config.show;
        }
      }

      // public
      return {
        init : function(cfg) {
          // create toc
          createTocWrapper();
          initCfg(cfg);
          headingsPatt = new RegExp("h([1-6])");
          getHeadings(tocRoot);
          var ol = win.document.createElement('ol');
          createTOC(0, ol);

          // insert title and toc
          var dt = document.createElement("dt");
          dt.innerHTML = " " + Config.tocTitle;
          var sw = document.createElement("a");
          sw.href = "";
          sw.className = Config.tocNS + "-switch";
          sw.innerHTML = Config.hide;
          dt.insertBefore(sw,dt.firstChild);
          tocWrapper.appendChild(dt);
          var dd = document.createElement("dd");
          dd.appendChild(ol);
          tocWrapper.appendChild(dd);

          // register and run toggle
          sw.addEventListener("click", function(e){
            toggleToc(e.target);
            e.preventDefault();
          });
        }
      }
   };

   var toc = new TOC();
   win.TOC = toc;

})(window);