<?php

class Cms {

  private $config;
  private $content;
  #private const $page;

  function __construct() {
    try {

      $config = new Dom();
      #$content = new Content();

    } catch(Exception $e) {
      echo "Exception: ".$e->getMessage();
    }
  }

  #public function getStructure() {}
  #public function setOutputStrategy() {}
  #public function getContent() {}
  #public function setContent() {}

}

?>
