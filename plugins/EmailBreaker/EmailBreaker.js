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
        var toUnwrap = [];
        for(var i = 0; i < spans.length; i++) {
          if(!spans[i].classList.contains("emailbreaker")) continue;
          var addrs = spans[i].getElementsByTagName("span");
          for(var j = 0; j < addrs.length; j++) {
            if(!addrs[j].classList.contains("addr")) continue;
            createEmailLink(spans[i], addrs[j]);
          }
        }
      },
      createEmailLink = function(span, addr) {
        var a = document.createElement("a");
        a.className = span.className;
        var email = addr.textContent;
        for(var i = 0; i < Config.rep.length; i++) {
          email = email.replace(new RegExp(IGCMS.preg_quote(Config.rep[i][1]), "g"), Config.rep[i][0]);
        }
        a.href = "mailto:" + email.replace(" ", "");
        if(addr.classList.contains("del")) addr.parentNode.removeChild(addr);
        else addr.textContent = email;
        span.parentNode.insertBefore(a, span);
        span.parentNode.removeChild(span);
        for(var i = 0; i < span.childNodes.length; i++) {
          a.appendChild(span.childNodes[i]);
        }
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
