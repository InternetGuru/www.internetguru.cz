(function(win){

  var forms = document.getElementsByTagName("form"),
      jsforms = [],
      values = [];

  for(var i = 0; i < forms.length; i++) {
    if(forms[i]["placeholder"]) {
      values.push(forms[i]["placeholder"].value);
      jsforms.push(forms[i]);
    }
  }

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
  function submit(value, input) {
    if(input.value.trim() == "" || input.value == value) {
      input.value = "";
      input.focus();
      e.preventDefault();
      return false;
    }
  }

  for(var i = 0; i < jsforms.length; i++) {
    var inputs = jsforms[i].getElementsByTagName("input");
    var input = null;
    for(var j = 0; j < inputs.length; j++) {
      if(inputs[j].type != "text") continue;
      input = inputs[j];
      break;
    }
    if(input == null) continue;
    input.value = values[i];
    input.style.color = "#666";

    input.onfocus = (function() {
      var value = values[i];
      var lInput = input;
      return function() {
        focus(value, lInput);
      }
    })();
    input.onblur = (function() {
      var value = values[i];
      var lInput = input;
      return function() {
        blur(value, lInput);
      }
    })();
    jsforms[i].onsubmit = (function(e) {
      var value = values[i];
      var lInput = input;
      return function() {
        return submit(e, value, lInput);
      }
    })();
  }

})(window);