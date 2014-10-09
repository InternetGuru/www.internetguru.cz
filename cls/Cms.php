<?php

#TODO: link2var
#TODO: singleton contentXpath, contentFullXpath
#TODO: outputStrategy interface
#TODO: default outputStrategy (ignore methods)

class Cms {

  private $domBuilder; // DOMBuilder
  private $config; // DOMDocument
  private $contentFull = null; // HTMLPlus
  private $content = null; // HTMLPlus
  private $outputStrategy = null; // OutputStrategyInterface
  private $link = ""; // empty for no link
  private $plugins = null; // SplSubject
  private $titleQueries = array("/body/h");
  private $variables = array();

  function __construct() {
    $this->domBuilder = new DOMBuilder();
    if(isset($_GET["page"])) $this->link = $_GET["page"];
  }

  public function setPlugins(SplSubject $p) {
    $this->plugins = $p;
  }

  public function isAttachedPlugin($pluginName) {
    return $this->plugins->isAttachedPlugin($pluginName);
  }

  public function getLink() {
    return $this->link;
  }

  public function getDomBuilder() {
    return $this->domBuilder;
  }

  public function init() {
    $this->config = $this->domBuilder->buildDOMPlus("Cms.xml");
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
    $this->loadContent();
  }

  public function getConfig() {
    return $this->config;
  }

  public function getContentFull() {
    return $this->contentFull;
  }

  public function buildContent() {
    if(is_null($this->contentFull)) throw new Exception("Content not set");
    if(!is_null($this->content)) throw new Exception("Should not run twice");
    $this->content = clone $this->contentFull;
    #$contentStrategies = $this->plugins->getContentStrategies();
    #foreach($contentStrategies as $cs) {
    #  $this->titleQueries = $cs->getTitle($this->titleQueries);
    #}
    try {
      $cs = null;
      foreach($this->plugins->getIsInterface("ContentStrategyInterface") as $cs) {
        $c = $cs->getContent($this->content);
        #echo $c->saveXML(); die();
        if(!($c instanceof HTMLPlus))
          throw new Exception("Content must be an instance of HTMLPlus");
        $c->validatePlus();
        $this->content = $c;
      }
    } catch (Exception $e) {
      #var_dump($cs);
      #echo $this->content->saveXML();
      #echo $c->saveXML();
      throw new Exception($e->getMessage() . " (" . get_class($cs) . ")");
    }
    $this->loadVariables();
  }

  public function setOutputStrategy(OutputStrategyInterface $strategy) {
    $this->outputStrategy = $strategy;
  }

  private function loadVariables() {
    $this->loadDefaultVariables();
    $isi = $this->plugins->getIsInterface("InputStrategyInterface");
    foreach($isi as $k => $o) {
      $this->variables = array_merge($this->variables,$o->getVariables());
    }
  }

  private function loadDefaultVariables() {
    $desc = $this->content->getElementsByTagName("desc")->item(0);
    $h1 = $this->content->getElementsByTagName("h")->item(0);
    $this->variables["cms-link"] = getRoot() . $this->getLink();
    $this->variables["cms-lang"] = $this->content->getElementsByTagName("body")->item(0)->getAttribute("xml:lang");
    $this->variables["cms-desc"] = $desc->nodeValue;
    if($h1->hasAttribute("short")) $this->variables["cms-title"] = $h1->getAttribute("short");
    else $this->variables["cms-title"] = $h1->nodeValue;
    if($h1->hasAttribute("author")) $this->variables["cms-author"] = $h1->getAttribute("author");
    if($desc->hasAttribute("kw")) $this->variables["cms-kw"] = $desc->getAttribute("author");
    if($h1->hasAttribute("mtime")) $this->variables["cms-mtime"] = $h1->getAttribute("mtime");
    if($h1->hasAttribute("ctime")) $this->variables["cms-ctime"] = $h1->getAttribute("ctime");
    #TODO: load others
  }

  private function loadContent() {
    $this->contentFull = $this->domBuilder->buildHTMLPlus("Content.html");
  }

  public function getVariable($name) {
    if(!array_key_exists($name, $this->variables)) return null;
    return $this->variables[$name];
  }

  public function getAllVariables() {
    return $this->variables;
  }

  public function getOutput() {
    if(is_null($this->content)) throw new Exception("Content not set");
    if(!is_null($this->outputStrategy)) return $this->outputStrategy->getOutput($this->content);
    return $this->content->saveXML();
  }

  public function getOutputStrategy() {
    return $this->outputStrategy;
  }

}

interface OutputStrategyInterface {
  public function getOutput(HTMLPlus $content);
}

?>
