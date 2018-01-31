(function (win) {

  var MAX_ATTEMPTS = 50

  function getObjectIndex (obj, index) {
    if (obj === undefined) {
      return undefined
    }
    return obj[index]
  }

  function require (objectName, callback, attempt) {
    if (attempt === undefined) {
      attempt = 1
    }
    if (attempt === MAX_ATTEMPTS) {
      throw "require " + objectName + ": max attempts exceeded"
    }
    if (objectName.split(".").reduce(getObjectIndex, win) === undefined) {
      setTimeout(require, 100, objectName, callback, ++attempt)
      return
    }
    callback()
  }

  win.require = require
})(window)
