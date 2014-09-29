<?php

class ContentMatch extends Plugin implements SplObserver, ContentStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getCms()->getLink() == ".") {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    if($this->detachIfNotOS("Xhtml11")) return;
    $subject->setPriority($this,1);
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
    if($exactMatch->length == 1) return;
    $link = normalize($cms->getLink());
    $links = array();
    foreach($xpath->query("//h[@link]") as $h) $links[] = $h->getAttribute("link");
    $linkId = $this->findSimilar($links,$link);

    if(is_null($linkId)) $link = ".";
    else $link = $links[$linkId];
    $this->redirToLink($link);
  }

  /**
   * exists: aa/bb/cc/dd, aa/bb/cc/ee, aa/bb/dd, aa/dd
   * call: aa/b/cc/dd -> find aa/bb/cc/dd (not aa/dd)
   */
  private function findSimilar(Array $links,$link) {
    if(!strlen($link)) return null;
    // zero pos substring
    if(($newLink = $this->minPos($links,$link,0)) !== false) return $newLink;
    // low levenstein first
    if(($newLink = $this->minLev($links,$link,1)) !== false) return $newLink;

    $parts = explode("/", $link);
    $first = array_shift($parts);
    $subset = array();
    foreach($links as $k => $l) {
      if(strpos($l,$first) !== 0) continue;
      if(strpos($l,"/") === false) continue;
      else $subset[$k] = substr($l,strpos($l,"/")+1);
    }
    if(count($subset) == 1) return key($subset);
    if(empty($subset)) $subset = $links;
    return $this->findSimilar($subset,implode("/",$parts));
  }

  private function minPos(Array $links,$link,$max) {
    $linkpos = array();
    foreach ($links as $k => $l) {
      $pos = strpos($l, $link);
      if($pos === false || $pos > $max) continue;
      $linkpos[$k] = $pos;
    }
    asort($linkpos);
    if(!empty($linkpos)) return key($linkpos);
    $sublinks = array();
    foreach($links as $k => $l) {
      $l = str_replace(array("_","-"), "/", $l);
      if(strpos($l,"/") === false) continue;
      $sublinks[$k] = substr($l,strpos($l,"/")+1);
    }
    if(empty($sublinks)) return false;
    return $this->minPos($sublinks,$link,$max);
  }

  private function minLev(Array $links,$link,$limit) {
    $leven = array();
    foreach ($links as $k => $l) $leven[$k] = levenshtein($l, $link);
    asort($leven);
    if(reset($leven) <= $limit) return key($leven);
    $sublinks = array();
    foreach($links as $k => $l) {
      $l = str_replace(array("_","-"), "/", $l);
      if(strpos($l,"/") === false) continue;
      $sublinks[$k] = substr($l,strpos($l,"/")+1);
    }
    if(empty($sublinks)) return false;
    return $this->minLev($sublinks,$link,$limit);
  }

  private function redirToLink($link) {
    header("Location: $link",true,404);
    header("Refresh: 0; url=$link");
    exit();
  }

}

?>
