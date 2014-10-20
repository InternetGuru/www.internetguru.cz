document.addEventListener("click", changeUrl);

function changeUrl(event) {
  if(!/h[1-6]/.test(event.target.nodeName.toLowerCase())) return true;
  window.history.replaceState("", "", "#"+event.target.id);
}