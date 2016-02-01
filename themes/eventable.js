(function(win) {

  if(typeof IGCMS === "undefined") throw "IGCMS is not defined";

  var Config = {}
  Config.ns = "eventable";

   var Eventable = function() {

      // private
      var
      debug = false,
      elements = null,
      idRegExp = null,
      fireEvents = function() {
        formElemets = [];
        for(var i = 0; i < elements.length; i++) {
          var form = elements[i].form;
          if(!form || elements[i].nodeName.toLowerCase() != "input") continue; // working only on form inputs
          var m = form.className.match(idRegExp);
          if(!m) {
            m = Config.ns+"-"+i;
            form.classList.add(m);
            form.onsubmit = sendGAEvents;
          }
          if(typeof formElemets[m] === "undefined") formElemets[m] = [];
          formElemets[m].push(elements[i]);
        }
      },
      sendGAEvents = function(e) {
        var id = this.className.match(idRegExp)[0];
        var inputs = formElemets[id];
        for(var i = 0; i < inputs.length; i++) {
          sendGAEvent(this, inputs[i]);
        }
        if(debug) e.preventDefault();
      },
      sendGAEvent = function(form, input) {
        if(debug) {
          alert("category: '" + form.id + (form.id.length ? "_" : "") + form.action + "',"
            + "action: '" + input.name + "',"
            + "label: '" + input.value + "'");
        } else {
          ga('send', {
            'hitType': 'event',
            'eventCategory': form.id + (form.id.length ? "_" : "") + form.action,
            'eventAction': input.name,
            'eventLabel': input.value
          });
        }
      }

      // public
      return {
        debug : debug,
        init : function(cfg) {
          IGCMS.initCfg(cfg);
          idRegExp = new RegExp(Config.ns+"-\\d+");
          elements = document.getElementsByClassName(Config.ns);
          if(elements.length == 0) return;
          fireEvents();
        }
      }
   };

   IGCMS.Eventable = Eventable();

})(window);
