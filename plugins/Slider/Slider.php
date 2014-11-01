<?php

# disable if admin

class Slider extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "process") return;
    if($this->detachIfNotAttached("Xhtml11")) return;
    if(is_null($this->cms->getOutputStrategy())) {
      $subject->detach($this);
      return;
    }
    $this->init();
  }

  private function init() {
    #FIXME $this->cms deleted
    $cfg = $this->cms->buildDOM("Slider");
    $setters = array();
    // get js resources
    foreach($cfg->getElementsByTagName("jsLib") as $r) {
      $user = !$r->hasAttribute("readonly");
      $this->cms->getOutputStrategy()->addJsFile($r->nodeValue,10,"head",$user);
    }
    // set css
    #todo:fixme
    #$fileName = findFile(PLUGIN_FOLDER ."/". get_class($this) . "/Slider.css");
    #if($fileName === false) throw new Exception("CSS file not found");
    #$fileName = getLocalLink("").$fileName;
    #$setters["setCss"] = "Slider.setCss('$fileName');";
    // get parameters
    foreach($cfg->getElementsByTagName("parameters")->item(0)->childElements as $r) {
      $setters[$r->nodeName] = "Slider." . $r->nodeName . "('" . $r->nodeValue . "');";
    }
    $f = PLUGIN_FOLDER ."/". get_class($this) ."/Slider.js";
    $this->cms->getOutputStrategy()->addJsFile($f,10,"head",false);
    $this->cms->getOutputStrategy()->addJs(implode($setters),20);
  }

}

?>
