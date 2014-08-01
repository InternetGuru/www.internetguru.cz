<?php

class ContentLink implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject
  private $titleQueries = array();
  private $descriptionQuery = null;
  private $content;
  private $lang = null;

  public function update(SplSubject $subject) {
    if($subject->getCms()->getLink() == ".") {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    $subject->setPriority($this,2);
  }

  private function build() {
    if(!is_null($this->descriptionQuery)) throw new Exception("Should not run twice");
    $cms = $this->subject->getCms();
    $xpath = new DOMXPath($cms->getContentFull());
    $exactMatch = $xpath->query("//h[@link='" . $cms->getLink() . "']");
    if($exactMatch->length != 1)
      throw new Exception("No unique exact match found for link '{$cms->getLink()}'");
    $this->content = new HTMLPlus();
    $this->content->formatOutput = true;
    $body = $this->content->appendChild($this->content->createElement("body"));
    $this->appendUntil($exactMatch->item(0),$body);
    $this->addTitleQueries($exactMatch->item(0));
    if(is_null($this->lang)) $cms->getContentFull()->documentElement->getAttribute("lang");
    $body->setAttribute("lang",$this->lang);
  }

  public function getTitle(Array $queries) {
    $this->build();
    return $this->titleQueries;
  }

  public function getDescription($query) {
    return $this->descriptionQuery;
  }

  public function getContent(HTMLPlus $content) {
    return $this->content;
  }

  private function addTitleQueries(DOMElement $h) {
    $this->titleQueries[] = $h->getNodePath();
    $e = $h->parentNode;
    if($e->nodeName == "section") {
      if(is_null($this->lang) && $e->hasAttribute("lang")) {
        $this->lang = $e->getAttribute("lang");
      }
      while(($e = $e->previousSibling) !== null) {
        if($e->nodeName != "h") continue;
        if(!$e->hasAttribute("link")) continue;
        $this->addTitleQueries($e);
        break;
      }
    }
    if(is_null($e)) $this->titleQueries[] = "/body/h";
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