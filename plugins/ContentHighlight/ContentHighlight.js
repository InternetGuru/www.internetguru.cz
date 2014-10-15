// hljs.initHighlightingOnLoad();

var co = document.getElementsByTagName("code");
for (var i = 0; i < co.length; i++) {
  if(co[i].classList.contains("nohighlight")) continue;
  hljs.highlightBlock(co[i]);
}