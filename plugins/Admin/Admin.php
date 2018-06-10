<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMBuilder;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\GetContentStrategyInterface;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use IGCMS\Core\TitleStrategyInterface;
use IGCMS\Core\XMLBuilder;
use SplObserver;
use SplSubject;

/**
 * Class Admin
 * @package IGCMS\Plugins
 */
class Admin extends Plugin implements SplObserver, GetContentStrategyInterface, TitleStrategyInterface {
  /**
   * @var int
   */
  const STATUS_NEW = 0;
  /**
   * @var int
   */
  const STATUS_ENABLED = 1;
  /**
   * @var int
   */
  const STATUS_DISABLED = 2;
  /**
   * @var int
   */
  const STATUS_INVALID = 3;
  /**
   * @var string
   */
  const FILE_DISABLE = "disable";
  /**
   * @var string
   */
  const FILE_ENABLE = "enable";
  /**
   * @var string
   */
  private $contentValue = "";
  /**
   * @var string|null
   */
  private $scheme = null;
  /**
   * @var string
   */
  private $type = "txt";
  /**
   * @var string|null
   */
  private $title = null;
  /**
   * @var bool
   */
  private $redir = false;
  /**
   * @var bool
   */
  private $replace = true;
  /**
   * @var string|null
   */
  private $dataFile = null;
  /**
   * @var string|null
   */
  private $destFile = null;
  /**
   * @var string|null
   */
  private $dataFileDisabled = null;
  /**
   * @var int
   */
  private $dataFileStatus;
  /**
   * @var string|null
   */
  private $defaultFile = null;
  /**
   * @var array
   */
  private $dataFileStatuses;
  /**
   * @var bool
   */
  private $contentChanged = false;
  /**
   * @var array
   */
  private $allowedTypes = ["html", "xml", "xsl", "js", "css"];

  /**
   * Admin constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 150);
    $this->dataFileStatuses = [
      _("new file"), _("active file"),
      _("inactive file"), _("invalid file"), _("unknown status"),
    ];
    $this->dataFileStatus = self::STATUS_NEW;
  }

  /**
   * @param Plugins|SplSubject $subject
   * @throws Exception
   */
  public function update (SplSubject $subject) {
    switch ($subject->getStatus()) {
      case STATUS_INIT:
        if (!isset($_GET[$this->className])) {
          $subject->detach($this);
          return;
        }
        $this->main();
        break;
      case STATUS_PROCESS:
        $this->checkCache();
        break;
      case STATUS_POSTPROCESS:
        $this->createVarList();
    }
  }

  /**
   * @throws Exception
   */
  private function main () {
    $this->requireActiveCms();
    try {
      $this->process();
    } catch (Exception $exc) {
      Logger::user_error($this->className.": ".$exc->getMessage());
      return;
    }
    if (!$this->isPost()) {
      return;
    }
    if (!$this->redir) {
      return;
    }
    $pLink["path"] = get_link();
    if (!isset($_POST["saveandgo"])) {
      $pLink["query"] = $this->className."=".$_POST["filename"];
    }
    redir_to(build_local_url($pLink, true));
  }

  /**
   * @throws Exception
   */
  private function process () {
    $this->setDefaultFile();
    $this->initDataFiles();
    if ($this->isPost()) {
      if ($_POST["userfilehash"] != $this->getDataFileHash()) {
        throw new Exception(sprintf(_("User file '%s' changed during administration"), $this->defaultFile));
      }
      $this->contentValue = str_replace("\r\n", "\n", $_POST["content"]);;
    } else {
      $this->setContent();
    }
    if ($this->isPost() && !Cms::isSuperUser()) {
      throw new Exception(_("Insufficient right to save changes"));
    }
    if (!$this->isResource($this->type)) {
      $this->processXml();
    }
    if ($this->isPost()) {
      $this->contentChanged = $this->getContentChanged();
    }
    if ($this->isPost()) {
      $this->destFile = USER_FOLDER."/".$this->getFilepath($_POST["filename"]);
      if ($this->contentChanged || $this->dataFile != $this->destFile) {
        $this->savePost();
        $this->updateCache();
      } else {
        Logger::user_notice(_("No changes made"));
      }
    }
  }

