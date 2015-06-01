(function(win) {

  var Config = {}
  Config.saveInactive = "Data file is inactive. Are you sure?";

   var Admin = function() {

      // private
      var
      initCfg = function(cfg) {
        if(typeof cfg === 'undefined') return;
        for(var attr in cfg) {
          if(!Config.hasOwnProperty(attr)) continue;
          Config[attr] = cfg[attr];
        }
      },
      confirmInactive = function(e, form) {
        if(!form.classList.contains("disabled")) return true;
        if(!form["disabled"].checked) return true;
        if(confirm(Config.saveInactive)) return true;
        e.preventDefault();
        return false;
      },
      setEvents = function() {
        var forms = document.getElementsByTagName("form");
        for(var i = 0; i < forms.length; i++) {
          if(!forms[i].classList.contains(Editable.getEditableClass())) continue;
          setSaveEvents(forms[i]);
        }
      },
      setSaveEvents = function(form) {
        form.onkeydown = function(e) {
          // letter s and ctrl or meta
          if(e.keyCode != 83) return true;
          if(!e.ctrlKey && !e.metaKey) return true;
          // save and exit if shift
          if(e.shiftKey) {
            form['saveandgo'].click();
            return false;
          }
          // save and stay
          form['saveandstay'].click();
          return false;
        }

        form['saveandgo'].onclick = function(e) { confirmInactive(e, form); }
        form['saveandstay'].onclick = function(e) { confirmInactive(e, form); }
      }

      // public
      return {
        init : function(cfg) {
          initCfg(cfg);
          setEvents();
        }
      }
   };

   var admin = new Admin();
   win.Admin = admin;

})(window);
