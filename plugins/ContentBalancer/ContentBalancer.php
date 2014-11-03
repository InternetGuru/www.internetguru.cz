<?php

class ContentBalancer extends Plugin implements SplObserver, ContentStrategyInterface {
  private $content = null;

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      if($this->detachIfNotAttached(array("Xhtml11","ContentLink"))) return;
    }
  }

  public function getContent(HTMLPlus $content) {
    $this->filter($content);
    return $content;
  }

  private function filter(HTMLPlus $content) {
    $xpath = new DOMXPath($content);
    $nodes = array();
    foreach($xpath->query("/body/section/section") as $e) $nodes[] = $e;
    foreach($nodes as $section) {
      $hs = array();
      foreach($section->childElements as $e) if($e->nodeName == "h") $hs[] = $e;
      $force = $section->getPreviousElement("h")->hasAttribute("link");
      $ul = $content->createElement("ul");
      $ul->setAttribute("class","contentbalancer");
      foreach($hs as $h) {
        if(!$force && !$h->hasAttribute("link")) continue 2;
        $li = $content->createElement("li");
        $textContent = $h->nodeValue;
        if($h->hasAttribute("short")) $textContent = $h->getAttribute("short");
        $a = $content->createElement("a",$textContent);
        $a->setAttribute("href","#".$h->getAttribute("id"));
        $li->appendChild($a);
        $ul->appendChild($li);
      }
      $section->parentNode->replaceChild($ul,$section);
    }
  }

  private function getParentHeading(DOMElement $e) {
    $h = $e;
    while( ($h = $h->previousElement) != null) {
      if($h->nodeName == "h") return $h;
    }
    throw new Exception("Unable to find parent heading for {$h->nodeValue}");
  }

}

?>