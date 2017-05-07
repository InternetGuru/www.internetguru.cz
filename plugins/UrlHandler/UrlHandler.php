<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\ErrorPage;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class UrlHandler
 * @package IGCMS\Plugins
 */
class UrlHandler extends Plugin implements SplObserver {
  /**
   * @var bool
   */
  const DEBUG = false;
  /**
   * @var DOMDocumentPlus|null
   */
  private $cfg = null;
  /**
   * @var array
   */
  private $newReg = [];

  /**
   * UrlHandler constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 2);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_PREINIT) {
      return;
    }
    $this->cfg = $this->getXML();
    $this->httpsRedir();
    $this->cfgRedir();
    if (getCurLink() == "") {
      $subject->detach($this);
      return;
    }
    $this->proceed();
  }

  private function httpsRedir () {
    $protocol = is_file(HTTPS_FILE) ? "https" : "http";
    Cms::setVariable("default_protocol", $protocol);
    if (SCHEME == $protocol) {
      return;
    }
    if (SCHEME == "https" && !is_null(Cms::getLoggedUser())) {
      return;
    }
    redirTo("$protocol://".HOST.$_SERVER["REQUEST_URI"]);
  }

  private function cfgRedir () {
    $this->newReg = HTMLPlusBuilder::getIdToLink();
    foreach ($this->cfg->documentElement->childNodes as $e) {
      switch ($e->nodeName) {
        case 'redir':
        case 'rewrite':
          $this->{$e->nodeName}($e);
          break;
        default:
          continue;
      }
    }
    if (!empty($this->newReg)) {
      HTMLPlusBuilder::setIdToLink($this->newReg);
    }
  }

  private function proceed () {
    $links = array_keys(HTMLPlusBuilder::getLinkToId());
    $path = getCurLink();
    if (!HTMLPlusBuilder::isLink($path)) {
      $path = normalize($path, "a-zA-Z0-9/_-");
      if (self::DEBUG) {
        var_dump($links);
      }
      $linkId = $this->findSimilarLinkId($links, $path);
      if (!is_null($linkId) && !$linkId == $links[0]) {
        $path = $links[$linkId];
      }
    }
    if (!HTMLPlusBuilder::isLink($path)) {
      new ErrorPage(_("Requested page not found"), 404, true);
    } elseif ($path == $links[0]) {
      $path = "";
    }
    if ($path == getCurLink()) {
      return;
    }
    if (self::DEBUG) {
      die("Redirecting to '$path'");
    }
    redirTo(buildLocalUrl(["path" => $path, "query" => getCurQuery()]), 303);
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
   *
   * @param array $links
   * @param string $link
   * @return null|string
   */
  private function findSimilarLinkId (Array $links, $link) {
    if (!strlen($link)) {
      return null;
    }
    // zero pos substring
    $found = $this->minPos($links, $link);
    if (self::DEBUG) {
      var_dump($found);
    }
    if (count($found)) {
      return $this->getBestId($links, $found);
    }
    // low levenstein first
    $found = $this->minLev($links, $link, 2);
    if (self::DEBUG) {
      var_dump($found);
    }
    if (count($found)) {
      return $this->getBestId($links, $found);
    }
    // first "directory" search
    $parts = explode("/", $link);
    if (count($parts) == 1) {
      return null;
    }
    $first = array_shift($parts);
    $foundId = $this->findSimilarLinkId($links, $first);
    if (is_null($foundId)) {
      return null;
    }
    array_unshift($parts, $links[$foundId]);
    $newLink = implode("/", $parts);
    if ($newLink == $link) {
      return $foundId;
    }
    $foundId = $this->findSimilarLinkId($links, $newLink);
    return $foundId;
  }

  /**
   * @param array $links
   * @param string $link
   * @param null $max
   * @return array
   */
  private function minPos (Array $links, $link, $max = null) {
    $linkpos = [];
    foreach ($links as $k => $l) {
      $pos = strpos($l, $link);
      if ($pos === false || (!is_null($max) && $pos > $max)) {
        continue;
      }
      $linkpos[$k] = strpos($l, "#") === 0 ? $pos - 1 : $pos;
    }
    asort($linkpos);
    if (count($linkpos)) {
      return $linkpos;
    }
    $sublinks = [];
    foreach ($links as $k => $l) {
      $l = str_replace(["_", "-"], "/", $l);
      if (strpos($l, "/") === false) {
        continue;
      }
      $sublinks[$k] = substr($l, strpos($l, "/") + 1);
    }
    if (empty($sublinks)) {
      return [];
    }
    return $this->minPos($sublinks, $link, $max);
  }

