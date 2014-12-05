<?php

class Plugin {
  private $doms = array();
  protected $subject = null;

  public function __construct(SplSubject $s) {
    $this->subject = $s;
  }

  protected function detachIfNotAttached($pluginName) {
    if(!is_array($pluginName)) $pluginName = array($pluginName);
    foreach($pluginName as $p) {
      global $plugins;
      if($plugins->isAttachedPlugin($p)) continue;
      $this->subject->detach($this);
      new Logger(sprintf(_("Detaching '%s' due to '%s' dependancy"), get_class($this), $p), "warning");
      return true;
    }
    return false;
  }

  protected function getDir() {
    return PLUGINS_DIR ."/". get_class($this);
  }

  protected function getHTMLPlus($filePath=null, $user=true) {
    if(is_null($filePath)) return $this->getDOMExt("html",true,$user);
    return $this->getDOMPlus($filePath,true,$user);
  }

  protected function getDOMExt($ext=null, $htmlPlus=false, $user=true) {
    if(is_null($ext)) $ext = "xml";
    return $this->getDOMPlus($this->getDir() ."/". get_class($this) .".$ext", $htmlPlus, $user);
  }

  protected function getDOMPlus($filePath=null, $htmlPlus=false, $user=true) {
    if(is_null($filePath)) return $this->getDOMExt(null,$htmlPlus,$user);
    $key = $this->getKey($filePath,$htmlPlus,$user);
    if(array_key_exists($key,$this->doms)) return $this->doms[$key];
    return $this->buildDOMPlus($filePath,$htmlPlus,$user);
  }

  private function getKey($a,$b=null,$c=null) {
    return hash(FILE_HASH_ALGO, $a.$b.$c);
  }

  private function buildDOMPlus($filePath, $htmlPlus, $user) {
    if(is_null($this->subject)) throw new Exception(_("Unable to build DOM if SplSubject not set"));
    $key = $this->getKey($filePath,$htmlPlus,$user);
    if($htmlPlus)
      $this->doms[$key] = DOMBuilder::buildHTMLPlus($filePath,$user);
    else
      $this->doms[$key] = DOMBuilder::buildDOMPlus($filePath,false,$user);
    return $this->doms[$key];
  }

}

?>