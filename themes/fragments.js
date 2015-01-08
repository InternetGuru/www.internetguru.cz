document.addEventListener("click", headingLookup);

function headingLookup(event) {
  if(changeFragment(event.target)) return;
  if(changeFragment(event.target.parentNode)) return;
}

function changeFragment(e) {
  if(!/h[2-6]/.test(e.nodeName.toLowerCase())) return false;
  window.history.replaceState("", "", "#"+e.id);
  return true;
}