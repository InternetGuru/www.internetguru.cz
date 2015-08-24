(function(win) {

  var Config = {};
  Config.rep = [];

   var EmailBreaker = function() {

      // private
      var
      initCfg = function(cfg) {
        if(typeof cfg === 'undefined') return;
        for(var attr in cfg) {
          if(!Config.hasOwnProperty(attr)) continue;
          Config[attr] = cfg[attr];
        }
      },
      createEmails = function() {
        var spans = document.getElementsByTagName("span");
        for(var i = 0; i < spans.length; i++) {
          if(!spans[i].classList.contains("emailbreaker")) continue;
          createEmailLink(spans[i]);
        }
      },
      createEmailLink = function(span) {
        var a = document.createElement("a");
        var email = span.textContent;
        for(var i = 0; i < Config.rep.length; i++) {
          email = email.replace(new RegExp(preg_quote(Config.rep[i][1]), "g"), Config.rep[i][0]);
        }
        span.textContent = email;
        a.href = "mailto:" + email;
        span.parentNode.insertBefore(a, span);
        span.parentNode.removeChild(span);
        a.appendChild(span);
      }
      preg_quote = function (str, delimiter) {
        return (str + '').replace(new RegExp('[.\\\\+*?\\[\\^\\]$(){}=!<>|:\\' + (delimiter || '') + '-]', 'g'), '\\$&');
      }

      // public
      return {
        init : function(cfg) {
          initCfg(cfg);
          createEmails();
        }
      }
   };

   var emailbreaker = new EmailBreaker();
   win.EmailBreaker = emailbreaker;

})(window);
