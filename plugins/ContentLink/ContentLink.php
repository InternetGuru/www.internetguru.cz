<?php

class ContentLink implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject
  private $titleQueries = array();
  private $descriptionQuery = null;

  public function update(SplSubject $subject) {
    if($subject->getCms()->getLink() == ".") {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->getCms()->setContentStrategy($this);
    }
  }

  public function getTitle(Array $queries) {
    #if(empty($this->titleQueries)) return $queries;
    return $this->titleQueries;
  }

  public function getDescription($query) {
    #if(is_null($this->descriptionQuery)) return $query;
    return $this->descriptionQuery;
  }

  public function getContent(HTMLPlus $origContent) {
    if(!is_null($this->descriptionQuery)) throw new Exception("Should not run twice");
    $cms = $this->subject->getCms();
    $xpath = new DOMXPath($cms->getContentFull());
    $q = "//h[@link='" . $cms->getLink() . "']";
    $exactMatch = $xpath->query($q);
    if($exactMatch->length != 1)
      throw new Exception("No unique exact match found for link '{$cms->getLink()}'");
    $content = new HTMLPlus();
    $content->formatOutput = true;
    $body = $content->appendChild($content->createElement("body"));
    $this->addTitleQueries($exactMatch->item(0));
    $this->appendUntil($exactMatch->item(0),$body);
    return $content;
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