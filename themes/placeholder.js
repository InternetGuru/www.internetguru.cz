(function(win){

  var forms = document.getElementsByTagName("form"),
      debug  = false;

  function focus(value, input) {
    if(input.value == value) input.value = "";
    input.style.color = "inherit";
  }
  function blur(value, input) {
    if(input.value.trim() == "") {
      input.value = value;
      input.style.color = "#666";
    }
  }
  function submit(e, defaults) {
    for(var i = 0; i < defaults.length; i++) {
      var input = defaults[i][0];
      var value = defaults[i][1];
      var required = defaults[i][2];
      if(input.value.trim() == "" || input.value == value) {
        input.value = "";
        if(required) {
          input.focus();
          if(!input.classList.contains("warning"))
            input.classList.add("warning");
          e.preventDefault();
          return false;
        }
      }
    }
    if(debug) {
      alert("akce: '" + e.target.action
        + "'\nnazev: '" + input.name
        + "'\nhodnota: '" + input.value + "'");
      e.preventDefault();
    } else if(typeof ga == "function") {
      ga('send', {
        'hitType': 'event',
        'eventCategory': 'placeholder',
        'eventAction': e.target.action,
        'eventLabel': input.name,
        'eventValue': input.value
      });

    }
  }

  for(var i = 0; i < forms.length; i++) {
    if(!forms[i]["placeholder"]) continue;
    var inputs = forms[i].getElementsByTagName("input");
    var defaults = [];
    for(var j = 0; j < inputs.length; j++) {
      if(inputs[j].type != "text") continue;
      input = inputs[j];
      if(!input.nextElementSibling || input.nextElementSibling.type != "hidden" || input.nextElementSibling.name.indexOf("placeholder") != 0)
        continue;
      var placeholder = input.nextElementSibling.value + " ";
      defaults.push([input, placeholder, input.classList.contains("required")]);
      input.value = placeholder;
      input.style.color = "#666";
      input.onfocus = (function() {
        var value = placeholder;
        var lInput = input;
        return function() {
          focus(value, lInput);
        }
      })();
      input.onblur = (function() {
        var value = placeholder;
        var lInput = input;
        return function() {
          blur(value, lInput);
        }
      })();
    }
    forms[i].onsubmit = (function() {
      var lDefaults = defaults;
      return function(e) {
        submit(e, lDefaults);
      }
    })();
  }

})(window);
