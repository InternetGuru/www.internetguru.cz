<?php

class ContentBreadcrumb implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject
  private $titleQueries = array();

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->setPriority($this,50);
    }
  }

  public function getTitle(Array $queries) {
    $this->titleQueries = $queries;
    return $queries;
  }

  public function getDescription($query) {
    return $query;
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $xpath = new DOMXPath($cms->getContentFull());
    #$content->documentElement->appendChild($this->getBreadcrumb($xpath,$content));
    $s = $content->documentElement->getElementsByTagName("section")->item(0);
    $content->documentElement->insertBefore($this->getBreadcrumb($xpath,$content),$s);
    return $content;
  }

  private function getBreadcrumb(DOMXPath $xpath, HTMLPlus $content) {
    $ol = $content->createElement("ol");
    $ol->setAttribute("class","cms-breadcrumb");
    foreach(array_reverse($this->titleQueries) as $k => $q) {
      $i = $xpath->query($q)->item(0);
      $li = $content->createElement("li");
      if(!$i->hasAttribute("short") || count($this->titleQueries) == 1) {
        $text = $i->nodeValue;
      } else {
        $text = $i->getAttribute("short");
      }
      if(count($this->titleQueries)-1 != $k) {
        $a = $content->createElement("a",$text);
        if($k == 0) $href = ".";
        else $href = $i->getAttribute("link");
        $a->setAttribute("href",$href);
        $li->appendChild($a);
      } else {
        $li->nodeValue = $text;
      }
      $ol->appendChild($li);
    }
    return $ol;
  }

}

?>