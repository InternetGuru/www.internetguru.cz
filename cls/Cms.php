<?php

class Cms {

  private $domBuilder; // DOMBuilder
  private $config; // DOMDocument
  private $content; // DOMDocument
  private $outputStrategy; // OutputStrategyInterface

  function __construct() {
    $this->domBuilder = new DOMBuilder();;
    #error_log("CMS created:0",0);
    #error_log("CMS created:3",3,"aaa.log");
  }

  public function init() {
    $this->config = $this->domBuilder->build();
    $er = $this->config->getElementsByTagName("error_reporting")->item(0)->nodeValue;
    if(@constant($er) === null) // keep outside if to check value
      throw new Exception("Undefined constatnt '$er' used in error_reporting.");
    if(!isAtLocalhost()) {
      error_reporting(E_ALL);
      ini_set("display_errors", 1);
    } else {
      error_reporting(constant($er));
    }
    $tz = $this->config->getElementsByTagName("timezone")->item(0)->nodeValue;
    date_default_timezone_set($tz);
  }

  private function addStylesheets() {
    foreach($this->config->getElementsByTagName("stylesheet") as $css) {
      $this->outputStrategy->addCssFile($css->nodeValue);
      if($css->hasAttribute("media")) {
        $this->outputStrategy->setCssMedia($css->nodeValue,$css->getAttribute("media"));
      }
    }
  }

  public function setBackupStrategy(BackupStrategyInterface $backupStrategy) {
    $this->domBuilder->setBackupStrategy($backupStrategy);
  }

  public function getDOM($plugin,$ext="xml") {
    return $this->domBuilder->build($plugin,$ext);
  }

  #public function getStructure() {}

  public function getTitle() {
    $h = $this->content->getElementsByTagName("h")->item(0);
    if($h->hasAttribute("short")) return $h->getAttribute("short");
    return $h->nodeValue;
  }

  public function getDescription() {
    return $this->content->getElementsByTagName("description")->item(0)->nodeValue;
  }

  public function getLanguage() {
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
    $this->addStylesheets();
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

  public function getOutputStrategy() {
    return $this->outputStrategy;
  }

}

interface OutputStrategyInterface {
    public function output();
}

?>
