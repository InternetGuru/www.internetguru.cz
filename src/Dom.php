<?php

class Dom {
  private $doc;

  public function __construct($plugin=null) {
    if(!strlen($plugin))
      $this->doc = DomBuilder::build();
    else
      $this->doc = DomBuilder::build($plugin);
  }

  public function getDoc() {
    return $this->doc;
  }

  #public function finalize() {} // procede replacements etc.
  #public function getPath(XPath $xpath) {}

}

?>
