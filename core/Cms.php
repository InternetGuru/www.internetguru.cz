<?php

namespace IGCMS\Core;

use Closure;
use DOMNode;
use Exception;

/**
 * Class Cms
 * @package IGCMS\Core
 *
 * @method static success($msg)
 * @method static notice($msg)
 * @method static warning($msg)
 * @method static error($msg)
 *
 */
class Cms {

  /**
   * @var array|null
   */
  private static $types = null;
  /**
   * @var OutputStrategyInterface|null
   */
  private static $outputStrategy = null;
  /**
   * @var array
   */
  private static $variables = [];
  /**
   * @var array
   */
  private static $functions = [];
  /**
   * @var bool
   */
  private static $forceFlash = false;
  /**
   * @var DOMElementPlus|null
   */
  private static $flashList = null;
  /**
   * @var bool
   */
  private static $error = false;
  /**
   * @var bool
   */
  private static $warning = false;
  /**
   * @var bool
   */
  private static $notice = false;
  /**
   * @var bool
   */
  private static $success = false;
  /**
   * @var string|null
   */
  private static $requestToken = null;

  /**
   * @param string $methodName
   * @param array $arguments
   */
  public static function __callStatic ($methodName, $arguments) {
    validate_callStatic($methodName, $arguments, self::getTypes(), 1);
    self::addMessage($methodName, $arguments[0]);
  }

  /**
   * @return array
   */
  private static function getTypes () {
    if (!is_null(self::$types)) {
      return self::$types;
    }
    self::$types = [
      'success' => _("Success"),
      'notice' => _("Notice"),
      'warning' => _("Warning"),
      'error' => _("Error"),
    ];
    return self::$types;
  }

  /**
   * @param string $type
   * @param string $message
   */
  private static function addMessage ($type, $message) {
    if (is_null(self::$flashList)) {
      self::createFlashList();
    }
    if (is_null(self::$requestToken)) {
      self::$requestToken = rand();
    }
    if (self::isSuperUser()) {
      $_SESSION["cms"]["flash"][$type][hash(FILE_HASH_ALGO, $message)] = $message;
      $_SESSION["cms"]["request"][$type][hash(FILE_HASH_ALGO, $message)][] = self::$requestToken;
      return;
    }
    self::addFlashItem($message, $type, [self::$requestToken]);
  }

  private static function createFlashList () {
    $doc = new DOMDocumentPlus();
    self::$flashList = $doc->appendChild($doc->createElement("root"));
    self::$flashList->appendChild($doc->createElement("ul"));
    self::setVariable("messages", self::$flashList);
  }

  /**
   * @param string $name
   * @param mixed $value
   * @param string|null $prefix
   * @return string
   * @throws Exception
   */
  public static function setVariable ($name, $value, $prefix = null) {
    $varId = self::getVarId($name, $prefix);
    // if (!array_key_exists($varId, self::$variables)) {
    //   self::addVariableItem("variables", $varId);
    // }
    self::$variables[$varId] = $value;
    return $varId;
  }

  /**
   * @param $name
   * @param string|null $prefix
   * @return string
   * @throws Exception
   */
  private static function getVarId ($name, $prefix = null) {
    $name = normalize($name);
    if (is_null($prefix)) {
      $prefix = self::getCaller();
    }
    if ($prefix == $name || !strlen($prefix)) {
      if (!strlen($name)) {
        throw new Exception("Unable to set variable: name and prefix are empty");
      }
      return $name;
    }
    return $prefix.(strlen($name) ? "-$name" : "");
  }

  /**
   * @return string
   * @throws Exception
   */
  private static function getCaller () {
    $d = debug_backtrace();
    if (!isset($d[3]["class"])) {
      throw new Exception(_("Unknown caller class"));
    }
    return strtolower((new \ReflectionClass($d[3]["class"]))->getShortName());
  }

