(function(window){

  // CodeMirror Configuration
  // http://codemirror.net/doc/manual.html
  var TextArea = document.getElementsByTagName("textarea")[0];
  var cm = CodeMirror.fromTextArea(TextArea,{
      tabMode: "default",
      keyMap:"sublime",
      theme:"tomorrow-night-eighties",
      lineNumbers: true,
      mode: TextArea.className,
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
        "Ctrl-E": "deleteLine",
        "End": "goLineRight",
        "Home": "goLineLeft"
      }
  });

  cm.on("change",function(cm,change) {
    if(typeof setModified != "function") return;
    setModified();
  });

})(window);