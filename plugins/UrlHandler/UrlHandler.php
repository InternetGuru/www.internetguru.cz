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
use IGCMS\Core\ResourceInterface;
use SplObserver;
use SplSubject;

/**
 * Class UrlHandler
 * @package IGCMS\Plugins
 */
class UrlHandler extends Plugin implements SplObserver, ResourceInterface {
  /**
   * @var bool
   */
  const DEBUG = false;
  /**
   * @var array
   */
  private static $newReg = [];
  /**
   * @var bool
   */
  private static $notFound = false;

  /**
   * UrlHandler constructor.
   * @param Plugins|SplSubject $s
   * @throws \ReflectionException
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 90);
  }

  /**
   * @param string $filePath
   * @return bool
   */
  public static function isSupportedRequest ($filePath) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    return $ext !== "php";
  }

  /**
   * @throws Exception
   */
  public static function handleRequest () {
    $cfg = self::getXML();
    self::httpsRedir();
    self::cfgRedir($cfg);
  }

  /**
   * @param Plugins|SplSubject $subject
   * @throws Exception
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_PREINIT) {
      return;
    }
    $cfg = self::getXML();
    self::httpsRedir();
    self::cfgRedir($cfg);
    if (get_link() == "") {
      $subject->detach($this);
      return;
    }
    self::proceed();
  }

  /**
   * @throws Exception
   */
  private static function httpsRedir () {
    $protocol = is_file(HTTPS_FILE) ? "https" : "http";
    Cms::setVariable("default_protocol", $protocol);
    if (SCHEME == $protocol) {
      return;
    }
    if (SCHEME == "https" && !is_null(Cms::getLoggedUser())) {
      return;
    }
    redir_to("$protocol://".HTTP_HOST.$_SERVER["REQUEST_URI"]);
  }

  /**
   * @param DOMDocumentPlus $cfg
   */
  private static function cfgRedir (DOMDocumentPlus $cfg) {
    self::$newReg = HTMLPlusBuilder::getIdToLink();
    foreach ($cfg->documentElement->childNodes as $childElm) {
      switch ($childElm->nodeName) {
        case 'notfound':
        case 'redir':
        case 'rewrite':
          $nodeName = $childElm->nodeName;
          self::{$nodeName}($childElm);
          break;
        default:
          continue;
      }
    }
    if (!empty(self::$newReg)) {
      HTMLPlusBuilder::setIdToLink(self::$newReg);
    }
  }

  /**
   * @throws Exception
   */
  private static function proceed () {
    if (self::$notFound) {
      Logger::notice(_("This page is visible only for logged user (otherwise 404)"));
    }
    $links = array_keys(HTMLPlusBuilder::getLinkToId());
    $path = get_link();
    if (!HTMLPlusBuilder::isLink($path)) {
      $path = normalize($path, "a-zA-Z0-9/_-");
      if (self::DEBUG) {
        var_dump($path);
        var_dump($links);
      }
      $linkId = self::findSimilarLinkId($links, $path);
      if (!is_null($linkId) && !$linkId == $links[0]) {
        $path = $links[$linkId];
      }
    }
    if (!HTMLPlusBuilder::isLink($path)) {
      new ErrorPage(_("Requested page not found"), 404, true);
    } elseif ($path == $links[0]) {
      $path = "";
    }
    if ($path == get_link()) {
      return;
    }
    if (self::DEBUG) {
      die("Redirecting to '$path'");
    }
    redir_to(build_local_url(["path" => $path, "query" => get_query()]), 303);
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
  private static function findSimilarLinkId (Array $links, $link) {
    if (!strlen($link)) {
      return null;
    }
    // zero pos substring
    $found = self::minPos($links, $link);
    if (self::DEBUG) {
      var_dump($found);
    }
    if (count($found)) {
      return self::getBestId($links, $found);
    }
    // low levenstein first
    $found = self::minLev($links, $link, 2);
    if (self::DEBUG) {
      var_dump($found);
    }
    if (count($found)) {
      return self::getBestId($links, $found);
    }
    // first "directory" search
    $parts = explode("/", $link);
    if (count($parts) == 1) {
      return null;
    }
    $first = array_shift($parts);
    $foundId = self::findSimilarLinkId($links, $first);
    if (is_null($foundId)) {
      return null;
    }
    array_unshift($parts, $links[$foundId]);
    $newLink = implode("/", $parts);
    if ($newLink == $link) {
      return $foundId;
    }
    $foundId = self::findSimilarLinkId($links, $newLink);
    return $foundId;
  }

  /**
   * @param array $links
   * @param string $curLink
   * @param null $max
   * @return array
   */
  private static function minPos (Array $links, $curLink, $max = null) {
    $linkpos = [];
    foreach ($links as $key => $link) {
      $link = strtolower($link);
      $pos = strpos($link, $curLink);
      if ($pos === false || (!is_null($max) && $pos > $max)) {
        continue;
      }
      $linkpos[$key] = strpos($link, "#") === 0 ? $pos - 1 : $pos;
    }
    asort($linkpos);
    if (count($linkpos)) {
      return $linkpos;
    }
    $sublinks = [];
    foreach ($links as $key => $link) {
      $link = strtolower($link);
      $link = str_replace(["_", "-"], "/", $link);
      if (strpos($link, "/") === false) {
        continue;
      }
      $sublinks[$key] = substr($link, strpos($link, "/") + 1);
    }
    if (empty($sublinks)) {
      return [];
    }
    return self::minPos($sublinks, $link, $max);
  }

  /**
   * @param array $links
   * @param array $found
   * @return string
   */
  private static function getBestId (Array $links, Array $found) {
    if (count($found) == 1) {
      return key($found);
    }
    $minVal = PHP_INT_MAX;
    $minLvl = PHP_INT_MAX;
    $foundLvl = [];
    foreach ($found as $linkId => $val) {
      $lvl = substr_count($links[$linkId], "/");
      if ($val < $minVal) {
        $minVal = $val;
      }
      if ($lvl < $minLvl) {
        $minLvl = $lvl;
      }
      $foundLvl[$linkId] = $lvl;
    }
    $minLen = PHP_INT_MAX;
    $short = [];
    foreach ($found as $linkId => $val) {
      if ($foundLvl[$linkId] != $minLvl) {
        continue;
      }
      if ($val != $minVal) {
        continue;
      }
      $len = strlen($links[$linkId]);
      if ($len < $minLen) {
        $minLen = $len;
      }
      $short[$linkId] = $len;
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
  private static function minLev (Array $links, $link, $limit) {
    $leven = [];
    foreach ($links as $key => $link) {
      $lVal = levenshtein($link, $link);
      if ($lVal > $limit) {
        continue;
      }
      $leven[$key] = $lVal;
    }
    asort($leven);
    if (count($leven)) {
      return $leven;
    }
    $sublinks = [];
    foreach ($links as $key => $link) {
      $link = str_replace(["_", "-"], "/", $link);
      if (strpos($link, "/") === false) {
        continue;
      }
      $sublinks[$key] = substr($link, strpos($link, "/") + 1);
    }
    if (empty($sublinks)) {
      return [];
    }
    return self::minLev($sublinks, $link, $limit);
  }

  /** @noinspection PhpUnusedPrivateMethodInspection */
  /**
   * @param DOMElementPlus $rewrite
   * @throws Exception
   */
  private static function rewrite (DOMElementPlus $rewrite) {
    $match = $rewrite->getRequiredAttribute("match");
    foreach (self::$newReg as $linkId => $link) {
      if (strpos($link, $match) === false) {
        continue;
      }
      self::$newReg[$linkId] = str_replace($match, $rewrite->nodeValue, $link);
    }
  }

  /** @noinspection PhpUnusedPrivateMethodInspection */
  /**
   * @param DOMElementPlus $notfound
   * @throws Exception
   */
  private static function notfound (DOMElementPlus $notfound) {
    $link = $notfound->getRequiredAttribute("link");
    if (strpos(get_link(), $link) === false) {
      return;
    }
    if (Cms::isSuperUser()) {
      self::$notFound = true;
      return;
    }
    new ErrorPage('', 404, true);
    exit;
  }

  /** @noinspection PhpUnusedPrivateMethodInspection */
  /**
   * @param DOMElementPlus $redir
   */
  private static function redir (DOMElementPlus $redir) {
    if ($redir->hasAttribute("link") && $redir->getAttribute("link") != get_link()) {
      return;
    }
    $pNam = $redir->hasAttribute("parName") ? $redir->getAttribute("parName") : null;
    $pVal = $redir->hasAttribute("parValue") ? $redir->getAttribute("parValue") : null;
    if (!self::queryMatch($pNam, $pVal)) {
      return;
    }
    try {
      $value = replace_vars($redir->nodeValue, [
        "parvalue" => [
          "cacheable" => false,
          "value" => (!is_null($pNam) && isset($_GET[$pNam])) ? $_GET[$pNam] : "",
         ],
      ]);
      if ($value == "/") {
        redir_to(ROOT_URL);
      }
      $pLink = parse_local_link($value);
      if (is_null($pLink)) {
        redir_to($value);
      } // external redir
      $silent = !isset($pLink["path"]);
      if ($silent) {
        $pLink["path"] = get_link();
      } // no path = keep current path
      if (strpos($value, "?") === false) {
        $pLink["query"] = get_query();
      } // no query = keep current query
      #todo: no value ... keep current parameter value, eg. "?Admin" vs. "?Admin="
      try {
        # TODO
        #$pLink = DOMBuilder::normalizeLink($pLink);
        #todo: configurable status code
        redir_to(build_local_url($pLink));
      } catch (Exception $exc) {
        if (!$silent) {
          throw $exc;
        }
      }
    } catch (Exception $exc) {
      Logger::user_warning(sprintf(_("Unable to redirect to %s: %s"), $redir->nodeValue, $exc->getMessage()));
    }
  }

  /**
   * @param string $pNam
   * @param string $pVal
   * @return bool
   */
  private static function queryMatch ($pNam, $pVal) {
    foreach (explode("&", get_query()) as $param) {
      if (is_null($pVal) && strpos("$param=", "$pNam=") === 0) {
        return true;
      }
      if (!is_null($pVal) && "$param" == "$pNam=$pVal") {
        return true;
      }
    }
    return false;
  }

}
