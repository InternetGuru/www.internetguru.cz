(function(win){

  var forms = document.getElementsByTagName("form"),
      values = [],
      debug  = true;

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
  function submit(e, required) {
    for(var i = 0; i < required.length; i++) {
      var input = required[i][0];
      var value = required[i][1];
      if(input.value.trim() == "" || input.value == value) {
        input.value = "";
        input.focus();
        e.preventDefault();
        return false;
      }
    }
    if(debug) {
      alert("_gaq.push(['_trackEvent', 'placeholder', '" + e.target.action + "', '"
          + input.name + "', '" + input.value + "']);");
      e.preventDefault();
    } else if(typeof _gaq == "object") {
      _gaq.push(['_trackEvent', 'placeholder', e.target.action, input.name, input.value]);
    }
  }

  for(var i = 0; i < forms.length; i++) {
    if(!forms[i]["placeholder"]) continue;
    var inputs = forms[i].getElementsByTagName("input");
    var required = [];
    for(var j = 0; j < inputs.length; j++) {
      if(inputs[j].type != "text") continue;
      input = inputs[j];
      if(input.nextElementSibling.type != "hidden" || input.nextElementSibling.name.indexOf("placeholder") != 0)
        continue;
      var placeholder = input.nextElementSibling.value + " ";
      if(input.classList.contains("required")) required.push([input, placeholder]);
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
      var lRequired = required;
      return function(e) {
        submit(e, lRequired);
      }
    })();
  }

})(window);