  /**
   * LOGIC (-> == redir if exists)
   * null -> [current_link].html in loaded html files -> INDEX_HTML
   * F -> plugins/$0/$0.xml -> $0.xml
   * [dir/]+F -> $0.xml
   * [dir/]*F.ext (direct match) -> plugins/$0
   *
   * EXAMPLES
   * /about?admin -> /about?admin=about.html -> /about?admin=INDEX_HTML
   * HtmlOutput -> plugins/HtmlOutput/HtmlOutput.xml (F default plugin config)
   * HtmlOutput/HtmlOutput.xsl -> plugins/HtmlOutput/HtmlOutput.xsl (dir/F.ext plugin)
   * Cms.xml -> Cms.xml (F.ext direct match)
   * themes/simpleLayout.css -> themes/simpleLayout.css (dir/F.ext direct)
   * themes/userFile.css -> usr/themes/userFile.css (dir/F.ext user)
   *
   * @throws Exception
   */
  private function setDefaultFile () {
    $fileName = $_GET[$this->className];
    $this->defaultFile = $this->getFilepath($fileName);
    $fLink = HTMLPlusBuilder::getIdToLink(HTMLPlusBuilder::getFileToId($this->defaultFile));
    if (is_null($fLink)) {
      $fLink = get_link();
    }
    if ($this->defaultFile != $fileName || $fLink != get_link()) {
      redir_to(build_local_url(["path" => $fLink, "query" => $this->className."=".$this->defaultFile]));
    }
    $this->type = pathinfo($this->defaultFile, PATHINFO_EXTENSION);
    if (!in_array($this->type, $this->allowedTypes)) {
      $this->defaultFile = "";
      throw new Exception(sprintf(_("File type '%s' is not allowed"), $this->type));
    }
  }

  /**
   * @param string $file
   * @return string
   * @throws Exception
   */
  private function getFilepath ($file) {
    if (!strlen($file)) {
      if (get_link() == "") {
        return INDEX_HTML;
      }
      $path = HTMLPlusBuilder::getIdToFile(HTMLPlusBuilder::getLinkToId(get_link()));
      if (!is_null($path)) {
        return $path;
      }
      return INDEX_HTML;
    }
    $file = strip_data_dir($file);
    if (preg_match('~^[\w-]+$~', $file)) {
      $pluginFile = PLUGINS_DIR."/$file/$file.xml";
      if (is_file(CMS_FOLDER."/$pluginFile")) {
        return $pluginFile;
      }
    }
    if (!preg_match("/^".FILEPATH_PATTERN."$/", $file) || strpos($file, "..") !== false) {
      $this->dataFileStatus = self::STATUS_INVALID;
      throw new Exception(sprintf(_("Unsupported file name format '%s'"), $file));
    }
    if (strpos(basename($file), ".") === 0) {
      $file = dirname($file)."/".substr(basename($file), 1);
    }
    if (is_file(CMS_FOLDER."/".PLUGINS_DIR."/$file")) {
      $file = PLUGINS_DIR."/$file";
    }
    return $file;
  }

  private function initDataFiles () {
    $this->dataFile = USER_FOLDER."/".$this->defaultFile;
    $this->dataFileDisabled = dirname($this->dataFile)."/.".basename($this->dataFile);
    try {
      if (isset($_GET[self::FILE_ENABLE])) {
        $this->enableDataFile();
        Logger::user_success(sprintf(_("File '%s' enabled"), $this->defaultFile));
      }
      if (isset($_GET[self::FILE_DISABLE])) {
        $this->disableDataFile();
        $this->clearResCache();
        Logger::user_success(sprintf(_("File '%s' disabled"), $this->defaultFile));
      }
    } catch (Exception $exc) {
      Logger::user_warning($exc->getMessage());
    }
    if (stream_resolve_include_path($this->dataFile)) {
      $this->dataFileStatus = self::STATUS_ENABLED;
    }
    if (stream_resolve_include_path($this->dataFileDisabled)) {
      $this->dataFileStatus = self::STATUS_DISABLED;
    }
  }

  /**
   * @throws Exception
   */
  private function enableDataFile () {
    if (!is_file($this->dataFileDisabled)) {
      throw new Exception("Cannot enable active file");
    }
    if (is_file($this->dataFile)) {
      $status = unlink($this->dataFileDisabled);
    } else {
      $status = rename($this->dataFileDisabled, $this->dataFile);
    }
    if (!$status) {
      throw new Exception(_("Failed to enable file"));
    }
  }

