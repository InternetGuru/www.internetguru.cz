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
  private static $xml = array();
  /**
   * @var array
   */
  private static $html = array();
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
  public function __construct(SplSubject $s) {
    $this->subject = $s;
    $this->className = (new \ReflectionClass($this))->getShortName();
    $this->pluginDir = PLUGINS_DIR."/".$this->className;
  }

  /**
   * @return bool
   */
  public function isDebug() {
    return defined('static::DEBUG') ? static::DEBUG : false;
  }

  /**
   * @param string $pluginName
   * @return bool
   */
  protected function detachIfNotAttached($pluginName) {
    if(!is_array($pluginName)) $pluginName = array($pluginName);
    foreach($pluginName as $p) {
      global $plugins;
      if($plugins->isAttachedPlugin($p)) continue;
      $this->subject->detach($p);
      Logger::user_warning(sprintf(_("Detaching '%s' due to '%s' dependency"), get_class($this), $p));
      return true;
    }
    return false;
  }

  protected function requireActiveCms() {
    if(Cms::isActive()) return;
    new ErrorPage(sprintf(_("Active CMS version required for plugin %s"), get_class($this)), 403);
  }

  /**
   * @return string
   */
  private static function getCallerName() {
    $backtrace = debug_backtrace();
    return basename(dirname($backtrace[1]["file"]));
  }

  /**
   * @param string|null $fileName
   * @return HTMLPlus
   */
  public static function getHTMLPlus($fileName=null) {
    if(array_key_exists($fileName, self::$html)) return self::$html[$fileName];
    $pluginName = self::getCallerName();
    if(is_null($fileName)) $fileName = "$pluginName.html";
    self::$html[$fileName] = HTMLPlusBuilder::build(PLUGINS_DIR."/$pluginName/$fileName");
    return self::$html[$fileName];
  }

  /**
   * @param string|null $fileName
   * @return DOMDocumentPlus
   */
  public static function getXML($fileName=null) {
    if(array_key_exists($fileName, self::$xml)) return self::$xml[$fileName];
    $pluginName = self::getCallerName();
    if(is_null($fileName)) $fileName = "$pluginName.xml";
    self::$xml[$fileName] = XMLBuilder::build(PLUGINS_DIR."/$pluginName/$fileName");
    return self::$xml[$fileName];
  }

}

?>