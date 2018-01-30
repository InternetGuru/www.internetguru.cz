(function (win) {

  var MAX_ATTEMPTS = 50

  function index(obj, i) {
    return obj[i]
  }

  function require (requiredObjectStr, callback, attempt) {
    if (attempt === undefined) {
      attempt = 1
    }
    if (attempt === MAX_ATTEMPTS) {
      throw "require: max attempts exceeded"
    }
    var requiredObjects = requiredObjectStr
    if (!Array.isArray(requiredObjectStr)) {
      requiredObjects = [requiredObjectStr]
    }
    var loaded = true
    for (var i = 0; i < requiredObjects.length; i++) {
      if (requiredObjects[i].split(".").reduce(index, win) === undefined) {
        loaded = false
        break
      }
    }
    if (!loaded){
      setTimeout(require, 100, requiredObjectStr, callback, ++attempt)
    } else {
      callback()
    }
  }

  win.require = require

})(window)
