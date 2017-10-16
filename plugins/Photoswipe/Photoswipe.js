(function() {

  if(typeof IGCMS === "undefined") throw "IGCMS is not defined";

  var Config = {};
  Config.ns = "pswp";
  Config.galleryClassSelector = ".gallery";
  Config.shareEl = false;
  Config.pswpElement = '<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">'
    + '    <div class="pswp__bg"></div>'
    + '    <div class="pswp__scroll-wrap">'
    + '        <div class="pswp__container">'
    + '            <div class="pswp__item"></div>'
    + '            <div class="pswp__item"></div>'
    + '            <div class="pswp__item"></div>'
    + '        </div>'
    + '        <div class="pswp__ui pswp__ui--hidden">'
    + '            <div class="pswp__top-bar">'
    + '                <div class="pswp__counter"></div>'
    + '                <button class="pswp__button pswp__button--close" title="Close (Esc)"></button>'
    + '                <button class="pswp__button pswp__button--share" title="Share"></button>'
    + '                <button class="pswp__button pswp__button--fs" title="Toggle fullscreen"></button>'
    + '                <button class="pswp__button pswp__button--zoom" title="Zoom in/out"></button>'
    + '                <div class="pswp__preloader">'
    + '                    <div class="pswp__preloader__icn">'
    + '                      <div class="pswp__preloader__cut">'
    + '                        <div class="pswp__preloader__donut"></div>'
    + '                      </div>'
    + '                    </div>'
    + '                </div>'
    + '            </div>'
    + '            <div class="pswp__share-modal pswp__share-modal--hidden pswp__single-tap">'
    + '                <div class="pswp__share-tooltip"></div> '
    + '            </div>'
    + '            <button class="pswp__button pswp__button--arrow--left" title="Previous (arrow left)">'
    + '            </button>'
    + '            <button class="pswp__button pswp__button--arrow--right" title="Next (arrow right)">'
    + '            </button>'
    + '            <div class="pswp__caption">'
    + '                <div class="pswp__caption__center"></div>'
    + '            </div>'
    + '        </div>'
    + '    </div>'
    + '</div>'

  var Pswp = function() {

    var initPswp = function () {
      document.body.innerHTML += Config.pswpElement
    }

    var openPswp = function (e) {
      var index = this.index
      var links = this.links
      e.preventDefault()
      var options = {
        index: index,
        shareEl: Config.shareEl,
      }
      var items = []
      for (var j = 0; j < links.length; j++) {
        var link = links[j]
        var item = {
          src: link.href,
          w: link.getAttribute("data-target-width"),
          h: link.getAttribute("data-target-height")
        }
        items.push(item)
      }
      // Initializes and opens PhotoSwipe
      var gallery = new PhotoSwipe(this.pswpElement, PhotoSwipeUI_Default, items, options)
      gallery.init()
      return false
    }

    return {
      init : function (cfg) {
        IGCMS.initCfg(Config, cfg);
        initPswp()
        var pswpElement = document.querySelectorAll("." + Config.ns)[0]
        var galleries = document.querySelectorAll(Config.galleryClassSelector)
        for (var i = 0; i < galleries.length; i++) {
          var gallery = galleries[i]
          var links = gallery.getElementsByTagName('a')
          for (var j = 0; j < links.length; j++) {
            var link = links[j]
            var params = {
              index: j,
              gallery: gallery,
              links: links,
              pswpElement: pswpElement
            }
            link.addEventListener("click", openPswp.bind(params), false)
          }
        }
      }
    }

  }

  IGCMS.Pswp = new Pswp()
})();