  /**
   * @throws Exception
   */
  private function disableDataFile () {
    if (is_file($this->dataFileDisabled)) {
      throw new Exception("Cannot disable inactive file");
    }
    if (!touch($this->dataFileDisabled)) {
      throw new Exception(_("Failed to disable file"));
    }
  }

  /**
   * @return bool
   */
  private function isPost () {
    return isset($_POST["content"], $_POST["userfilehash"], $_POST["filename"]);
  }

  /**
   * @return string
   */
  private function getDataFileHash () {
    if (is_file($this->dataFile)) {
      return file_hash($this->dataFile);
    }
    return file_hash($this->dataFileDisabled);
  }

  /**
   * @throws Exception
   */
  private function setContent () {
    $file = $this->dataFile;
    if (!stream_resolve_include_path($file)) {
      $file = $this->dataFileDisabled;
      if (!stream_resolve_include_path($file)) {
        return;
      }
    }
    if (($this->contentValue = file_get_contents($file)) === false) {
      throw new Exception(sprintf(_("Unable to get contents from '%s'"), $this->dataFile));
    }
  }

  /**
   * @param string $type
   * @return bool
   */
  private function isResource ($type) {
    return !in_array($type, ["xml", "xsl", "html"]);
  }

  /**
   * @throws Exception
   */
  private function processXml () {
    // get default schema
    try {
      $file = find_file($this->defaultFile, false);
      $this->scheme = $this->getScheme($file);
    } catch (Exception $exc) {}
    // get user schema if default schema not exists
    if (is_null($this->scheme) && stream_resolve_include_path($this->dataFile)) {
      $this->scheme = $this->getScheme($this->dataFile);
    }
    $htmlId = normalize(pathinfo($this->dataFile, PATHINFO_FILENAME));
    if (!$this->isPost() && $this->dataFileStatus == self::STATUS_NEW) {
      if ($this->type == "html") {
        $doc = new HTMLPlus();
        $doc->load(CMS_FOLDER."/".INDEX_HTML);
        $doc->documentElement->removeAttribute("ns");
        $doc->documentElement->firstElement->removeAttribute("ctime");
        $doc->documentElement->firstElement->setAttribute("id", $htmlId);
        $doc->validatePlus(true);
      } else {
        $doc = new DOMDocumentPlus();
        $doc->formatOutput = true;
        if ($this->type == "xsl") {
          $rootName = "xslt";
        } else {
          $rootName = pathinfo($this->defaultFile, PATHINFO_FILENAME);
        }
        $root = $doc->appendChild($doc->createElement($rootName));
        $root->appendChild($doc->createComment(" "._("user content")." "));
      }
      $this->contentValue = $doc->saveXML();
    } else {
      if ($this->type == "html") {
        $doc = new HTMLPlus();
        $doc->defaultAuthor = Cms::getVariableValue("cms-author");
        $doc->defaultId = $htmlId;
      } else {
        $doc = new DOMDocumentPlus();
      }
      $doc->loadXML($this->contentValue);
    }
    $repair = $this->isPost() && isset($_POST["repair"]);
    try {
      if ($this->type == "html") {
        $doc->validatePlus();
      }
    } catch (Exception $exc) {
      $doc->validatePlus(true);
      foreach ($doc->getErrors() as $error) {
        Logger::user_notice("$error".($repair ? " (".$doc->getStatus().")" : ""));
      }
      if (!$repair) {
        throw new Exception(_("Repairable error(s) occurred"));
      }
      $this->contentValue = $doc->saveXML();
    }
    if ($this->type != "xml" || $this->isPost()) {
      return;
    }
    $this->replace = false;
    $this->validateXml($doc);
  }

  /**
   * @param string $file
   * @return string|null
   * @throws Exception
   */
  private function getScheme ($file) {
    $fHandler = fopen($file, "r");
    fgets($fHandler); // skip first line
    $line = str_replace("'", '"', fgets($fHandler));
    fclose($fHandler);
    $scheme = null;
    if (!preg_match('<\?xml-model href="([^"]+)" ?\?>', $line, $matches)) {
      return $scheme;
    }
    try {
      $scheme = find_file($matches[1], false, false);
    } catch (Exception $exc) {
      throw new Exception(sprintf(_("Schema file '%s' not found"), $scheme));
    }
    return $scheme;
  }

