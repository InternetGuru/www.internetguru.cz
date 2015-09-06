(function(win){

  function appendStyle(css) {
    var elem=document.createElement('style');
    elem.setAttribute('type', 'text/css');
    if(elem.styleSheet && !elem.sheet)elem.styleSheet.cssText=css;
    else elem.appendChild(document.createTextNode(css));
    document.getElementsByTagName('head')[0].appendChild(elem);
  }

  function appendStyleLink(url) {
    var head = document.head || document.getElementsByTagName('head')[0];
    link = document.createElement("link");
    link.type = "text/css";
    link.rel = "stylesheet";
    link.href = url;
    head.appendChild(link);
  }

  function initHl() {
    for(var i = 0; i < toHighlight.length; i++) {
      hljs.highlightBlock(toHighlight[i]);
    }
  }

  var found = false;
  var toHighlight = [];

  var co = document.getElementsByTagName("code");
  for (var i = 0; i < co.length; i++) {
    if(co[i].classList.contains("nohighlight")) continue;
    toHighlight.push(co[i]);
    found = true;
  }

  if(found) {
    appendStyleLink("lib/highlight.js/src/styles/tomorrow.css");
    appendStyle('code.hljs { display: inline; font-family: "Emilbus Mono","Lucida Console",monospace; padding: 0 0.3em; background: #f0f0f0; } pre code.hljs {display: block; padding: 0.5em; }');
    var head = document.head || document.getElementsByTagName('head')[0];
    script = document.createElement("script");
    script.type = "text/javascript";
    script.src = "lib/highlight.pack.js";
    script.onload = initHl;
    head.appendChild(script);
  }


})(window);