
(function(window){

  var CHANGE = "* ";
  var UNLOAD_MSG = "Changes have not been saved.";
  var modified = false;

  function setEvents() {
    var forms = document.getElementsByTagName("form");
    if(forms.length != 1) return; // multiple forms not supported
    forms[0].onsubmit = function(){
      modified = false;
    }
    setSaveEvents(forms[0]);
  }

  function setSaveEvents(form) {
    form.onkeydown=function(){

      // letter s and ctrl or meta
      if(event.keyCode != 83) return true;
      if(!event.ctrlKey && !event.metaKey) return true;

      // save and exit if shift
      if(event.shiftKey) {
        form['saveandgo'].click();
        return false;
      }

      // save and stay
      form['saveandstay'].click();
      return false;
    }
  }

  function indicateChange(){
    var areas = document.getElementsByTagName("textarea");
    for(var i=0; i < areas.length; i++) {
      areas[i].addEventListener('input', setModified, false);
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

  window.setModified = function() {
    if(modified) return;
    modified = true;
    document.title = CHANGE + document.title;
  }

  setEvents();
  indicateChange();

})(window);