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
        var emails = [];
        for(var i = 0; i < spans.length; i++) {
          if(!spans[i].classList.contains("emailbreaker")) continue;
          emails.push(spans[i]);
        }
        for(var i = 0; i < emails.length; i++) {
          var addrs = emails[i].getElementsByTagName("span");
          for(var j = 0; j < addrs.length; j++) {
            if(!addrs[j].classList.contains("addr")) continue;
            createEmailLink(emails[i], addrs[j]);
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
        while(span.childNodes.length > 0) {
          a.appendChild(span.childNodes[0]);
        }
        span.parentNode.insertBefore(a, span);
        span.parentNode.removeChild(span);
      };

      // public
      return {
        init : function(cfg) {
          initCfg(cfg);
          createEmails();
        }
      }
   };

   win.EmailBreaker = new EmailBreaker();

})(window);
