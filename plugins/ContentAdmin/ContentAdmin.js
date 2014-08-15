
var isCtrl = false;
document.onkeyup=function(e){
    if(e.keyCode == 17|| e.metaKey) isCtrl=false;
}

document.onkeydown=function(e){

    if(e.keyCode == 17|| e.metaKey) isCtrl=true;
    if(e.keyCode == 83 && isCtrl == true) {

        // parent is form?
        var p = e.target.parentNode;
        while(true) {
          if(p == null) return true;
          if(p.nodeName.toLowerCase() == "form") break;
          p = p.parentNode;
        }

        // save and exit
        if(e.shiftKey){
          p['saveandgo'].click();
          return false;
        }
        p['saveandstay'].click();
        return false;
    }
}