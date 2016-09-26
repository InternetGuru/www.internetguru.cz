(function(win) {

  if(typeof IGCMS === "undefined") throw "IGCMS is not defined";

  var Config = {}
  Config.favicon = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABWElEQVR42oXTzysEcRjH8e/OrEiUlZRCxJ5ZF8rBQSGllJSbH1c5kwMOLrQHfwGpTWolyclhS5z9ipOTlMtusg67GLtf78OnZtr146lXz8zneea7U9uYQIXUXfUh5JFFnzIHZRUKDFwx1DmsHCgL+3P1wE0ETuBNLmHlyD+gvCqQhIcTtCjfgpV5Zc1I4BFLRjWmpS/1W7jo0n0a9ajBnbKi9ntgupHTwFPf1eHX2NZ1smQngzajGscV3gILy4hjCqvK8njFPQb9f8GvajQhih1Y5HRQOyKoguM/6x9QiU70YhSHKCCjwyYxgA64pT8+iycUxGIGa5jGhLKi5g8YMaoYPrXwob6h2QX2dB3X7F39GQ0w/QoK6ik9EEURL2hUdgorWbQa1SJusIk6ZSuwsqCsFuvancOP5aqfwcqxsvBfDzklH0sKVvaVhcWB+98bDMNDGrHfPudvDyNmsqHBLvwAAAAASUVORK5CYII=";
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
