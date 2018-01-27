<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMBuilder;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class Sitemap
 * @package IGCMS\Plugins
 *
 * @see http://www.sitemaps.org/protocol.html Sitemap definition
 */
class Sitemap extends Plugin implements SplObserver {
  /**
   * @var string
   */
  const SITEMAP = "sitemap.xml";

  /**
   * @var array Names of configurable elements
   */
  private static $cfgElements = ["changefreq", "priority"];

  /**
   * @var array Allowed values for changefreq element
   */
  private static $changefreqVals = ["always", "hourly", "daily", "weekly", "monthly", "yearly", "never", ""];

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_POSTPROCESS) {
      return;
    }
    // do not update if uptodate
    if (is_file(self::SITEMAP) && filemtime(self::SITEMAP) == HTMLPlusBuilder::getNewestFileMtime()) {
      return;
    }
    // do not update if cache is outdated
    if (DOMBuilder::isCacheOutdated()) {
      return;
    }
    self::createSitemap();
  }

  /**
   * Create sitemap file and touch newest filemtime
   */
  private static function createSitemap () {
    try {
      $cfg = self::getXML();
      $links = self::getLinks();
      $cfgLinks = self::getConfigLinks($cfg);
      // update user lastmod by $lastmods
      foreach ($links as $link => $mod) {
        if (isset($cfgLinks[$link]) && isset($cfgLinks[$link]["lastmod"])) {
          continue;
        }
        $cfgLinks[$link]["lastmod"] = $links[$link];
      }
      $cfgDefaults = self::getConfigDefaults($cfg);
      $sitemap = self::generateSitemap($links, $cfgLinks, $cfgDefaults);
      $sitemap->save(self::SITEMAP);
      touch(self::SITEMAP, HTMLPlusBuilder::getNewestFileMtime());
      Logger::info(_("Sitemap updated"));
    } catch (Exception $exc) {
      Logger::user_warning($exc->getMessage());
    }
  }

  /**
   * Get links from all included files
   * @return array links Asociative array of links => mtime in W3C format
   */
  private static function getLinks () {
    $links = [];
    foreach (HTMLPlusBuilder::getIdToLink() as $hId => $link) {
      if (strpos($link, "#") !== false) {
        continue;
      }
      $file = HTMLPlusBuilder::getIdToFile($hId);
      $mtime = w3c_timestamp(HTMLPlusBuilder::getFileToMtime($file));
      $links[$link] = $mtime;
    }
    return $links;
  }

  /**
   * Get links from configuration
   * @param  DOMDocumentPlus $cfg
   * @return array Asociative array of links => the values of their elements
   * @throws Exception
   */
  private static function getConfigLinks (DOMDocumentPlus $cfg) {
    $links = [];
    foreach ($cfg->documentElement->childElementsArray as $childElm) {
      if ($childElm->nodeName != "url") {
        continue;
      }
      if (!$childElm->hasAttribute("link")) {
        throw new Exception(_("Element url missing attribute link"));
      }
      foreach ($childElm->childElementsArray as $linkElm) {
        if (!in_array($linkElm->nodeName, self::$cfgElements)) {
          continue;
        }
        $links[$childElm->getAttribute("link")][$linkElm->nodeName] = $linkElm->nodeValue;
      }
    }
    return $links;
  }

  /**
   * Get default config values from root configirable elements
   * @var DOMDocumentPlus cfg
   * @return array Associative array of configuration elements
   */
  private static function getConfigDefaults (DOMDocumentPlus $cfg) {
    $defaults = [];
    foreach ($cfg->documentElement->childElementsArray as $childElm) {
      if (!in_array($childElm->nodeName, self::$cfgElements)) {
        continue;
      }
      $defaults[$childElm->nodeName] = $childElm->nodeValue;
    }
    return $defaults;
  }

  /**
   * Create SITEMAP according to $links modified by $cfgLinks
   * @param  array $links
   * @param  array $cfgLinks
   * @param  array $cfgDefaults
   * @return DOMDocumentPlus
   * @throws Exception
   */
  private static function generateSitemap (Array $links, Array $cfgLinks, Array $cfgDefaults) {
    $sitemap = new DOMDocumentPlus();
    $sitemap->formatOutput = true;
    $urlset = $sitemap->createElement("urlset");
    $sitemap->appendChild($urlset);
    $urlset->setAttribute("xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9");
    foreach ($links as $link => $headingElm) {
      $url = $urlset->appendChild($sitemap->createElement("url"));
      // loc
      $scheme = Cms::getVariable("urlhandler-default_protocol");
      if (is_null($scheme)) {
        $scheme = "http";
      }
      $url->appendChild($sitemap->createElement("loc", "$scheme://".HTTP_HOST."/".$link));
      // changefreq
      $changefreq = self::getValue("changefreq", $link, $cfgLinks, $cfgDefaults);
      if (!is_null($changefreq)) {
        if (!in_array($changefreq, self::$changefreqVals)) {
          throw new Exception(sprintf(_("Invalid element changefreq value: %s"), $changefreq));
        }
        $url->appendChild($sitemap->createElement("changefreq", $changefreq));
      }
      // priority
      $priority = self::getValue("priority", $link, $cfgLinks, $cfgDefaults);
      if (!is_null($priority)) {
        if ($priority < 0 || $priority > 1) {
          throw new Exception(sprintf(_("Invalid element priority value: %s"), $priority));
        }
        $url->appendChild($sitemap->createElement("priority", $priority));
      }
      // lastmod
      $lastmod = self::getValue("lastmod", $link, $cfgLinks, $cfgDefaults);
      if (!is_null($lastmod)) {
        if (!preg_match("/^".W3C_DATETIME_PATTERN."$/", $lastmod)) {
          throw new Exception(sprintf(_("Invalid element lastmod value: %s"), $lastmod));
        }
        $url->appendChild($sitemap->createElement("lastmod", $lastmod));
      }
    }
    return $sitemap;
  }

  /**
   * Get value from $cfgLinks or from $cfgDefaults or null
   * @param  string $name
   * @param  string $link
   * @param  array $cfgLinks
   * @param  array $cfgDefaults
   * @return string|null
   */
  private static function getValue ($name, $link, $cfgLinks, $cfgDefaults) {
    if (isset($cfgLinks[$link]) && isset($cfgLinks[$link][$name]) && strlen($cfgLinks[$link][$name])) {
      return $cfgLinks[$link][$name];
    }
    if (isset($cfgDefaults[$name]) && strlen($cfgDefaults[$name])) {
      return $cfgDefaults[$name];
    }
    return null;
  }

}