  /**
   * @param DOMDocumentPlus $doc
   * @throws Exception
   */
  private function validateXml (DOMDocumentPlus $doc) {
    if (is_null($this->scheme)) {
      return;
    }
    switch (pathinfo($this->scheme, PATHINFO_EXTENSION)) {
      case "rng":
        $doc->relaxNGValidatePlus($this->scheme);
        break;
      default:
        throw new Exception(sprintf(_("Unsupported schema '%s'"), $this->scheme));
    }
  }

  /**
   * @return bool
   */
  private function getContentChanged () {
    $post_rn = str_replace("\n", "\r\n", $this->contentValue);
    return !in_array($_POST["userfilehash"], [$this->getHash($this->contentValue), $this->getHash($post_rn)]);
  }

  /**
   * @param string $data
   * @return string
   */
  private function getHash ($data) {
    return hash(FILE_HASH_ALGO, $data);
  }

  /**
   * @throws Exception
   */
  private function savePost () {
    mkdir_plus(dirname($this->destFile));
    $filePointer = lock_file($this->destFile);
    try {
      try {
        $destFileDisabled = dirname($this->destFile)."/.".basename($this->destFile);
        if ($this->destFile != $this->dataFile && !isset($_POST["overwrite"])
          && (is_file($this->destFile) || is_file($destFileDisabled))
        ) {
          throw new Exception(_("Destination file already exists"));
        }
        if (is_file($destFileDisabled)) {
          if (!unlink($destFileDisabled)) {
            throw new Exception(_("Unable to enable destination file"));
          }
        }
        fput_contents($this->destFile, $this->contentValue);
      } catch (Exception $exc) {
        throw new Exception(sprintf(_("Unable to save changes to %s: %s"), $_POST["filename"], $exc->getMessage()));
      }
      try {
        if (is_file($this->destFile.".old")) {
          rename_incr($this->destFile.".old", $this->destFile.".");
        }
      } catch (Exception $exc) {
        throw new Exception(sprintf(_("Unable to backup %s: %s"), $_POST["filename"], $exc->getMessage()));
      }
      $this->redir = true;
      Logger::user_success(_("Changes successfully saved"));
    } finally {
      unlock_file($filePointer, $this->destFile);
    }
  }

  private function clearResCache () {
    if ($this->isResource($this->type)) {
      $resFile = get_real_resdir($this->defaultFile);
      if (is_file($resFile)) {
        unlink($resFile);
      }
      if (get_real_resdir() != RESOURCES_DIR) {
        return;
      }
      if (is_file($this->defaultFile)) {
        unlink($this->defaultFile);
      }
      return;
    }
  }

  private function updateCache () {
    $this->clearResCache();
    #if(isset($_GET[DEBUG_PARAM]) && $_GET[DEBUG_PARAM] == DEBUG_ON) return;
    try {
      clear_nginx();
    } catch (Exception $exc) {
      Logger::critical($exc->getMessage());
    }
  }

  private function checkCache () {
    if (!$this->isResource($this->type)) {
      if (DOMBuilder::isCacheOutdated()) {
        Cms::notice(_("Saving changes will clear server cache"));
      }
      return;
    }
    if (!is_file($this->dataFile)) {
      return;
    }
    if (!is_file(get_real_resdir($this->defaultFile))
      || is_uptodate($this->dataFile, get_real_resdir($this->defaultFile))
    ) {
      if (get_real_resdir() != RESOURCES_DIR) {
        return;
      }
      if (!is_file($this->defaultFile)
        || is_uptodate($this->dataFile, $this->defaultFile)
      ) {
        return;
      }
    }
    Cms::notice(_("Saving changes will remove outdated file cache"));
  }

  /**
   * @throws Exception
   */
  private function createVarList () {
    $doc = new DOMDocumentPlus();
    $varListVar = $doc->createElement("var");
    $varlist = $doc->createElement("dl");
    $varListVar->appendChild($varlist);
    foreach (Cms::getAllVariables() as $name => $var) {
      $value = $var["value"];
      $varlist->appendChild($doc->createTextNode("\n  "));
      $varlist->appendChild($doc->createElement("dt", "$name"));
      $varlist->appendChild($doc->createTextNode("\n  "));
      $ddElement = $varlist->appendChild($doc->createElement("dd"));
      switch (gettype($value)) {
        case "NULL":
          $ddElement->nodeValue = _("null value");
          continue 2;
        case "array":
          $value = implode(", ", $value);
        case "string":
          if (!strlen($value)) {
            $ddElement->nodeValue = _("empty value");
            continue 2;
          }
          break;
        case "integer":
        case "double":
        break;
        case "object":
          if ($value instanceof \DOMDocument) {
            $value = $value->documentElement;
          }
          if ($value instanceof \DOMElement) {
            $value = $value->nodeValue;
          }
          if (is_string($value)) {
            $value = preg_replace('/^\s*/m', "", $value);
            break;
          }
          $ddElement->nodeValue = get_class($value);
          continue 2;
        default:
          $ddElement->nodeValue = gettype($value);
          continue 2;
      }
      $ddElement->appendChild($doc->createElement("samp", shorten($value)));
    }
    Cms::setVariable("varlist", $varListVar);
  }

