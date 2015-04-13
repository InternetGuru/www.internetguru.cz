(function() {

  function setEvents() {
    var forms = document.getElementsByTagName("form");
    for(var i = 0; i < forms.length; i++) {
      if(!forms[i].classList.contains(Editable.getEditableClass())) continue;
      setSaveEvents(forms[i]);
    }
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

  setEvents();

})();