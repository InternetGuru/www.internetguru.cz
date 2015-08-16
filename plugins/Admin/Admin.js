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
          var key = (window.event) ? window.event.keyCode : key = e.which;
          var isCtrl;
          var isShift;
          if (window.event) {
            key = window.event.keyCode;
            isShift = !!window.event.shiftKey; // typecast to boolean
            isCtrl = !!window.event.ctrlKey; // typecast to boolean
          } else {
            key = e.which;
            isShift = !!e.shiftKey;
            isCtrl = !!e.ctrlKey;
          }
          // letter s and ctrl or meta
          if(!e.ctrlKey && !e.metaKey) return true;
          switch(key) {
            // S
            case 83:
            // save and exit if shift
            if(isShift) {
              form['saveandgo'].click();
              return false;
            }
            // save and stay
            form['saveandstay'].click();
            return false;
            break;

            default: return true;
          }

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
