(function(win) {

  if(typeof IGCMS === "undefined") throw "IGCMS is not defined";

  var Config = {}
  Config.change = "* ";
  Config.unload_msg = "Form content has been changed.";
  Config.editable_class = "editable";

  var Editable = function() {

    // private
    var
    modified = false,
    setEvents = function() {
      var forms = document.getElementsByTagName("form");
      //if(forms.length != 1) return; // multiple forms not supported
      for(var i = 0; i < forms.length; i++) {
        if(!forms[i].classList.contains(Config.editable_class)) continue;
        forms[i].onsubmit = function() {
          modified = false;
        }
      }
      win.onbeforeunload = function(e) {
        if(!modified) return;
        e = e || win.event;
        // For IE and Firefox
        if (e) {
          e.returnValue = Config.unload_msg;
        }
        // For Safari
        return Config.unload_msg;
      }
    },
    indicateChange = function() {
      var elements = document.getElementsByTagName("textarea");
      for(var i = 0; i < elements.length; i++) setInputEvent(elements[i]);
      var elements = document.getElementsByTagName("input")
      for(var i = 0; i < elements.length; i++) setInputEvent(elements[i]);
    },
    setInputEvent = function(e) {
      if(e.nodeName.toLowerCase() == "input" && e.type != "text") return;
      var form = getParentForm(e);
      if(!form || !form.classList.contains(Config.editable_class)) return;
      e.addEventListener('input', setModified, false);
    },
    getParentForm = function(e) {
      if(!e.parentNode) return null;
      if(e.parentNode.nodeName.toLowerCase() == "form") return e.parentNode;
      return getParentForm(e.parentNode);
    },
    setModified = function() {
      if(modified) return;
      modified = true;
      document.title = Config.change + document.title;
    }
    // public
    return {
      getParentForm : getParentForm,
      setModified: setModified,
      getEditableClass : function() { return Config.editable_class; },

      init : function(cfg) {
        IGCMS.initCfg(Config, cfg);
        setEvents();
        indicateChange();
      }
    }
  };

  IGCMS.Editable = new Editable();

})(window);
