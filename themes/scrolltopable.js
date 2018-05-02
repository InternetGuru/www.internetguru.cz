(function() {

  require("IGCMS", function () {

    var Config = {};
    Config.ns = "scrolltopable"; // wrapper element (a) id value
    Config.wrapperId = "scrolltop"; // wrapper element (a) id value
    Config.time = 250; // int ms to scroll
    Config.text = "^"; // text content
    Config.title = "Nahoru"; // text content
    Config.hidePosition = 200; // display / hide button int px from top
    Config.scrollhideClass = "scrollhide";
    Config.noprintClass = "noprint";
    Config.visibleClass = Config.ns + "-visible";
    Config.hiddenClass = Config.ns + "-hidden";
    Config.inactiveTimeout = 10; // s
    Config.deltaY = 200;
    Config.actionTimeout = 200; // ms
    Config.animationSpeed = 250; // ms

    var Scrolltopable = function () {

      var scrollTimeOut = null,
        windowScrollTimeOut = null,
        button = null,
        displayed = false,
        step = null,
        wait = 10,
        lastScrollTop = 0,
        getScrollTop = function () {
          return document.body.scrollTop || document.documentElement.scrollTop;
        },
        doScroll = function () {
          if (getScrollTop() !== 0) {
            window.scrollBy(0, -1 * step);
            scrollTimeOut = setTimeout(function () {
              doScroll();
            }, wait);
          } else {
            clearTimeout(scrollTimeOut);
            window.history.replaceState("", "", window.location.pathname + window.location.search);
          }
        },
        hideButton = function () {
          displayed = false;
          button.className = Config.noprintClass + " " + Config.hiddenClass;
        },
        showButton = function () {
          displayed = true;
          button.className = Config.noprintClass + " " + Config.visibleClass;
        },
        processScroll = function () {
          window.clearTimeout(windowScrollTimeOut);
          windowScrollTimeOut = window.setTimeout(function () {
            var scrollTop = getScrollTop();
            if (Math.abs(scrollTop - lastScrollTop) < Config.deltaY) {
              lastScrollTop = scrollTop;
              return;
            }
            if (scrollTop <= Config.hidePosition || scrollTop - lastScrollTop > 0) {
              hideButton();
            } else if (displayed === false) {
              showButton();
            }
            lastScrollTop = scrollTop;
          }, Config.actionTimeout);
        },
        setScrollEvent = function () {
          window.addEventListener('scroll', processScroll, false);
        },
        createButton = function () {
          button = document.createElement("a");
          button.id = Config.wrapperId;
          button.title = Config.title;
          button.className = Config.noprintClass + " " + Config.hiddenClass;
          var span = document.createElement("span");
          span.innerHTML = Config.text;
          button.appendChild(span);
          document.body.appendChild(button);
          button.onclick = function () {
            step = Math.round(getScrollTop() / Config.time * wait);
            if (Config.time === 0) window.scrollTo(0, 0);
            else doScroll();
            return false;
          }
        };

      return {
        /**
         * Works only for body with class=[Config.ns]
         * @param  {Object} cfg custom configuration
         */
        init: function (cfg) {
          IGCMS.initCfg(Config, cfg);
          var css = '/* scrolltopable.js */'
            + 'a#' + Config.wrapperId + ' { '
            + '  font-family: "Times new roman", serif;'
            + '  position: fixed;'
            + '  right: 0;'
            + '  bottom: 0;'
            + '  text-decoration: none;'
            + '  background: rgba(0, 0, 0, 0.45);'
            + '  font-size: 1.75rem;'
            + '  margin: 0.75rem;'
            + '  display: block;'
            + '  color: white;'
            + '  width: 1em;'
            + '  text-align: center;'
            + '  height: 0;'
            + '  overflow: hidden;'
            + '  z-index: 100;'
            + '  cursor: pointer;'
            + '  line-height: 1;'
            + '  opacity: 0;'
            + '  transition: opacity ' + Config.animationSpeed + 'ms ease;'
            + '}'
            + 'a#' + Config.wrapperId + '.' + Config.visibleClass + ' { '
            + '  padding: 0.4em;'
            + '  height: 1em;'
            + '  opacity: 1;'
            + '}'
            + 'a#' + Config.wrapperId + ':hover { background: rgba(0, 0, 0, 0.65) }'
            + 'a#' + Config.wrapperId + ' span { font-size: 2.3rem; }';
          IGCMS.appendStyle(css);
          createButton();
          setScrollEvent();
        }
      }
    };

    window.IGCMS.Scrolltopable = new Scrolltopable();
  })
})()
