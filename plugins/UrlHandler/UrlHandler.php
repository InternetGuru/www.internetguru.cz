<?php

#TODO: keep missing url_parts from var->nodeValue
#TODO: user redir in preinit

class UrlHandler extends Plugin implements SplObserver {
  const DEBUG = false;

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
      redirTo(ROOT_URL.$path.(strlen($query) ? "?$query" : ""), $code);
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
    $links = DOMBuilder::getLinks();
    if(DOMBuilder::isLink(getCurLink())) {
      if(getCurLink() != $links[0]) return;
      $link = ROOT_URL; // link to root heading permanent redir to root
      $code = 301;
    } else {
      $newLink = normalize(getCurLink(), "a-zA-Z0-9/_-");
      if(self::DEBUG) print_r($links);
      $linkId = $this->findSimilarLinkId($links, $newLink);
      if(is_null($linkId) || $linkId == $links[0]) $newLink = ""; // nothing found, redir to root
      else $newLink = $links[$linkId];
      $link = ROOT_URL.$newLink;
      $code = 404;
    }
    if(self::DEBUG) die("Redirecting to $link");
    redirTo($link, $code);
  }

  private function getBestId(Array $links, Array $found) {
    if(count($found) == 1) return key($found);
    $minLvl = PHP_INT_MAX;
    foreach($found as $id => $null) {
      $lvl = substr_count($links[$id], "/");
      if($lvl < $minLvl) $minLvl = $lvl;
      $found[$id] = $lvl;
    }
    $keys = array_keys($found, $minLvl);
    if(count($keys) == 1) return $keys[0];
    $minLen = PHP_INT_MAX;
    foreach($keys as $id) {
      $len = strlen($links[$id]);
      if($len < $minLen) $minLen = $len;
      $short[$id] = $len;
    }
    $keys = array_keys($short, $minLen);
    return $keys[0];
  }

  /**
   * [suppose]
   * - hotelpatriot (1)
   * - hotelpatriot/archiv (2)
   * - rsbstavebniny (3)
   * - rsbstavebniny/archiv (4)
   *
   * [url (redir)]
   * - hotel (1)
   * - patriot (1)
   * - patriot/a (2)
   * - patriot/archvi (2)
   * - rbstavebniny (3)
   * - rbstavebniny/a (4)
   * - rbstavebniny/archvi (4)
   * - rbstavebniny/chiv (4)
   */
  private function findSimilarLinkId(Array $links, $link) {
    if(!strlen($link)) return null;
    if(self::DEBUG) echo "findSimilarLinkId(links, $link)";
    // zero pos substring
    $found = $this->minPos($links, $link);
    if(self::DEBUG) print_r($found);
    if(count($found)) return $this->getBestId($links, $found);
    // low levenstein first
    $found = $this->minLev($links, $link, 2);
    if(self::DEBUG) print_r($found);
    if(count($found)) return $this->getBestId($links, $found);
    // first "directory" search
    $parts = explode("/", $link);
    if(count($parts) == 1) return null;
    $first = array_shift($parts);
    $foundId = $this->findSimilarLinkId($links, $first);
    if(is_null($foundId)) return null;
    array_unshift($parts, $links[$foundId]);
    $newLink = implode("/", $parts);
    if($newLink == $link) return $foundId;
    $foundId = $this->findSimilarLinkId($links, $newLink);
    return $foundId;
  }

  private function minPos(Array $links, $link, $max = null) {
    $linkpos = array();
    foreach ($links as $k => $l) {
      $pos = strpos($l, $link);
      if($pos === false || (!is_null($max) && $pos > $max)) continue;
      $linkpos[$k] = $pos;
    }
    asort($linkpos);
    if(count($linkpos)) return $linkpos;
    $sublinks = array();
    foreach($links as $k => $l) {
      $l = str_replace(array("_", "-"), "/", $l);
      if(strpos($l, "/") === false) continue;
      $sublinks[$k] = substr($l, strpos($l, "/")+1);
    }
    if(empty($sublinks)) return array();
    return $this->minPos($sublinks, $link, $max);
  }

  private function minLev(Array $links, $link, $limit) {
    $leven = array();
    foreach ($links as $k => $l) {
      $lVal = levenshtein($l, $link);
      if($lVal > $limit) continue;
      $leven[$k] = $lVal;
    }
    asort($leven);
    if(count($leven)) return $leven;
    $sublinks = array();
    foreach($links as $k => $l) {
      $l = str_replace(array("_", "-"), "/", $l);
      if(strpos($l, "/") === false) continue;
      $sublinks[$k] = substr($l, strpos($l, "/")+1);
    }
    if(empty($sublinks)) return array();
    return $this->minLev($sublinks, $link, $limit);
  }

}

?>
