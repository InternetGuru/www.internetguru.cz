<?php

#TODO: keep missing url_parts from var->nodeValue
#TODO: user redir in preinit

class UrlHandler extends Plugin implements SplObserver {

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 3);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached(array("Xhtml11", "ContentLink"))) return;
    $this->cfgRedir();
    if(getCurLink() == "") {
      $subject->detach($this);
      return;
    }
    $this->proceed();
  }

  private function cfgRedir() {
    $cfg = $this->getDOMPlus();
    foreach($cfg->documentElement->childNodes as $var) {
      if($var->nodeName != "var") continue;
      if($var->hasAttribute("link") && $var->getAttribute("link") != getCurLink()) continue;
      $pNam = $var->hasAttribute("parName") ? $var->getAttribute("parName") : null;
      $pVal = $var->hasAttribute("parValue") ? $var->getAttribute("parValue") : null;
      if(!$this->queryMatch($pNam, $pVal)) continue;
      $code = $var->hasAttribute("code") && $var->getAttribute("code") == "permanent" ? 301 : 302;
      $path = parse_url($var->nodeValue, PHP_URL_PATH);
      if(!strlen($path)) $path = getCurLink(); // current link if empty string
      while(strpos($path, "/") === 0) $path = substr($path, 1); // empty string if root
      if($path != getCurLink() && strlen($path) && !DOMBuilder::isLink($path)) {
        new Logger(sprintf(_("Redirection link '%s' not found"), $path), "warning");
        continue;
      }
      $query = parse_url($var->nodeValue, PHP_URL_QUERY);
      if(strlen($query)) $query = $this->alterQuery($query, $pNam);
      redirTo(getRoot().$path.(strlen($query) ? "?$query" : ""), $code);
    }
  }

  private function alterQuery($query, $pNam) {
    $param = array();
    foreach(explode("&", $query) as $p) {
      list($parName, $parValue) = explode("=", "$p="); // ensure there is always parValue
      if(!strlen($parValue)) $parValue = $_GET[$pNam];
      $param[$parName] = $parValue;
    }
    $query = array();
    foreach($param as $k => $v) $query[] = $k.(strlen($v) ? "=$v" : "");
    return implode("&", $query);
  }

  private function queryMatch($pNam, $pVal) {
    foreach(explode("&", parse_url(getCurLink(true), PHP_URL_QUERY)) as $q) {
      if(is_null($pVal) && strpos("$q=", "$pNam=$pVal") === 0) return true;
      if(!is_null($pVal) && "$q=" == "$pNam=$pVal") return true;
    }
    return false;
  }

  private function proceed() {
    if(DOMBuilder::isLink(getCurLink())) return;
    $newLink = normalize(getCurLink(), "a-zA-Z0-9/_-");
    $links = DOMBuilder::getLinks();
    $linkId = $this->findSimilar($links, $newLink);
    if(is_null($linkId)) $newLink = ""; // nothing found, redir to hp
    else $newLink = $links[$linkId];
    new Logger(sprintf(_("Link '%s' not found, redir to '%s'"), getCurLink(), $newLink), "info");
    redirTo(getRoot().$newLink, 404);
  }

  /**
   * exists: aa/bb/cc/dd, aa/bb/cc/ee, aa/bb/dd, aa/dd
   * call: aa/b/cc/dd -> find aa/bb/cc/dd (not aa/dd)
   */
  private function findSimilar(Array $links, $link) {
    if(!strlen($link)) return null;
    // zero pos substring
    if(($newLink = $this->minPos($links, $link, 0)) !== false) return $newLink;
    // low levenstein first
    if(($newLink = $this->minLev($links, $link, 1)) !== false) return $newLink;

    $parts = explode("/", $link);
    $first = array_shift($parts);
    $subset = array();
    foreach($links as $k => $l) {
      if(strpos($l, $first) !== 0) continue;
      if(strpos($l, "/") === false) continue;
      else $subset[$k] = substr($l, strpos($l, "/")+1);
    }
    if(count($subset) == 1) return key($subset);
    if(empty($subset)) $subset = $links;
    return $this->findSimilar($subset, implode("/", $parts));
  }

  private function minPos(Array $links, $link, $max) {
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
      $l = str_replace(array("_", "-"), "/", $l);
      if(strpos($l, "/") === false) continue;
      $sublinks[$k] = substr($l, strpos($l, "/")+1);
    }
    if(empty($sublinks)) return false;
    return $this->minPos($sublinks, $link, $max);
  }

  private function minLev(Array $links, $link, $limit) {
    $leven = array();
    foreach ($links as $k => $l) $leven[$k] = levenshtein($l, $link);
    asort($leven);
    if(reset($leven) <= $limit) return key($leven);
    $sublinks = array();
    foreach($links as $k => $l) {
      $l = str_replace(array("_", "-"), "/", $l);
      if(strpos($l, "/") === false) continue;
      $sublinks[$k] = substr($l, strpos($l, "/")+1);
    }
    if(empty($sublinks)) return false;
    return $this->minLev($sublinks, $link, $limit);
  }

}

?>
