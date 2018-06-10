(function(win) {

  require("IGCMS", function () {

    var Config = {}
    Config.ns = "eventable"
    Config.dataCategory = "data-" + Config.ns + "-category"
    Config.dataAction = "data-" + Config.ns + "-action"
    Config.debug = false

    var Eventable = function () {

      // private
      var
        fireEvents = function (elements) {
          if (elements.length === 0) {
            return
          }
          for (var i = 0; i < elements.length; i++) {
            var handler = sendGAEvents
            var eventName = "click"
            if (elements[i].nodeName.toLowerCase() === "form") {
              handler = sendGAFormEvents
              eventName = "submit"
            }
            elements[i].addEventListener(eventName, handler, false)
          }
        },
        sendGAEvents = function (event) {
          var element = event.target
          var category = element.getAttribute(Config.dataCategory) || element.id || element.className || element.nodeName
          var action = element.getAttribute(Config.dataAction) || element.href || element.nodeName
          sendGAEvent(category, action, element.innerText)
          if (Config.debug) {
            event.preventDefault();
          }
        },
        sendGAFormEvents = function (event) {
          var form = event.target
          var inputs = form.getElementsByTagName("input")
          var category = form.getAttribute(Config.dataCategory) || form.id || form.className || 'form-' + form.action + '-' + form.method
          for (var i = 0; i < inputs.length; i++) {
            sendGAEvent(category, inputs[i].name, inputs[i].value)
          }
          if (Config.debug) {
            event.preventDefault();
          }
        },
        sendGAEvent = function (category, action, label) {
          if (Config.debug) {
            alert("category: '" + category + "',"
              + "action: '" + action + "',"
              + "label: '" + label + "'")
          } else {
            ga('send', {
              'hitType': 'event',
              'eventCategory': category,
              'eventAction': action,
              'eventLabel': label
            });
          }
        };

      // public
      return {
        debug: Config.debug,
        init: function (cfg) {
          IGCMS.initCfg(Config, cfg)
          var allLinks = document.getElementsByTagName("a")
          var externalLinks = []
          for (var i = 0; i < allLinks.length; i++) {
            if (allLinks[i].host && allLinks[i].host !== window.location.host) {
              externalLinks.push(allLinks[i])
            }
          }
          fireEvents(externalLinks)
          fireEvents(document.getElementsByTagName("form"))
          fireEvents(document.getElementsByClassName(Config.ns))
        }
      }
    };

    IGCMS.Eventable = Eventable();
  })
})(window)
