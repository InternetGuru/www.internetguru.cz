<?php

#TODO: detach if admin logged

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
    $ga_id = $this->getDOMPlus()->getElementById("ga_id");
    if(is_null($ga_id)) {
      $ga_id = $this->getDOMPlus()->getElementById(getDomain());
    }
    if(is_null($ga_id)) {
      $ga_id = $this->getDOMPlus()->getElementById("other");
    }
    $ga_id = $ga_id->nodeValue;
    if(!$this->isValidId($ga_id)) {
      $subject->getCms()->getOutputStrategy()->addJs("// invalid ga_id format '$ga_id'",1,"body");
      $subject->detach($this);
      return;
    }
    $subject->getCms()->getOutputStrategy()->addJs("var ga_id = '$ga_id';",1,"body");
    foreach($this->getDOMPlus()->getElementsByTagName("jsFile") as $jsFile) {
      $user = !$jsFile->hasAttribute("readonly");
      $f = $this->getDir() ."/". $jsFile->nodeValue;
      $subject->getCms()->getOutputStrategy()->addJsFile($f,1,"body",$user);
    }
  }

  private function isValidId($id) {
    return preg_match("/^UA-\d+-\d+$/",$id);
  }

}

?>
