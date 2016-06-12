<?php

namespace IGCMS\Core;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use Exception;
use DOMXPath;
use DOMDocument;
use DOMElement;
use DOMComment;
use DateTime;

class DOMBuilder {
  private static $newestFileMtime = null;
  private static $newestCacheMtime = null;

  public static function isCacheOutdated() {
    if(IS_LOCALHOST) return false;
    if(is_null(self::getNewestCacheMtime())) return false;
    return self::$newestFileMtime > self::getNewestCacheMtime();
  }

  public static function getNewestFileMtime() {
    return timestamptToW3C(self::$newestFileMtime);
  }

  protected static function setNewestFileMtime($mtime) {
    if($mtime <= self::$newestFileMtime) return;
    self::$newestFileMtime = $mtime;
  }

  protected static function getNewestCacheMtime() {
    if(!is_null(self::$newestCacheMtime)) return self::$newestCacheMtime;
    foreach(getNginxCacheFiles() as $cacheFilePath) {
      $cacheMtime = filemtime($cacheFilePath);
      if($cacheMtime < self::$newestCacheMtime) continue;
      self::$newestCacheMtime = $cacheMtime;
    }
    return self::$newestCacheMtime;
  }

}

?>