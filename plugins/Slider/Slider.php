<?php

# disable if admin

class Slider implements SplObserver {
  private $cms;

  public function update(SplSubject $subject) {
    $this->cms = $subject->getCms();
    if($subject->getStatus() != "process") return;
    if(is_null($this->cms->getOutputStrategy())) {
      $subject->detach($this);
      return;
    }
    $this->init();
  }

  private function init() {
    $cfg = $this->cms->buildDOM("Slider");
    $setters = array();
    // get js resources
    foreach($cfg->getElementsByTagName("jsLib") as $r) {
      $user = !$r->hasAttribute("readonly");
      $this->cms->getOutputStrategy()->addJsFile($r->nodeValue,10,"head",$user);
    }
    // set css
    $fileName = findFile(PLUGIN_FOLDER ."/". get_class($this) . "/Slider.css");
    if($fileName === false) throw new Exception("CSS file not found");
    $fileName = getRoot().$fileName;
    $setters["setCss"] = "Slider.setCss('$fileName');";
    // get parameters
    foreach($cfg->getElementsByTagName("parameters")->item(0)->childNodes as $r) {
      if($r->nodeType != 1) continue;
      $setters[$r->nodeName] = "Slider." . $r->nodeName . "('" . $r->nodeValue . "');";
    }
    $f = PLUGIN_FOLDER ."/". get_class($this) ."/Slider.js";
    $this->cms->getOutputStrategy()->addJsFile($f,10,"head",false);
    $this->cms->getOutputStrategy()->addJs(implode($setters),20);
  }

}

?>
