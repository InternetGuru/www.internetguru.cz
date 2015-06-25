var a = 1;

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
        var a = document.getElementsByTagName("a");
        for(var i = 0; i < a.length; i++) {
          if(a[i].href) continue;
          var spans = a[i].getElementsByTagName("span");
          for(var j = 0; j < spans.length; j++) createEmailLink(spans[j]);
        }
      },
      createEmailLink = function(span) {
        var a = span.parentNode;
        var email = span.innerText;
        for(var i = 0; i < Config.rep.length; i++) {
          email = email.replace(new RegExp(preg_quote(Config.rep[i][1]), "g"), Config.rep[i][0]);
        }
        span.innerText = email;
        a.href = "mailto:" + email;
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
