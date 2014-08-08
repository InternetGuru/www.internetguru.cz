<?php

class ContentMenu implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->setPriority($this,60);
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
    if(is_null($menu)) return $content;
    $menu->setAttribute("class","cms-menu");
    $s = $content->documentElement->getElementsByTagName("section")->item(0);
    $content->documentElement->insertBefore($menu,$s);
    $this->trimList($menu);
    return $content;
  }

  private function trimList(DOMElement $ul) {
    $currentLink = false;
    $deepLink = false;
    $children = array();
    foreach($ul->childNodes as $li) {
      foreach($li->childNodes as $n) {
        if($n->nodeName == "a") $currentLink = true;
        if($n->nodeName == "ul") $deepLink = $this->trimList($n);
      }
    }
    if($currentLink || $deepLink) return true;
    $ul->parentNode->removeChild($ul);
    return false;
  }

  private function getMenu(HTMLPlus $content, DOMElement $section) {
    $ul = $content->createElement("ul");
    $li = null;
    foreach($section->childNodes as $n) {
      if($n->nodeType != 1) continue;
      if($n->nodeName == "section") {
        $menu = $this->getMenu($content,$n);
        if(!is_null($menu)) {
          $li->appendChild($menu);
        }
        continue;
      }
      if($n->nodeName != "h") continue;
      $li = $content->createElement("li");
      $link = null;
      if($n->hasAttribute("link")) $link = $n->getAttribute("link");
      $text = $n->nodeValue;
      if($n->hasAttribute("short")) $text = $n->getAttribute("short");
      if(!is_null($link)) {
        $a = $content->createElement("a",$text);
        if($this->subject->getCms()->getLink() != $link) $a->setAttribute("href",$link);
        $li->appendChild($a);
      } else {
        $li->nodeValue = $text;
      }
      $ul->appendChild($li);
    }
    if(is_null($li)) return null;
    return $ul;
  }

}

?>