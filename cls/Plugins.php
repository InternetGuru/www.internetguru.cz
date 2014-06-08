<?php

class Plugins implements SplSubject {
  private $status = null;
  private $cms;
  private $observers = array(); // list of observers
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

  public function attach(SplObserver $observer) {
    $this->observers[get_class($observer)] = $observer;
  }

  public function detach(SplObserver $observer) {
        $key = array_search($observer,$this->observers, true);
        if($key){
            unset($this->observers[$key]);
        }
  }

  public function notify() {
        foreach ($this->observers as $value) {
            $value->update($this);
        }
        $this->status = null;
  }

}

?>
