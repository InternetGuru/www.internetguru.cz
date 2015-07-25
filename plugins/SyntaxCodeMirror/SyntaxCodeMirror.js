(function(window){

  // CodeMirror Configuration
  // http://codemirror.net/doc/manual.html
  var cm = false
  var TextArea = document.getElementsByTagName("textarea")[0];
  var visible = true;
  var on = "Vypnout CodeMirror";
  var off = "Zapnout CodeMirror";

  init(TextArea);

  var ul = document.createElement("ul");
  toggleButton = appendButton(on, ul);
  TextArea.parentNode.insertBefore(ul, TextArea);

  toggleButton.onclick = function() {
    if(visible) {
      cm.toTextArea();
      toggleButton.innerText = off;
    }
    else {
      init(cm.getTextArea());
      toggleButton.innerText = on;
    }
    visible = !visible;
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

  function init(textarea) {
    cm = CodeMirror.fromTextArea(TextArea, {
      tabMode: "default",
      keyMap:"sublime",
      theme:"tomorrow-night-eighties",
      lineNumbers: true,
      mode: TextArea.classList.item(0),
      width:"100%",
      lineWrapping: true,
      //tabSize: 2,
      //styleActiveLine: true,
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
        "F11": function(c) {
          c.setOption("fullScreen", !cm.getOption("fullScreen"));
        },
      }
    });
    cm.on("change",function(cm,change) {
      if(!Editable) return;
      var form = Editable.getParentForm(TextArea);
      if(!form || !form.classList.contains(Editable.getEditableClass())) return;
      Editable.setModified();
    });
  }

})(window);