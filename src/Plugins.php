<?php

class Plugins implements SplSubject {
  private $status = null;
  private $cms = null;
  private $observers = array();

  public function setCms(Cms $cms) {
    if($this->cms === null) $this->cms = $cms;
  }

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
