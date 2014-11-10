document.addEventListener("click", changeUrl);

function changeUrl(event) {
  if(/h[2-6]/.test(event.target.nodeName.toLowerCase()))
    window.history.replaceState("", "", "#"+event.target.id);
}