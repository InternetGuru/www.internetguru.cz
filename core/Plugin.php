<?php

namespace IGCMS\Core;

use IGCMS\Core\Cms;
use IGCMS\Core\ErrorPage;
use IGCMS\Core\Logger;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\XMLBuilder;
use Exception;
use SplSubject;

class Plugin {
  private static $xml = array();
  private static $html = array();
  protected $subject;
  protected $pluginDir;
  protected $className;

  public function __construct(SplSubject $s) {
    $this->subject = $s;
    $this->className = (new \ReflectionClass($this))->getShortName();
    $this->pluginDir = PLUGINS_DIR."/".$this->className;
  }

  public function isDebug() {
    return defined('static::DEBUG') ? static::DEBUG : false;
  }

  protected function detachIfNotAttached($pluginName) {
    if(!is_array($pluginName)) $pluginName = array($pluginName);
    foreach($pluginName as $p) {
      global $plugins;
      if($plugins->isAttachedPlugin($p)) continue;
      $this->subject->detach($this);
      Logger::user_warning(sprintf(_("Detaching '%s' due to '%s' dependancy"), get_class($this), $p));
      return true;
    }
    return false;
  }

  protected function requireActiveCms() {
    if(Cms::isActive()) return;
    new ErrorPage(sprintf(_("Active CMS version required for plugin %s"), get_class($this)), 403);
  }

  private static function getCallerName() {
    $backtrace = debug_backtrace();
    return basename(dirname($backtrace[1]["file"]));
  }

  public static function getHTMLPlus($fileName=null) {
    if(array_key_exists($fileName, self::$html)) return self::$html[$fileName];
    $pluginName = self::getCallerName();
    if(is_null($fileName)) $fileName = "$pluginName.html";
    self::$html[$fileName] = HTMLPlusBuilder::build(PLUGINS_DIR."/$pluginName/$fileName");
    return self::$html[$fileName];
  }

  public static function getXML($fileName=null) {
    if(array_key_exists($fileName, self::$xml)) return self::$xml[$fileName];
    $pluginName = self::getCallerName();
    if(is_null($fileName)) $fileName = "$pluginName.xml";
    self::$xml[$fileName] = XMLBuilder::build(PLUGINS_DIR."/$pluginName/$fileName");
    return self::$xml[$fileName];
  }

  private function getKey($a, $b=null, $c=null) {
    return hash(FILE_HASH_ALGO, $a.$b.$c);
  }

}

?>