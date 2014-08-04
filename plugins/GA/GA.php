<?php

class GA implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "process") return;
    if(isAtLocalhost() || is_null($subject->getCms()->getOutputStrategy())) {
      $subject->detach($this);
      return;
    }
    $this->init($subject);
  }

  private function init(SplSubject $subject) {
    $cfg = $subject->getCms()->buildDOM("GA");
    $ga_id = $cfg->getElementById("ga_id")->nodeValue;
    if($ga_id == "") {
      $subject->detach($this);
      return;
    }
    $subject->getCms()->getOutputStrategy()->addJs("var ga_id = '$ga_id';",1,"body");
    foreach($cfg->getElementsByTagName("jsFile") as $jsFile) {
      $subject->getCms()->getOutputStrategy()->addJsFile($jsFile->nodeValue,"GA",1,"body");
    }
  }

}

?>
