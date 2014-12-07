<?php

class Plugins implements SplSubject {
  private $status = null;
  private $observers = array(); // list of enabled observer (names => Observer)
  private $observerPriority = array();
  private $availableObservers = array(); // list of available observer (names => null)

  public function __construct() {
    $this->attachPlugins();
  }

  public function getObservers() {
    return $this->observers;
  }

  public function getAvailableObservers() {
    return $this->availableObservers;
  }

  public function isAttachedPlugin($pluginName) {
    return array_key_exists($pluginName, $this->observers);
  }

  public function printObservers() {
    stableSort($this->observerPriority);
    print_r($this->observerPriority);
  }

  private function attachPlugins() {
    $dir = PLUGINS_FOLDER;
    if(!is_dir($dir))
      throw new Exception(sprintf(_("Missing plugin folder '%s'"), $dir));
    foreach(scandir($dir) as $p) {
      if(strpos($p, ".") === 0 || file_exists("$dir/.$p")) continue; // skip.plugin
      if(IS_LOCALHOST && file_exists(PLUGINS_FOLDER."/.$p")) {
        $this->availableObservers[$p] = null;
        continue;
      }
      if(!IS_LOCALHOST && (file_exists(".PLUGIN.$p") || !file_exists("PLUGIN.$p"))) {
        if(!file_exists(".PLUGIN.$p")) $this->availableObservers[$p] = null;
        continue;
      }
      $this->attach(new $p($this));
      $this->availableObservers[$p] = null;
    }
  }

  public function setStatus($status) {
    if($this->status === null) $this->status = $status;
  }

  public function getStatus() {
    return $this->status;
  }

  public function attach(SplObserver $observer, $priority=10) {
    $o = get_class($observer);
    $this->observers[$o] = $observer;
    if(array_key_exists($o, $this->observerPriority)) return;
    $this->observerPriority[$o] = $priority;
  }

  public function setPriority(SplObserver $observer, $priority) {
    $this->observerPriority[get_class($observer)] = $priority;
  }

  public function detach(SplObserver $observer) {
    $o = get_class($observer);
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
