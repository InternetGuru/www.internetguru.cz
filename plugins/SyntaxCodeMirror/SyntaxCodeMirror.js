(function(window){

  // CodeMirror Configuration
  // http://codemirror.net/doc/manual.html
  var cm = false
  var TextArea = document.getElementsByTagName("textarea")[0];
  var visible = true;
  var appDisable = "×";
  var appDisableTitle = "Deaktivovat CodeMirror (F4)";
  var appEnable = "Aktivovat CodeMirror";
  var appEnableTitle = "F4";
  var format = "Formátovat";
  var formatTitle = "Ctrl+Alt+F";
  var fullscreenDisable = "▫";
  var fullscreenDisableTitle = "Obnovit (Shift+F11)";
  var fullscreenEnable = "□";
  var fullScreenEnableTitle = "Maximalizovat (Shift+F11)";
  var find = "Najít";
  var findTitle = "Ctrl+F";
  var replace = "Nahradit";
  var replaceTitle = "Ctrl+H";
  var appName = "CodeMirror";

  init(TextArea);

  var ul = document.createElement("ul");
  activateButton = appendButton(appEnable, ul);
  TextArea.parentNode.insertBefore(ul, TextArea);
  ul.style.display = "none";
  activateButton.onclick = function() {
    toggleApp(cm);
  }

  function userInit() {
    var ul = document.createElement("ul");
    ul.className="codemirror-user-controll";

    findButton = appendButton(find, ul);
    findButton.title = findTitle;
    replaceButton = appendButton(replace, ul);
    replaceButton.title = replaceTitle;
    formatButton = appendButton(format, ul);
    formatButton.title = formatTitle;
    fullScreenButton = appendButton(fullscreenEnable, ul);
    fullScreenButton.title = fullScreenEnableTitle;
    disableButton = appendButton(appDisable, ul);
    disableButton.title = appDisableTitle;
    TextArea.nextSibling.insertBefore(ul, TextArea.nextSibling.firstChild);

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
  }

  function toggleApp(cm) {
    if(activateButton.parentNode.parentNode.style.display == "none") {
      cm.toTextArea();
      activateButton.parentNode.parentNode.style.display = "initial";
      TextArea.focus();
    } else {
      cm = init(cm.getTextArea());
      activateButton.parentNode.parentNode.style.display = "none";
      cm.focus();
    }
  }

  function appendButton(text, ul) {
    var li = document.createElement("li");
    ul.appendChild(li);
    var b = document.createElement("button");
    li.appendChild(b);
    b.type = "button";
    b.innerText = text;
    return b;
  }

  function getSelectedRange(c) {
    var start = c.getCursor(true),
        end = c.getCursor(false);
    if(start == end) { // all
      return { from: {line: 0, ch: 0}, to: {line: c.lineCount()} }
    }
    return { from: c.getCursor(true), to: c.getCursor(false) };
  }

  function autoFormatSelection(c) {
    var range = getSelectedRange(c);
    c.autoFormatRange(range.from, range.to);
  }

  function autoIndentSelection(c) {
    var range = getSelectedRange(c);
    c.autoIndentRange(range.from, range.to);
  }

  function scrollToCursor(c) {
    var cursor = c.getCursor();
    var line = cursor.line;
    var char = cursor.ch;
    cm.setCursor({line:line,ch:char});
    var myHeight = cm.getScrollInfo().clientHeight;
    var coords = cm.charCoords({line: line, ch: char}, "global");
    window.scrollTo(0, (coords.top + coords.bottom - myHeight) / 2);
  }

  function toggleFullScreen(c) {
    c.setOption("fullScreen", !c.getOption("fullScreen"));
    if(c.getOption("fullScreen")) {
      fullScreenButton.innerText = fullscreenDisable;
      fullScreenButton.title = fullscreenDisableTitle;
    } else {
      fullScreenButton.innerText = fullscreenEnable;
      fullScreenButton.title = fullScreenEnableTitle;
    }
  }

  window.onkeydown = function(e) {
    var key;
    var isShift;
    if (window.event) {
      key = window.event.keyCode;
      isShift = !!window.event.shiftKey; // typecast to boolean
    } else {
      key = ev.which;
      isShift = !!ev.shiftKey;
    }
    switch (e.keyCode) {
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
      default: return true;
    }
    return false;
  }

  function init(textarea) {
    cm = CodeMirror.fromTextArea(TextArea, {
      tabMode: "default",
      keyMap:"sublime",
      theme:"tomorrow-night-eighties",
      lineNumbers: true,
      mode: TextArea.classList.item(0),
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
      extraKeys: {
        "Tab": false,
        "Shift-Tab": false,
        "Ctrl--": "toggleComment",
        "Ctrl-G": "gotoLine",
        "Ctrl-E": "deleteLine",
        "End": "goLineRight",
        "Home": "goLineLeft",
        "Ctrl-Alt-F": function(c) {
          autoFormatSelection(c);
        },
        "Ctrl-Alt-I": function(c) {
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
      }
    });
    userInit();
    cm.on("change",function(cm,change) {
      if(!Editable) return;
      var form = Editable.getParentForm(TextArea);
      if(!form || !form.classList.contains(Editable.getEditableClass())) return;
      Editable.setModified();
    });
    return cm;
  }

})(window);