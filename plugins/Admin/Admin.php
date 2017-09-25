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

  private function main () {
    $this->requireActiveCms();
    try {
      $this->process();
    } catch (Exception $e) {
      Logger::user_error($this->className.": ".$e->getMessage());
      return;
    }
    if (!$this->isPost()) {
      return;
    }
    if (!$this->redir) {
      return;
    }
    $pLink["path"] = getCurLink();
    if (!isset($_POST["saveandgo"])) {
      $pLink["query"] = $this->className."=".$_POST["filename"];
    }
    redirTo(buildLocalUrl($pLink, true));
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
   */
  private function setDefaultFile () {
    $fileName = $_GET[$this->className];
    $this->defaultFile = $this->getFilepath($fileName);
    $fLink = HTMLPlusBuilder::getIdToLink(HTMLPlusBuilder::getFileToId($this->defaultFile));
    if (is_null($fLink)) {
      $fLink = getCurLink();
    }
    if ($this->defaultFile != $fileName || $fLink != getCurLink()) {
      redirTo(buildLocalUrl(["path" => $fLink, "query" => $this->className."=".$this->defaultFile]));
    }
    $this->type = pathinfo($this->defaultFile, PATHINFO_EXTENSION);
    if (!in_array($this->type, $this->allowedTypes)) {
      $this->defaultFile = "";
      throw new Exception(sprintf(_("File type '%s' is not allowed"), $this->type));
    }
  }

  /**
   * @param string $f
   * @return string
   * @throws Exception
   */
  private function getFilepath ($f) {
    if (!strlen($f)) {
      if (getCurLink() == "") {
        return INDEX_HTML;
      }
      $path = HTMLPlusBuilder::getIdToFile(HTMLPlusBuilder::getLinkToId(getCurLink()));
      if (!is_null($path)) {
        return $path;
      }
      return INDEX_HTML;
    }
    $f = stripDataFolder($f);
    if (preg_match('~^[\w-]+$~', $f)) {
      $pluginFile = PLUGINS_DIR."/$f/$f.xml";
      if (is_file(CMS_FOLDER."/$pluginFile")) {
        return $pluginFile;
      }
    }
    if (!preg_match("/^".FILEPATH_PATTERN."$/", $f) || strpos($f, "..") !== false) {
      $this->dataFileStatus = self::STATUS_INVALID;
      throw new Exception(sprintf(_("Unsupported file name format '%s'"), $f));
    }
    if (strpos(basename($f), ".") === 0) {
      $f = dirname($f)."/".substr(basename($f), 1);
    }
    if (is_file(CMS_FOLDER."/".PLUGINS_DIR."/$f")) {
      $f = PLUGINS_DIR."/$f";
    }
    return $f;
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
    } catch (Exception $e) {
      Logger::user_warning($e->getMessage());
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
      return getFileHash($this->dataFile);
    }
    return getFileHash($this->dataFileDisabled);
  }

  /**
   * @throws Exception
   */
  private function setContent () {
    $f = $this->dataFile;
    if (!stream_resolve_include_path($f)) {
      $f = $this->dataFileDisabled;
      if (!stream_resolve_include_path($f)) {
        return;
      }
    }
    if (($this->contentValue = file_get_contents($f)) === false) {
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
      $df = findFile($this->defaultFile, false);
      $this->scheme = $this->getScheme($df);
    } catch (Exception $e) {
      $df = false;
    }
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
        $doc->defaultAuthor = Cms::getVariable("cms-author");
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
    } catch (Exception $e) {
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
    if ($df && $doc->removeNodes("//*[@readonly]")) {
      $this->contentValue = $doc->saveXML();
    }
    $this->validateXml($doc);
  }

  /**
   * @param string $f
   * @return string|null
   * @throws Exception
   */
  private function getScheme ($f) {
    $h = fopen($f, "r");
    fgets($h); // skip first line
    $line = str_replace("'", '"', fgets($h));
    fclose($h);
    $scheme = null;
    if (!preg_match('<\?xml-model href="([^"]+)" ?\?>', $line, $m)) {
      return $scheme;
    }
    try {
      $scheme = findFile($m[1], false, false);
    } catch (Exception $e) {
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
    $fp = lock_file($this->destFile);
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
        file_put_contents_plus($this->destFile, $this->contentValue);
      } catch (Exception $e) {
        throw new Exception(sprintf(_("Unable to save changes to %s: %s"), $_POST["filename"], $e->getMessage()));
      }
      try {
        if (is_file($this->destFile.".old")) {
          incrementalRename($this->destFile.".old", $this->destFile.".");
        }
      } catch (Exception $e) {
        throw new Exception(sprintf(_("Unable to backup %s: %s"), $_POST["filename"], $e->getMessage()));
      }
      $this->redir = true;
      Logger::user_success(_("Changes successfully saved"));
    } finally {
      unlock_file($fp, $this->destFile);
    }
  }

  private function clearResCache () {
    if ($this->isResource($this->type)) {
      $resFile = getRealResDir($this->defaultFile);
      if (is_file($resFile)) {
        unlink($resFile);
      }
      if (getRealResDir() != RESOURCES_DIR) {
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
      clearNginxCache();
    } catch (Exception $e) {
      Logger::critical($e->getMessage());
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
    if (!is_file(getRealResDir($this->defaultFile))
      || isUptodate($this->dataFile, getRealResDir($this->defaultFile))
    ) {
      if (getRealResDir() != RESOURCES_DIR) {
        return;
      }
      if (!is_file($this->defaultFile)
        || isUptodate($this->dataFile, $this->defaultFile)
      ) {
        return;
      }
    }
    Cms::notice(_("Saving changes will remove outdated file cache"));
  }

  private function createVarList () {
    $doc = new DOMDocumentPlus();
    $varListVar = $doc->createElement("var");
    $varlist = $doc->createElement("dl");
    $varListVar->appendChild($varlist);
    foreach (Cms::getAllVariables() as $name => $value) {
      $varlist->appendChild($doc->createElement("dt", "$name"));
      $dd = $varlist->appendChild($doc->createElement("dd"));
      switch (gettype($value)) {
        case "NULL":
          $dd->nodeValue = _("null value");
          continue 2;
        case "array":
          $value = implode(", ", $value);
        case "string":
          if (!strlen($value)) {
            $dd->nodeValue = _("empty value");
            continue 2;
          }
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
          $dd->nodeValue = get_class($value);
          continue 2;
        default:
          $dd->nodeValue = gettype($value);
          continue 2;
      }
      $dd->appendChild($doc->createElement("samp", getShortString($value)));
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
   */
  public function getContent () {
    Cms::getOutputStrategy()->addJsFile($this->pluginDir.'/Admin.js', 100, "body");
    Cms::getOutputStrategy()->addJs(
      "
      if(typeof IGCMS === \"undefined\") throw \"IGCMS is not defined\";
      IGCMS.Admin.init({
        saveInactive: '"._("Data file is inactive. Save anyways?")."'
      });
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

    $content = $this->getHTMLPlus();

    $la = "?".$this->className."=".$_GET[$this->className];
    if ($this->dataFileStatus == self::STATUS_DISABLED) {
      $vars["warning"] = "warning";
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

    #$d = new DOMDocumentPlus();
    #$v = $d->appendChild($d->createElement("var"));
    #$v->appendChild($d->importNode($content->getElementsByTagName("h")->item(0), true));
    #$vars["heading"] = $d->documentElement;
    $vars["rooturl"] = HTMLPlusBuilder::getFileToId(INDEX_HTML);
    $vars["heading"] = _("Administration");
    if (strlen($this->defaultFile)) {
      $vars["heading"] = sprintf(_("File %s Administration"), basename($this->defaultFile));
      $this->title = sprintf(_("%s (%s) - Administration"), basename($this->defaultFile), ROOT_URL.$this->defaultFile);
    } else {
      $this->title = $vars["heading"];
    }
    $vars["link"] = getCurLink();
    $vars["linkadmin"] = $la;
    if ($this->contentValue !== "") {
      $vars["content"] = htmlspecialchars($this->contentValue);
    }
    $vars["filename"] = $_GET[$this->className];
    $vars["filepathpattern"] = FILEPATH_PATTERN;
    $vars["schema"] = $format;
    $vars["mode"] = $mode;
    $vars["classtype"] = $type;
    if ($this->dataFileStatus == self::STATUS_DISABLED) {
      $vars["disabled"] = "disabled";
    }
    $vars["defaultcontent"] = htmlspecialchars($this->showContent(false));
    $vars["resultcontent"] = htmlspecialchars($this->showContent(true));
    $vars["status"] = $this->dataFileStatuses[$this->dataFileStatus];
    $vars["changestatus"] = $this->dataFileStatus == self::STATUS_DISABLED ? _("enable") : _("disable");
    $vars["changestatusurl"] = $this->dataFileStatus == self::STATUS_DISABLED ? "$la&enable" : "$la&disable";
    $vars["userfilehash"] = $usrDestHash;
    if ((!$this->isPost() && $this->dataFileStatus == self::STATUS_DISABLED)) {
      $vars["checked"] = "checked";
    }
    if ($this->dataFileStatus == self::STATUS_NEW) {
      $vars["warning"] = "warning";
      $vars["nohide"] = "nohide";
    }
    $ps = isset($_GET[PAGESPEED_PARAM]) && $_GET[PAGESPEED_PARAM] == PAGESPEED_OFF;
    $vars["pagespeed"] = $ps ? null : "";
    $debug = isset($_GET[DEBUG_PARAM]) && $_GET[DEBUG_PARAM] == DEBUG_ON;
    $vars["debug"] = $debug ? null : "";
    $cache = isset($_GET[CACHE_PARAM]);
    $vars["cache"] = $cache ? null : "";
    $vars["cache_value"] = $cache ? $_GET[CACHE_PARAM] : "";
    $vars["filepicker_options"] = $this->createFilepicker();
    $content->processVariables($vars);
    return $content;
  }

  /**
   * @param string $user
   * @return string|null
   */
  private function showContent ($user) {
    if (is_null($this->defaultFile)) {
      return null;
    }
    try {
      $df = findFile($this->defaultFile, $user, true);
    } catch (Exception $e) {
      return null;
    }
    if ($this->replace) {
      return file_get_contents($df);
    }
    $doc = XMLBuilder::build($this->defaultFile, $user);
    $doc->removeNodes("//*[@readonly]");
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
    foreach ($files as $f) {
      $option = $dom->createElement("option");
      $option->setAttribute("value", $f);
      $v = basename($f)." $f";
      if (is_file(CMS_FOLDER."/$f")) {
        $v .= " #default";
      }
      if (is_file(ADMIN_FOLDER."/$f")) {
        $v .= " #admin";
      }
      if (is_file(USER_FOLDER."/".dirname($f)."/.".basename($f))) {
        $v .= " #user #disabled";
      }
      else if (is_file(USER_FOLDER."/$f")) {
        $v .= " #user";
      }
      $option->nodeValue = $v;
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
    foreach (scandir($folder) as $f) {
      if ($f == "." || $f == "..") {
        continue;
      }
      if (is_dir($folder."/".$f)) {
        if (substr($f, 0, 1) == ".") {
          continue;
        }
        $files = array_merge($files, $this->getFilesRecursive($folder."/$f", $prefix."$f/"));
        continue;
      }
      if (!in_array(pathinfo($f, PATHINFO_EXTENSION), $this->allowedTypes)) {
        continue;
      }
      if (substr($f, 0, 1) == ".") {
        $f = substr($f, 1);
      }
      $files[$prefix.$f] = $prefix.$f;
    }
    return $files;
  }

}

?>
