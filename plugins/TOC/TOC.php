<?php

#TODO: detect lang & editable language pack

class TOC extends Plugin implements SplObserver, ContentStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    if($this->detachIfNotAttached("Xhtml11")) return;
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
    $vars = array();
    foreach($this->getDOMPlus()->getElementsByTagName("var") as $v) {
      if(!$v->hasAttribute("id")) continue;
      $vars[] = $v->getAttribute("id") . ": \"{$v->nodeValue}\"";
    }
    $tocVars = implode(", ",$vars);
    global $cms;
    $cms->getOutputStrategy()->addCssFile($this->getDir() ."/TOC.css");
    $cms->getOutputStrategy()->addJsFile($this->getDir() ."/TOC.js",5,"body");
    $cms->getOutputStrategy()->addJs("TOC.init({".$tocVars."});",20);
  }

  public function getContent(HTMLPlus $c) {
    $this->init($c);
    return $c;
  }

}

?>
