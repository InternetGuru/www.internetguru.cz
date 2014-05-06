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

    // if HP
    $xpath = new DOMXPath($this->content->getDoc());
    $h = $xpath->query("body/h");
    return $h->item(0)->nodeValue;

    // else add path (attr title if exists)
    #if($h->item(0)->hasAttribute("title"))
    #  $h->item(0)->getAttribute("title");

  }

  public function getBodyLang() {
    $h = $this->content->getDoc()->getElementsByTagName("body");
    return $h->item(0)->getAttribute("lang");
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
