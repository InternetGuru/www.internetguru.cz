<?php

class Plugins implements SplSubject {
  private $status = null;
  private $cms;
  private $observers = array();

  public function __construct(Cms $cms) {
    $this->cms = $cms;
    // plugin attach into class Plugins (Subject)
    foreach(scandir(PLUGIN_FOLDER) as $plugin) {
      // omit folders starting with a dot
      if(substr($plugin,0,1) == ".") continue;
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
    $this->observers[] = $observer;
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
