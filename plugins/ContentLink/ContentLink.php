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
    if($this->isRoot) $h1 = $cf->documentElement->firstElement;
    else {
      $h1 = $cf->getElementById($link, "link", "h");
      if(is_null($h1)) new ErrorPage(sprintf(_("Page '%s' not found"), $link), 404);
    }
    $this->setPath($h1);
    $this->setBc($c);
    if($this->isRoot) return $c;

    $desc = $h1->nextElement;
    if(!$h1->hasAttribute("ns")) $h1->setAttribute("ns", $h1->getAncestorValue("ns"));
    if(!strlen($desc->nodeValue)) $desc->nodeValue = $desc->getAncestorValue();
    if(!$desc->hasAttribute("kw")) $desc->setAttribute("kw", $desc->getAncestorValue("kw"));

    $this->handleAttribute($h1, "ctime");
    $this->handleAttribute($h1, "mtime");
    $this->handleAttribute($h1, "author");
    $this->handleAttribute($h1, "authorid");
    $this->handleAttribute($h1, "resp");
    $this->handleAttribute($h1, "respid");
    $this->handleAttribute($h1->parentNode, "xml:lang", "lang", Cms::getVariable("cms-lang"));

    $content = new HTMLPlus();
    $content->formatOutput = true;
    $body = $content->appendChild($content->createElement("body"));
    foreach($h1->parentNode->attributes as $attName => $attNode) {
      $body->setAttributeNode($content->importNode($attNode));
    }
    $this->appendUntilSame($h1, $body);

    return $content;
  }

  private function handleAttribute(DOMElement $e, $aName, $vName=null, $def=null) {
    if(is_null($vName)) $vName = $aName;
    if($e->hasAttribute($aName)) {
      Cms::setVariable($vName, $e->getAttribute($aName));
      return;
    }
    $value = $e->getAncestorValue($aName);
    if(is_null($value)) {
      if(is_null($def)) return;
      $value = $def;
    }
    $e->setAttribute($aName, $value);
    if($value == Cms::getVariable("cms-$vName")) return;
    Cms::setVariable($vName, $value);
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
    Cms::setVariable("title", implode(" - ", array_reverse($subtitles)));
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