function initToc() {
  var d = document.getElementsByTagName("div");
  var f = false;
  var i = 0;
  for(; i<d.length; i++) {
    if(!d[i].classList.contains("section")) continue;
    f = true;
    break;
  }
  if(!f) return;
  var div = document.createElement("div");
  div.className = "list";
  var toc = document.createElement("dl");
  toc.id = "generated-toc";
  toc.className = "generate_from_h2";
  div.appendChild(toc);
  d[i].parentNode.insertBefore(div,d[i]);
}
initToc();