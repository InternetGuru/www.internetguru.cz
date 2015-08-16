(function(win) {

  var Completable = function() {

    var
    Config = {},
    open = false,
    active = -1,
    activeElement = null,
    textNavigValue = "",
    list = null,
    key = null,

    getWinHeight = function() {
      var w = window,
        d = document,
        e = d.documentElement,
        g = d.getElementsByTagName('body')[0];
        //x = w.innerWidth || e.clientWidth || g.clientWidth
      return w.innerHeight || e.clientHeight|| g.clientHeight;
    },
    getScrolltop = function() {
      var doc = document.documentElement;
      return (window.pageYOffset || doc.scrollTop)  - (doc.clientTop || 0);
    },
    getOffsetTop = function(e) {
      var bodyRect = document.body.getBoundingClientRect(),
        elemRect = e.getBoundingClientRect();
      return elemRect.top - bodyRect.top;
    },
    findAncestor = function (el, cls) {
      while ((el = el.parentElement) && !el.classList.contains(cls));
      return el;
    }
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
      //Config.navig.addEventListener("focus", inputText, false);
      Config.navig.addEventListener("input", inputText, false);
      Config.navig.addEventListener("blur", close, false);
      Config.navig.form.addEventListener("submit", fillVal, false);
      //list.addEventListener("blur", close, false);
      win.addEventListener("resize", updateSize, false);
      win.addEventListener("scroll", updateSize, false);
      //win.addEventListener("mousedown", function(e){activeElement = e.target}, false);
      //win.addEventListener("mousedown", function(e) {}, false);
      //win.addEventListener("mouseup", function(e){activeElement = null}, false);
      Config.navig.addEventListener("keydown", processKey, false);
    },
    fillVal = function(e) {
      files = Config.files;
      for(var i = 0; i < files.length; i++) {
        if(files[i].defaultVal != Config.navig.value) continue;
        Config.navig.value = files[i].path;
      }
    },
    close = function(e) {
      //if(e && activeElement && activeElement.className == "navigList") return;
      list.innerHTML = "";
      textNavigValue = "";
      open = false;
      active = -1;
    },
    processKey = function(e) {
      switch(e.keyCode) {
        case 27: //esc
          Config.navig.value = textNavigValue;
          close();
        break;
        case 38: //up
          if(typeof list.childNodes[active] !== "undefined")
            list.childNodes[active].classList.remove("active");
          if(active == -1) active = list.childNodes.length;
          if(--active <= -1) {
            Config.navig.value = textNavigValue;
            e.preventDefault();
            return;
          }
          list.childNodes[active].classList.add("active");
          Config.navig.value = list.childNodes[active].dataset.val;
          e.preventDefault();
        break;
        case 40: //down
          if(!open) {
            inputText(null);
          }
          if(typeof list.childNodes[active] !== "undefined")
            list.childNodes[active].classList.remove("active");
          if(active == list.childNodes.length) active = -1;
          if(++active >= list.childNodes.length) {
            Config.navig.value = textNavigValue;
            e.preventDefault();
            return;
          }
          list.childNodes[active].classList.add("active");
          Config.navig.value = list.childNodes[active].dataset.val;
          e.preventDefault();
        break;
        default:
          key = e.keyCode;
          return true;
      }
    },
    inputText = function(e) {
      close();
      open = true;
      var navig = e === null ? Config.navig : e.target;
      var value = navig.value;
      textNavigValue = value;
      var fs = filter(Config.files, value);
      update(fs);
    },
    createSelection = function(navig, start, end) {
      if(navig.createTextRange) {
        var selRange = navig.createTextRange();
        selRange.collapse(true);
        selRange.moveStart('character', start);
        selRange.moveEnd('character', end);
        selRange.select();
        navig.focus();
      } else if(navig.setSelectionRange) {
        navig.focus();
        navig.setSelectionRange(start, end);
      } else if(typeof navig.selectionStart != 'undefined') {
        navig.selectionStart = start;
        navig.selectionEnd = end;
        navig.focus();
      }
    },
    updateSize = function() {
      list.style.width = Config.navig.offsetWidth+"px";
      list.style.maxHeight = (getWinHeight() - getOffsetTop(list) + getScrolltop()) + "px";
    },
    fileMergeSort = function(arr) {
      if (arr.length < 2) return arr;
      var middle = parseInt(arr.length / 2);
      var left   = arr.slice(0, middle);
      var right  = arr.slice(middle, arr.length);
      return merge(fileMergeSort(left), fileMergeSort(right));
    },
    merge = function(left, right) {
      var result = [];
      while (left.length && right.length) {
        if (left[0].priority <= right[0].priority) {
          result.push(left.shift());
        } else {
          result.push(right.shift());
        }
      }
      while (left.length) result.push(left.shift());
      while (right.length) result.push(right.shift());
      return result;
    }
    filter = function(arr, value) {
      var fs = [];
      var testPatt = new RegExp(value, "gi");
      var matchPatt = new RegExp("("+value+")", "gi");
      for(var i = 0; i < arr.length; i++) {
        var r = doFilter(arr[i], value, testPatt, matchPatt);
        if(typeof r != "undefined") {
         fs.push(r);
        }
      }
      fs = fileMergeSort(fs);
      return fs;
    },
    doFilter = function(f, value, testPatt, matchPatt) {
      try {
        if(!f.val.match(testPatt)) return;
        r = {};
        //var priority = f.val.search(matchPatt);
        var priority = 3;
        if(f.path.replace(/^.*[\\\/]/, '').indexOf(value) === 0) priority = 1;
        else {
          var parts = f.path.split(/[ _\/-]/);
          for(var i = 0; i < parts.length; i++) {
            if(parts[i].indexOf(value) !== 0) continue;
            priority = 2;
          }
        }
        r.val = f.val.replace(matchPatt, "<strong>$1</strong>");
        r.priority = priority;
        r.defaultVal = f.defaultVal;
        r.path = f.path;
        r.user = f.user;
        return r;
      } catch(e) {}
    },
    update = function(fs) {
      first = true;
      for(var i = 0; i < fs.length; i++) {
        if(Config.navig.value.length && key !== 8 && fs[i].defaultVal.indexOf(Config.navig.value) == 0) { // 8 is backspace
          var start = Config.navig.value.length;
          var end = fs[i].defaultVal.length;
          Config.navig.value = fs[i].defaultVal;
          createSelection(Config.navig, start, end);
        }
        var li = document.createElement("li");
        if(first) {
          first = false;
        }
        li.innerHTML = fs[i].val;
        if(fs[i].user) li.classList.add("user");
        li.dataset.path = fs[i].path;
        li.dataset.val = fs[i].defaultVal;
        li.onmousedown = (function() {
          var localValue = fs[i].defaultVal;
          var navig = Config.navig;
          return function() {
            navig.value = localValue;
            window.setTimeout(function(){
              navig.focus();
              close();
            }, 50);
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
      var val = options[j].textContent;
      files.push({
        path: (options[j].value ? options[j].value : options[j].textContent),
        priority: 0,
        val: val,
        defaultVal: val,
        user: val.indexOf("#user") !== -1
      })
    }
    toInit.push({files: files, navig: s });
  }

  if(found) appendStyle();

  for (var i = 0; i < toInit.length; i++) {
    completable = new Completable();
    completable.init(toInit[i]);
  }


  function appendStyle() {
    var css = '/* completable.js */'
      + ' .completable-input { width: 35em; max-width: 100%; }'
      + ' ul.navigList {overflow-y: scroll; position: absolute; background: white; z-index: 100; /*width: 25em; max-width: 100%;*/ margin: 0; padding: 0; list-style: none; box-shadow: 0.2em 0.2em 0.2em #555; }'
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
