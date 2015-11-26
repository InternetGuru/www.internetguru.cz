(function(win){

  var Config = {};
  Config.appDisable = "×";
  Config.appDisableTitle = "Deaktivovat CodeMirror (F4)";
  Config.appEnable = "Aktivovat CodeMirror";
  Config.appEnableTitle = "F4";
  Config.format = "Formátovat";
  Config.formatTitle = "Ctrl+Shift+F";
  Config.fullscreenDisable = "▫";
  Config.fullscreenDisableTitle = "Obnovit (Shift+F11)";
  Config.fullscreenEnable = "□";
  Config.fullScreenEnableTitle = "Maximalizovat (Shift+F11)";
  Config.find = "Najít";
  Config.findTitle = "Ctrl+F";
  Config.replace = "Nahradit";
  Config.replaceTitle = "Ctrl+H";
  Config.appName = "CodeMirror";

  var SyntaxCodeMirror = function() {
    // private
    var
    cm = null,
    scm = null,
    textarea = null,
    visible = true,
    active = true,
    activateUl = null,
    appendButton = function(text, ul) {
      var li = document.createElement("li");
      ul.appendChild(li);
      var b = document.createElement("button");
      li.appendChild(b);
      b.type = "button";
      b.textContent = text;
      return b;
    },
    toggleApp = function() {
      if(cm.getOption("fullScreen")) toggleFullScreen(cm);
      if(active) {
        cm.toTextArea();
        activateUl.style.display = "";
        textarea.focus();
      } else {
        _init();
        activateUl.style.display = "none";
        cm.focus();
      }
      active = !active;
    },
    getSelectedRange = function(c) {
      var start = c.getCursor(true),
          end = c.getCursor(false);
      if(start == end) { // all
        return { from: {line: 0, ch: 0}, to: {line: c.lineCount()} }
      }
      return { from: c.getCursor(true), to: c.getCursor(false) };
    },
    autoFormatSelection = function(c) {
      var range = getSelectedRange(c);
      c.autoFormatRange(range.from, range.to);
    },
    autoIndentSelection = function(c) {
      var range = getSelectedRange(c);
      c.autoIndentRange(range.from, range.to);
    },
    scrollToCursor = function(c) {
      var cursor = c.getCursor();
      var line = cursor.line;
      var char = cursor.ch;
      var range = getSelectedRange(c);
      c.setCursor({line:line,ch:char});
      var myHeight = c.getScrollInfo().clientHeight;
      var coords = c.charCoords({line: line, ch: char}, "global");
      win.scrollTo(0, (coords.top + coords.bottom - myHeight) / 2);
      c.setSelection(range.from, range.to);
    },
    toggleFullScreen = function(c, off) {
      if(typeof off === "undefined") off = false;
      if(!active) return;
      if(c.getOption("fullScreen") || off) {
        c.setOption("fullScreen", false);
        fullScreenButton.textContent = Config.fullscreenEnable;
        fullScreenButton.title = Config.fullScreenEnableTitle;
      } else {
        c.setOption("fullScreen", true);
        fullScreenButton.textContent = Config.fullscreenDisable;
        fullScreenButton.title = Config.fullscreenDisableTitle;
      }
    },
    scrollWin = function(step) {
      var doc = document.documentElement;
      var top = (win.pageYOffset || doc.scrollTop)  - (doc.clientTop || 0);
      var height = document.body.scrollHeight;
      var scrollTo = top + step;
      if(scrollTo < 0 || scrollTo > height ) return;
      win.scrollTo(0, scrollTo);
    },
    fireEvents = function() {
      cm.on("change",function(cm,change) {
        if(!IGCMS.Editable) return;
        var form = IGCMS.Editable.getParentForm(textarea);
        if(!form || !form.classList.contains(IGCMS.Editable.getEditableClass())) return;
        IGCMS.Editable.setModified();
      });
      win.onkeydown = function(e) {
        var key;
        var isShift;
        if (win.event) {
          key = win.event.keyCode;
          isShift = !!win.event.shiftKey; // typecast to boolean
          isCtrl = !!win.event.ctrlKey; // typecast to boolean
        } else {
          key = e.which;
          isShift = !!e.shiftKey;
          isCtrl = !!e.ctrlKey;
        }
        switch (key) {
          // F4
          case 115:
          toggleApp(cm);
          break;
          // Shift + F11
          case 122:
          if(isShift) {
            toggleFullScreen(cm);
            cm.focus();
            break;
          }
          // Tab, Shift+Tab
          case 9:
          toggleFullScreen(cm, true);
          default: return true;
        }
        return false;
      }
    },
    initEditor = function() {
      cm = CodeMirror.fromTextArea(textarea, {
        tabMode: "default",
        keyMap:"sublime",
        theme:"tomorrow-night-eighties",
        lineNumbers: true,
        mode: textarea.classList.item(1),
        width:"100%",
        lineWrapping: true,
        matchTags: {bothTags: true},
        //tabSize: 2,
        styleActiveLine: true,
        styleSelectedText: true,
        autoCloseTags: {
          whenClosing: true,
          whenOpening: false
        },
        extraKeys: CodeMirror.normalizeKeyMap({
          "Tab": false,
          "Shift-Tab": false,
          "Ctrl-Up": function(c) {
            if(c.getOption("fullScreen"))
              c.execCommand("scrollLineUp");
            else scrollWin(-32);
          },
          "Ctrl-Down": function(c) {
            if(c.getOption("fullScreen"))
              c.execCommand("scrollLineDown");
            else scrollWin(32);
          },
          "Ctrl--": "toggleComment",
          "Ctrl-G": "gotoLine",
          "Ctrl-E": "deleteLine",
          "End": "goLineRight",
          "Home": "goLineLeftSmart",
          "Ctrl-Shift-F": function(c) {
            autoFormatSelection(c);
          },
          "Ctrl-Shift-I": function(c) {
            autoIndentSelection(c);
          },
          "F3": function(c) {
            c.execCommand("findNext");
            scrollToCursor(c);
          },
          "Shift-F3": function(c) {
            c.execCommand("findPrev");
            scrollToCursor(c);
          },
        })
      });
    },
    initActivateButton = function() {
      activateUl = document.createElement("ul");
      var activateButton = appendButton(Config.appEnable, activateUl);
      activateButton.title = Config.appEnableTitle;
      textarea.parentNode.insertBefore(activateUl, textarea);
      activateUl.style.display = "none";
      activateButton.addEventListener("click", toggleApp, false);
    },
    initUserMenu = function() {
      var ul = document.createElement("ul");
      ul.className="codemirror-user-controll";

      findButton = appendButton(Config.find, ul);
      findButton.title = Config.findTitle;
      replaceButton = appendButton(Config.replace, ul);
      replaceButton.title = Config.replaceTitle;
      formatButton = appendButton(Config.format, ul);
      formatButton.title = Config.formatTitle;
      fullScreenButton = appendButton(Config.fullscreenEnable, ul);
      fullScreenButton.title = Config.fullScreenEnableTitle;
      disableButton = appendButton(Config.appDisable, ul);
      disableButton.title = Config.appDisableTitle;
      textarea.nextSibling.insertBefore(ul, textarea.nextSibling.firstChild);

      findButton.onclick = function() {
        CodeMirror.commands.find(cm);
      }
      replaceButton.onclick = function() {
        CodeMirror.commands.replace(cm);
      }
      fullScreenButton.onclick = function() {
        toggleFullScreen(cm);
      }
      formatButton.onclick = function() {
        autoFormatSelection(cm);
      }
      disableButton.onclick = function() {
        toggleApp(cm);
      }
    },
    _init = function() {
      initEditor();
      initUserMenu();
    }
    // public
    return {
      init : function(newTextarea, newScm) {
        // initCfg(cfg);
        scm = newScm;
        textarea = newTextarea;
        _init();
        initActivateButton();
        fireEvents();
      },
      scrollToCursor: scrollToCursor
    }
  };

  var textareas = document.querySelectorAll('textarea.codemirror');
  var cm = new SyntaxCodeMirror();
  cm.init(textareas[0], cm);
  win.IGCMS.CodeMirror = cm;

})(window);