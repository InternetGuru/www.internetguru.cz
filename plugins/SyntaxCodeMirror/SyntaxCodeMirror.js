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
  var li = document.createElement("li");
  ul.appendChild(li);
  var toggleButton = document.createElement("button");
  li.appendChild(toggleButton);
  toggleButton.type = "button";
  toggleButton.innerText = on;
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
        "Ctrl-G": "gotoLine",
        "Ctrl-E": "deleteLine",
        "End": "goLineRight",
        "Home": "goLineLeft",
        "F3": function(c) {
          c.execCommand("findNext");
          scrollToCursor(c);
        },
        "Shift-F3": function(c) {
          c.execCommand("findPrev");
          scrollToCursor(c);
        }
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