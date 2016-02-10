<?php

class Cms {

  private static $contentFull = null; // HTMLPlus
  private static $content = null; // HTMLPlus
  private static $outputStrategy = null; // OutputStrategyInterface
  private static $variables = array();
  private static $functions = array();
  private static $forceFlash = false;
  private static $flashList = null;
  private static $error = false;
  private static $warning = false;
  private static $other = false;
  private static $success = false;
  private static $requestToken = null;
  const DEBUG = false;
  const MSG_ERROR = "Error";
  const MSG_WARNING = "Warning";
  const MSG_INFO = "Info";
  const MSG_SUCCESS = "Success";

  public static function init() {
    global $plugins;
    if(self::DEBUG) Logger::log("DEBUG");
    self::setVariable("messages", self::$flashList);
    self::setVariable("release", CMS_RELEASE);
    self::setVariable("version", CMS_VERSION);
    self::setVariable("name", CMS_NAME);
    self::setVariable("ip", $_SERVER["REMOTE_ADDR"]);
    self::setVariable("admin_id", ADMIN_ID);
    self::setVariable("plugins", array_keys($plugins->getObservers()));
    self::$contentFull = DOMBuilder::buildHTMLPlus(INDEX_HTML);
    $h1 = self::$contentFull->documentElement->firstElement;
    self::setVariable("lang", self::$contentFull->documentElement->getAttribute("xml:lang"));
    self::setVariable("mtime", $h1->getAttribute("mtime"));
    self::setVariable("ctime", $h1->getAttribute("ctime"));
    self::setVariable("author", $h1->getAttribute("author"));
    self::setVariable("authorid", $h1->hasAttribute("authorid") ? $h1->getAttribute("authorid") : null);
    self::setVariable("resp", $h1->getAttribute("resp"));
    self::setVariable("respid", $h1->hasAttribute("respid") ? $h1->getAttribute("respid") : null);
    self::setVariable("host", HOST);
    self::setVariable("url", URL);
    self::setVariable("uri", URI);
    self::setVariable("cache_nginx", getCurLink()."?".CACHE_PARAM."=".CACHE_NGINX);
    self::setVariable("cache_ignore", getCurLink()."?".CACHE_PARAM."=".CACHE_IGNORE);
    self::setVariable("link", getCurLink());
    self::setVariable("url_debug_on", getCurLink()."/?".PAGESPEED_PARAM."=".PAGESPEED_OFF
      ."&".DEBUG_PARAM."=".DEBUG_ON."&".CACHE_PARAM."=".CACHE_IGNORE);
    if(isset($_GET[PAGESPEED_PARAM]) || isset($_GET[DEBUG_PARAM]) || isset($_GET[CACHE_PARAM]))
      self::setVariable("url_debug_off", getCurLink()."/?".PAGESPEED_PARAM."&".DEBUG_PARAM."&".CACHE_PARAM);
    if(isset($_GET[PAGESPEED_PARAM])) self::setVariable(PAGESPEED_PARAM, $_GET[PAGESPEED_PARAM]);
  }

  private static function createFlashList() {
    $doc = new DOMDocumentPlus();
    self::$flashList = $doc->appendChild($doc->createElement("root"));
    $ul = self::$flashList->appendChild($doc->createElement("ul"));
    self::setVariable("messages", self::$flashList);
  }

  private static function addFlashItem($message, $type) {
    $class = $type;
    switch($type) {
      case self::MSG_ERROR: self::$error = true; break;
      case self::MSG_WARNING: self::$warning = true; break;
      case self::MSG_SUCCESS: self::$success = true; break;
      default: self::$other = true; $class = self::MSG_INFO;
    }
    if(!is_null(self::getLoggedUser())) $message = "$type: $message";
    $li = self::$flashList->ownerDocument->createElement("li");
    self::$flashList->firstElement->appendChild($li);
    $li->setAttribute("class", strtolower($class));
    $doc = new DOMDocumentPlus();
    if(!@$doc->loadXML("<var>$message</var>")) {
      $li->nodeValue = htmlspecialchars($message);
    } else {
      foreach($doc->documentElement->childNodes as $ch)
        $li->appendChild($li->ownerDocument->importNode($ch, true));
    }
  }

  public static function getMessages() {
    if(!isset($_SESSION["cms"]["flash"]) || !count($_SESSION["cms"]["flash"])) return;
    if(is_null(self::$flashList)) self::createFlashList();
    foreach($_SESSION["cms"]["flash"] as $type => $item) {
      foreach($item as $token => $messages) {
        foreach($messages as $message) {
          if($token != self::$requestToken) $message = sprintf(_("%s (previous requests)"), $message);
          self::addFlashItem($message, $type);
        }
      }
    }
    $_SESSION["cms"]["flash"] = array();
  }

  public static function getContentFull() {
    return self::$contentFull;
  }

  public static function buildContent() {
    if(is_null(self::$contentFull)) throw new Exception(_("Full content must be set to build content"));
    if(!is_null(self::$content)) throw new Exception(_("Method cannot run twice"));
    self::$content = clone self::$contentFull;
    try {
      $cs = null;
      global $plugins;
      foreach($plugins->getIsInterface("ContentStrategyInterface") as $cs) {
        $c = $cs->getContent(self::$content);
        $object = gettype($c) == "object";
        if(!($object && $c instanceof HTMLPlus)) {
          throw new Exception(sprintf(_("Content must be an instance of HTMLPlus (%s given)"), ($object ? get_class($c) : gettype($c))));
        }
        try {
          $c->validatePlus();
        } catch(Exception $e) {
          throw new Exception(sprintf(_("HTMLPlus content is invalid: %s"), $e->getMessage()));
        }
        self::$content = $c;
      }
    } catch (Exception $e) {
      if(self::DEBUG) echo $c->saveXML();
      throw new Exception(sprintf(_("Plugin %s exception: %s"), get_class($cs), $e->getMessage()));
    }
  }

