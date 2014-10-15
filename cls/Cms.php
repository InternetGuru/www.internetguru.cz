<?php

#TODO: link2var
#TODO: singleton contentXpath, contentFullXpath
#TODO: outputStrategy interface
#TODO: default outputStrategy (ignore methods)

class Cms {

  private $domBuilder; // DOMBuilder
  private $contentFull = null; // HTMLPlus
  private $content = null; // HTMLPlus
  private $outputStrategy = null; // OutputStrategyInterface
  private $plugins = null; // SplSubject
  private $titleQueries = array("/body/h");
  private $variables = array();
  const DEBUG = false;

  function __construct() {
    $this->domBuilder = new DOMBuilder();
  }

  public function setPlugins(SplSubject $p) {
    $this->plugins = $p;
  }

  public function isAttachedPlugin($pluginName) {
    return $this->plugins->isAttachedPlugin($pluginName);
  }

  public function getDomBuilder() {
    return $this->domBuilder;
  }

  public function init() {
    $cfg = $this->domBuilder->buildDOMPlus("Cms.xml")->getElementsByTagName("environmental");
    $env = null;
    foreach($cfg as $e) {
      if($e->hasAttribute("domain")) {
        if($e->getAttribute("domain") == getDomain()) {
          $env = $e;
          break;
        }
      } else $env = $e;
    }
    $er = $env->getElementsByTagName("error_reporting")->item(0)->nodeValue;
    if(@constant($er) === null)
      throw new Exception("Undefined constatnt '$er' used in error_reporting");
    error_reporting(constant($er));
    $er = $env->getElementsByTagName("display_errors")->item(0)->nodeValue;
    if(ini_set("display_errors", 1) === false)
      throw new Exception("Unable to set display_errors to value '$er'");
    $tz = $env->getElementsByTagName("timezone")->item(0)->nodeValue;
    if(!date_default_timezone_set($tz))
      throw new Exception("Unable to set date_default_timezone to value '$er'");
    $loc = $env->getElementsByTagName("locale")->item(0);
    $cat = $loc->getAttribute("cat");
    if(@constant($cat) === null)
      throw new Exception("Undefined constatnt '$cat' used in locale");
    setlocale(constant($cat), $loc->nodeValue);
    $this->loadContent();
    $this->loadDefaultVariables($this->contentFull);
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
      if(self::DEBUG) echo $c->saveXML();
      throw new Exception($e->getMessage() . " (" . get_class($cs) . ")");
    }
    $this->loadDefaultVariables($this->content);
    $this->setVariable("variables", array_keys($this->variables));
    foreach($this->variables as $k => $v) $this->content->insertVar($k,$v);
  }

  public function setOutputStrategy(OutputStrategyInterface $strategy) {
    $this->outputStrategy = $strategy;
  }

  private function loadDefaultVariables(HTMLPlus $doc) {
    $desc = $doc->getElementsByTagName("desc")->item(0);
    $h1 = $doc->getElementsByTagName("h")->item(0);
    $this->setVariable("ig", "&copy;" . date("Y") . " <a href='http://www.internetguru.cz'>InternetGuru</a>");
    $this->setVariable("ez", "<a href='http://www.ezakladna.cz'>E-Základna</a>");
    $this->setVariable("url", $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"]);
    $this->setVariable("link", getLocalLink());
    $this->setVariable("lang", $doc->documentElement->getAttribute("xml:lang"));
    $this->setVariable("desc", $desc->nodeValue);
    if($h1->hasAttribute("short")) $this->setVariable("title", $h1->getAttribute("short"));
    else $this->setVariable("title", $h1->nodeValue);
    if($h1->hasAttribute("author")) $this->setVariable("author", $h1->getAttribute("author"));
    if($desc->hasAttribute("kw")) $this->setVariable("kw", $desc->getAttribute("kw"));
    if($h1->hasAttribute("mtime")) $this->setVariable("mtime", $h1->getAttribute("mtime"));
    if($h1->hasAttribute("ctime")) $this->setVariable("ctime", $h1->getAttribute("ctime"));
  }

  private function loadContent() {
    $this->contentFull = $this->domBuilder->buildHTMLPlus("Content.html");
  }

  public function getVariable($name) {
    if(!array_key_exists($name, $this->variables)) return null;
    return $this->variables[$name];
  }

  public function setVariable($name, $value) {
    $d = debug_backtrace();
    if(!isset($d[1]["class"])) throw new LoggerException("Unknown caller class");
    $varId = strtolower($d[1]["class"])."-".normalize($name);
    $this->variables[$varId] = $value;
    return $varId;
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
