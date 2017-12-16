<?php

namespace IGCMS\Core;

/**
 * Class DOMBuilder
 * @package IGCMS\Core
 */
class DOMBuilder {
  /**
   * @var int|null
   */
  private static $newestFileMtime = null;
  /**
   * @var int|null
   */
  private static $newestCacheMtime = null;

  /**
   * @return bool
   */
  public static function isCacheOutdated () {
    if (is_null(self::getNewestCacheMtime())) {
      return false;
    }
    return self::$newestFileMtime > self::getNewestCacheMtime();
  }

  /**
   * @return int|null
   */
  public static function getNewestCacheMtime () {
    if (!is_null(self::$newestCacheMtime)) {
      return self::$newestCacheMtime;
    }
    foreach (getNginxCacheFiles() as $cacheFilePath) {
      $cacheMtime = filemtime($cacheFilePath);
      if ($cacheMtime < self::$newestCacheMtime) {
        continue;
      }
      self::$newestCacheMtime = $cacheMtime;
    }
    return self::$newestCacheMtime;
  }

  /**
   * @return string
   */
  public static function getNewestFileMtime () {
    return self::$newestFileMtime;
  }

  /**
   * @param int $mtime
   */
  protected static function setNewestFileMtime ($mtime) {
    if ($mtime <= self::$newestFileMtime) {
      return;
    }
    self::$newestFileMtime = $mtime;
  }

}