  /**
   * @return string|null
   */
  public function getTitle () {
    return $this->title;
  }

  /**
   * TODO addJsFile & addJs to interface?
   * @return HTMLPlus
   * @throws Exception
   */
  public function getContent () {
    Cms::getOutputStrategy()->addJsFile($this->pluginDir.'/Admin.js', 100, "body");
    Cms::getOutputStrategy()->addJs(
      "
      require('IGCMS.Admin', function () {
        IGCMS.Admin.init({
          saveInactive: '"._("Data file is inactive. Save anyways?")."'
        })
      })
      ",
      100,
      "body"
    );
    $format = $this->type;
    if ($this->type == "html") {
      $format = "html+";
    }
    if (!is_null($this->scheme)) {
      $format .= " (".pathinfo($this->scheme, PATHINFO_BASENAME).")";
    }

    $content = self::getHTMLPlus();

    $adminLink = "?".$this->className."=".$_GET[$this->className];
    if ($this->dataFileStatus == self::STATUS_DISABLED) {
      $vars["warning"] = [
        "value" => "warning",
        "cacheable" => true,
      ];
    }
    $usrDestHash = $this->getDataFileHash();
    $mode = $this->replace ? _("replace") : _("modify");
    switch ($this->type) {
      case "html":
        $type = "htmlmixed";
        break;
      case "xsl":
        $type = "xml";
        break;
      case "js":
        $type = "javascript";
        break;
      default:
        $type = $this->type;
    }

    $vars["rooturl"] = [
      "value" => HTMLPlusBuilder::getFileToId(INDEX_HTML),
      "cacheable" => true,
    ];
    $vars["heading"] = [
      "value" => _("Administration"),
      "cacheable" => true,
    ];
    if (strlen($this->defaultFile)) {
      $vars["heading"] = [
        "value" => sprintf(_("File %s Administration"), basename($this->defaultFile)),
        "cacheable" => true,
      ];
      $this->title = sprintf(_("%s (%s) - Administration"), basename($this->defaultFile), ROOT_URL.$this->defaultFile);
    } else {
      $this->title = $vars["heading"]["value"];
    }
    $vars["link"] = [
      "value" => get_link(),
      "cacheable" => true,
    ];
    $vars["linkadmin"] = [
      "value" => $adminLink,
      "cacheable" => true,
    ];
    if ($this->contentValue !== "") {
      $vars["content"] = [
        "value" => htmlspecialchars($this->contentValue),
        "cacheable" => true,
      ];
    }
    $vars["filename"] = [
      "value" => $_GET[$this->className],
      "cacheable" => true,
    ];
    $vars["filepathpattern"] = [
      "value" => FILEPATH_PATTERN,
      "cacheable" => true,
    ];
    $vars["schema"] = [
      "value" => $format,
      "cacheable" => true,
    ];
    $vars["mode"] = [
      "value" => $mode,
      "cacheable" => true,
    ];
    $vars["classtype"] = [
      "value" => $type,
      "cacheable" => true,
    ];
    if ($this->dataFileStatus == self::STATUS_DISABLED) {
      $vars["disabled"] = [
        "value" => "disabled",
        "cacheable" => true,
      ];
    }
    $vars["defaultcontent"] = [
      "value" => htmlspecialchars($this->showContent(false)),
      "cacheable" => true,
    ];
    $vars["resultcontent"] = [
      "value" => htmlspecialchars($this->showContent(true)),
      "cacheable" => true,
    ];
    $vars["status"] = [
      "value" => $this->dataFileStatuses[$this->dataFileStatus],
      "cacheable" => true,
    ];
    $vars["changestatus"] = [
      "value" => $this->dataFileStatus == self::STATUS_DISABLED ? _("enable") : _("disable"),
      "cacheable" => true,
    ];
    $vars["changestatusurl"] = [
      "value" => $this->dataFileStatus == self::STATUS_DISABLED ? "$adminLink&enable" : "$adminLink&disable",
      "cacheable" => true,
    ];
    $vars["userfilehash"] = [
      "value" => $usrDestHash,
      "cacheable" => true,
    ];
    if ((!$this->isPost() && $this->dataFileStatus == self::STATUS_DISABLED)) {
      $vars["checked"] = [
        "value" => "checked",
        "cacheable" => true,
      ];
    }
    if ($this->dataFileStatus == self::STATUS_NEW) {
      $vars["warning"] = [
        "value" => "warning",
        "cacheable" => true,
      ];
      $vars["nohide"] = [
        "value" => "nohide",
        "cacheable" => true,
      ];
    }
    $pagespeed = isset($_GET[PAGESPEED_PARAM]) && $_GET[PAGESPEED_PARAM] == PAGESPEED_OFF;
    $vars["pagespeed"] = [
      "value" => $pagespeed ? null : "",
      "cacheable" => true,
    ];
    $debug = isset($_GET[DEBUG_PARAM]) && $_GET[DEBUG_PARAM] == DEBUG_ON;
    $vars["debug"] = [
      "value" => $debug ? null : "",
      "cacheable" => true,
    ];
    $cache = isset($_GET[CACHE_PARAM]);
    $vars["cache"] = [
      "value" => $cache ? null : "",
      "cacheable" => true,
    ];
    $vars["cache_value"] = [
      "value" => $cache ? $_GET[CACHE_PARAM] : "",
      "cacheable" => true,
    ];
    $vars["filepicker_options"] = [
      "value" => $this->createFilepicker(),
      "cacheable" => true,
    ];
    $content->processVariables($vars);
    return $content;
  }

