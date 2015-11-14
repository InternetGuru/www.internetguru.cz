(function(win) {

  if(typeof IGCMS === "undefined") throw "IGCMS is not defined";

  var Config = {}
  Config.tocTitle = "Table of contents";
  Config.maxDepth = 2; // 0 for no limit
  Config.ns = "tocable";

   var TOCable = function() {

      var
      tocWrapper = null,
      hElements = [],
      hLevels = [],
      headingsPatt = null,
      tocRoot = null,
      include = function(src, e) {
        var base = window.Base ? window.Base : "/";
        script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = base + src;
        e.appendChild(script);
      },
      createTocWrapper = function() {
        var d = document.getElementsByTagName("div");
        var i = 0;
        for(; i<d.length; i++) {
          if(!d[i].classList.contains(Config.ns)) continue;
          tocRoot = d[i];
          break;
        }
        if(tocRoot == null) return false
        var div = document.createElement("div");
        div.className = "list";
        tocWrapper = document.createElement("dl");
        tocWrapper.className = "hideable nohide toc";
        div.appendChild(tocWrapper);
        tocRoot.parentNode.insertBefore(div,tocRoot);
        return true;
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
          if(Config.maxDepth > 0 && currentLevel > Config.maxDepth) continue;
          var subOl = win.document.createElement('ol');
          createTOC(i+1, subOl);
          li.appendChild(subOl);
        }
      }

      return {
        init : function(cfg) {
          IGCMS.initCfg(Config, cfg);
          // create toc
          var wrapper = createTocWrapper();
          if(!wrapper) return;
          headingsPatt = new RegExp("h([1-6])");
          getHeadings(tocRoot);
          var ol = win.document.createElement('ol');
          createTOC(0, ol);

          // insert title and toc
          var dt = document.createElement("dt");
          dt.innerHTML = " " + Config.tocTitle;
          tocWrapper.appendChild(dt);
          var dd = document.createElement("dd");
          dd.appendChild(ol);
          tocWrapper.appendChild(dd);
          var css = '/* toc.js */'
          + 'dl.toc dd {margin-left: 0em; font-style: normal; }'
          + 'dl.toc ol {counter-reset: item; list-style: none; }'
          + 'dl.toc ol > li {margin-left: 1em; }'
          + 'dl.toc ol > li:before {content: counters(item, ".") " "; counter-increment: item; }';
          IGCMS.appendStyle();
          if(IGCMS.Hideable) return;
          include("themes/hideable.js", document.body);
          include("themes/hideableinit.js", document.body);
        }
      }
   };

   win.IGCMS.TOCable = TOCable();

})(window);