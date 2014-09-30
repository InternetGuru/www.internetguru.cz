<?php

class ContentLink extends Plugin implements SplObserver, ContentStrategyInterface {
  private $titleQueries = array();
  private $descriptionQuery = null;
  private $content;
  private $lang = null;
  private $isRoot;

  public function update(SplSubject $subject) {
    $this->isRoot = $subject->getCms()->getLink() == getRoot();
    if($this->isRoot) return;
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $subject->setPriority($this,2);
  }

  private function build() {
    if(!is_null($this->descriptionQuery)) throw new Exception("Should not run twice");
    $cms = $this->subject->getCms();
    $xpath = new DOMXPath($cms->getContentFull());
    $exactMatch = $xpath->query("//h[@link='" . $cms->getLink() . "']");
    if($exactMatch->length != 1)
      throw new Exception("No unique exact match found for link '{$cms->getLink()}'");
    if($exactMatch->item(0)->parentNode->nodeName != "section") {
      $this->content = $cms->getContentFull();
      return;
    }
    $this->content = new HTMLPlus();
    $this->content->formatOutput = true;
    $body = $this->content->appendChild($this->content->createElement("body"));
    foreach($exactMatch->item(0)->parentNode->attributes as $attName => $attNode) {
      $body->setAttributeNode($this->content->importNode($attNode));
    }
    $this->appendUntil($exactMatch->item(0),$body);
    $this->addTitleQueries($exactMatch->item(0));
    if($body->hasAttribute("xml:lang")) return;
    if(is_null($this->lang)) {
      $this->lang = $cms->getContentFull()->documentElement->getAttribute("xml:lang");
    }
    $body->setAttribute("xml:lang",$this->lang);
  }

  public function getTitle(Array $queries) {
    if($this->isRoot) return $queries;
    $this->build();
    return $this->titleQueries;
  }

  public function getDescription($query) {
    if($this->isRoot) return $query;
    if(is_null($this->descriptionQuery)) return "/body/desc";
    return $this->descriptionQuery;
  }

  public function getContent(HTMLPlus $content) {
    if($this->isRoot) return $content;
    return $this->content;
  }

  private function addTitleQueries(DOMElement $h) {
    $this->titleQueries[] = $h->getNodePath();
    $e = $h->parentNode;
    if($e->nodeName == "section") {
      if(is_null($this->lang) && $e->hasAttribute("xml:lang")) {
        $this->lang = $e->getAttribute("xml:lang");
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
      if($e->nodeName != "desc") continue;
      $this->createDescription($e);
      break;
    }
  }

  private function appendUntil(DOMElement $e,DOMElement $into) {
    $doc = $into->ownerDocument;
    $into->appendChild($doc->importNode($e,true));
    $untilName = $e->nodeName;
    while(($e = $e->nextSibling) !== null) {
      if($e->nodeName == "desc") $this->createDescription($e);
      if($e->nodeName == $untilName) break;
      $into->appendChild($doc->importNode($e,true));
    }
  }

}

?>