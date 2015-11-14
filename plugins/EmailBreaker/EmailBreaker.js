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
      unwrap = function(el) {
        var parent = el.parentNode;
        while (el.firstChild) parent.insertBefore(el.firstChild, el);
        parent.removeChild(el);
      },
      createEmails = function() {
        var spans = document.getElementsByTagName("span");
        var toUnwrap = [];
        for(var i = 0; i < spans.length; i++) {
          if(!spans[i].classList.contains("emailbreaker")) continue;
          var addrs = spans[i].querySelectorAll(".addr");
          for(var j = 0; j < addrs.length; j++) {
            createEmailLink(spans[i], addrs[j]);
            toUnwrap.push(addrs[j]);
          }
          unwrap(spans[i]);
        }
        for(var i = 0; i < toUnwrap.length; i++) unwrap(toUnwrap[i]);
      },
      createEmailLink = function(span, addr) {
        var a = document.createElement("a");
        var email = addr.textContent;
        for(var i = 0; i < Config.rep.length; i++) {
          email = email.replace(new RegExp(preg_quote(Config.rep[i][1]), "g"), Config.rep[i][0]);
        }
        addr.textContent = email;
        a.href = "mailto:" + email;
        span.parentNode.insertBefore(a, span);
        span.parentNode.removeChild(span);
        a.appendChild(span);
      },
      preg_quote = function(str, delimiter) {
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
