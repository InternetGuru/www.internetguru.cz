<?php

class Cms {

  private $domBuilder; // DOMBuilder
  private $config; // DOMDocument
  private $contentFull = null; // DOMDocument
  private $content = null; // DOMDocument
  private $outputStrategy = null; // OutputStrategyInterface
  private $contentStrategy = array(); // ContentStrategyInterface
  private $link = null;

  function __construct() {
    $this->domBuilder = new DOMBuilder();
    if(isset($_GET["page"])) $this->link = $_GET["page"]; // todo: linkStrategy
    #error_log("CMS created:0",0);
    #error_log("CMS created:3",3,"aaa.log");
  }

  public function getLink() {
    return $this->link;
  }

  public function init() {
    $this->config = $this->domBuilder->build();
    $er = $this->config->getElementsByTagName("error_reporting")->item(0)->nodeValue;
    if(@constant($er) === null) // keep outside if to check value
      throw new Exception("Undefined constatnt '$er' used in error_reporting");
    error_reporting(constant($er));
    $er = $this->config->getElementsByTagName("display_errors")->item(0)->nodeValue;
    if(ini_set("display_errors", 1) === false)
      throw new Exception("Unable to set display_errors to value '$er'");
    $tz = $this->config->getElementsByTagName("timezone")->item(0)->nodeValue;
    if(!date_default_timezone_set($tz))
      throw new Exception("Unable to set date_default_timezone to value '$er'");
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

  public function buildDOM($plugin,$ext="xml") {
    return $this->domBuilder->build($plugin,$ext);
  }

  #public function getStructure() {}

  public function getTitle() {
    $queries = array("/body/h");
    $title = array();
    // add queries using strategies
    foreach($this->contentStrategy as $cs) {
      $queries = $cs->getTitle($queries);
    }
    // execute queries
    $xpath = new DOMXPath($this->contentFull);
    foreach($queries as $q) {
      $r = $xpath->query($q)->item(0);
      if($r->hasAttribute("short")) $title[] = $r->getAttribute("short");
      else $title[] = $r->nodeValue;
    }
    return implode(" - ",$title);
  }

  public function getDescription() {
    $query = "/body/description";
    foreach($this->contentStrategy as $cs) {
      $query = $cs->getDescription($query);
    }
    $xpath = new DOMXPath($this->contentFull);
    return $xpath->query($query)->item(0)->nodeValue;
  }

  public function getLanguage() {
    $h = $this->contentFull->getElementsByTagName("body");
    return $h->item(0)->getAttribute("lang");
  }

  public function getConfig() {
    return $this->config;
  }

  public function getContentFull() {
    return $this->contentFull;
  }

  private function getContent() {
    if(!is_null($this->content)) return $this->content;
    $tmpContent = $this->contentFull->cloneNode(true);
    ksort($this->contentStrategy);
    foreach($this->contentStrategy as $cs) {
      $tmpContent = $cs->getContent($tmpContent);
    }
    $this->content = $tmpContent;
    return $tmpContent;
  }

  public function setContentStrategy(ContentStrategyInterface $strategy, $pos=10) {
    $this->contentStrategy[$pos] = $strategy;
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
    $this->contentFull = $content;
  }

  public function getOutput() {
    if(is_null($this->contentFull)) throw new Exception("Content not set");
    $content = $this->getContent();
    if(!is_null($this->outputStrategy)) return $this->outputStrategy->getOutput($content);
    return $content->saveXML();
  }

  public function getOutputStrategy() {
    return $this->outputStrategy;
  }

}

interface OutputStrategyInterface {
  public function getOutput(DOMDocument $content);
}

interface ContentStrategyInterface {
  public function getContent(DOMDocument $content);
  public function getTitle(Array $queries);
  public function getDescription($query);
}

?>
