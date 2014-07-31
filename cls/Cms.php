<?php

#TODO: singleton contentXpath, contentFullXpath

class Cms {

  private $domBuilder; // DOMBuilder
  private $config; // DOMDocument
  private $contentFull = null; // HTMLPlus
  private $content = null; // HTMLPlus
  private $outputStrategy = null; // OutputStrategyInterface
  private $contentStrategy = array(); // ContentStrategyInterface
  private $link = ".";
  private $titleQueries = array("/body/h");

  function __construct() {
    $this->domBuilder = new DOMBuilder();
    if(isset($_GET["page"])) $this->link = $_GET["page"];
    if(!strlen(trim($this->link))) $this->link = ".";
    #error_log("CMS created:0",0);
    #error_log("CMS created:3",3,"aaa.log");
  }

  public function getLink() {
    return $this->link;
  }

  public function init() {
    $this->config = $this->buildDOM();
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

  private function addJsFiles() {
    foreach($this->config->getElementsByTagName("jsFile") as $jsFile) {
      if($jsFile->nodeValue == "") continue;
      $this->outputStrategy->addJsFile($jsFile->nodeValue,"",1);
    }
  }

  private function addStylesheets() {
    foreach($this->config->getElementsByTagName("stylesheet") as $css) {
      $media = ($css->hasAttribute("media") ? $css->getAttribute("media") : false);
      $this->outputStrategy->addCssFile($css->nodeValue,"",$media);
    }
  }

  public function insertCmsVars() {
    if(is_null($this->content)) throw new Exception("Content not set");
    foreach($this->config->getElementsByTagName("var") as $var) {
      if(!$var->hasAttribute("id")) throw new Exception ("Var is missing id");
      $this->insertVar($var->getAttribute("id"),$var);
    }
  }

  public function setBackupStrategy(BackupStrategyInterface $backupStrategy) {
    $this->domBuilder->setBackupStrategy($backupStrategy);
  }

  public function buildDOM($plugin="",$replace=false,$filename="") {
    return $this->domBuilder->buildDOM($plugin,$replace,$filename);
  }

  public function buildHTML($plugin="",$replace=false,$filename="") {
    return $this->domBuilder->buildHTML($plugin,$replace,$filename);
  }

  #public function getStructure() {}

  public function insertVar($varName,$varValue,$plugin="") {
    $xpath = new DOMXPath($this->content);
    if($plugin == "") $plugin = "Cms";
    $var = "{".$plugin.":".$varName."}";
    if(is_string($varValue)) {
      $where = $xpath->query("//@*[contains(.,'$var')]");
      $this->insertVarString($var,$varValue,$where);
    }
    $where = $xpath->query("//text()[contains(.,'$var')]");
    if($where->length == 0) return;
    $type = gettype($varValue);
    if($type == "object") $type = get_class($varValue);
    switch($type) {
      case "string":
      $this->insertVarString($var,$varValue,$where);
      break;
      case "array":
      $this->insertVarArray($var,$varValue,$where);
      break;
      case "DOMElement":
      $this->insertVarDOMElement($var,$varValue,$where);
      break;
      default:
      throw new Exception("Unsupported type '$type'");
    }
  }

  private function insertVarString($varName,$varValue,DOMNodeList $where) {
    foreach($where as $e) {
      $e->nodeValue = str_replace($varName, $varValue, $e->nodeValue);
    }
  }

  private function insertVarArray($varName,Array $varValue,DOMNodeList $where) {
    if(empty($varValue)) {
      $this->insertVarString($varName,"",$where);
      return;
    }
    $doc = new DOMDocument();
    $ul = $doc->createElement("ul");
    foreach($varValue as $i) $ul->appendChild($doc->createElement("li",$i));
    $this->insertVarDOMElement($varName,$ul,$where);
  }

  private function insertVarDOMElement($varName,DOMElement $varValue,DOMNodeList $where) {
    $into = array();
    foreach($where as $e) $into[] = $e;
    foreach($into as $e) {
      $newParent = $e->parentNode->cloneNode();
      $children = array();
      foreach($e->parentNode->childNodes as $ch) $children[] = $ch;
      foreach($children as $ch) {
        if(!$ch->isSameNode($e)) {
          $newParent->appendChild($ch);
          continue;
        }
        $parts = explode($varName,$ch->nodeValue);
        foreach($parts as $id => $part) {
          $newParent->appendChild($e->ownerDocument->createTextNode($part));
          if((count($parts)-1) == $id) continue;
          $appendInto = array();
          foreach($varValue->childNodes as $n) $appendInto[] = $n;
          foreach($appendInto as $n) {
            $newParent->appendChild($e->ownerDocument->importNode($n,true));
          }
        }
      }
      $e->parentNode->parentNode->replaceChild($e->ownerDocument->importNode($newParent),$e->parentNode);
    }
  }

  public function getTitle() {
    $title = array();
    $xpath = new DOMXPath($this->contentFull);
    foreach($this->titleQueries as $q) {
      $r = $xpath->query($q)->item(0);
      if($r->hasAttribute("short") && count($this->titleQueries) > 1) $title[] = $r->getAttribute("short");
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

  public function buildContent() {
    if(is_null($this->contentFull)) throw new Exception("Content not set");
    if(!is_null($this->content)) throw new Exception("Should not run twice");
    $this->content = $this->contentFull->cloneNode(true);
    ksort($this->contentStrategy);
    foreach($this->contentStrategy as $cs) {
      $this->titleQueries = $cs->getTitle($this->titleQueries);
    }
    foreach($this->contentStrategy as $cs) {
      $this->content = $cs->getContent($this->content);
    }
  }

  public function setContentStrategy(ContentStrategyInterface $strategy, $pos=10) {
    $this->contentStrategy[$pos] = $strategy;
  }

  public function setOutputStrategy(OutputStrategyInterface $strategy) {
    $this->outputStrategy = $strategy;
    $this->addStylesheets();
    $this->addJsFiles();
  }

  private function loadContent() {
    $this->contentFull = $this->buildHTML("",true,"Content.xml");
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

interface ContentStrategyInterface {
  public function getContent(HTMLPlus $content);
  public function getTitle(Array $queries);
  public function getDescription($query);
}

?>
