<?php

class ContentMatch extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    if($this->detachIfNotAttached(array("Xhtml11","ContentLink"))) return;
    $link = getCurLink();
    $this->cfgRedir($link);
    if($link == "") {
      $subject->detach($this);
      return;
    }
    $this->proceed($link);
  }

  private function cfgRedir($link) {
    $cfg = $this->getDOMPlus();
    $dest = $cfg->getElementById($link,"link");
    if(is_null($dest)) return;
    $code = 302;
    if($dest->hasAttribute("code") && $dest->getAttribute("code") == "permanent")
      $code = 301;
    $this->proceed($dest->nodeValue,$code);
  }

  private function proceed($link,$code=404) {
    $xpath = new DOMXPath($this->subject->getCms()->getContentFull());
    $q = "//h[@link='" . $link . "']";
    $exactMatch = $xpath->query($q);
    if($exactMatch->length > 1)
      throw new Exception("Link not unique");
    if($code != 404) {
      if($exactMatch->length != 1) {
        new Logger("Destination redir link '$link' not found","warning");
        if(getCurLink() == "") return;
        $link = ""; // redir to hp if page not found (and not at hp)
      }
      redirTo(getRoot().$link,$code);
    }
    if($exactMatch->length == 1) return;
    $newLink = normalize($link);
    $links = array();
    foreach($xpath->query("//h[@link]") as $h) $links[] = $h->getAttribute("link");
    $linkId = $this->findSimilar($links,$newLink);
    if(is_null($linkId)) $newLink = ""; // nothing found, redir to hp
    else $newLink = $links[$linkId];
    new Logger("Link '$link' not found, redir to '$newLink'","info");
    redirTo(getRoot().$newLink,$code);
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

}

?>
