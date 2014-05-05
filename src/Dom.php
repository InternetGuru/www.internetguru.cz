<?php

class Dom {
  private $doc;

  public function __construct($plugin=null) {
    if(!strlen($plugin))
      $this->doc = DomBuilder::build();
    else
      $this->doc = DomBuilder::build($plugin);
  }

  public function finalize() {
    // procede replacements etc.
    #todo
    return $this->doc;
  }

  public function getStructure() {
    return $this->doc->saveXML();
  }

}

?>
