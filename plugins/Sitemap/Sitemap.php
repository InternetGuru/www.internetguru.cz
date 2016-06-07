<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\ResourceInterface;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use Exception;
use SplObserver;
use SplSubject;
use DateTime;


/**
 * Unlogged user (on root url) generate SITEMAP from all loaded files and save it to the root of current domain
 * @see http://www.sitemaps.org/protocol.html Sitemap definition
 */
class Sitemap extends Plugin implements SplObserver, ResourceInterface {
  const SITEMAP = "sitemap.xml";

  /**
   * @var array Names of configurable elements
   */
  private static $configurableElements = array("changefreq", "priority");

  /**
   * Allowed values for changefreq element
   * @var array
   */
  private static $changefreqVals = array("always", "hourly", "daily", "weekly", "monthly", "yearly", "never", "");

  public static function isSupportedRequest($filePath=null) {
    if(is_null($filePath)) $filePath = getCurLink();
    return $filePath == self::SITEMAP;
  }

  public function update(SplSubject $subject) {}

  public static function handleRequest() {
    try {
      $cfg = self::getXML();
      $links = self::getLinks();
      $cfgLinks = self::getConfigLinks($cfg);
      // update user lastmod by $lastmods
      foreach($links as $link => $mod) {
        if(isset($cfgLinks[$link]) && isset($cfgLinks[$link]["lastmod"])) continue;
        $cfgLinks[$link]["lastmod"] = $links[$link];
      }
      $cfgDefaults = self::getConfigDefaults($cfg);
      $sitemap = self::createSitemap($links, $cfgLinks, $cfgDefaults);
      echo $sitemap->saveXML();
      exit;
    } catch(Exception $e) {
      Logger::user_warning($e->getMessage());
    }
  }

  /**
   * Get links from all included files + root link "/"
   * @return Array links Asociative array of links => mtime in W3C format
   */
  private static function getLinks() {
    $links = array();
    foreach(HTMLPlusBuilder::getIdToLink() as $id => $link) {
      if(strpos($link, "#") !== false) continue;
      $file = HTMLPlusBuilder::getIdToFile($id);
      $mtime = timestamptToW3C(HTMLPlusBuilder::getFileMtime($file));
      $links[$link] = $mtime;
    }
    return $links;
  }

  /**
   * Get links from configuration
   * @var DOMDocumentPlus cfg
   * @return Array Asociative array of links => the values of their elements
   */
  private static function getConfigLinks(DOMDocumentPlus $cfg) {
    $links = array();
    foreach($cfg->documentElement->childElementsArray as $e) {
      if($e->nodeName != "url") continue;
      if(!$e->hasAttribute("link")) throw new Exception(_("Element url missing atribute link"));
      foreach($e->childElementsArray as $f) {
        if(!in_array($f->nodeName, self::$configurableElements)) continue;
        $links[$e->getAttribute("link")][$f->nodeName] = $f->nodeValue;
      }
    }
    return $links;
  }

  /**
   * Get default config values from root configirable elements
   * @var DOMDocumentPlus cfg
   * @return Array Associative array of configuration elements
   */
  private static function getConfigDefaults(DOMDocumentPlus $cfg) {
    $defaults = array();
    foreach($cfg->documentElement->childElementsArray as $e) {
      if(!in_array($e->nodeName, self::$configurableElements)) continue;
      $defaults[$e->nodeName] = $e->nodeValue;
    }
    return $defaults;
  }

  /**
   * Create SITEMAP according to $links modified by $cfgLinks
   * @param  Array  $links
   * @param  Array  $cfgLinks
   * @param  Array  $cfgDefaults
   */
  private static function createSitemap(Array $links, Array $cfgLinks, Array $cfgDefaults) {
    $sitemap = new DOMDocumentPlus();
    $sitemap->formatOutput = true;
    $urlset = $sitemap->appendChild($sitemap->createElement("urlset"));
    $urlset->setAttribute("xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9");
    foreach($links as $link => $h) {
      $url = $urlset->appendChild($sitemap->createElement("url"));
      // loc
      $scheme = Cms::getVariable("urlhandler-default_protocol");
      if(is_null($scheme)) $scheme = "http";
      $url->appendChild($sitemap->createElement("loc", "$scheme://".HOST."/".$link));
      // changefreq
      $changefreq = self::getValue("changefreq", $link, $cfgLinks, $cfgDefaults);
      if(!is_null($changefreq)) {
        if(!in_array($changefreq, self::$changefreqVals))
          throw new Exception(sprintf(_("Invalid element changefreq value: %s"), $changefreq));
        $url->appendChild($sitemap->createElement("changefreq", $changefreq));
      }
      // priority
      $priority = self::getValue("priority", $link, $cfgLinks, $cfgDefaults);
      if(!is_null($priority)) {
        if($priority < 0 || $priority > 1)
          throw new Exception(sprintf(_("Invalid element priority value: %s"), $priority));
        $url->appendChild($sitemap->createElement("priority", $priority));
      }
      // lastmod
      $lastmod = self::getValue("lastmod", $link, $cfgLinks, $cfgDefaults);
      if(!is_null($lastmod)) {
        if(!preg_match("/^".W3C_DATETIME_PATTERN."$/", $lastmod))
          throw new Exception(sprintf(_("Invalid element lastmod value: %s"), $lastmod));
        $url->appendChild($sitemap->createElement("lastmod", $lastmod));
      }
    }
    return $sitemap;
  }

  /**
   * Get value from $cfgLinks or from $cfgDefaults or null
   * @param  String $name
   * @param  Array  $cfgLinks
   * @param  Array  $cfgDefaults
   * @return String|null
   */
  private static function getValue($name, $link, $cfgLinks, $cfgDefaults) {
    if(isset($cfgLinks[$link]) && isset($cfgLinks[$link][$name]) && strlen($cfgLinks[$link][$name]))
      return $cfgLinks[$link][$name];
    if(isset($cfgDefaults[$name]) && strlen($cfgDefaults[$name]))
      return $cfgDefaults[$name];
    return null;
  }

}

?>