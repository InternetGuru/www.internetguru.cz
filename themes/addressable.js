(function(win) {

  var Config = {};
  Config.classPrefix = "addressable";
  Config.buttonValue = "Get form URL";

  var Addressable = function() {

    var
    input = null,
    button = null,

    initCfg = function(cfg) {
      if(typeof cfg === 'undefined') return;
      for(var attr in cfg) {
        if(!Config.hasOwnProperty(attr)) continue;
        Config[attr] = cfg[attr];
      }
    },
    serialize = function(form) {
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
          case 'email':
          case 'search':
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

    appendStyle = function() {
      var css = '/* addressable.js */'
        + ' p.addressable { padding: 1em }'
        + ' p.addressable button, p.addressable input { padding: 0.5em 1em; font-size: inherit; width: 100%; box-sizing: border-box; line-height: 1em; max-width: 30em }';
      var style = document.getElementsByTagName('style')[0];
      if(style == undefined) {
        var head = document.head || document.getElementsByTagName('head')[0];
        style = document.createElement('style');
        style.type = 'text/css';
        head.appendChild(style);
      }
      style.appendChild(document.createTextNode(css));
    },

    appendButton = function() {
      var forms = document.getElementsByTagName("form");
      for(var i = 0; i < forms.length; i++) {
        if(!forms[i].classList.contains(Config.classPrefix)) continue;
        setFormEvent(forms[i]);
        var p = document.createElement("p");
        p.className = "hidden " + Config.classPrefix;
        button = document.createElement("button");
        button.innerHTML = Config.buttonValue;
        button.type = "button";
        button.addEventListener("click", performAction, false);
        forms[i].appendChild(p);
        p.appendChild(button);
      }
    },

    setFormEvent = function(form) {
      var inputs = form.getElementsByTagName("input");
      for(var i = 0; i < inputs.length; i++) {
        if(["text", "email", "search"].indexOf(inputs[i].type) === -1) continue;
        inputs[i].addEventListener("input", function() {
          if(input === null) return;
          input.parentNode.removeChild(input);
          input = null;
          button.style.display = "";
        }, false);
      }
    },

    performAction = function(e) {
      if(input !== null) return;
      var b = e.target;
      var url = location.protocol + '//' + location.host + location.pathname + "?";
      input = document.createElement("input");
      input.value = url + serialize(b.form);
      b.parentNode.appendChild(input);
      b.style.display = "none";
      input.addEventListener("focus", function(e) {e.target.setSelectionRange(0, e.target.value.length)}, false);
      input.focus();
    };

    // public
    return {
      init : function(cfg) {
        initCfg(cfg);
        appendButton();
        appendStyle();
      }
    }
  };

  var addressable = new Addressable();
  win.Addressable = addressable;

})(window);
