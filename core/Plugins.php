<?php

namespace IGCMS\Core;

use Exception;
use SplObserver;
use SplSubject;

# TODO: to static
class Plugins implements SplSubject {
  private $status = null;
  private $observers = array(); // list of enabled observer (names => Observer)
  private $observerPriority = array();

  public function __construct() {
    $this->attachPlugins();
  }

  public function getObservers() {
    return $this->observers;
  }

  public function isAttachedPlugin($pluginName) {
    return array_key_exists($pluginName, $this->observers);
  }

  public function printObservers() {
    stableSort($this->observerPriority);
    print_r($this->observerPriority);
  }

  private function attachPlugins() {
    if(!is_dir(PLUGINS_FOLDER))
      throw new Exception(sprintf(_("Missing plugin folder '%s'"), PLUGINS_FOLDER));
    foreach(scandir(PLUGINS_FOLDER) as $p) {
      if(strpos($p, ".") === 0) continue;
      if(!is_dir(PLUGINS_FOLDER."/$p")) continue;
      if(file_exists(PLUGINS_FOLDER."/.$p")) continue;
      if(file_exists(".PLUGIN.$p")) continue;
      $p = "IGCMS\Plugins\\$p";
      $this->attach(new $p($this));
    }
  }

  public function setStatus($status) {
    if($this->status === null) $this->status = $status;
  }

  public function getStatus() {
    return $this->status;
  }

  public function attach(SplObserver $observer, $priority=10) {
    $o = (new \ReflectionClass($observer))->getShortName();
    $this->observers[$o] = $observer;
    if(!array_key_exists($o, $this->observerPriority)) $this->observerPriority[$o] = $priority;
    if($observer->isDebug()) Logger::notice(sprintf(_("Plugin %s debug mode is enabled"), $o));
  }

  public function setPriority(SplObserver $observer, $priority) {
    $this->observerPriority[(new \ReflectionClass($observer))->getShortName()] = $priority;
  }

  public function detach(SplObserver $observer) {
    $o = (new \ReflectionClass($observer))->getShortName();
    if(array_key_exists($o, $this->observers)) $this->observers[$o] = null;
    if(array_key_exists($o, $this->observerPriority)) unset($this->observerPriority[$o]);
  }

  public function notify() {
    stableSort($this->observerPriority);
    foreach ($this->observerPriority as $key => $value) {
      $this->observers[$key]->update($this);
    }
    $this->status = null;
  }

  /**
   * @param $itf
   * @return array
   */
  public function getIsInterface($itf) {
    $contentStrategies = array();
    stableSort($this->observerPriority);
    foreach ($this->observerPriority as $key => $p) {
      if(!$this->observers[$key] instanceOf $itf) continue;
      $contentStrategies[$key] = $this->observers[$key];
    }
    return $contentStrategies;
  }

}

?>