  /**
   * @param string $user
   * @return string|null
   * @throws Exception
   */
  private function showContent ($user) {
    if (is_null($this->defaultFile)) {
      return null;
    }
    try {
      $defaultFile = find_file($this->defaultFile, $user, true);
    } catch (Exception $exc) {
      return null;
    }
    if ($this->replace) {
      return file_get_contents($defaultFile);
    }
    $doc = XMLBuilder::build($this->defaultFile, $user);
    #$doc->removeNodes("//*[@readonly]");
    $doc->formatOutput = true;
    return $doc->saveXML();
  }

  /**
   * @return DOMElementPlus
   */
  private function createFilepicker () {
    $paths = [
      USER_FOLDER => "",
      CMS_FOLDER."/".THEMES_DIR => THEMES_DIR."/",
      PLUGINS_FOLDER => PLUGINS_DIR."/",
    ];
    $files = [];
    foreach ($paths as $path => $prefix) {
      $files = array_unique(array_merge($files, $this->getFilesRecursive($path, $prefix)));
    }
    $dom = new DOMDocumentPlus();
    $var = $dom->createElement("var");
    usort($files, "strnatcmp");
    foreach ($files as $file) {
      $option = $dom->createElement("option");
      $option->setAttribute("value", $file);
      $optionValue = basename($file)." $file";
      if (is_file(CMS_FOLDER."/$file")) {
        $optionValue .= " #default";
      }
      if (is_file(ADMIN_FOLDER."/$file")) {
        $optionValue .= " #admin";
      }
      if (is_file(USER_FOLDER."/".dirname($file)."/.".basename($file))) {
        $optionValue .= " #user #disabled";
      }
      else if (is_file(USER_FOLDER."/$file")) {
        $optionValue .= " #user";
      }
      $option->nodeValue = $optionValue;
      $var->appendChild($option);
    }
    return $var;
  }

  /**
   * @param string $folder
   * @param string $prefix
   * @return array
   */
  private function getFilesRecursive ($folder, $prefix = "") {
    $files = [];
    foreach (scandir($folder) as $file) {
      if ($file == "." || $file == "..") {
        continue;
      }
      if (is_dir($folder."/".$file)) {
        if (substr($file, 0, 1) == ".") {
          continue;
        }
        $files = array_merge($files, $this->getFilesRecursive($folder."/$file", $prefix."$file/"));
        continue;
      }
      if (!in_array(pathinfo($file, PATHINFO_EXTENSION), $this->allowedTypes)) {
        continue;
      }
      if (substr($file, 0, 1) == ".") {
        $file = substr($file, 1);
      }
      $files[$prefix.$file] = $prefix.$file;
    }
    return $files;
  }

}
