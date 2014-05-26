<?php

class Slider implements SplObserver {
  private $cms;

  public function update(SplSubject $subject) {
    #echo "notification status = " . $subject->getStatus() ."<br />";
    #var_dump($subject->getCms());
    if($subject->getStatus() != "process") return;
    $this->cms = $subject->getCms();
    $this->init();
  }

  private function init() {
    $cfg = $this->cms->getDomBuilder()->build("Slider");
    $setters = array();
    // get resources (readonly)
    foreach($cfg->getElementsByTagName("resources")->item(0)->childNodes as $r) {
      switch ($r->nodeName) {
        case "jsLib" :
        $this->cms->getOutputStrategy()->addJsFile($r->nodeValue,($r->hasAttribute("absolute") ? "" : "Slider"));
        continue;
        case "setCss" :
        $setters[$r->nodeName] = "Slider." . $r->nodeName . "('" . basename(PLUGIN_FOLDER) . "/Slider/" . $r->nodeValue . "');";
        continue;
      }
    }
    // get parameters
    foreach($cfg->getElementsByTagName("parameters")->item(0)->childNodes as $r) {
      if($r->nodeType != 1) continue;
      $setters[$r->nodeName] = "Slider." . $r->nodeName . "('" . $r->nodeValue . "');";
    }
    $this->cms->getOutputStrategy()->addJsFile("Slider.js","Slider");
    $this->cms->getOutputStrategy()->addJs(implode($setters));


    // insert jquery
    #$this->cms->addScript("");
    // insert slider.js
    // insert config
  }

}

?>
