document.addEventListener("click", changeUrl);

function changeUrl(event) {
  if(!event.target.id) return true;
  window.history.replaceState("", "", "#"+event.target.id);
}