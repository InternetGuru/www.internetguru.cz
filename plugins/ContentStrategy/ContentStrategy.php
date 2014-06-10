<?php

class ContentStrategy implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->getCms()->setContentStrategy($this);
    }
  }

  public function getContent(DOMDocument $origContent) {
    $cms = $this->subject->getCms();
    if(!strlen($cms->getLink())) return $origContent;
    $content = new DOMDocument("1.0","utf-8");
    $content->formatOutput = true;
    $body = $content->appendChild($content->createElement("body"));
    $headings = $origContent->getElementsByTagName("h");
    foreach($headings as $h) {
      if(!$h->hasAttribute("link")) continue;
      if($h->getAttribute("link") == $cms->getLink()) {
        $this->appendUntil($h,$body);
        break;
      }
    }
    return $content;
  }

  private function appendUntil(DOMElement $e,DOMElement $into) {
    $doc = $into->ownerDocument;
    $into->appendChild($doc->importNode($e,true));
    $untilName = $e->nodeName;
    while(($e = $e->nextSibling) !== null) {
      if($e->nodeName == $untilName) break;
      $into->appendChild($doc->importNode($e,true));
    }
  }

}

?>