(function(win) {

  /**
   * Definition of IGCMS global object
   */
  var IGCMS = function() {

    var
    /**
     * Check if collection has element
     * @return {bool} a has b
     */
    collectionHas = function(collection, element) {
      for(var i = 0, len = collection.length; i < len; i ++) {
        if(collection[i] == element) return true;
      }
      return false;
    }

    return {
      /**
       * Update existing Config properities by cfg properities
       * @param  {Object} Config updated object
       * @param  {Object} cfg    new object
       */
      initCfg: function(Config, cfg) {
        if(typeof cfg === 'undefined') return;
        for(var attr in cfg) {
          if(!Config.hasOwnProperty(attr)) continue;
          Config[attr] = cfg[attr];
        }
      },
      /**
       * Return element ancestor which match given selector or null if ancestor not exits
       * @param  {DOMElement} elm      starting element
       * @param  {String}     selector standard DOM selector
       * @return {DOMElement|null}
       */
      findParentBySelector: function(elm, selector) {
        var all = document.querySelectorAll(selector);
        var cur = elm;
        while(cur && !collectionHas(all, cur)) { // keep going up until you find a match
          cur = cur.parentNode; // go up
        }
        return cur; // will return null if not found
      },
      /**
       * Append new element style with given css
       * @param {String} css
       */
      appendStyle: function(css) {
        var elem=document.createElement('style');
        elem.setAttribute('type', 'text/css');
        if(elem.styleSheet && !elem.sheet)elem.styleSheet.cssText=css;
        else elem.appendChild(document.createTextNode(css));
        document.getElementsByTagName('head')[0].appendChild(elem);
      }

    }
  }

  win.IGCMS = new IGCMS();

})(window)