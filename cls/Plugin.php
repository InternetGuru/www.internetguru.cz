<?php

class Plugin {
  private $doms = array();
  protected $subject = null;

  protected function getDir() {
    return PLUGIN_FOLDER ."/". get_class($this);
  }

  protected function getHTMLPlus($filePath=null, $user=true) {
    return $this->getDOMPlus($filePath,true,$user);
  }

  protected function getDOMExt($ext=null, $htmlPlus=false, $user=true) {
    if(is_null($ext)) $ext = "xml";
    return $this->getDOMPlus($this->getDir() ."/". get_class($this) .".$ext", $htmlPlus, $user);
  }

  protected function getDOMPlus($filePath=null, $htmlPlus=false, $user=true) {
    if(is_null($filePath)) return $this->getDOMExt(null,$htmlPlus,$user);
    if(!array_key_exists($filePath,$this->doms)) $this->buildDOMPlus($filePath,$htmlPlus,$user);
    return $this->doms[$filePath];
  }

  private function buildDOMPlus($filePath, $htmlPlus, $user) {
    if(is_null($this->subject)) throw new Exception("SplSubject not set");
    $db = $this->subject->getCms()->getDombuilder();
    if($htmlPlus)
      $this->doms[$filePath] = $db->buildHTMLPlus($filePath,$user);
    else
      $this->doms[$filePath] = $db->buildDOMPlus($filePath,false,$user);
  }

}

?>