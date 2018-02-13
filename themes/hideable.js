(function(win) {

  require("IGCMS", function() {

  if(IGCMS.Hideable) return

  var Config = {}

  Config.ns = "hideable"
  Config.expand = "►"
  Config.collapse = "▼"
  Config.expandTitle = "Expand"
  Config.collapseTitle = "Collapse"
  Config.hideableHiddenClass = Config.ns + "-hidden"
  Config.hideableNohideClass = Config.ns + "-nohide"
  Config.hideClass = Config.ns + "-hide"
  Config.switchClass = Config.ns + "-switch"
  Config.noprintClass = "noprint"
  Config.hereClass = Config.ns + "-here"
  Config.afterClass = Config.ns + "-after"
  Config.after = "Details"
  Config.dataHere = "data-" + Config.ns + "-here" // TODO
  Config.dataAfter = "data-" + Config.ns + "-after" // TODO

   var Hideable = function() {

    var inited = false

     var initHideables = function () {
      var hideables = document.querySelectorAll('.' + Config.hideableHiddenClass + ', .' + Config.hideableNohideClass)
      for (var i = 0; i < hideables.length; i++) {
        var link = initToggleButton(hideables[i])
        if (link === null) {
          continue
        }
        if (hideables[i].classList.contains(Config.hideableNohideClass)) {
          continue
        }
        hideables[i].classList.add(Config.hideableNohideClass)
        toggleElement(link)
      }
    }

    var createToggleButton = function (value) {
      var link = document.createElement('a')
      link.href = 'Javascript:void(0)'
      linkButton = document.createElement('span')
      linkButton.innerHTML = Config.collapse
      link.appendChild(linkButton)
      link.title = Config.collapseTitle
      link.classList.add(Config.switchClass)
      link.classList.add(Config.noprintClass)
      link.addEventListener('click', toggle, false)
      link.innerHTML += ' ' + value
      return link
    }

    // TODO data- version
    // var initToggleButton = function (hideable) {
    //   for (var i = hideable.children.length - 1; i >= 0; i--) {
    //     var item = hideable.children[i]
    //     var here = item.getAttribute(Config.dataHere)
    //     var after = item.getAttribute(Config.dataAfter)
    //     if (here) {
    //       var value = (here == Config.dataHere || here === '') ? item.innerHTML : here
    //       var button = createToggleButton(value)
    //       item.innerHTML = ""
    //       item.appendChild(button)
    //       return button
    //     }
    //     if (after) {
    //       var value = (after == Config.dataAfter || after === '') ? item.innerHTML : after
    //       var button = createToggleButton(value)
    //       var el = document.createElement(item.nodeName)
    //       el.appendChild(button)
    //       item.parentNode.insertBefore(el, item.nextSibling)
    //       return button
    //     }
    //   }
    //   return null
    // }

    var initToggleButton = function (hideable) {
      for (var i = hideable.children.length-1; i >= 0; i--) {
        var item = hideable.children[i]
        var here = item.classList.contains(Config.hereClass)
        var after = item.classList.contains(Config.afterClass)
        if (here) {
          var value = item.innerHTML
          var button = createToggleButton(value)
          item.innerHTML = ""
          item.appendChild(button)
          return button
        }
        if (after) {
          var value = Config.after
          var button = createToggleButton(value)
          var el = document.createElement(item.nodeName)
          el.appendChild(button)
          item.parentNode.insertBefore(el, item.nextSibling)
          return button
        }
      }
      return null
    }

    var toggle = function (e) {
      var target = e.currentTarget
      toggleElement(target)
      e.preventDefault()
    }

    var toggleElement = function (link) {
      var e = link.parentNode.parentNode
      var linkButton = link.children[0]
      if (e.classList.contains(Config.hideableNohideClass)) {
        e.classList.remove(Config.hideableNohideClass)
        e.classList.add(Config.hideableHiddenClass)
        linkButton.innerHTML = Config.expand
        link.title = Config.expandTitle
      } else {
        e.classList.remove(Config.hideableHiddenClass)
        e.classList.add(Config.hideableNohideClass)
        linkButton.innerHTML = Config.collapse
        link.title = Config.collapseTitle
      }
      for (var i = e.childNodes.length - 1; i > 0; i--) {
        var ch = e.childNodes[i]
        if (ch.nodeType !== 1) {
          continue
        }
        if (ch.firstChild.classList && ch.firstChild.classList.contains(Config.switchClass)) {
          return
        }
        if (ch.classList.contains(Config.hideableNohideClass)) {
          continue
        }
        if (ch.classList.contains(Config.hideClass)) {
          ch.classList.remove(Config.hideClass)
        } else {
          ch.classList.add(Config.hideClass)
        }
      }
    }

    // public
    return {
      init : function(cfg) {
        if(inited) return;
        IGCMS.initCfg(Config, cfg);
        var css = '/* hideables.js */'
          + ' .' + Config.hideClass + ' { display: none !important; }'
          + ' a.' + Config.switchClass + ' { text-decoration: none;'
          + ' border: none !important;'
          + ' font-family: "Emilbus Mono", "Lucida Console", monospace;'
          + ' font-weight: bold }';
        IGCMS.appendStyle(css);
        initHideables();
        inited = true;
      },
      isInit : function() { return inited; }
    }
  };

   IGCMS.Hideable = new Hideable();

  })

})(window);
