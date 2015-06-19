var a = 1;

(function(win) {

  var Config = {}
  Config.at = "(at)";
  Config.dot = "(dot)";

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
        Config.at = preg_quote(Config.at);
        Config.dot = preg_quote(Config.dot);
        var emailPatt = new RegExp("\\b([_a-zA-Z0-9.-]+)"+Config.at+"([_a-zA-Z0-9.-]+)"+Config.dot+"([a-zA-Z]{2,})\\b", "g");
        document.body.innerHTML = document.body.innerHTML.replace(emailPatt, "<a href='mailto:$1@$2.$3'>$1@$2.$3</a>");
      },
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
