<?php

class ContentLink extends Plugin implements SplObserver, ContentStrategyInterface {
  private $lang = null;
  private $isRoot;
  private $headings;

  public function update(SplSubject $subject) {
    $this->isRoot = getCurLink() == "";
    $subject->setPriority($this,2);
    if($this->isRoot) return;
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    if($this->detachIfNotAttached("Xhtml11")) return;
  }

  public function getContent(HTMLPlus $c) {
    if($this->isRoot) return $c;
    $cf = $this->subject->getCms()->getContentFull();
    $link = getCurLink();
    $curH = $cf->getElementById($link,"link");
    if(is_null($curH))
      throw new Exception("No unique exact match found for link '$link'");

    $this->setPath($curH);
    $this->setTitle();
    $this->setBc($link);

    $this->setAncestorValue($curH, "author");
    $this->setAncestorValue($curH->parentNode, "xml:lang");
    if(!$curH->parentNode->hasAttribute("xml:lang")) {
      $bodyLang = $cf->documentElement->getAttribute("xml:lang");
      $curH->parentNode->setAttribute("xml:lang",$bodyLang);
    }
    $this->setAncestorValue($curH, "ctime");
    $this->setAncestorValue($curH, "mtime");
    $this->setAncestorValue($curH->nextElement);
    $this->setAncestorValue($curH->nextElement, "kw");

    $content = new HTMLPlus();
    $content->formatOutput = true;
    $body = $content->appendChild($content->createElement("body"));
    foreach($curH->parentNode->attributes as $attName => $attNode) {
      $body->setAttributeNode($content->importNode($attNode));
    }
    $this->appendUntilSame($curH,$body);

    $content->fragToLinks($cf);
    return $content;
  }

  private function setPath(DOMElement $h) {
    while(!is_null($h)) {
      $this->headings[$h->getAttribute("id")] = $h;
      $h = $h->parentNode->getPreviousElement("h");
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
    $this->subject->getCms()->setVariable("cms-title", implode(" - ", $subtitles));
  }

  private function setBc($curLink) {
    $first = true;
    $bc = new DOMDocumentPlus();
    $ol = $bc->appendChild($bc->createElement("ol"));
    $ol->setAttribute("class","cms-breadcrumb");
    $parentLink = getLocalLink("");
    foreach(array_reverse($this->headings) as $h) {
      $content = $h->nodeValue;
      if($h->hasAttribute("short")) {
        $content = $h->getAttribute("short");
      }
      $li = $ol->appendChild($bc->createElement("li"));
      if($h->hasAttribute("link") && $h->getAttribute("link") == $curLink) {
        $li->nodeValue = $content;
        continue;
      }
      if($first) {
        $first = false;
        $href = getLocalLink("");
      } elseif($h->hasAttribute("link")) {
        $href = getLocalLink($h->getAttribute("link"));
        $parentLink = $href;
      } else {
        $href = $parentLink ."#". $h->getAttribute("id");
      }
      $a = $li->appendChild($bc->createElement("a",$content));
      $a->setAttribute("href",$href);
      if($h->hasAttribute("title")) $a->setAttribute("title",$h->getAttribute("title"));
      else $a->setAttribute("title",$h->nodeValue);
    }
    $this->subject->getCms()->setVariable("cms-breadcrumb", $bc);
  }

  private function setAncestorValue(DOMElement $e, $attName=null) {
    $ancestor = $e;
    while(!is_null($ancestor)) {
      if(!is_null($attName) && $ancestor->hasAttribute($attName)) {
        $e->setAttribute($attName,$ancestor->getAttribute($attName));
        break;
      } elseif(is_null($attName) && strlen($ancestor->nodeValue)) {
        $e->nodeValue = $ancestor->nodeValue;
        break;
      }
      $ancestor = $ancestor->parentNode;
      if(is_null($ancestor)) return;
      $ancestor = $ancestor->getPreviousElement();
    }
  }

  private function appendUntilSame(DOMElement $e, DOMElement $into) {
    $doc = $into->ownerDocument;
    $into->appendChild($doc->importNode($e,true));
    $untilName = $e->nodeName;
    while(($e = $e->nextElement) !== null) {
      if($e->nodeName == $untilName) break;
      $into->appendChild($doc->importNode($e,true));
    }
  }

}

?>