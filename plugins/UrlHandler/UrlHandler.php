<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use Exception;
use SplObserver;
use SplSubject;

class UrlHandler extends Plugin implements SplObserver {
  const DEBUG = false;
  private $cfg = null;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 3);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached(array("HtmlOutput", "ContentLink"))) return;
    $this->cfg = $this->getXML();
    if(!IS_LOCALHOST) $this->httpsRedir();
    $this->cfgRedir();
    if(getCurLink() == "") {
      $subject->detach($this);
      return;
    }
    $this->proceed();
  }

  private function httpsRedir() {
    $https = true;
    $urlMatch = false;
    foreach($this->cfg->documentElement->childNodes as $redir) {
      if($redir->nodeName != "https") continue;
      $https = false;
      $urlMatch = true;
      $pRedir = parseLocalLink($redir->nodeValue);
      if(isset($pRedir["path"]) && $pRedir["path"] != getCurLink()) $urlMatch = false;
      if(isset($pRedir["query"]) && $pRedir["query"] != getCurQuery()) $urlMatch = false;
      if($urlMatch) break;
    }
    Cms::setVariable("default_protocol", ($https ? "https" : "http"));
    if(SCHEME == "https") {
      if(is_null(Cms::getLoggedUser()) && !$urlMatch && !$https) {
        redirTo("http://".HOST.$_SERVER["REQUEST_URI"]);
      }
     return;
    }
    if($urlMatch || $https) redirTo("https://".HOST.$_SERVER["REQUEST_URI"]);
  }

  private function cfgRedir() {
    foreach($this->cfg->documentElement->childNodes as $redir) {
      if($redir->nodeName != "redir") continue;
      if($redir->hasAttribute("link") && $redir->getAttribute("link") != getCurLink()) continue;
      $pNam = $redir->hasAttribute("parName") ? $redir->getAttribute("parName") : null;
      $pVal = $redir->hasAttribute("parValue") ? $redir->getAttribute("parValue") : null;
      if(!$this->queryMatch($pNam, $pVal)) continue;
      try {
        if($redir->nodeValue == "/" || $redir->nodeValue == "") redirTo(array("path" => ""));
        $pLink = parseLocalLink($redir->nodeValue);
        if(is_null($pLink)) redirTo($redir->nodeValue); // external redir
        $silent = !isset($pLink["path"]);
        if($silent) $pLink["path"] = getCurLink(); // no path = keep current path
        if(strpos($redir->nodeValue, "?") === false) $pLink["query"] = getCurQuery(); // no query = keep current query
        #todo: no value ... keep current parameter value, eg. "?Admin" vs. "?Admin="
        try {
          $pLink = DOMBuilder::normalizeLink($pLink);
          #todo: configurable status code
          redirTo(buildLocalUrl($pLink));
        } catch(Exception $e) {
          if(!$silent) throw $e;
        }
      } catch(Exception $e) {
        Logger::user_warning(sprintf(_("Unable to redir to %s: %s"), implodeLink($pLink), $e->getMessage()));
      }
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
    foreach(explode("&", getCurQuery()) as $q) {
      if(is_null($pVal) && strpos("$q=", "$pNam=") === 0) return true;
      if(!is_null($pVal) && "$q" == "$pNam=$pVal") return true;
    }
    return false;
  }

  private function proceed() {
    $links = DOMBuilder::getLinks();
    $path = normalize(getCurLink(), "a-zA-Z0-9/_-");
    if(!DOMBuilder::isLink($path)) {
      if(self::DEBUG) var_dump($links);
      $linkId = $this->findSimilarLinkId($links, $path);
      if(!is_null($linkId) && !$linkId == $links[0]) $path = $links[$linkId];
    }
    if(!DOMBuilder::isLink($path) || $path == $links[0]) $path = "";
    if($path == getCurLink()) return;
    $code = 404;
    if(self::DEBUG) die("Redirecting to '$path'");
    redirTo(buildLocalUrl(Array("path" => $path, "query" => getCurQuery())), $code);
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

