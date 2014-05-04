<?php

class Xhtml11 implements SplObserver, OutputStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $subject->getCms()->setOutputStrategy($this);
    }
  }

  public function output(DOMDocument $doc) {
    return $doc->saveXML();
  }

}

?>
