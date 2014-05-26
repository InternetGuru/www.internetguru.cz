<?php

class Plugins implements SplSubject {
  private $status = null;
  private $cms;
  private $observers = array();

  public function __construct(Cms $cms) {
    $this->cms = $cms;
    $this->scanPlugins(PLUGIN_FOLDER);
    $this->scanPlugins("../" . CMS_FOLDER . "/" . PLUGIN_FOLDER);
  }

  private function scanPlugins($dir) {
    if(!is_dir($dir)) return;
    // plugin attach into class Plugins (Subject)
    foreach(scandir($dir) as $plugin) {
      // omit folders starting with a dot
      if(substr($plugin,0,1) == ".") continue;
      if(isset($this->observers[$plugin])) continue;
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
