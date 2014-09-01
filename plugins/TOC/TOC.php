<?php

class TOC extends Plugin implements SplObserver, ContentStrategyInterface {

  public function update(SplSubject $subject) {
    $this->subject = $subject;
  }

  private function init(HTMLPlus $c) {
    if(!$c->documentElement->hasAttribute("class")) return;
    $classes = explode(" ", $c->documentElement->getAttribute("class"));
    if(!in_array("contenttoc", $classes)) return;
    $this->subject->getCms()->getOutputStrategy()->addCssFile($this->getDir() ."/TOC.css");
    $this->subject->getCms()->getOutputStrategy()->addJsFile($this->getDir() ."/TOC.js",5,"body");
    $this->subject->getCms()->getOutputStrategy()->addJs("TOC.init();",20);
  }

  public function getContent(HTMLPlus $c) {
    $this->init($c);
    return $c;
  }

  public function getTitle(Array $q) {
    return $q;
  }

  public function getDescription($q) {
    return $q;
  }

}

?>
