<?php

class Cms {

  private $config;
  private $content;
  private $outputStrategy;
  #private const $page;

  function __construct() {
      $config = DOMBuilder::build();
  }

  #public function getStructure() {}

  public function getTitle() {

    // if HP
    $this->content->getElementsByTagName("h")->item(0)->nodeValue;

    // else add path (attr title if exists)
    #if($h->item(0)->hasAttribute("title"))
    #  $h->item(0)->getAttribute("title");

  }

  public function getBodyLang() {
    $h = $this->content->getElementsByTagName("body");
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

  public function setContent(DOMDocument $content) {
    // must be in HTML+ format, see HTML+ specification
    #todo: validation
    if ($content->documentElement->tagName != "body")
      throw new Exception("Content DOM is invalid!");
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
