<?php

class Cms {

  private $config;
  private $content;
  private $outputStrategy;
  #private const $page;

  function __construct() {
      $config = new Dom();
  }

  #public function getStructure() {}
  #public function getContent() {}

  public function setOutputStrategy(OutputStrategyInterface $strategy) {
    $this->outputStrategy = $strategy;
  }

  public function setContent(Dom $content) {
    $this->content = $content;
  }

  public function getOutput() {
    if(!isset($this->content)) throw new Exception("Content not set");
    if(!isset($this->outputStrategy)) return $this->content->finalize()->saveXML();
    return $this->outputStrategy->output($this->content->finalize());
  }

}

interface OutputStrategyInterface {
    public function output(DOMDocument $dom);
}

?>
