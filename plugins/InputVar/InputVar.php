<?php

class InputVar extends Plugin implements SplObserver {
  private $contentXPath;

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    $cf = $subject->getCms()->getContentFull();
    $dom = $this->getDOMPlus();
    $vars = $dom->getElementsByTagName("var");
    foreach($vars as $var) $this->parseVar($var);
  }

  private function parseVar() {
    // function
    if($var->hasAttribute("fn")) switch($var->getAttribute("fn")) {
      case "hash":
      break;
      case "link":
      break;
      case "date":
      break;
    }

  }

}

?>