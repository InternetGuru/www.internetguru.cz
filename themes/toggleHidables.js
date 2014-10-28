
(function(window){

  var EXPAND = "[+]";
  var COLLAPSE = "[-]";
  var HIDABLE_CLASS = "hidable";
  var HIDE_CLASS = "hide";
  var NO_HIDE_CLASS = "nohide";

  function getHidables() {
    if (document.querySelectorAll) return document.querySelectorAll("." + HIDABLE_CLASS);
    var hidables = [];
    var allElements = document.getElementsByTagName("*");
    for(var i = 0; i < allElements.length; i++) {
      if(allElements[i].classList.contains(HIDABLE_CLASS)) hidables.push(allElements[i]);
    }
    return hidables;
  }

  function toggleHidable() {
    var hidables = getHidables();
    for(var i = 0; i < hidables.length; i++) {
      var firstElement = hidables[i].children[0];
      var link = document.createElement("a");
      link.href = "";
      link.innerHTML = COLLAPSE;
      link.classList.add("switch")
      link.addEventListener("click",toggle,false);
      firstElement.innerHTML = " " + firstElement.innerHTML;
      firstElement.insertBefore(link,firstElement.firstChild);
      if(hidables[i].classList.contains(NO_HIDE_CLASS)) continue;
      toggleElement(link);
    }
  }

  function toggle(e) {
    toggleElement(e.target);
    e.preventDefault();
  }

  function toggleElement(link) {
    var e = link.parentNode.parentNode;
    for(var i = 0; i < e.childNodes.length; i++) {
      var ch = e.childNodes[i];
      if(ch.nodeType != 1) continue;
      if(ch.nodeName.toLowerCase() == "legend") continue;
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

  toggleHidable();

})(window);