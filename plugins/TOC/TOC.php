<?php

#TODO: detect lang & editable language pack

class TOC extends Plugin implements SplObserver, ContentStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    $this->detachIfNotAttached("Xhtml11");
  }

  private function init(HTMLPlus $c) {
    $foundTocClass = false;
    foreach($c->getElementsByTagName("section") as $s) {
      if(!$s->hasAttribute("class")) continue;
      if(!in_array("contenttoc",explode(" ",$s->getAttribute("class")))) continue;
      $foundTocClass = true;
      break;
    }
    if(!$foundTocClass) return;
    Cms::getOutputStrategy()->addCssFile($this->getDir() ."/TOC.css");
    Cms::getOutputStrategy()->addJsFile($this->getDir() ."/TOC.js",5,"body");
    $tocTitle = _("Table of Contents");
    Cms::getOutputStrategy()->addJs("TOC.init({tocTitle: \"$tocTitle\"});",6);
  }

  public function getContent(HTMLPlus $c) {
    $this->init($c);
    return $c;
  }

}

?>