  /**
   * @param string $name
   * @param mixed $value
   */
  public static function addVariableItem ($name, $value) {
    $varId = self::getVarId($name);
    $var = self::getVariable($varId);
    if (is_null($var)) {
      self::$variables[$varId] = [$value];
      return;
    }
    if (!is_array($var)) {
      $var = [$var];
    }
    $var[] = $value;
    self::$variables[$varId] = $var;
  }

  /**
   * @param string|$name
   * @return mixed|null
   */
  public static function getVariable ($name) {
    $id = strtolower($name);
    if (!array_key_exists($id, self::$variables)) {
      return null;
    }
    return self::$variables[$id];
  }

  /**
   * @return bool
   */
  public static function isSuperUser () {
    if (self::getLoggedUser() == "admin") {
      return true;
    }
    if (self::getLoggedUser() == ADMIN_ID) {
      return true;
    }
    if (isset($_SERVER["REMOTE_ADDR"])
      && $_SERVER["REMOTE_ADDR"] == $_SERVER['SERVER_ADDR']
    ) {
      return true;
    }
    return false;
  }

  /**
   * @return null|string
   */
  public static function getLoggedUser () {
    if (isset($_SERVER["REMOTE_ADDR"])
      && $_SERVER["REMOTE_ADDR"] == $_SERVER['SERVER_ADDR']
    ) {
      return SERVER_USER;
    }
    if (isset($_SERVER['REMOTE_USER']) && strlen($_SERVER['REMOTE_USER'])) {
      return $_SERVER['REMOTE_USER'];
    }
    #if(isset($_SESSION[get_called_class()]["loggedUser"]))
    #  return $_SESSION[get_called_class()]["loggedUser"];
    return null;
  }

  /**
   * @param string $message
   * @param string $type
   * @param array $requests
   */
  private static function addFlashItem ($message, $type, Array $requests) {
    self::$$type = true;
    if (!is_null(self::getLoggedUser())) {
      $message = self::$types[$type].": $message";
    }
    $li = self::$flashList->ownerDocument->createElement("li");
    self::$flashList->firstElement->appendChild($li);
    $li->setAttribute("class", strtolower($type)." ".implode(" ", $requests));
    $doc = new DOMDocumentPlus();
    try {
      $doc->loadXML("<var>$message</var>");
      foreach ($doc->documentElement->childNodes as $ch) {
        $li->appendChild($li->ownerDocument->importNode($ch, true));
      }
    } catch (Exception $e) {
      $li->nodeValue = htmlspecialchars($message);
    }
  }

  public static function init () {
    global $plugins;
    self::setVariable("messages", self::$flashList);
    self::setVariable("release", CMS_RELEASE);
    self::setVariable("version", CMS_VERSION);
    self::setVariable("default_release", DEFAULT_RELEASE);
    self::setVariable("name", CMS_NAME);
    self::setVariable("stage", CMS_STAGE);
    self::setVariable("ip", $_SERVER["REMOTE_ADDR"]);
    self::setVariable("admin_id", ADMIN_ID);
    self::setVariable("plugins", array_keys($plugins->getObservers()));
    $id = HTMLPlusBuilder::getFileToId(INDEX_HTML);
    self::setVariable("author", HTMLPlusBuilder::getIdToAuthor($id));
    self::setVariable("authorid", HTMLPlusBuilder::getIdToAuthorId($id));
    self::setVariable("resp", HTMLPlusBuilder::getIdToResp($id));
    self::setVariable("respid", HTMLPlusBuilder::getIdToRespId($id));
    self::setVariable("ctime", HTMLPlusBuilder::getIdToCtime($id));
    self::setVariable("lang", HTMLPlusBuilder::getIdToLang($id));
    self::setVariable("host", HOST);
    self::setVariable("url", URL);
    self::setVariable("cache_nginx", getCurLink()."?".CACHE_PARAM."=".CACHE_NGINX);
    self::setVariable("cache_ignore", getCurLink()."?".CACHE_PARAM."=".CACHE_IGNORE);
    self::setVariable("link", getCurLink());
    self::setVariable(
      "url_debug_on",
      getCurLink()."/?".PAGESPEED_PARAM."=".PAGESPEED_OFF."&".DEBUG_PARAM."=".DEBUG_ON."&".CACHE_PARAM
      ."=".CACHE_IGNORE
    );
    if (isset($_GET[PAGESPEED_PARAM]) || isset($_GET[DEBUG_PARAM]) || isset($_GET[CACHE_PARAM])) {
      self::setVariable("url_debug_off", getCurLink()."/?".PAGESPEED_PARAM."&".DEBUG_PARAM."&".CACHE_PARAM);
    }
    if (isset($_GET[PAGESPEED_PARAM])) {
      self::setVariable(PAGESPEED_PARAM, $_GET[PAGESPEED_PARAM]);
    }
    if (self::getLoggedUser() == SERVER_USER) {
      self::setVariable("server", SERVER_USER);
      self::setVariable("uri", SERVER_USER);
      self::setVariable("mtime", 0);
      return;
    }
    self::setVariable("mtime", HTMLPlusBuilder::getIdToMtime($id));
    self::setVariable("uri", URI);
  }

