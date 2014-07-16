<?php

class GA implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "process") return;
    if(is_null($subject->getCms()->getOutputStrategy())) {
      $subject->detach($this);
      return;
    }
    $this->init($subject);
  }

  private function init(SplSubject $subject) {
    $cfg = $subject->getCms()->buildDOM("GA");
    $ga_id = $this->getElementById($cfg,"ga_id")->nodeValue;
    if($ga_id == "") {
      $subject->detach($this);
      return;
    }
    $subject->getCms()->getOutputStrategy()->addJs("var ga_id = '$ga_id';",1,"body");
    foreach($cfg->getElementsByTagName("jsFile") as $jsFile) {
      $subject->getCms()->getOutputStrategy()->addJsFile($jsFile->nodeValue,"GA",1,"body");
    }
  }

  private function getElementById(DOMDocument $doc,$id) {
    $xpath = new DOMXPath($doc);
    $q = $xpath->query("//*[@id='$id']");
    if($q->length == 0) return null;
    return $q->item(0);
  }

}

?>
