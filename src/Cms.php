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

  public function getTitle() {
    // HP
    $xpath = new DOMXPath($this->content->getDoc());
    $h = $xpath->query("body/h");
    // return title attr if exists
    #if($h->item(0)->hasAttribute("title"))
    return $h->item(0)->nodeValue;
  }

  public function getConfig() {
    return $this->config;
  }

  public function getContent() {
    return $this->content;
  }

  public function setOutputStrategy(OutputStrategyInterface $strategy) {
    $this->outputStrategy = $strategy;
  }

  public function setContent(Dom $content) {
    $this->content = $content;
  }

  public function getOutput() {
    if(!isset($this->content)) throw new Exception("Content not set");
    if(!isset($this->outputStrategy)) return $this->content->getDoc()->saveXML();
    return $this->outputStrategy->output($this);
  }

}

interface OutputStrategyInterface {
    public function output(Cms $cms);
}

?>
