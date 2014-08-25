<?php

class Plugin {
  private $doms = array();
  protected $subject = null;

  protected function getDir() {
    return PLUGIN_FOLDER ."/". get_class($this);
  }

  protected function getDOMExt($ext = "xml") {
    return $this->getDOM(get_class($this) . ".$ext");
  }

  protected function getDOM($fileName=null) {
    if(is_null($fileName)) return $this->getDOMExt();
    if(!array_key_exists($fileName,$this->doms)) $this->buildDOM($fileName);
    return $this->doms[$fileName];
  }

  private function buildDOM($fileName) {
    if(is_null($this->subject)) throw new Exception("SplSubject not set");
    $filePath = $this->getDir() . "/$fileName";
    $db = $this->subject->getCms()->getDombuilder();
    $this->doms[$fileName] = $db->buildDOMPlus($filePath);
  }

}

?>