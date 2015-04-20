
(function(window){

  var EXPAND = "[+]";
  var COLLAPSE = "[–]";
  var HIDEABLE_CLASS = "hideable";
  var HIDE_CLASS = "hide";
  var NO_HIDE_CLASS = "nohide";
  var HIDDEN_CLASS = "hidden";

  function getHideables() {
    if (document.querySelectorAll) return document.querySelectorAll("." + HIDEABLE_CLASS);
    var hideables = [];
    var allElements = document.getElementsByTagName("*");
    for(var i = 0; i < allElements.length; i++) {
      if(allElements[i].classList.contains(HIDEABLE_CLASS)) hideables.push(allElements[i]);
    }
    return hideables;
  }

  function appendStyle() {
    var css = '/* hideables.js */'
      + ' .hide {display: none;}'
      + ' .switch {text-decoration: none;'
      + ' border: none;'
      + ' font-family: "Emilbus Mono","Lucida Console",monospace;'
      + ' font-weight: bold; color: black; }';
    var style = document.getElementsByTagName('style')[0];
    if(style == undefined) {
      var head = document.head || document.getElementsByTagName('head')[0];
      style = document.createElement('style');
      style.type = 'text/css';
      head.appendChild(style);
    }
    style.appendChild(document.createTextNode(css));
  }

  function toggleHideables() {
    var hideables = getHideables();
    for(var i = 0; i < hideables.length; i++) {
      var firstElement = hideables[i].children[0];
      var link = document.createElement("a");
      link.href = "Javascript:void(0);";
      link.innerHTML = COLLAPSE;
      link.classList.add("switch")
      link.addEventListener("click",toggle,false);
      firstElement.innerHTML = " " + firstElement.innerHTML;
      firstElement.insertBefore(link,firstElement.firstChild);
      if(hideables[i].classList.contains(NO_HIDE_CLASS)) continue;
      hideables[i].classList.add(NO_HIDE_CLASS);
      toggleElement(link);
    }
  }

  function toggle(e) {
    toggleElement(e.target);
    e.preventDefault();
  }

  function toggleElement(link) {
    var e = link.parentNode.parentNode;
    if(e.classList.contains(NO_HIDE_CLASS)) {
      e.classList.remove(NO_HIDE_CLASS);
      e.classList.add(HIDDEN_CLASS);
    } else {
      e.classList.remove(HIDDEN_CLASS);
      e.classList.add(NO_HIDE_CLASS);
    }
    for(var i = 0; i < e.childNodes.length; i++) {
      var ch = e.childNodes[i];
      if(ch.nodeType != 1) continue;
      if(ch == link.parentNode) continue;
      if(ch.classList.contains(HIDE_CLASS)) {
        ch.classList.remove(HIDE_CLASS);
        link.innerHTML = COLLAPSE;
      }
      else {
        ch.classList.add(HIDE_CLASS);
        link.innerHTML = EXPAND;
      }
    }
  }

  appendStyle();
  toggleHideables();

})(window);