  public static function getMessages () {
    if (!isset($_SESSION["cms"]["flash"]) || !count($_SESSION["cms"]["flash"])) {
      return;
    }
    if (is_null(self::$flashList)) {
      self::createFlashList();
    }
    self::getTypes();
    foreach ($_SESSION["cms"]["flash"] as $type => $item) {
      foreach ($item as $hash => $message) {
        self::addFlashItem($message, $type, $_SESSION["cms"]["request"][$type][$hash]);
      }
    }
    $_SESSION["cms"]["flash"] = [];
    $_SESSION["cms"]["request"] = [];
  }

  /**
   * @return HTMLPlus|null
   */
  public static function buildContent () {
    global $plugins;
    $content = null;
    $pluginExceptionMessage = _("Plugin %s exception: %s");
    /** @var GetContentStrategyInterface $plugin */
    foreach ($plugins->getIsInterface("IGCMS\\Core\\GetContentStrategyInterface") as $plugin) {
      try {
        $content = $plugin->getContent();
        if (is_null($content)) {
          continue;
        }
        self::validateContent($content);
        break;
      } catch (Exception $e) {
        Logger::error(sprintf($pluginExceptionMessage, get_class($plugin), $e->getMessage()));
        $content = null;
      }
    }
    if (is_null($content)) {
      $content = HTMLPlusBuilder::getFileToDoc(INDEX_HTML);
      self::validateContent($content);
    }
    /** @var ModifyContentStrategyInterface $plugin */
    foreach ($plugins->getIsInterface("IGCMS\\Core\\ModifyContentStrategyInterface") as $plugin) {
      try {
        $tmpContent = clone $content;
        $plugin->modifyContent($tmpContent);
        self::validateContent($tmpContent);
        $content = $tmpContent;
      } catch (Exception $e) {
        Logger::error(sprintf($pluginExceptionMessage, get_class($plugin), $e->getMessage()));
      }
    }
    if (self::getLoggedUser() != SERVER_USER) {
      self::setVariable("mtime", timestamptToW3C(HTMLPlusBuilder::getNewestFileMtime()));
    }
    return $content;
  }

  /**
   * @param HTMLPlus $content
   * @throws Exception
   */
  private static function validateContent (HTMLPlus $content) {
    $object = gettype($content) == "object";
    if (!($object && $content instanceof HTMLPlus)) {
      $name = $object ? get_class($content) : gettype($content);
      throw new Exception(sprintf(_("Content must be an instance of HTML+ (%s given)"), $name));
    }
    try {
      $content->validatePlus();
    } catch (Exception $e) {
      throw new Exception(sprintf(_("Invalid HTML+ content: %s"), $e->getMessage()));
    }
  }

