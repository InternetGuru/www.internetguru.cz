<?php

class Cms {

  private $contentFull = null; // HTMLPlus
  private $content = null; // HTMLPlus
  private $outputStrategy = null; // OutputStrategyInterface
  private $titleQueries = array("/body/h");
  private $variables = array();
  private $forceFlash = false;
  private $flashList = null;
  const DEBUG = false;
  const FLASH_WARNING = "warning";
  const FLASH_INFO = "info";

  function __construct() {
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function __get($name) {
    switch($name) {
      case "forceFlash":
      return $this->forceFlash;
    }
  }

  public function init() {
    global $plugins;
    $this->setVariable("messages", $this->flashList);
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

  private function createFlashList() {
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));
    $ul = $root->appendChild($doc->createElement("ul"));
    $this->flashList = $root;
    $this->setVariable("messages", $this->flashList);
  }

  private function addFlashItem($message, $type, $class) {
    $li = $this->flashList->ownerDocument->createElement("li", "$class: $message");
    $li->setAttribute("class", "$class $type");
    $this->flashList->firstElement->appendChild($li);
  }

  public function getFlashMessages() {
    if(!isset($_SESSION["cms"]["flash"]) || !count($_SESSION["cms"]["flash"])) return;
    if(is_null($this->flashList)) $this->createFlashList();
    foreach($_SESSION["cms"]["flash"] as $class => $item) {
      foreach($item as $i) $this->addFlashItem($i[0], $i[1], $class);
    }
    $_SESSION["cms"]["flash"] = array();
  }

  public function getContentFull() {
    return $this->contentFull;
  }

  public function buildContent() {
    if(is_null($this->contentFull)) throw new Exception(_("Full content must be set to build content"));
    if(!is_null($this->content)) throw new Exception(_("Method cannot run twice"));
    $this->content = clone $this->contentFull;
    try {
      $cs = null;
      global $plugins;
      foreach($plugins->getIsInterface("ContentStrategyInterface") as $cs) {
        $c = $cs->getContent($this->content);
        if(!($c instanceof HTMLPlus))
          throw new Exception(_("Content must be an instance of HTML+"));
        if(!$c->validatePlus(true))
          new Logger(sprintf(_("Plugin '%s' HTML+ autocorrected"), get_class($cs)), "warning");
        $this->content = $c;
        $this->loadDefaultVariables($this->content);
      }
    } catch (Exception $e) {
      if(self::DEBUG) echo $c->saveXML();
      throw new Exception($e->getMessage() . " (" . get_class($cs) . ")");
    }
  }

  public function processVariables() {
    $newContent = clone $this->content;
    $this->loadDefaultVariables($this->content);
    $this->insertVariables($newContent);
    try {
      $newContent->validatePlus();
      $this->content = $newContent;
    } catch(Exception $e) {
      // Some variable generates invalid HTML+
      $eVars = array();
      $newContent = clone $this->content;
      foreach($this->variables as $varName => $varValue) {
        $tmpContent = clone $newContent;
        $tmpContent->insertVar($varName, $varValue);
        try {
          $tmpContent->validatePlus();
          $newContent = $tmpContent;
        } catch(Exception $e) {
          $eVars[] = $varName;
        }
      }
      new Logger(sprintf(_("Following variable(s) causing HTML+ error: %s"), implode(", ", $eVars)), "error");
    }
    return $this->content;
  }

  public function insertVariables(DOMDocumentPlus $doc) {
    foreach($this->variables as $varName => $varValue) $doc->insertVar($varName, $varValue);
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
    $this->setVariable("variables", array_keys($this->variables));
  }

  private function loadContent() {
    $db = new DOMBuilder();
    $this->contentFull = $db->buildHTMLPlus("Content.html");
  }

  public function addMessage($message, $type, $flash = false) {
    if(!in_array($type, array(self::FLASH_WARNING, self::FLASH_INFO))) $type = self::FLASH_INFO;
    if(!$flash && $this->forceFlash) {
      new Logger(_("Adding message after output - forcing flash"), Logger::LOGGER_WARNING);
      $flash = true;
    }
    if($flash) {
      $_SESSION["cms"]["flash"][$this->getCallerClass()][] = array($message, $type);
      return;
    }
    if(is_null($this->flashList)) $this->createFlashList();
    $this->addFlashItem($message, $type, $this->getCallerClass());
  }

  private function getCallerClass() {
    $callers = debug_backtrace();
    if(isset($callers[2]['class'])) return $callers[2]['class'];
    return "unknown";
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
    if(!isset($d[2]["class"])) throw new LoggerException(_("Unknown caller class"));
    $varId = strtolower($d[2]["class"]);
    if($varId != $name) $varId .= (strlen($name) ? "-".normalize($name) : "");
    return $varId;
  }

  public function setVariable($name,$value) {
    $varId = $this->getVarId($name);
    $this->variables[$varId] = $value;
    return $varId;
  }

  public function getAllVariables() {
    return $this->variables;
  }

  public function getOutput() {
    $this->forceFlash = true;
    if(is_null($this->content)) throw new Exception(_("Content is not set"));
    if(!is_null($this->outputStrategy)) return $this->outputStrategy->getOutput($this->content);
    return $this->content->saveXML();
  }

  public function getOutputStrategy() {
    return $this->outputStrategy;
  }

}

?>
