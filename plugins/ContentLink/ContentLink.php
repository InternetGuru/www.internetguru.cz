<?php

class ContentLink implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject
  private $content = null;
  private $titleQueries = array();
  private $descriptionQuery = null;

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->getCms()->setContentStrategy($this);
    }
  }

  public function getTitle(Array $queries) {
    if(empty($this->titleQueries)) return $queries;
    return $this->titleQueries;
  }

  public function getDescription($q) {
    if(is_null($this->descriptionQuery)) return $q;
    return $this->descriptionQuery;
  }

  public function getContent(DOMDocument $origContent) {
    if(!is_null($this->content)) return $this->content;
    $cms = $this->subject->getCms();
    if(!strlen($cms->getLink())) return $origContent;
    $this->content = new DOMDocument("1.0","utf-8");
    $this->content->formatOutput = true;
    $body = $this->content->appendChild($this->content->createElement("body"));
    $headings = $origContent->getElementsByTagName("h");
    foreach($headings as $h) {
      if(!$h->hasAttribute("link")) continue;
      if($h->getAttribute("link") == $cms->getLink()) {
        $this->addTitleQueries($h);
        $this->appendUntil($h,$body);
        break;
      }
    }
    return $this->content;
  }

  private function addTitleQueries(DOMElement $h) {
    $this->titleQueries[] = $h->getNodePath();
    $e = $h->parentNode;
    if($e->nodeName == "section") while(($e = $e->previousSibling) !== null) {
      if($e->nodeName != "h") continue;
      $this->addTitleQueries($e);
      break;
    }
  }

  /**
   * Add first non-empty (parent) description
   * @param  DOMElement $h starting level
   * @return void
   */
  private function createDescription(DOMElement $d) {
    if(strlen($d->nodeValue)) {
      $this->descriptionQuery = $d->getNodePath();
      return;
    }
    $e = $d->parentNode;
    if($e->nodeName == "section") while(($e = $e->previousSibling) !== null) {
      if($e->nodeName != "description") continue;
      $this->createDescription($e);
      break;
    }
  }

  private function appendUntil(DOMElement $e,DOMElement $into) {
    $doc = $into->ownerDocument;
    $into->appendChild($doc->importNode($e,true));
    $untilName = $e->nodeName;
    while(($e = $e->nextSibling) !== null) {
      if($e->nodeName == "description") $this->createDescription($e);
      if($e->nodeName == $untilName) break;
      $into->appendChild($doc->importNode($e,true));
    }
  }

}

?>