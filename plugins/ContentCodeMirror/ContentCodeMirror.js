// CodeMirror Configuration
// http://codemirror.net/doc/manual.html
var ta = document.getElementsByTagName("textarea");
for (var i = 0; i < ta.length; i++) {
  if(ta[i].className.match(/\bxml\b/)) {
    CodeMirror.fromTextArea(ta[i],{
        keyMap:"sublime",
        theme:"tomorrow-night-eighties",
        lineNumbers: true,
        mode:"xml",
        width:"100%",
        lineWrapping: true,
        tabSize: 2,
        extraKeys: {
        "Tab": function(cm){
           cm.replaceSelection("  " , "end");
         }
        }
      }
    );
  }
}