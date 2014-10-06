<?php

class InputVar extends Plugin implements SplObserver, InputStrategyInterface {
  private $variables = array();
  private $contentXPath;

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    $cf = $subject->getCms()->getContentFull();

    #$this->contentXPath = new DOMXPath($cf);
    #$rootXPath = "/body/h";
    #if(strlen($subject->getCms()->getLink()))
    #  $rootXPath = $cf->getElementById($subject->getCms()->getLink(),"link")->getNodePath();

  }

  public function getVariables() {
    return $this->variables;
  }

}

?>