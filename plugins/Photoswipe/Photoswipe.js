(function() {

  if(typeof IGCMS === "undefined") throw "IGCMS is not defined";

  var Config = {};
  Config.ns = "photoswipe";
  Config.buttonValue = "Get form URL";
  Config.hint = "Ctrl+C";

  var openPhotoSwipe = function (e) {
    var index = this.index
    var links = this.links
    e.preventDefault()
    var options = {
      index: index,
      shareEl: false,

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
    var gallery = new PhotoSwipe(pswpElement, PhotoSwipeUI_Default, items, options)
    gallery.init()
    return false
  }
})();

document.body.innerHTML += '<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">'
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

var pswpElement = document.querySelectorAll('.pswp')[0]
var galleries = document.querySelectorAll('.gallery')

for (var i = 0; i < galleries.length; i++) {
  var gallery = galleries[i]
  var links = gallery.getElementsByTagName('a')
  for (var j = 0; j < links.length; j++) {
    var link = links[j]
    var params = {
      index: j,
      gallery: gallery,
      links: links
    }
    link.addEventListener("click", openPhotoSwipe.bind(params), false)
  }
}

