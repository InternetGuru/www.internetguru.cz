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
    // exact match
    $path = get_link();
    if (HTMLPlusBuilder::isLink($path)) {
      return;
    }
    // normalize
    $path = preg_replace("/[#\/.,_-]+/", "_", normalize($path, "a-z0-9/.,_-"));
    $links = [];
    foreach (HTMLPlusBuilder::getIdToLink() as $link) {
      $links[$link] = preg_replace("/[#\/.,_-]+/", "_", strtolower($link));
    }
    // exact match
    foreach ($links as $key => $link) {
      if ($link == $path) {
        self::redirTo($key);
      }
    }
    // exact match (no sep)
    $path = str_replace("_", "", $path);
    foreach ($links as $key => $link) {
      $links[$key] = str_replace("_", "", $link);
      if ($links[$key] == $path) {
        self::redirTo($key);
      }
    }
    // starts with (no sep)
    foreach ($links as $key => $link) {
      if (strpos($link, $path) === 0) {
        self::redirTo($key);
      }
    }
    // levenshtein
    $newPath = self::getLowestLevenshtein($links, $path, min(4, floor(strlen($path) / 2)));
    if (strlen($newPath) > 0) {
      self::redirTo($newPath);
    }
    // exact word match (no sep)
    foreach ($links as $key => $link) {
      foreach (explode("_", $link) as $linkPart) {
        if ($linkPart == $path) {
          self::redirTo($key);
        }
      }
    }
    new ErrorPage(_("Requested page not found"), 404, true);
  }

  private static function redirTo ($path) {
    if (self::DEBUG) {
      die("Redirecting to '$path'");
    }
    redir_to(build_local_url(["path" => $path, "query" => get_query()]), 303);
  }

  private static function getLowestLevenshtein (Array $haystack, $needle, $maxLev) {
    $minKey = null;
    $minLev = 255;
    foreach ($haystack as $key => $value) {
      if ($value == $needle) {
        return $key;
      }
      $curLev = levenshtein($value, $needle);
      if ($curLev < $minLev && $curLev <= $maxLev) {
        $minLev = $curLev;
        $minKey = $key;
      }
    }
    return $minKey;
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
