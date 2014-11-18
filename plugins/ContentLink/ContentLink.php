<?php


class ContentLink extends Plugin implements SplObserver, ContentStrategyInterface {
  private $lang = null;
  private $isRoot;
  private $headings;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,4);
  }

  public function update(SplSubject $subject) {
    $this->isRoot = getCurLink() == "";
    if($this->isRoot) return;
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
  }

  public function getContent(HTMLPlus $c) {
    global $cms;
    $cf = $cms->getContentFull();
    $link = getCurLink();
    $curH = $cf->getElementById($link,"link");
    if(is_null($curH)) {
      if(strlen($link)) new ErrorPage("Page '$link' not found",404);
      $curH = $cf->documentElement->firstElement;
    }
    $this->setPath($curH);
    $this->setBc($c);
    if($this->isRoot) return $c;

    $this->setAncestorValue($curH, "author");
    $this->setAncestorValue($curH->parentNode, "xml:lang");
    if(!$curH->parentNode->hasAttribute("xml:lang")) {
      $bodyLang = $cf->documentElement->getAttribute("xml:lang");
      $curH->parentNode->setAttribute("xml:lang",$bodyLang);
    }
    $this->setAncestorValue($curH, "ctime");
    $this->setAncestorValue($curH, "mtime");
    #echo $cf->saveXML(); exit();
    #echo $curH->nextElement->nodeName; exit;
    $this->setAncestorValue($curH->nextElement);
    $this->setAncestorValue($curH->nextElement, "kw");

    $content = new HTMLPlus();
    $content->formatOutput = true;
    $body = $content->appendChild($content->createElement("body"));
    foreach($curH->parentNode->attributes as $attName => $attNode) {
      $body->setAttributeNode($content->importNode($attNode));
    }
    $this->appendUntilSame($curH,$body);

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
    $ol = $bc->appendChild($bc->createElement("ol"));
    $ol->setAttribute("class","contentlink-bc");
    foreach(array_reverse($this->headings) as $h) {
      $li = $ol->appendChild($bc->createElement("li"));
      $hs[] = $bc->importNode($h, true);
      $li->appendChild(end($hs));
    }
    global $cms;
    $cms->insertVariables($bc);
    $subtitles = array();
    foreach($hs as $h) {
      $content = $h->hasAttribute("short") ? $h->getAttribute("short") : $h->nodeValue;
      $subtitles[] = $content;
      $href = "#". $h->getAttribute("id");
      $a = $h->parentNode->appendChild($bc->createElement("a",$content));
      $a->setAttribute("href",$href);
      if($h->hasAttribute("title")) $a->setAttribute("title",$h->getAttribute("title"));
      else $a->setAttribute("title",$h->nodeValue);
      $h->parentNode->removeChild($h);
    }
    end($subtitles);
    $subtitles[key($subtitles)] = $h->nodeValue; // keep first title item long
    $cms->setVariable("bc", $bc);
    $cms->setVariable("cms-title", implode(" - ", array_reverse($subtitles)));
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
      $ancestor = $ancestor->getPreviousElement($e->nodeName);
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