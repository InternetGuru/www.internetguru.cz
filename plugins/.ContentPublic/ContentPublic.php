<?php

#TODO: valid HTML+ notation (attribute public is invalid)

class ContentPublic implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject

  public function update(SplSubject $subject) {
    if(!isset($_GET["public"])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->getCms()->setContentStrategy($this,20);
    }
  }

  public function getContent(HTMLPlus $content) {
    $this->filterPublic($content->documentElement);
    $content->validatePlus();
    return $content;
  }

  private function filterPublic(DOMElement $parent) {
    foreach($parent->childNodes as $e) if($e->nodeType == 1) $nodes[] = $e;
    foreach($nodes as $e) {
      if($e->nodeName == "section") {
        $this->filterPublic($e);
        if(!$e->hasChildNodes()) $parent->removeChild($e);
        continue;
      }
      if(!$e->hasAttribute("public")) $parent->removeChild($e);
    }
  }

  public function getTitle(Array $queries) {
    $title = array();
    $xpath = new DOMXPath($this->subject->getCms()->getContentFull());
    foreach($queries as $q) {
      $r = $xpath->query($q)->item(0);
      if($r->hasAttribute("public")) $title[] = $q;
    }
    return $title;
  }

  public function getDescription($q) {
    $xpath = new DOMXPath($this->subject->getCms()->getContentFull());
    if($xpath->query($q)->item(0)->hasAttribute("public")) return $q;
    return "(//*[@public])[1]";
  }

}

?>