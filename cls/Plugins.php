<?php

class Plugins implements SplSubject {
  private $status = null;
  private $cms;
  private $observers = array(); // list of observers
  private $observerPriority = array();
  private $disabledObservers = array(); // dirs starting with a dot

  public function __construct(Cms $cms) {
    $this->cms = $cms;
    $this->attachPlugins(PLUGIN_FOLDER);
    $this->attachPlugins("../" . CMS_FOLDER . "/" . PLUGIN_FOLDER);
  }

  private function attachPlugins($dir) {
    if(!is_dir($dir)) return;
    foreach(scandir($dir,SCANDIR_SORT_ASCENDING) as $plugin) { // dots first
      if(!is_dir($dir."/".$plugin) || $plugin == "." || $plugin == "..") continue;
      // disable plugins starting with a dot
      if(substr($plugin,0,1) == ".") {
        $this->disabledObservers[substr($plugin,1)] = null;
        continue;
      }
      if(array_key_exists($plugin,$this->observers)) continue;
      if(array_key_exists($plugin,$this->disabledObservers)) continue;
      $this->attach(new $plugin);
    }
  }

  #delete
  #public function setCms(Cms $cms) {
  #  if($this->cms === null) $this->cms = $cms;
  #}

  public function getCms() {
    return $this->cms;
  }

  public function setStatus($status) {
    if($this->status === null) $this->status = $status;
  }

  public function getStatus() {
    return $this->status;
  }

  public function attach(SplObserver $observer,$priority=10) {
    $o = get_class($observer);
    $this->observers[$o] = $observer;
    $this->observerPriority[$o] = $priority;
  }

  public function setPriority(SplObserver $observer,$priority) {
    $o = get_class($observer);
    if(!array_key_exists($o,$this->observers))
      throw new Exception("Observer '$o' not attached");
    $this->observerPriority[$o] = $priority;
  }

  public function detach(SplObserver $observer) {
    $o = get_class($observer);
    if(array_key_exists($o,$this->observers)) unset($this->observers[$o]);
    if(array_key_exists($o,$this->observerPriority)) unset($this->observerPriority[$o]);
  }

  public function notify() {
    stableSort($this->observerPriority);
    foreach ($this->observerPriority as $key => $value) {
      $this->observers[$key]->update($this);
    }
    $this->status = null;
  }

  public function getContentStrategies() {
    $contentStrategies = array();
    stableSort($this->observerPriority);
    foreach ($this->observerPriority as $key => $p) {
      if(!$this->observers[$key] instanceOf ContentStrategyInterface) continue;
      $contentStrategies[$key] = $this->observers[$key];
    }
    return $contentStrategies;
  }

}

interface ContentStrategyInterface {
  public function getContent(HTMLPlus $content);
  public function getTitle(Array $queries);
  public function getDescription($query);
}

?>
