<?php

class ContentLink extends Plugin implements SplObserver, ContentStrategyInterface {
  private $lang = null;
  private $isRoot;
  private $headings;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 4);
  }

  public function update(SplSubject $subject) {
    $this->isRoot = getCurLink() == "";
    if($this->isRoot) return;
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
  }

  public function getContent(HTMLPlus $c) {
    $cf = Cms::getContentFull();
    $link = getCurLink();
    $curH = $cf->getElementById($link, "link");
    if(is_null($curH)) {
      if(strlen($link)) new ErrorPage(sprintf(_("Page '%s' not found"), $link), 404);
      $curH = $cf->documentElement->firstElement;
    }
    $this->setPath($curH);
    $this->setBc($c);
    if($this->isRoot) return $c;

    $curH->setAncestorValue("ns");
    $curH->setAncestorValue("author");
    $curH->parentNode->setAncestorValue("xml:lang");
    if(!$curH->parentNode->hasAttribute("xml:lang")) {
      $bodyLang = $cf->documentElement->getAttribute("xml:lang");
      $curH->parentNode->setAttribute("xml:lang", $bodyLang);
    }
    $curH->setAncestorValue("ctime");
    $curH->setAncestorValue("mtime");
    $curH->nextElement->setAncestorValue();
    $curH->nextElement->setAncestorValue("kw");

    $content = new HTMLPlus();
    $content->formatOutput = true;
    $body = $content->appendChild($content->createElement("body"));
    foreach($curH->parentNode->attributes as $attName => $attNode) {
      $body->setAttributeNode($content->importNode($attNode));
    }
    $this->appendUntilSame($curH, $body);

    #$content->fragToLinks($cf);
    return $content;
  }

  private function setPath(DOMElement $h) {
    while(!is_null($h)) {
      $this->headings[$h->getAttribute("id")] = $h;
      $h = $h->parentNode->getPreviousElement("h");
    }
  }

  private function setBc(HTMLPlus $src) {
    $first = true;
    $bc = new DOMDocumentPlus();
    $root = $bc->appendChild($bc->createElement("root"));
    $ol = $root->appendChild($bc->createElement("ol"));
    $ol->setAttribute("class", "contentlink-bc");
    foreach(array_reverse($this->headings) as $h) {
      $li = $ol->appendChild($bc->createElement("li"));
      $hs[] = $bc->importNode($h, true);
      $li->appendChild(end($hs));
    }
    $subtitles = array();
    foreach($hs as $h) {
      $content = $h->hasAttribute("short") ? $h->getAttribute("short") : $h->nodeValue;
      $subtitles[] = $content;
      $href = "#".$h->getAttribute("id");
      $a = $h->parentNode->appendChild($bc->createElement("a", $content));
      $a->setAttribute("href", $href);
      if($h->hasAttribute("title")) $a->setAttribute("title", $h->getAttribute("title"));
      else $a->setAttribute("title", $h->nodeValue);
      $h->parentNode->removeChild($h);
    }
    end($subtitles);
    $subtitles[key($subtitles)] = $h->nodeValue; // keep first title item long
    $bc = Cms::processVariables($bc);
    Cms::setVariable("bc", $bc->documentElement);
    Cms::setVariable("cms-title", implode(" - ", array_reverse($subtitles)));
  }

  private function appendUntilSame(DOMElement $e, DOMElement $into) {
    $doc = $into->ownerDocument;
    $into->appendChild($doc->importNode($e, true));
    $untilName = $e->nodeName;
    while(($e = $e->nextElement) !== null) {
      if($e->nodeName == $untilName) break;
      $into->appendChild($doc->importNode($e, true));
    }
  }

}

?>