  /**
   * @param array $links
   * @param array $found
   * @return string
   */
  private function getBestId (Array $links, Array $found) {
    if (count($found) == 1) {
      return key($found);
    }
    $minVal = PHP_INT_MAX;
    $minLvl = PHP_INT_MAX;
    $foundLvl = [];
    foreach ($found as $id => $val) {
      $lvl = substr_count($links[$id], "/");
      if ($val < $minVal) {
        $minVal = $val;
      }
      if ($lvl < $minLvl) {
        $minLvl = $lvl;
      }
      $foundLvl[$id] = $lvl;
    }
    $minLen = PHP_INT_MAX;
    $short = [];
    foreach ($found as $id => $val) {
      if ($foundLvl[$id] != $minLvl) {
        continue;
      }
      if ($val != $minVal) {
        continue;
      }
      $len = strlen($links[$id]);
      if ($len < $minLen) {
        $minLen = $len;
      }
      $short[$id] = $len;
    }
    $keys = array_keys($short, $minLen); // filter result to minlength
    return $keys[0];
  }

  /**
   * @param array $links
   * @param string $link
   * @param int $limit
   * @return array
   */
  private function minLev (Array $links, $link, $limit) {
    $leven = [];
    foreach ($links as $k => $l) {
      $lVal = levenshtein($l, $link);
      if ($lVal > $limit) {
        continue;
      }
      $leven[$k] = $lVal;
    }
    asort($leven);
    if (count($leven)) {
      return $leven;
    }
    $sublinks = [];
    foreach ($links as $k => $l) {
      $l = str_replace(["_", "-"], "/", $l);
      if (strpos($l, "/") === false) {
        continue;
      }
      $sublinks[$k] = substr($l, strpos($l, "/") + 1);
    }
    if (empty($sublinks)) {
      return [];
    }
    return $this->minLev($sublinks, $link, $limit);
  }

  private function rewrite (DOMElementPlus $rewrite) {
    $match = $rewrite->getRequiredAttribute("match");
    foreach ($this->newReg as $id => $link) {
      if (strpos($link, $match) === false) {
        continue;
      }
      $this->newReg[$id] = str_replace($match, $rewrite->nodeValue, $link);
    }
  }

  private function redir (DOMElementPlus $redir) {
    if ($redir->hasAttribute("link") && $redir->getAttribute("link") != getCurLink()) {
      return;
    }
    $pNam = $redir->hasAttribute("parName") ? $redir->getAttribute("parName") : null;
    $pVal = $redir->hasAttribute("parValue") ? $redir->getAttribute("parValue") : null;
    if (!$this->queryMatch($pNam, $pVal)) {
      return;
    }
    try {
      if ($redir->nodeValue == "/") {
        redirTo(["path" => ""]);
      }
      $pLink = parseLocalLink($redir->nodeValue);
      if (is_null($pLink)) {
        redirTo($redir->nodeValue);
      } // external redir
      $silent = !isset($pLink["path"]);
      if ($silent) {
        $pLink["path"] = getCurLink();
      } // no path = keep current path
      if (strpos($redir->nodeValue, "?") === false) {
        $pLink["query"] = getCurQuery();
      } // no query = keep current query
      #todo: no value ... keep current parameter value, eg. "?Admin" vs. "?Admin="
      try {
        # TODO
        #$pLink = DOMBuilder::normalizeLink($pLink);
        #todo: configurable status code
        redirTo(buildLocalUrl($pLink));
      } catch (Exception $e) {
        if (!$silent) {
          throw $e;
        }
      }
    } catch (Exception $e) {
      Logger::user_warning(sprintf(_("Unable to redirect to %s: %s"), $redir->nodeValue, $e->getMessage()));
    }
  }

  /**
   * @param string $pNam
   * @param string $pVal
   * @return bool
   */
  private function queryMatch ($pNam, $pVal) {
    foreach (explode("&", getCurQuery()) as $q) {
      if (is_null($pVal) && strpos("$q=", "$pNam=") === 0) {
        return true;
      }
      if (!is_null($pVal) && "$q" == "$pNam=$pVal") {
        return true;
      }
    }
    return false;
  }

}

