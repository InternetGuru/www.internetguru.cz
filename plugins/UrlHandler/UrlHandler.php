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
      try {
        if($var->nodeValue == "/" || $var->nodeValue == "") redirTo(array("path" => ""));
        $pLink = parseLocalLink($var->nodeValue);
        if(is_null($pLink)) redirTo($var->nodeValue); // external redir
        if(!isset($pLink["path"])) $pLink["path"] = getCurLink(); // no path = keep current path
        if(strpos($var->nodeValue, "?") === false) $pLink["query"] = getCurQuery(); // no query = keep current query
        #todo: no value ... keep current parameter value, eg. "?Admin" vs. "?Admin="
        $pLink = DOMBuilder::normalizeLink($pLink);
        redirTo(buildLocalUrl($pLink));
      } catch(Exception $e) {
        new Logger($e->getMessage(), Logger::LOGGER_WARNING);
      }
    }
  }

  #todo: simplify parse_str()
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

  #todo: simplify $_GET
  private function queryMatch($pNam, $pVal) {
    foreach(explode("&", getCurQuery()) as $q) {
      if(is_null($pVal) && strpos("$q=", "$pNam=") === 0) return true;
      if(!is_null($pVal) && "$q" == "$pNam=$pVal") return true;
    }
    return false;
  }

  private function proceed() {
    $links = DOMBuilder::getLinks();
    if(DOMBuilder::isLink(getCurLink())) {
      if(getCurLink() != $links[0]) return;
      $link = array("path" => ""); // link to root heading permanent redir to root
      $code = 301;
    } else {
      #$newLink = array("path" => normalize(getCurLink(), "a-zA-Z0-9/_-"));
      $path = normalize(getCurLink(), "a-zA-Z0-9/_-");
      if(!DOMBuilder::isLink($path)) {
        if(self::DEBUG) var_dump($links);
        $linkId = $this->findSimilarLinkId($links, $path);
        if(!is_null($linkId) && !$linkId == $links[0]) $newLink = parseLocalLink($links[$linkId]);
        if(!isset($newLink["path"])) $newLink["path"] = "";
      }
      $link = $newLink;
      $code = 404;
    }
    $link = buildLocalUrl($link);
    if(self::DEBUG) die("Redirecting to $link");
    redirTo($link, $code);
  }

  private function getBestId(Array $links, Array $found) {
    if(count($found) == 1) return key($found);
    $minVal = PHP_INT_MAX;
    $minLvl = PHP_INT_MAX;
    $foundLvl = array();
    foreach($found as $id => $val) {
      $lvl = substr_count($links[$id], "/");
      if($val < $minVal) $minVal = $val;
      if($lvl < $minLvl) $minLvl = $lvl;
      $foundLvl[$id] = $lvl;
    }
    $minLen = PHP_INT_MAX;
    foreach($found as $id => $val) {
      if($foundLvl[$id] != $minLvl) continue;
      if($val != $minVal) continue;
      $len = strlen($links[$id]);
      if($len < $minLen) $minLen = $len;
      $short[$id] = $len;
    }
    $keys = array_keys($short, $minLen); // filter result to minlength
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
    // zero pos substring
    $found = $this->minPos($links, $link);
    if(self::DEBUG) var_dump($found);
    if(count($found)) return $this->getBestId($links, $found);
    // low levenstein first
    $found = $this->minLev($links, $link, 2);
    if(self::DEBUG) var_dump($found);
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
      $linkpos[$k] = strpos($l, "#") === 0 ? $pos-1 : $pos;
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
