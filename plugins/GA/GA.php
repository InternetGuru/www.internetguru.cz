<?php

#TODO: detach if admin logged
#TODO: detach if no id or no default domain basket id

class GA extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    $this->subject = $subject;
    if($subject->getStatus() != "process") return;
    if(isAtLocalhost() || is_null($subject->getCms()->getOutputStrategy())) {
      $subject->detach($this);
      return;
    }
    $this->init($subject);
  }

  private function init(SplSubject $subject) {
    $cfg = $this->getDOM();
    $ga_id = $cfg->getElementById("ga_id")->nodeValue;
    if($ga_id == "") {
      $subject->detach($this);
      return;
    }
    $subject->getCms()->getOutputStrategy()->addJs("var ga_id = '$ga_id';",1,"body");
    foreach($cfg->getElementsByTagName("jsFile") as $jsFile) {
      $user = !$jsFile->hasAttribute("readonly");
      $f = $this->getDir() ."/". $jsFile->nodeValue;
      $subject->getCms()->getOutputStrategy()->addJsFile($f,1,"body",$user);
    }
  }

}

?>
