<?php

class ContentMatch implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "preinit") {
      $subject->detach($this);
      $subject->attach($this,1);
    }
    if($subject->getCms()->getLink() == ".") {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    $subject->getCms()->setContentStrategy($this,1);
  }

  public function getTitle(Array $q) {
    $this->proceed();
    return $q;
  }

  public function getDescription($q) {
    return $q;
  }

  public function getContent(HTMLPlus $origContent) {
    return $origContent;
  }

  private function proceed() {
    $cms = $this->subject->getCms();
    $xpath = new DOMXPath($cms->getContentFull());
    $q = "//h[@link='" . $cms->getLink() . "']";
    $exactMatch = $xpath->query($q);
    if($exactMatch->length > 1)
      throw new Exception("Link not unique");
    if($exactMatch->length == 1) {
      $this->subject->detach($this);
      return;
    }
    $link = $this->findSimilar($xpath,$cms->getLink());
    $this->redirToLink($link);
  }

  private function findSimilar(DOMXPath $xpath,$link) {
    $link = mb_convert_encoding($link,"ASCII","UTF-8");
    $links = array();
    foreach($xpath->query("//h[@link]") as $h) $links[] = $h->getAttribute("link");
    // closest substring
    if(($newLink = $this->similarPos($links,$link)) !== false) return $newLink;
    // max typo
    if(($newLink = $this->bestLeven($links,$link,3)) !== false) return $newLink;
    // else go to homepage
    return ".";
  }

  private function similarPos(Array $links,$link) {
    $linkpos = array();
    foreach ($links as $l) {
      $pos = strpos($l, $link);
      if($pos === false) continue;
      $linkpos[$l] = $pos;
    }
    asort($linkpos);
    if(!empty($linkpos)) return key($linkpos);
    return false;
  }

  private function bestLeven(Array $links,$link,$limit) {
    $leven = array();
    foreach ($links as $l) $leven[$l] = levenshtein($l, $link);
    asort($leven);
    if(reset($leven) <= $limit) return key($leven);
    return false;
  }

  private function redirToLink($link) {
    header("Location: $link",true,404);
    header("Refresh: 0; url=$link");
    exit();
  }

}

?>
