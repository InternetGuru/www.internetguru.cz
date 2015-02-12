<?php

#TODO: detach if admin logged

class GA extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PROCESS) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
    if(!is_null(Cms::getVariable("auth-logged_user"))
      || preg_match("/^ig\d+/", CURRENT_SUBDOM_DIR)) {
      $subject->detach($this);
      return;
    }
    $this->init();
  }

  private function init() {
    $ga_id = $this->getDOMPlus()->getElementById("ga_id");
    if(!strlen($ga_id->nodeValue)) {
      $ga_id = $this->getDOMPlus()->getElementById(DOMAIN);
    }
    if(is_null($ga_id)) {
      $ga_id = $this->getDOMPlus()->getElementById("other");
    }
    $ga_id = $ga_id->nodeValue;
    if(!$this->isValidId($ga_id)) {
      Cms::getOutputStrategy()->addJs("// ".sprintf(_("Invalid ga_id format '%s'"), $ga_id), 1, "body");
      $this->subject->detach($this);
      return;
    }
    Cms::getOutputStrategy()->addJs("var ga_id = '$ga_id';", 1, "body");
    foreach($this->getDOMPlus()->getElementsByTagName("jsFile") as $jsFile) {
      $user = !$jsFile->hasAttribute("readonly");
      $f = $this->pluginDir."/".$jsFile->nodeValue;
      Cms::getOutputStrategy()->addJsFile($f, 1, "body", $user);
    }
  }

  private function isValidId($id) {
    return preg_match("/^UA-\d+-\d+$/", $id);
  }

}

?>