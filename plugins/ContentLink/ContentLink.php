<?php

class ContentLink extends Plugin implements SplObserver, ContentStrategyInterface, InputStrategyInterface {
  private $lang = null;
  private $isRoot;
  private $variables = array();
  private $headings;

  public function update(SplSubject $subject) {
    $this->isRoot = $subject->getCms()->getLink() == "";
    if($this->isRoot) return;
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $subject->setPriority($this,2);
  }

  public function getContent(HTMLPlus $c) {
    if($this->isRoot) return $c;
    $cf = $this->subject->getCms()->getContentFull();
    $link = $this->subject->getCms()->getLink();
    $curH = $cf->getElementById($link,"link");
    if(is_null($curH))
      throw new Exception("No unique exact match found for link '$link'");

    $this->setPath($curH);
    $this->setTitle();
    $this->setBc($link);

    $this->setAncestorAttribute($curH, "author");
    $this->setAncestorAttribute($curH->parentNode, "xml:lang");
    if(!$curH->parentNode->hasAttribute("xml:lang")) {
      $bodyLang = $curH->ownerDocument->documentElement->getAttribute("xml:lang");
      $curH->parentNode->setAttribute("xml:lang",$bodyLang);
    }
    $this->setAncestorAttribute($curH, "ctime");
    $this->setAncestorAttribute($curH, "mtime");
    $this->setAncestorValue($curH->nextSibling);
    $this->setAncestorAttribute($curH->nextSibling, "kw");

    $content = new HTMLPlus();
    $content->formatOutput = true;
    $body = $content->appendChild($content->createElement("body"));
    foreach($curH->parentNode->attributes as $attName => $attNode) {
      $body->setAttributeNode($content->importNode($attNode));
    }
    $this->appendUntilSame($curH,$body);

    return $content;
  }

  private function setPath(DOMElement $h) {
    while(!is_null($h)) {
      $this->headings[] = $h;
      $h = $h->ownerDocument->getParentSibling($h);
    }
  }

  private function setTitle() {
    $subtitles = array();
    foreach($this->headings as $h) {
      if($h->hasAttribute("short")) {
        $subtitles[] = $h->getAttribute("short");
        continue;
      }
      $subtitles[] = $h->nodeValue;
    }
    $this->variables["title"] = implode(" - ", $subtitles);
  }

  private function setBc($curLink) {
    $first = true;
    $bc = new DOMDocumentPlus();
    $ol = $bc->appendChild($bc->createElement("ol"));
    foreach(array_reverse($this->headings) as $h) {
      $content = $h->nodeValue;
      if($h->hasAttribute("short")) {
        $content = $h->getAttribute("short");
      }
      $li = $ol->appendChild($bc->createElement("li"));
      if(!$this->hasLink($first,$h,$curLink)) {
        $li->nodeValue = $content;
        continue;
      }
      if($first) {
        $first = false;
        $href = getRoot();
      } else {
        $href = getRoot() . $h->getAttribute("link");
      }
      $a = $li->appendChild($bc->createElement("a",$content));
      $a->setAttribute("href",$href);
      if($h->hasAttribute("title")) $a->setAttribute("title",$h->getAttribute("title"));
      else $a->setAttribute("title",$h->nodeValue);
    }
    $this->variables["breadcrumb"] = $bc;
  }

  private function hasLink($first,DOMElement $h, $curLink) {
    if($first) return true;
    if(!$h->hasAttribute("link")) return false;
    return $h->getAttribute("link") != $curLink;
  }

  private function setAncestorAttribute(DOMElement $e, $aName) {
    while(!$e->hasAttribute($aName)) {
      $ps = $e->ownerDocument->getParentSibling($e);
      if(is_null($ps)) return;
      if($ps->hasAttribute($aName)) $e->setAttribute($aName,$ps->getAttribute($aName));
      $e = $ps;
    }
  }

  private function setAncestorValue(DOMElement $e) {
    while(!strlen($e->nodeValue)) {
      $ps = $e->ownerDocument->getParentSibling($e);
      if(is_null($ps)) return;
      if(strlen($ps->nodeValue)) $e->nodeValue = $ps->nodeValue;
      $e = $ps;
    }
  }

  public function getVariables() {
    return $this->variables;
  }

  /*
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
  */

  private function appendUntilSame(DOMElement $e, DOMElement $into) {
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