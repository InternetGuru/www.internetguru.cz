(function(win) {

  var Config = {};
  Config.wrapperId = "scrolltop";
  Config.speed = 10;
  Config.text = "^";
  Config.scrollBy = 50;
  Config.hidePosition = 200;

   var ScrollTop = function() {

      var scrollTimeOut = null,
      windowScrollTimeOut = null,
      button = null,
      h1 = document.getElementsByTagName("h1")[0],
      displayed = false,
      initCfg = function(cfg) {
        if(typeof cfg === 'undefined') return;
        for(var attr in cfg) {
          if(!Config.hasOwnProperty(attr)) continue;
          Config[attr] = cfg[attr];
        }
      },
      appendStyle = function() {
        var css = '/* scrolltop.js */'
          + 'a#scrolltop { position: fixed; right: 0; bottom: 0; text-decoration: none; background: rgba(0, 0, 0, 0.6); padding: 0.5em; font-size: 1.75rem; margin: 0.75rem; display: block; color: white; width: 1em; text-align: center; height: 1em; border-radius: 1em; }'
          + 'a#scrolltop span { position: relative; top: -0.1em; }';
          var style = document.getElementsByTagName('style')[0];
        if(style == undefined) {
          var head = document.head || document.getElementsByTagName('head')[0];
          style = document.createElement('style');
          style.type = 'text/css';
          head.appendChild(style);
        }
        style.appendChild(document.createTextNode(css));
      },
      getScrollTop = function() {
        return document.body.scrollTop || document.documentElement.scrollTop;
      },
      doScroll = function() {
        if (getScrollTop() != 0) {
          window.scrollBy(0, -1 * Config.scrollBy);
          scrollTimeOut = setTimeout(function(){ doScroll(); }, Config.speed);
        } else {
          clearTimeout(scrollTimeOut);
          window.history.replaceState("", "", "#"+h1.id);
        }
      },
      setScrollEvent = function() {
        fn = function() {
          win.clearTimeout(windowScrollTimeOut);
          windowScrollTimeOut = window.setTimeout( function() {
            if(getScrollTop() <= Config.hidePosition) {
              if(button === null) return;
              displayed = false;
              button.style.display = "none";
            } else if(displayed == false) {
              displayed = true;
              if(button === null) createButton();
              button.style.display = "block";
            }
          }, 50);
        };
        win.onscroll = fn;
      },
      createButton = function() {
        button = document.createElement("a");
        button.href = "#" + h1.id;
        button.id = Config.wrapperId;
        var span = document.createElement("span");
        span.innerHTML = Config.text;
        button.appendChild(span);
        document.body.appendChild(button);
        button.onclick = function() {
          doScroll();
          return false;
        }
      };

      // public
      return {
        init : function(cfg) {
          initCfg(cfg);
          appendStyle();
          setScrollEvent();
        }
      }
   };

   var scrolltop = new ScrollTop();
   win.ScrollTop = scrolltop;

})(window);
