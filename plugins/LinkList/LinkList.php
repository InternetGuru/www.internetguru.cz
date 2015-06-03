<?php

# todo: linklist-href style

class LinkList extends Plugin implements SplObserver, ContentStrategyInterface {

  private $cssClass = "linklist";

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 200);
  }

  public function update(SplSubject $subject) {}

  public function getContent(HTMLPlus $content) {
    $sections = $content->documentElement->getElementsByTagName("section");
    foreach($sections as $s) {
      if(!$s->hasClass($this->cssClass)) continue;
      $this->createLinkList($s);
    }
    return $content;
  }

  private function createLinkList(DOMElementPlus $section) {
    $i = 0;
    $count = 0;
    $links = array();
    $linksArray = array();
    foreach($section->getElementsByTagName("a") as $l) { $links[] = $l; }
    foreach($links as $l) {
      if(!$l->hasAttribute("href")) continue;
      $i++;
      if(!isset($linksArray[$l->getAttribute("href")])) $count++;
      $linksArray[$l->getAttribute("href")] = $l;
      $a = $l->ownerDocument->createElement("a");
      $a->nodeValue = "[$count]";
      $a->setAttribute("class", "{$this->cssClass}-href");
      $a->setAttribute("href", "#{$this->cssClass}-$count");
      if(!is_null($l->nextSibling)) $l->parentNode->insertBefore($a, $l->nextSibling);
      else $l->parentNode->appendChild($a);
    }
    $list = $section->appendChild($section->ownerDocument->createElement("ol"));
    $i = 0;
    foreach($linksArray as $link) {
      $i++;
      $li = $list->appendChild($list->ownerDocument->createElement("li"));
      $text = "";
      if($link->hasAttribute("title")) $text = $link->getAttribute("title");
      $text .= " [".$link->getAttribute("href")."]";
      $li->nodeValue = $text;
      $li->setAttribute("id", "{$this->cssClass}-$i");
    }
  }

}

?>