<?php

class Plugins implements SplSubject {
  private $status = null;
  private $cms;
  private $observers = array(); // list of observers
  private $observerPriority = array();
  private $disabledObservers = array(); // dirs starting with a dot

  public function __construct(Cms $cms) {
    $this->cms = $cms;
    $this->attachPlugins();
  }

  public function isAttachedPlugin($pluginName) {
    return array_key_exists($pluginName,$this->observers);
  }

  public function printObservers() {
    stableSort($this->observerPriority);
    print_r($this->observerPriority);
  }

  private function attachPlugins() {
    $dir = CMS_FOLDER . "/". PLUGIN_FOLDER;
    if(!is_dir($dir))
      throw new Exception("Missing plugin folder '$dir'");
    $cfg = $this->cms->getDomBuilder()->buildDOMPlus("Cms.xml");
    $plugins = $cfg->getElementsByTagName("plugin");
    foreach($plugins as $plugin) {
      $p = $plugin->nodeValue;
      // disable plugins starting with a dot
      if(is_dir("$dir/.$p")) {
        $this->disabledObservers[$p] = null;
        continue;
      }
      if($this->isAttachedPlugin($p)) continue;
      $this->attach(new $p);
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

interface ContentStrategyInterface {
  public function getContent(HTMLPlus $content);
}

interface InputStrategyInterface {
  public function getVariables();
}

?>
