(function(win) {

  var Navigation = function() {

    var
    Config = {},
    open = false,
    active = -1,
    textNavigValue = "",
    list = null,

    updateQueryStringParameter = function(uri, key, value) {
      var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
      var separator = uri.indexOf('?') !== -1 ? "&" : "?";
      if (uri.match(re)) {
        return uri.replace(re, '$1' + key + "=" + value + '$2');
      }
      else {
        return uri + separator + key + "=" + value;
      }
    },
    clone = function(obj) {
        if (null == obj || "object" != typeof obj) return obj;
        var copy = obj.constructor();
        for (var attr in obj) {
            if (obj.hasOwnProperty(attr)) copy[attr] = obj[attr];
        }
        return copy;
    },
    initCfg = function(cfg) {
      if(typeof cfg === 'undefined') return;
      for(var attr in cfg) {
        if(!Config.hasOwnProperty(attr)) continue;
        Config[attr] = cfg[attr];
      }
    },
    initStructure = function(){
      list = document.createElement("ul");
      list.className = "navigList";
      textNavig = document.createElement("input");
      textNavig.autocomplete = "off";
      if(Config.navig.tabIndex) textNavig.tabIndex = Config.navig.tabIndex;
      textNavig.name = Config.navig.name;
      textNavig.type = "text";
      textNavig.className = "completable-input";
      Config.navig.parentNode.replaceChild(textNavig, Config.navig);
      Config.navig = textNavig;
      Config.navig.parentNode.appendChild(list);
      updateSize();
    },
    initEvents = function() {
      Config.navig.addEventListener("focus", inputText, false);
      Config.navig.addEventListener("input", inputText, false);
      Config.navig.addEventListener("blur", close, false);
      win.addEventListener("resize", updateSize, false);
      Config.navig.addEventListener("keydown", processKey, false);
    },
    close = function(e) {
      list.innerHTML = "";
      textNavigValue = "";
      open = false;
      active = -1;
    },
    processKey = function(e) {
      switch(e.keyCode) {
        case 13: //enter
          if(!open) return;
          redir();
          e.preventDefault();
        break;
        case 27: //esc
          Config.navig.value = textNavigValue;
          close();
        break;
        case 38: //up
          if(active - 1 <= -1) {
            Config.navig.value = textNavigValue;
            close();
            e.preventDefault();
            return;
          }
          list.childNodes[active].classList.remove("active");
          list.childNodes[--active].classList.add("active");
          Config.navig.value = list.childNodes[active].innerText;
          e.preventDefault();
        break;
        case 40: //down
          if(!open) {
            inputText(null);
          }
          if(active + 1 >= list.childNodes.length) return;
          if(active != -1) list.childNodes[active].classList.remove("active");
          list.childNodes[++active].classList.add("active");
          Config.navig.value = list.childNodes[active].innerText;
          e.preventDefault();
        break;
      }
    },
    redir = function(path) {
      if(typeof path == "undefined") {
        var clickEvent = document.createEvent ('MouseEvents');
        clickEvent.initEvent("mousedown", true, true);
        list.childNodes[active].dispatchEvent(clickEvent);
        return;
      }
      win.location.href = updateQueryStringParameter(win.location.href, "Admin", path);
    },
    inputText = function(e) {
      close();
      open = true;
      var value = e === null ? Config.navig.value : e.target.value;
      textNavigValue = value;
      var fs = filter(Config.files, value);
      update(fs);
    },
    updateSize = function() {
      list.style.width = Config.navig.offsetWidth+"px";
    },
    filter = function(arr, value) {
      var fs = [];
      var testPatt = new RegExp(value, "gi");
      var matchPatt = new RegExp("("+value+")", "gi");
      for(var i = 0; i < arr.length; i++) {
        var r = doFilter(arr[i], value, testPatt, matchPatt);
        if(typeof r != "undefined") fs.push(r);
      }
      return fs;
    },
    doFilter = function(f, value, testPatt, matchPatt) {
      try {
        if(!f.val.match(testPatt)) return;
        r = {};
        r.val = f.val.replace(matchPatt, "<strong>$1</strong>");
        r.path = f.path;
        r.user = f.user;
        return r;
      } catch(e) {}
    },
    update = function(fs) {
      first = true;
      for(var i = 0; i < fs.length; i++) {
        var li = document.createElement("li");
        if(first) {
          first = false;
        }
        li.innerHTML = fs[i].val;
        if(fs[i].user) li.classList.add("user");
        li.onmousedown = (function() {
          var localPath = fs[i].path;
          return function() {
            redir(localPath);
          }
        })();
        list.appendChild(li);
      }
    }

    // public
    return {
      init : function(cfg) {
        Config.files = {};
        Config.navig = null;
        initCfg(cfg);
        if(Config.navig === null) throw "Config.navig is null";
        initStructure();
        initEvents();
      }
    }
  };


  var found = false;
  var selects = document.getElementsByTagName("select");
  var toInit = [];
  for (var i = 0; i < selects.length; i++) {
    var s = selects[i];
    if(!s.classList.contains("completable")) continue;
    found = true;
    var options = s.getElementsByTagName("option");
    var files = [];
    for (var j = 0; j < options.length; j++) {
      var val = options[j].innerText;
      files.push({
        path : (options[j].value ? options[j].value : options[j].innerText),
        val  : val,
        user : val.indexOf("#user") !== -1
      })
    }
    files.sort(function(a, b) {
       return a.val.localeCompare(b.val);
    });
    toInit.push({files: files, navig: s });
  }

  if(found) appendStyle();

  for (var i = 0; i < toInit.length; i++) {
    navigation = new Navigation();
    navigation.init(toInit[i]);
  }


  function appendStyle() {
    var css = '/* completable.js */'
      + ' .completable-input { width: 35em; max-width: 100%; }'
      + ' ul.navigList {/*overflow-y: scroll; max-height: 10em;*/ position: absolute; background: white; z-index: 100; /*width: 25em; max-width: 100%;*/ margin: 0; padding: 0; list-style: none; box-shadow: 0.2em 0.2em 0.2em #555; }'
      + ' ul.navigList li { margin: 0; padding: 0.25em 0.5em; }'
      + ' ul.navigList li:hover { background: #eee; cursor: pointer; }'
      + ' ul.navigList li.active { background: #ddd; }'
      + ' ul.navigList li.user { background: #E7F6FE; }'
      + ' ul.navigList li.user.active, ul.navigList li.user:hover { background: #D4E5EE; }';
    var style = document.getElementsByTagName('style')[0];
    if(style == undefined) {
      var head = document.head || document.getElementsByTagName('head')[0];
      style = document.createElement('style');
      style.type = 'text/css';
      head.appendChild(style);
    }
    style.appendChild(document.createTextNode(css));
  }

})(window);
