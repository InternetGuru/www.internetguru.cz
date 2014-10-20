<?php

class Plugin {
  const HASH_ALGO = 'crc32b';
  private $doms = array();
  protected $subject = null;

  protected function detachIfNotAttached($pluginName) {
    if(!is_array($pluginName)) $pluginName = array($pluginName);
    foreach($pluginName as $p) {
      if($this->subject->getCms()->isAttachedPlugin($p)) continue;
      $this->subject->detach($this);
      new Logger("Detaching '".get_class($this)."' due to '$p' dependancy","warning");
      return true;
    }
    return false;
  }

  protected function getDir() {
    return PLUGIN_FOLDER ."/". get_class($this);
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
    return hash(self::HASH_ALGO, $a.$b.$c);
  }

  private function buildDOMPlus($filePath, $htmlPlus, $user) {
    if(is_null($this->subject)) throw new Exception("SplSubject not set");
    $db = $this->subject->getCms()->getDombuilder();
    $key = $this->getKey($filePath,$htmlPlus,$user);
    if($htmlPlus)
      $this->doms[$key] = $db->buildHTMLPlus($filePath,$user);
    else
      $this->doms[$key] = $db->buildDOMPlus($filePath,false,$user);
    return $this->doms[$key];
  }

}

?>