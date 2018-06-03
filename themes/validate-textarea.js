(function (){

var textareas = document.getElementsByTagName("textarea")
for (var i = 0; i < textareas.length; i++) {
  if (!textareas[i].getAttribute('data-pattern')) {
    continue;
  }
  textareas[i].addEventListener("input", validateTextarea, false)
  validateTextarea.bind(textareas[i])()
}

function validateTextarea(event) {
  var errorMsg = "Please match the format requested."
  var textarea = this
  var pattern = new RegExp('^' + textarea.getAttribute("data-pattern") + '$')
  if (!textarea.getAttribute('required') && !textarea.value) {
    return true
  }
  
  var hasError = !pattern.test(textarea.value)
  if (typeof textarea.setCustomValidity === 'function') {
    textarea.setCustomValidity(hasError ? errorMsg : '')
  } else {
    textarea.classList.toggleClass('error', !!hasError)
    textarea.classList.toggleClass('ok', !hasError)
    if (hasError) {
      textarea.setAttribute('title', errorMsg)
    } else {
      textarea.removeAttribute('title')
    }
  }
  return !hasError
}

})()
