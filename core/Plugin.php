<?php

namespace IGCMS\Core;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMBuilder;
use IGCMS\Core\ErrorPage;
use IGCMS\Core\Logger;
use Exception;
use SplSubject;

class Plugin {
  private $doms = array();
  protected $subject;
  protected $pluginDir;
  protected $className;

  public function __construct(SplSubject $s) {
    $this->subject = $s;
    $this->pluginDir = PLUGINS_DIR."/".(new \ReflectionClass($this))->getShortName();
    $this->className = (new \ReflectionClass($this))->getShortName();
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

  protected function getHTMLPlus($filePath=null) {
    if(is_null($filePath))
      $filePath = $this->pluginDir."/".$this->className.".html";
    return HTMLPlusBuilder::build($filePath);
  }

  protected function getXML($filePath=null) {
    if(is_null($filePath))
      $filePath = $this->pluginDir."/".$this->className.".xml";
    return XMLBuilder::build($filePath);
  }

  private function getKey($a, $b=null, $c=null) {
    return hash(FILE_HASH_ALGO, $a.$b.$c);
  }

}

?>