(function(){

  require("IGCMS", function () {

    function hasObjectChild(e) {
      for (var i = 0; i < e.childNodes.length; i++) {
        if (e.childNodes[i].nodeName.toLowerCase() == "object") return true;
        if (e.childNodes[i].hasChildNodes()) return hasObjectChild(e.childNodes[i]);
      }
      return false;
    }

    function fixObjectInHref() {
      var anchors = document.getElementsByTagName("a");
      for (var i = 0; i < anchors.length; i++) {
        if (hasObjectChild(anchors[i])) anchors[i].className += " obj";
      }
      var css = 'html[data-useragent*=\'Trident\'] a.obj, html[data-useragent*=\'MSIE\'] a.obj{position:relative;display:inline-block} html[data-useragent*=\'Trident\'] a.obj:after, html[data-useragent*=\'MSIE\'] a.obj:after{content:\'\';position:absolute;left:0;top:0;height:100%;width:100%;background:url("data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7")}';
      IGCMS.appendStyle(css);
    }

    fixObjectInHref();
  })
})()
