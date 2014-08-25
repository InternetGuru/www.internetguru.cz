<?php

class Plugin {
  private $doms = array();
  protected $subject = null;

  protected function getDir() {
    return PLUGIN_FOLDER ."/". get_class($this);
  }

  protected function getHTMLPlus($fileName=null) {
    return $this->getDOMPlus($fileName,true);
  }

  protected function getDOMExt($ext=null, $htmlPlus=false) {
    if(is_null($ext)) $ext = "xml";
    return $this->getDOMPlus(get_class($this) . ".$ext", $htmlPlus);
  }

  protected function getDOMPlus($fileName=null,$htmlPlus=false) {
    if(is_null($fileName)) return $this->getDOMExt(null,$htmlPlus);
    if(!array_key_exists($fileName,$this->doms)) $this->buildDOMPlus($fileName,$htmlPlus);
    return $this->doms[$fileName];
  }

  private function buildDOMPlus($fileName,$htmlPlus=false) {
    if(is_null($this->subject)) throw new Exception("SplSubject not set");
    $filePath = $this->getDir() . "/$fileName";
    $db = $this->subject->getCms()->getDombuilder();
    if($htmlPlus)
      $this->doms[$fileName] = $db->buildHTMLPlus($filePath);
    else
      $this->doms[$fileName] = $db->buildDOMPlus($filePath);
  }

}

?>