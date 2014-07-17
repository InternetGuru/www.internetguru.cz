<?php

class ContentMenu implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->getCms()->setContentStrategy($this,60);
    }
  }

  public function getTitle(Array $queries) {
    return $queries;
  }

  public function getDescription($query) {
    return $query;
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $xpath = new DOMXPath($cms->getContentFull());
    $menu = $this->getMenu($content,$xpath->query("/body/section")->item(0));
    if(!is_null($menu)) $content->documentElement->appendChild($menu);
    return $content;
  }

  private function getMenu(HTMLPlus $content, DOMElement $section) {
    $ul = $content->createElement("ul");
    $li = null;
    foreach($section->childNodes as $n) {
      if($n->nodeType != 1) continue;
      if($n->nodeName == "section") {
        $menu = $this->getMenu($content,$n);
        if(!is_null($menu)) $li->appendChild($menu);
        continue;
      }
      if($n->nodeName != "h" || !$n->hasAttribute("link")) continue;
      $li = $content->createElement("li");
      $link = $n->getAttribute("link");
      if($this->subject->getCms()->getLink() != $link) {
        $a = $content->createElement("a",$n->nodeValue);
        $a->setAttribute("href",$link);
        $li->appendChild($a);
      } else {
        $li->nodeValue = $n->nodeValue;
      }
      $ul->appendChild($li);
    }
    if(is_null($li)) return null;
    return $ul;
  }

}

?>