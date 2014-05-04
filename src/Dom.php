<?php

class Dom {
  private $document;

  public function __construct ($plugin="default") {
    $this->document = DomBuilder::build($plugin);
  }

  public function finalize() {
    // procede replacements etc.
    #todo
    return $this->document;
  }

}

?>
