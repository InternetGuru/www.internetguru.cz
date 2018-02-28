(function() {

  require("IGCMS", function () {

    var Config = {};
    Config.ns = "addressable";
    Config.buttonValue = "Get form URL";
    Config.hint = "Ctrl+C";

    var Addressable = function () {

      var
        input = null,
        button = null,
        serialize = function (form) {
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
        appendButton = function () {
          var forms = document.getElementsByTagName("form");
          for (var i = 0; i < forms.length; i++) {
            if (!forms[i].classList.contains(Config.ns)) continue;
            setFormEvent(forms[i]);
            var p = document.createElement("p");
            p.className = "hidden " + Config.ns;
            button = document.createElement("button");
            button.innerHTML = Config.buttonValue;
            button.type = "button";
            button.addEventListener("click", performAction, false);
            forms[i].appendChild(p);
            input = document.createElement("input");
            input.type = "text";
            input.title = Config.hint;
            p.appendChild(button);
            p.appendChild(input);
          }
        },
        setFormEvent = function (form) {
          var inputs = form.getElementsByTagName("input");
          for (var i = 0; i < inputs.length; i++) {
            if (["text", "email", "search"].indexOf(inputs[i].type) === -1) continue;
            inputs[i].addEventListener("input", function () {
              if (input === null) return;
              input.style.display = "none";
              button.style.display = "";
            }, false);
          }
        },
        performAction = function (e) {
          var b = e.target || e.srcElement;
          var url = location.protocol + '//' + location.host + location.pathname + "?";
          input.value = url + serialize(b.form);
          b.style.display = "none";
          input.addEventListener("focus", function (e) {
            e.target.setSelectionRange(0, e.target.value.length);
          }, false);
          input.style.display = "block";
          input.focus();
        };

      // public
      return {
        init: function (cfg) {
          IGCMS.initCfg(Config, cfg);
          appendButton();
          var css = '/* addressable.js */'
            + ' p.addressable { padding: 1em }'
            + ' p.addressable button, p.addressable input { padding: 0.5em 1em; font-size: inherit; width: 100%; box-sizing: border-box; line-height: 1em; max-width: 30em }'
            + ' p.addressable input { display: none; width: 50vw; max-width: 100%; }';
          IGCMS.appendStyle(css);
        }
      }
    };

    IGCMS.Addressable = new Addressable();
  })
})(window);
