// CodeMirror Configuration
// http://codemirror.net/doc/manual.html
var ta = document.getElementsByTagName("textarea");
for (var i = 0; i < ta.length; i++) {
  var myTextArea = ta[i];
  var myCodeMirror = CodeMirror(function(elt) {
    myTextArea.parentNode.replaceChild(elt, myTextArea);
  }, {
    value: myTextArea.value,
    keyMap:"sublime",
    theme:"tomorrow-night-eighties",
    lineNumbers: true,
    mode: myTextArea.className,
    width:"100%",
    lineWrapping: true,
    tabSize: 2,
    styleActiveLine: true,
    autoCloseTags: true,
    // viewportMargin: Infinity,
    extraKeys: {
      "Tab": function(cm){cm.replaceSelection("  " , "end");}
    }
  });
}
