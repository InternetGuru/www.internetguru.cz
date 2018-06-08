(function () {
  var linklistLinks = document.querySelectorAll('a[data-linklist-href]')
  for (var i = 0; i < linklistLinks.length; i++) {
    var a = document.createElement('a')
    a.href = linklistLinks[i].getAttribute('data-linklist-href')
    a.innerText = "[" + linklistLinks[i].getAttribute('data-linklist-value') + "]"
    a.className = linklistLinks[i].getAttribute('data-linklist-class')
    linklistLinks[i].parentNode.insertBefore(a, linklistLinks[i].nextSibling)
  }
})()
