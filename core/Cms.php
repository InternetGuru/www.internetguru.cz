<?php

class Cms {

  private $contentFull = null; // HTMLPlus
  private $content = null; // HTMLPlus
  private $outputStrategy = null; // OutputStrategyInterface
  private $titleQueries = array("/body/h");
  private $variables = array();
  const DEBUG = false;

  function __construct() {
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function init() {
    global $plugins;
    $this->setVariable("version", CMS_VERSION);
    $this->setVariable("name", CMS_NAME);
    $this->setVariable("user_id", USER_ID);
    $this->setVariable("ig", "&copy;" . date("Y") . " <a href='http://www.internetguru.cz'>InternetGuru</a>");
    $this->setVariable("ez", "<a href='http://www.ezakladna.cz'>E-Základna</a>");
    $this->setVariable("plugins", array_keys($plugins->getObservers()));
    $this->setVariable("plugins_available", array_keys($plugins->getAvailableObservers()));
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
    try {
      $cs = null;
      global $plugins;
      foreach($plugins->getIsInterface("ContentStrategyInterface") as $cs) {
        $c = $cs->getContent($this->content);
        if(!($c instanceof HTMLPlus))
          throw new Exception("Content must be an instance of HTMLPlus");
        if(!$c->validatePlus(true))
          new Logger("Plugin '".get_class($cs)."' HTML+ autocorrected","warning");
        $this->content = $c;
        $this->loadDefaultVariables($this->content);
      }
    } catch (Exception $e) {
      if(self::DEBUG) echo $c->saveXML();
      throw new Exception($e->getMessage() . " (" . get_class($cs) . ")");
    }
  }

  public function processVariables() {
    $this->loadDefaultVariables($this->content);
    $this->setVariable("variables", array_keys($this->variables));
    $newContent = clone $this->content;
    foreach($this->variables as $k => $v) $newContent->insertVar($k,$v);
    try {
      $newContent->validatePlus();
      $this->content = $newContent;
    } catch(Exception $e) {
      new Logger("Variables generate invalid HTML+: ".$e->getMessage(), "error");
    }
    return $this->content;
  }

  public function setOutputStrategy(OutputStrategyInterface $strategy) {
    $this->outputStrategy = $strategy;
  }

  private function loadDefaultVariables(HTMLPlus $doc) {
    $desc = $doc->getElementsByTagName("desc")->item(0);
    $h1 = $doc->getElementsByTagName("h")->item(0);
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
    $db = new DOMBuilder();
    $this->contentFull = $db->buildHTMLPlus("Content.html");
  }

  public function getVariable($name) {
    $id = strtolower($name);
    if(!array_key_exists($id, $this->variables)) return null;
    return $this->variables[$id];
  }

  public function addVariableItem($name,$value) {
    $varId = $this->getVarId($name);
    $var = $this->getVariable($varId);
    if(is_null($var)) {
      $this->variables[$varId] = array($value);
      return;
    }
    if(!is_array($var)) $var = array($var);
    $var[] = $value;
    $this->variables[$varId] = $var;
  }

  private function getVarId($name) {
    $d = debug_backtrace();
    if(!isset($d[2]["class"])) throw new LoggerException("Unknown caller class");
    $varId = strtolower($d[2]["class"]);
    if($varId != $name) $varId .= (strlen($name) ? "-".normalize($name) : "");
    return $varId;
  }

  public function setVariable($name,$value) {
    $varId = $this->getVarId($name);
    if(!is_string($value) && !is_array($value) && !$value instanceof DOMDocument) {
      new Logger("Unsupported variable '$varId' type","error");
      return null;
    }
    if(!$value instanceof DOMDocument) {
      $items = $value;
      if(is_string($value)) $items = array($value);
      foreach($items as $k => $i) if(!$this->validateXMLMarkup($i)) {
        new Logger("Input variable '$varId' is not HTML valid","warning");
        if(!is_string($i)) return null; // in case of an array with non-string item
        $items[$k] = htmlentities($i);
      }
      if(is_string($value)) $value = $items[0];
      else $value = $items;
    }
    $this->variables[$varId] = $value;
    return $varId;
  }

  private function validateXMLMarkup($v) {
    $doc = new DOMDocument();
    if(@$doc->loadXML($v)) return true;
    $html = '<html>'.translateUtf8Entities($v).'</html>';
    if(!@$doc->loadXML($html)) {
      return false;
    }
    return true;
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

?>
