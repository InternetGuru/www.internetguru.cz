(function(win) {

  if(typeof IGCMS === "undefined") throw "IGCMS is not defined";

  var Config = {};
  Config.change = "* ";
  Config.favicon = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAfwAAAH8BuLbMiQAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAADVSURBVDiNldM9TsNAEIbhBxrKSEhOcgkuQcPF0iNOkCZniGgiUVKkyo9dcIOcIFRDwTpyFttsRhpZWu/7fjvWWkQobVTY4OmydiO8Q+DUSm4RLBMcXUlp8gKP+MwkmxJ4nzavMskO1Rg8xSFLbCVLVIPfYABue3G1twee4TgA79vkXsE/8CGHrwSYox6Bp73jJniCZgA+DsER4d5vnfHub9V4johTz7tLPWCbRnjtJNeYFVw0LwloOpIG88Ir7q2Tuk0nmpT+I3dY4ys9PyLie2zevH4AVeK3Am/wPTAAAAAASUVORK5CYII=";
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
      elements = document.getElementsByTagName("input");
      for(i = 0; i < elements.length; i++) setInputEvent(elements[i]);
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
      var links = document.getElementsByTagName("link");
      link = null;
      for(var i = 0; i < links.length; i++) {
        if(links[i].rel != "shortcut icon") continue;
        link = links[i];
      }
      if(link === null) {
        var link = document.createElement('link');
        link.type = "image/x-icon";
        link.rel = "shortcut icon";
        document.getElementsByTagName('head')[0].appendChild(link);
      }
      link.href = Config.favicon;
    };
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
