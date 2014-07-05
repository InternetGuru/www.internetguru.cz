<?php

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
    // get resources (readonly)
    foreach($cfg->getElementsByTagName("resources")->item(0)->childNodes as $r) {
      switch ($r->nodeName) {
        case "jsLib" :
        $this->cms->getOutputStrategy()->addJsFile($r->nodeValue,($r->hasAttribute("absolute") ? "" : "Slider"));
        continue;
        case "setCss" :
        $fileName = PLUGIN_FOLDER . "/Slider/" . $r->nodeValue;
        if(!is_file($fileName)) $fileName = "../" . CMS_FOLDER . "/$fileName";
        $setters[$r->nodeName] = "Slider." . $r->nodeName . "('$fileName');";
        continue;
      }
    }
    // get parameters
    foreach($cfg->getElementsByTagName("parameters")->item(0)->childNodes as $r) {
      if($r->nodeType != 1) continue;
      $setters[$r->nodeName] = "Slider." . $r->nodeName . "('" . $r->nodeValue . "');";
    }
    $this->cms->getOutputStrategy()->addJsFile("Slider.js","Slider");
    $this->cms->getOutputStrategy()->addJs(implode($setters),20);
  }

}

?>
