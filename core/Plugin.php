<?php

namespace IGCMS\Core;

use SplSubject;

/**
 * Class Plugin
 * @package IGCMS\Core
 */
class Plugin {
  /**
   * @var array
   */
  private static $xml = [];
  /**
   * @var array
   */
  private static $html = [];
  /**
   * @var Plugins|SplSubject
   */
  protected $subject;
  /**
   * @var string
   */
  protected $pluginDir;
  /**
   * @var string
   */
  protected $className;

  /**
   * Plugin constructor.
   * @param SplSubject $s
   */
  public function __construct (SplSubject $s) {
    $this->subject = $s;
    $this->className = (new \ReflectionClass($this))->getShortName();
    $this->pluginDir = PLUGINS_DIR."/".$this->className;
  }

  /**
   * @param string|null $fileName
   * @return HTMLPlus
   * @throws \Exception
   */
  public static function getHTMLPlus ($fileName = null) {
    if (array_key_exists($fileName, self::$html)) {
      return self::$html[$fileName];
    }
    $pluginName = self::getCallerName();
    if (is_null($fileName)) {
      $fileName = "$pluginName.html";
    }
    $html = HTMLPlusBuilder::build(PLUGINS_DIR."/$pluginName/$fileName");
    $html->getElementsByTagName('body')->item(0)->setAttribute('ns', HTTP_URL);
    self::$html[$fileName] = $html;
    return self::$html[$fileName];
  }

  /**
   * @return string
   */
  private static function getCallerName () {
    $backtrace = debug_backtrace();
    return basename(dirname($backtrace[1]["file"]));
  }

  /**
   * @param string|null $fileName
   * @return DOMDocumentPlus
   * @throws \Exception
   */
  public static function getXML ($fileName = null) {
    if (array_key_exists($fileName, self::$xml)) {
      return self::$xml[$fileName];
    }
    $pluginName = self::getCallerName();
    if (is_null($fileName)) {
      $fileName = "$pluginName.xml";
    }
    self::$xml[$fileName] = XMLBuilder::build(PLUGINS_DIR."/$pluginName/$fileName");
    return self::$xml[$fileName];
  }

  /**
   * @return bool
   */
  public function isDebug () {
    /** @noinspection PhpUndefinedClassConstantInspection */
    return defined('static::DEBUG') ? static::DEBUG : false;
  }

  /**
   * @param string $pluginName
   * @return bool
   */
  protected function detachIfNotAttached ($pluginName) {
    if (!is_array($pluginName)) {
      $pluginName = [$pluginName];
    }
    foreach ($pluginName as $plugin) {
      global $plugins;
      if ($plugins->isAttachedPlugin($plugin)) {
        continue;
      }
      $this->subject->detach($plugin);
      Logger::user_warning(sprintf(_("Detaching '%s' due to '%s' dependency"), get_class($this), $plugin));
      return true;
    }
    return false;
  }

  protected function requireActiveCms () {
    if (Cms::isActive()) {
      return;
    }
    new ErrorPage(sprintf(_("Active CMS version required for plugin %s"), get_class($this)), 403);
  }

}