  public static function checkAuth () {
    $loggedUser = self::getLoggedUser();
    if (!is_null($loggedUser)) {
      self::setLoggedUser($loggedUser);
      return;
    }
    if (!stream_resolve_include_path(PROTECTED_FILE) && (SCRIPT_NAME == "index.php" || SCRIPT_NAME == FINDEX_PHP)) {
      return;
    }
    loginRedir();
  }

  /**
   * @param string $user
   * @throws Exception
   */
  public static function setLoggedUser ($user) {
    self::setVariable("logged_user", $user);
    if (self::isSuperUser()) {
      self::setVariable("super_user", $user);
    } else {
      self::setVariable("super_user", "");
    }
    if ((session_status() == PHP_SESSION_NONE && !session_start())
      || !session_regenerate_id()
    ) {
      throw new Exception(_("Unable to re/generate session ID"));
    }
  }

  /**
   * @return bool
   */
  public static function isActive () {
    return !stream_resolve_include_path(CMS_ROOT_FOLDER."/.".CMS_RELEASE);
  }

  /**
   * @param HTMLPlus $content
   * @return HTMLPlus
   */
  public static function contentProcessVariables (HTMLPlus $content) {
    $tmpContent = clone $content;
    try {
      /** @var HTMLPlus $tmpContent */
      $dataVariables = HTMLPlusBuilder::getIdToData(HTMLPlusBuilder::getFileToId(HTMLPlusBuilder::getCurFile()));
      $vars = is_null($dataVariables) ? self::$variables : array_merge(self::$variables, $dataVariables);
      $tmpContent = $tmpContent->processVariables($vars);
      $tmpContent->validatePlus(true);
      return $tmpContent;
    } catch (Exception $e) {
      Logger::user_error(sprintf(_("Invalid HTML+: %s"), $e->getMessage()));
      return $content;
    }
  }

  /**
   * @return bool
   */
  public static function hasErrorMessage () {
    return self::$error;
  }

  /**
   * @return bool
   */
  public static function hasWarningMessage () {
    return self::$warning;
  }

  /**
   * @return bool
   */
  public static function hasNoticeMessage () {
    return self::$notice;
  }

  /**
   * @return bool
   */
  public static function hasSuccessMessage () {
    return self::$success;
  }

  /**
   * @param string $name
   * @param Closure $value
   * @return null|string
   */
  public static function setFunction ($name, Closure $value) {
    if (!$value instanceof Closure) {
      Logger::error(sprintf(_("Unable to set function %s: not a function"), $name));
      return null;
    }
    $varId = self::getVarId($name);
    self::$functions[$varId] = $value;
    return $varId;
  }

  /**
   * @return array
   */
  public static function getAllVariables () {
    return self::$variables;
  }

  /**
   * @return array
   */
  public static function getAllFunctions () {
    return self::$functions;
  }

  public static function setForceFlash () {
    self::$forceFlash = true;
  }

  /**
   * @param HTMLPlus $content
   * @return string
   */
  public static function getOutput (HTMLPlus $content) {
    if (is_null(self::$outputStrategy)) {
      return $content->saveXML();
    }
    return self::$outputStrategy->getOutput($content);
  }

  /**
   * @return OutputStrategyInterface|null
   */
  public static function getOutputStrategy () {
    return self::$outputStrategy;
  }

  /**
   * @param OutputStrategyInterface $strategy
   */
  public static function setOutputStrategy (OutputStrategyInterface $strategy) {
    self::$outputStrategy = $strategy;
  }

  /**
   * @param string $fName
   * @param DOMNode $node
   * @return mixed
   * @throws Exception
   */
  public static function applyUserFn ($fName, DOMNode $node) {
    $fn = self::getFunction($fName);
    if (is_null($fn)) {
      throw new Exception(sprintf(_("Function %s does not exist"), $fName));
    }
    return $fn($node);
  }

  /**
   * @param $name
   * @return Closure|null
   */
  public static function getFunction ($name) {
    $id = strtolower($name);
    if (!array_key_exists($id, self::$functions)) {
      return null;
    }
    return self::$functions[$id];
  }

}

?>