  public static function checkAuth() {
    $loggedUser = self::getLoggedUser();
    if(!is_null($loggedUser)) {
      self::setLoggedUser($loggedUser);
      return;
    }
    if(!file_exists(FORBIDDEN_FILE) && SCRIPT_NAME == "index.php") return;
    loginRedir();
  }

  public static function setLoggedUser($user) {
    self::setVariable("logged_user", $user);
    if(self::isSuperUser()) self::setVariable("super_user", $user);
    else self::setVariable("super_user", "");
    if((session_status() == PHP_SESSION_NONE && !session_start())
      || !session_regenerate_id()) {
      throw new Exception(_("Unable to re/generate session ID"));
    }
  }

  public static function isSuperUser() {
    if(IS_LOCALHOST) return true;
    if(self::getLoggedUser() == "admin") return true;
    if(self::getLoggedUser() == ADMIN_ID) return true;
    if(isset($_SERVER["REMOTE_ADDR"])
      && $_SERVER["REMOTE_ADDR"] == $_SERVER['SERVER_ADDR']) return true;
    return false;
  }

  public static function getLoggedUser() {
    if(IS_LOCALHOST) return ADMIN_ID;
    if(isset($_SERVER["REMOTE_ADDR"])
      && $_SERVER["REMOTE_ADDR"] == $_SERVER['SERVER_ADDR']) return "server";
    if(isset($_SERVER['REMOTE_USER']) && strlen($_SERVER['REMOTE_USER']))
      return $_SERVER['REMOTE_USER'];
    #if(isset($_SESSION[get_called_class()]["loggedUser"]))
    #  return $_SESSION[get_called_class()]["loggedUser"];
    return null;
  }

  public static function isActive() {
    if(IS_LOCALHOST) return true;
    return file_exists(CMS_RELEASE);
  }

  public static function contentProcessVariables() {
    $oldContent = clone self::$content;
    try {
      self::$content = self::$content->processVariables(self::$variables);
      self::$content->validatePlus(true);
      #self::$content->processFunctions(self::$functions, self::$variables);
    } catch(Exception $e) {
      Logger::log(sprintf(_("Some variables are causing HTML+ error: %s"), $e->getMessage()), Logger::LOGGER_ERROR);
      self::$content = $oldContent;
    }
  }

  public static function processVariables(DOMDocumentPlus $doc) {
    Logger::log(sprintf(METHOD_NA, __CLASS__.".".__FUNCTION__), Logger::LOGGER_ERROR);
    return $doc;
  }

  private static function insertVar(HTMLPlus $newContent, $varName, $varValue) {
    Logger::log(sprintf(METHOD_NA, __CLASS__.".".__FUNCTION__), Logger::LOGGER_ERROR);
    return;
    $tmpContent = clone $newContent;
    $tmpContent->insertVar($varName, $varValue);
    $tmpContent->validatePlus();
    $newContent = $tmpContent;
  }

  public static function setOutputStrategy(OutputStrategyInterface $strategy) {
    self::$outputStrategy = $strategy;
  }

  public static function addMessage($message, $type) {
    if(is_null(self::$requestToken)) self::$requestToken = rand();
    if(Cms::isSuperUser()) {
      $_SESSION["cms"]["flash"][$type][self::$requestToken][] = $message;
      return;
    }
    if(is_null(self::$flashList)) self::createFlashList();
    self::addFlashItem($message, $type);
  }

  public static function getVariable($name) {
    $id = strtolower($name);
    if(!array_key_exists($id, self::$variables)) return null;
    return self::$variables[$id];
  }

  public static function getFunction($name) {
    $id = strtolower($name);
    if(!array_key_exists($id, self::$functions)) return null;
    return self::$functions[$id];
  }

  public static function addVariableItem($name, $value) {
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
    if($varId == $name) return $varId;
    return $varId.(strlen($name) ? "-".normalize($name) : "");
  }

  public static function hasErrorMessage() { return self::$error; }
  public static function hasWarningMessage() { return self::$warning; }
  public static function hasOtherMessage() { return self::$other; }
  public static function hasSuccessMessage() { return self::$success; }

  public static function setFunction($name, $value) {
    if(!$value instanceof Closure) {
      Logger::log(sprintf(_("Unable to set function %s: not a function"), $name), Logger::LOGGER_WARNING);
      return null;
    }
    $varId = self::getVarId($name, "fn");
    self::$functions[$varId] = $value;
    return $varId;
  }

  public static function setVariable($name, $value) {
    $varId = self::getVarId($name);
    if(!array_key_exists($varId, self::$variables))
      self::addVariableItem("variables", $varId);
    self::$variables[$varId] = $value;
    return $varId;
  }

  public static function getAllVariables() {
    return self::$variables;
  }

  public static function getAllFunctions() {
    return self::$functions;
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

  public static function applyUserFn($fName, DOMNode $node) {
    $fn = self::getFunction($fName);
    if(is_null($fn))
      throw new Exception(sprintf(_("Function %s does not exist"), $fName));
    return $fn($node);
  }

}

?>
