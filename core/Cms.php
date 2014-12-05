<?php

class Cms {

  private static $contentFull = null; // HTMLPlus
  private static $content = null; // HTMLPlus
  private static $outputStrategy = null; // OutputStrategyInterface
  private static $variables = array();
  private static $forceFlash = false;
  private static $flashList = null;
  const DEBUG = false;
  const MSG_WARNING = "warning";
  const MSG_INFO = "info";
  const MSG_SUCCESS = "success";

  public static function isForceFlash() {
    return self::$forceFlash;
  }

  public static function init() {
    global $plugins;
    if(self::DEBUG) new Logger("DEBUG");
    self::setVariable("messages", self::$flashList);
    self::setVariable("version", CMS_VERSION);
    self::setVariable("name", CMS_NAME);
    self::setVariable("user_id", USER_ID);
    self::setVariable("ig", "&copy;" . date("Y") . " <a href='http://www.internetguru.cz'>InternetGuru</a>");
    self::setVariable("ez", "<a href='http://www.ezakladna.cz'>E-Základna</a>");
    self::setVariable("plugins", array_keys($plugins->getObservers()));
    self::setVariable("plugins_available", array_keys($plugins->getAvailableObservers()));
    self::loadContent();
    self::loadDefaultVariables(self::$contentFull);
  }

  private static function createFlashList() {
    $doc = new DOMDocumentPlus();
    self::$flashList = $doc->appendChild($doc->createElement("root"));
    $ul = self::$flashList->appendChild($doc->createElement("ul"));
    #$ul->setAttribute("class", "selectable");
    self::setVariable("messages", self::$flashList);
  }

  private static function addFlashItem($message, $type, $class) {
    $li = self::$flashList->ownerDocument->createElement("li", "$class: $message");
    $li->setAttribute("class", "$class $type");
    self::$flashList->firstElement->appendChild($li);
  }

  private static function getFlashMessages() {
    if(!isset($_SESSION["cms"]["flash"]) || !count($_SESSION["cms"]["flash"])) return;
    if(is_null(self::$flashList)) self::createFlashList();
    foreach($_SESSION["cms"]["flash"] as $class => $item) {
      foreach($item as $i) self::addFlashItem($i[0], $i[1], $class);
    }
    $_SESSION["cms"]["flash"] = array();
  }

  public static function getContentFull() {
    return self::$contentFull;
  }

  public static function buildContent() {
    self::getFlashMessages();
    if(is_null(self::$contentFull)) throw new Exception(_("Full content must be set to build content"));
    if(!is_null(self::$content)) throw new Exception(_("Method cannot run twice"));
    self::$content = clone self::$contentFull;
    try {
      $cs = null;
      global $plugins;
      foreach($plugins->getIsInterface("ContentStrategyInterface") as $cs) {
        $c = $cs->getContent(self::$content);
        if(!($c instanceof HTMLPlus))
          throw new Exception(_("Content must be an instance of HTML+"));
        try {
          $c->validatePlus();
        } catch(Exception $e) {
          $c->validatePlus(true);
          new Logger(sprintf(_("HTML+ generated by %s autocorrected: %s"), get_class($cs), $e->getMessage()), "warning");
        }
        self::$content = $c;
        self::loadDefaultVariables(self::$content);
      }
    } catch (Exception $e) {
      if(self::DEBUG) echo $c->saveXML();
      throw new Exception($e->getMessage() . " (" . get_class($cs) . ")");
    }
  }

  public static function processVariables() {
    $newContent = clone self::$content;
    self::loadDefaultVariables(self::$content);
    self::insertVariables($newContent);
    try {
      $newContent->validatePlus();
      self::$content = $newContent;
    } catch(Exception $e) {
      // Some variable generates invalid HTML+
      $eVars = array();
      $newContent = clone self::$content;
      foreach(self::$variables as $varName => $varValue) {
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
    return self::$content;
  }

  public static function insertVariables(DOMDocumentPlus $doc) {
    foreach(self::$variables as $varName => $varValue) $doc->insertVar($varName, $varValue);
  }

  public static function setOutputStrategy(OutputStrategyInterface $strategy) {
    self::$outputStrategy = $strategy;
  }

  private static function loadDefaultVariables(HTMLPlus $doc) {
    $desc = $doc->getElementsByTagName("desc")->item(0);
    $h1 = $doc->getElementsByTagName("h")->item(0);
    self::setVariable("lang", $doc->documentElement->getAttribute("xml:lang"));
    self::setVariable("desc", $desc->nodeValue);
    self::setVariable("title", $h1->nodeValue);
    if($h1->hasAttribute("short")) self::setVariable("title", $h1->getAttribute("short"));
    self::setVariable("author", $h1->getAttribute("author"));
    self::setVariable("kw", $desc->getAttribute("kw"));
    self::setVariable("mtime", $h1->getAttribute("mtime"));
    self::setVariable("ctime", $h1->getAttribute("ctime"));
    self::setVariable("variables", array_keys(self::$variables));
  }

  private static function loadContent() {
    $db = new DOMBuilder();
    self::$contentFull = $db->buildHTMLPlus("Content.html");
  }

  public static function addMessage($message, $type, $flash = false) {
    $caller = self::getCallerClass();
    if(!in_array($type, array(self::MSG_WARNING, self::MSG_INFO, self::MSG_SUCCESS))) $type = self::MSG_INFO;
    if(!$flash && self::$forceFlash) {
      new Logger(_("Adding message after output - forcing flash"), Logger::LOGGER_WARNING);
      $flash = true;
    }
    if($flash) {
      $_SESSION["cms"]["flash"][$caller][] = array($message, $type);
      return;
    }
    if(is_null(self::$flashList)) self::createFlashList();
    self::addFlashItem($message, $type, $caller);
  }

  private static function getCallerClass() {
    $callers = debug_backtrace();
    if(isset($callers[2]['class'])) return $callers[2]['class'];
    return "unknown";
  }

  public static function getVariable($name) {
    $id = strtolower($name);
    if(!array_key_exists($id, self::$variables)) return null;
    return self::$variables[$id];
  }

  public static function addVariableItem($name,$value) {
    $varId = self::getVarId($name);
    $var = self::getVariable($varId);
    if(is_null($var)) {
      self::$variables[$varId] = array($value);
      return;
    }
    if(!is_array($var)) $var = array($var);
    $var[] = $value;
    self::$variables[$varId] = $var;
  }

  private static function getVarId($name) {
    $d = debug_backtrace();
    if(!isset($d[2]["class"])) throw new LoggerException(_("Unknown caller class"));
    $varId = strtolower($d[2]["class"]);
    if($varId != $name) $varId .= (strlen($name) ? "-".normalize($name) : "");
    return $varId;
  }

  public static function setVariable($name,$value) {
    $varId = self::getVarId($name);
    self::$variables[$varId] = $value;
    return $varId;
  }

  public static function getAllVariables() {
    return self::$variables;
  }

  public static function setForceFlash() {
    self::$forceFlash = true;
  }

  public static function getOutput() {
    if(is_null(self::$content)) throw new Exception(_("Content is not set"));
    if(!is_null(self::$outputStrategy)) return self::$outputStrategy->getOutput(self::$content);
    return self::$content->saveXML();
  }

  public static function getOutputStrategy() {
    return self::$outputStrategy;
  }

}

?>
