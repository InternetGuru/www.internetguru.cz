
(function(window){

  var CHANGE = "* ";
  var EXPAND = "[+]";
  var COLLAPSE = "[-]";
  var HIDE_CLASS = "contentadmin-hide";
  var NO_HIDE_CLASS = "contentadmin-nohide";
  var UNLOAD_MSG = "Changes have not been saved.";
  var modified = false;

  function dynamicFieldset() {
    var fieldsets = document.getElementsByTagName("fieldset");
    for(var i = 0; i < fieldsets.length; i++) {
      var l = fieldsets[i].getElementsByTagName("legend")[0];
      var link = document.createElement("a");
      link.href = "";
      link.innerHTML = COLLAPSE;
      link.classList.add("contentadmin-switch")
      link.addEventListener("click",toggle,false);
      l.innerHTML = " " + l.innerHTML;
      l.insertBefore(link,l.firstChild);
      if(fieldsets[i].classList.contains(NO_HIDE_CLASS)) continue;
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

  function setSaveEvents() {
    document.onkeydown=function(e){
      // letter s and ctrl or meta
      if(e.keyCode != 83) return true;
      if(!e.ctrlKey && !e.metaKey) return true;

      // parent is form
      var p = e.target.parentNode;
      while(true) {
        if(p == null) return true;
        if(p.nodeName.toLowerCase() == "form") break;
        p = p.parentNode;
      }

      p.onsubmit = function(){
        modified = false;
      }

      // save and exit if shift
      if(e.shiftKey) {
        p['saveandgo'].click();
        return false;
      }

      // save and stay
      p['saveandstay'].click();
      return false;
    }
  }

  function indicateChange(){
    var areas = document.getElementsByTagName("textarea");
    for(var i=0; i < areas.length; i++) {
      areas[i].addEventListener('keyup', function() {
        if(modified) return;
        modified = true;
        document.title = CHANGE + document.title;
      }, false);
    }
  }



  window.onbeforeunload = function(e) {
    if(!modified) return;
    e = e || window.event;
    // For IE and Firefox
    if (e) {
      e.returnValue = UNLOAD_MSG;
    }
    // For Safari
    return UNLOAD_MSG;
  }



  setSaveEvents();
  dynamicFieldset();
  indicateChange();

})(window);