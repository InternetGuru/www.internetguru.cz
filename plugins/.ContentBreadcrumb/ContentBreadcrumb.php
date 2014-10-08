<?php

class ContentBreadcrumb extends Plugin implements SplObserver, ContentStrategyInterface {
  private $titleQueries = array();

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $subject->setPriority($this,50);
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
    #FIXME
    $titleQueries = array();
    foreach(array_reverse($titleQueries) as $k => $q) {
      $i = $xpath->query($q)->item(0);
      $li = $content->createElement("li");
      if(!$i->hasAttribute("short") || count($titleQueries) == 1) {
        $text = $i->nodeValue;
      } else {
        $text = $i->getAttribute("short");
      }
      if(count($titleQueries)-1 != $k) {
        $a = $content->createElement("a",$text);
        if($k == 0) $href = ".";
        else $href = $i->getAttribute("link");
        if($i->hasAttribute("title")) $a->setAttribute("title",$i->getAttribute("title"));
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