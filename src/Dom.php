<?php

class Dom {
  private $document;

  public function __construct ($plugin="default") {
    $document = DomBuilder::build($plugin);
  }

  public function getDom () {
    return $this->document;
  }

  public function setDom (DOMDocument $document) {
    #$this->document = $document;
  }

}

?>
