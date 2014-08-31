(function(window){

  // CodeMirror Configuration
  // http://codemirror.net/doc/manual.html
  var TextArea = document.getElementsByTagName("textarea")[0];
  var myCodeMirror = CodeMirror.fromTextArea(TextArea,{
      tabMode: "default",
      keyMap:"sublime",
      theme:"tomorrow-night-eighties",
      lineNumbers: true,
      mode: TextArea.className,
      width:"100%",
      lineWrapping: true,
      //tabSize: 2,
      styleActiveLine: true,
      styleSelectedText: true,
      autoCloseTags: true,
      extraKeys: {
        "Tab": false,
        "Shift-Tab": false
      }
  });

})(window);