(function(win) {

  if(typeof IGCMS === "undefined") throw "IGCMS is not defined";


  var Config = {};
  Config.ns = "scrolltopable"; // wrapper element (a) id value
  Config.wrapperId = "scrolltop"; // wrapper element (a) id value
  Config.time = 250; // int ms to scroll
  Config.text = "^"; // text content
  Config.title = "Nahoru"; // text content
  Config.hidePosition = 200; // display / hide button int px from top

   var ScrollTopable = function() {

      var scrollTimeOut = null,
      windowScrollTimeOut = null,
      button = null,
      displayed = false,
      step = null,
      wait = 10,
      getScrollTop = function() {
        return document.body.scrollTop || document.documentElement.scrollTop;
      },
      doScroll = function() {
        if (getScrollTop() != 0) {
          window.scrollBy(0, -1 * step);
          scrollTimeOut = setTimeout(function(){ doScroll(); }, wait);
        } else {
          clearTimeout(scrollTimeOut);
          window.history.replaceState("", "", window.location.pathname + window.location.search);
        }
      },
      setScrollEvent = function() {
        fn = function() {
          win.clearTimeout(windowScrollTimeOut);
          windowScrollTimeOut = window.setTimeout( function() {
            if(getScrollTop() <= Config.hidePosition) {
              if(button === null) return;
              displayed = false;
              button.className = "scrollhide";
            } else if(displayed == false) {
              displayed = true;
              if(button === null) createButton();
              button.className = "";
            }
          }, 50);
        };
        win.onscroll = fn;
      },
      createButton = function() {
        button = document.createElement("a");
        button.id = Config.wrapperId;
        button.title = Config.title;
        var span = document.createElement("span");
        span.innerHTML = Config.text;
        button.appendChild(span);
        document.body.appendChild(button);
        button.onclick = function() {
          step = Math.round(getScrollTop() / Config.time * wait);
          if(Config.time == 0) window.scrollTo(0, 0);
          else doScroll();
          return false;
        }
      };

      return {
        /**
         * Works only for body with class=[Config.ns]
         * @param  {Object} cfg custom configuration
         */
        init : function(cfg) {
          IGCMS.initCfg(Config, cfg);
          var css = '/* scrolltopable.js */'
          + 'a#scrolltop { font-family: "Times new roman", serif; position: fixed; right: 0; bottom: 0; text-decoration: none; background: rgba(0, 0, 0, 0.45); padding: 0.5em; font-size: 1.75rem; margin: 0.75rem; display: block; color: white; width: 1em; text-align: center; height: 1em; border-radius: 1em; z-index: 100; cursor: pointer }'
          + 'a#scrolltop:hover { background: rgba(0, 0, 0, 0.65) }'
          + 'a#scrolltop span { position: relative; top: -0.05em; font-size: 2.3rem; }'
          + 'a#scrolltop.scrollhide { display: none; }';
          IGCMS.appendStyle(css);
          setScrollEvent();
        }
      }
   };

   win.IGCMS.ScrollTopable = new ScrollTopable();

})(window);
