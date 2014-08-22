document.onkeydown=function(e){

  // letter s and ctrl or meta
  if(e.keyCode != 83) return true;
  if(!e.ctrlKey && !e.metaKey) return true;

  // parent is form
  var p = e.target.parentNode;
  while(true) {
    if(p == null) return true;
    if(p.nodeName.toLowerCase() == "form") break;
    p = p.parentNode;
  }

  // save and exit if shift
  if(e.shiftKey) {
    p['saveandgo'].click();
    return false;
  }

  // save and stay
  p['saveandstay'].click();
  return false;

}