// CodeMirror Configuration
// http://codemirror.net/doc/manual.html
var TextArea = document.getElementsByTagName("textarea")[0];
var myCodeMirror = CodeMirror.fromTextArea(TextArea,{
    keyMap:"sublime",
    theme:"tomorrow-night-eighties",
    lineNumbers: true,
    mode: TextArea.className,
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