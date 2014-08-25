<?php

class ContentBalancer extends Plugin implements SplObserver, ContentStrategyInterface {
  private $content = null;

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->setPriority($this,10);
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
    foreach($nodes as $e) {
      $hs = array();
      foreach($xpath->query($e->getNodePath() . "/h") as $h) $hs[] = $h;
      $ul = $content->createElement("ul");
      $ul->setAttribute("class","cms-balancer");
      foreach($hs as $h) {
        if($h->hasAttribute("link")) $href = $h->getAttribute("link");
        else {
          $parentHeading = $this->getParentHeading($e);
          if($parentHeading->hasAttribute("link") && $h->hasAttribute("id")) {
            $href = $parentHeading->getAttribute("link") . "#" . $h->getAttribute("id");
          } else {
            #throw new Exception("Unable to build link for {$h->nodeValue}");
            continue 2;
          }
        }
        $li = $content->createElement("li");
        $textContent = $h->nodeValue;
        if($h->hasAttribute("short")) $textContent = $h->getAttribute("short");
        $a = $content->createElement("a",$textContent);
        $a->setAttribute("href",$href);
        $li->appendChild($a);
        $ul->appendChild($li);
      }
      $e->parentNode->replaceChild($ul,$e);
    }
  }

  private function getParentHeading(DOMElement $e) {
    $h = $e;
    while( ($h = $h->previousSibling) != null) {
      if($h->nodeName == "h") return $h;
    }
    throw new Exception("Unable to find parent heading for {$h->nodeValue}");
  }

  /*
  private function filterPublic(DOMElement $parent) {
    foreach($parent->childNodes as $e) if($e->nodeType == 1) $nodes[] = $e;
    foreach($nodes as $e) {
      if($e->nodeName == "section") {
        $this->filterPublic($e);
        continue;
      }
      if(!$e->hasAttribute("public")) $parent->removeChild($e);
    }
  }
  */

  public function getTitle(Array $queries) {
    return $queries;
  }

  public function getDescription($q) {
    return $q;
  }

}

?>