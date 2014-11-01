<?php

#TODO: detach if admin logged

class GA extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "process") return;
    if($this->detachIfNotAttached("Xhtml11")) return;
    if(isAtLocalhost()) {
      $subject->detach($this);
      return;
    }
    $this->init();
  }

  private function init() {
    $ga_id = $this->getDOMPlus()->getElementById("ga_id");
    if(is_null($ga_id)) {
      $ga_id = $this->getDOMPlus()->getElementById(getDomain());
    }
    if(is_null($ga_id)) {
      $ga_id = $this->getDOMPlus()->getElementById("other");
    }
    $ga_id = $ga_id->nodeValue;
    global $cms;
    if(!$this->isValidId($ga_id)) {
      $cms->getOutputStrategy()->addJs("// invalid ga_id format '$ga_id'",1,"body");
      $this->subject->detach($this);
      return;
    }
    $cms->getOutputStrategy()->addJs("var ga_id = '$ga_id';",1,"body");
    foreach($this->getDOMPlus()->getElementsByTagName("jsFile") as $jsFile) {
      $user = !$jsFile->hasAttribute("readonly");
      $f = $this->getDir() ."/". $jsFile->nodeValue;
      $cms->getOutputStrategy()->addJsFile($f,1,"body",$user);
    }
  }

  private function isValidId($id) {
    return preg_match("/^UA-\d+-\d+$/",$id);
  }

}

?>
