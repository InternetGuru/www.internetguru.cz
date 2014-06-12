<?php

class ContentMatch implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject

  public function update(SplSubject $subject) {
    if(!strlen($subject->getCms()->getLink())) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->getCms()->setContentStrategy($this,1);
    }
  }

  public function getTitle(Array $q) {
    return $q;
  }

  public function getDescription($q) {
    return $q;
  }

  public function getContent(DOMDocument $origContent) {
    $cms = $this->subject->getCms();
    $xpath = new DOMXPath($cms->getContentFull());
    $q = "//h[@link='" . $cms->getLink() . "']";
    $exactMatch = $xpath->query($q);
    if($exactMatch->length > 1)
      throw new Exception("Link not unique");
    if($exactMatch->length == 0) {
      $link = $this->findSimilar($xpath,$cms->getLink());
      $this->redirToLink($link);
    }
    return $origContent;
  }

  private function findSimilar(DOMXPath $xpath,$link) {
    $link = mb_convert_encoding($link,"ASCII","UTF-8");
    $headings = $xpath->query("//h[@link]");
    $simil = array();
    foreach($headings as $h) {
      $simil[$h->getAttribute("link")] = similar_text($h->getAttribute("link"),$link);
    }
    arsort($simil);
    if(strlen($link) - reset($simil) < 3) return key($simil);
    return "";
  }

  private function redirToLink($link) {
    header("HTTP/1.0 404 Not Found");
    header("Location: $link");
    exit();
  }

}

?>