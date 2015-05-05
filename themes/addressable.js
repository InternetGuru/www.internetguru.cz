(function(win) {

  var Config = {};
  Config.class = "addressable";
  Config.getAddress = "Get form address";

  var Addressable = function() {

    var serialize = function(form) {
      if (!form || form.nodeName !== "FORM") {
        return;
      }
      var i, j, q = [];
      for (i = form.elements.length - 1; i >= 0; i = i - 1) {
        if (form.elements[i].name === "") {
          continue;
        }
        switch (form.elements[i].nodeName) {
        case 'INPUT':
        switch (form.elements[i].type) {
          case 'text':
          case 'hidden':
          case 'password':
          case 'button':
          case 'reset':
          case 'submit':
            q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].value));
            break;
          case 'checkbox':
          case 'radio':
            if (form.elements[i].checked) {
              q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].value));
            }
            break;
        }
        break;
        case 'file':
        break;
        case 'TEXTAREA':
          q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].value));
          break;
        case 'SELECT':
          switch (form.elements[i].type) {
          case 'select-one':
            q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].value));
            break;
          case 'select-multiple':
            for (j = form.elements[i].options.length - 1; j >= 0; j = j - 1) {
              if (form.elements[i].options[j].selected) {
                q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].options[j].value));
              }
            }
            break;
          }
          break;
        case 'BUTTON':
          switch (form.elements[i].type) {
          case 'reset':
          case 'submit':
          case 'button':
            q.push(form.elements[i].name + "=" + encodeURIComponent(form.elements[i].value));
            break;
          }
          break;
        }
      }
      return q.join("&");
    },

    appendButtons = function() {
      var forms = document.getElementsByTagName("form");
      for(var i = 0; i < forms.length; i++) {
        if(!forms[i].classList.hasClass(Config.class)) continue;
        var printButton = document.createElement("button");
        printButton.value = Config.getAddress;
        forms[i].appendChild(printButton);
      }
    };

    // public
    return {
      init : function(cfg) {
        appendButtons();
      }
    }
  };

  var addressable = new Addressable();
  win.Addressable = addressable;

})(window);