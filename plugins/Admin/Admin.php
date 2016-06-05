<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\ContentStrategyInterface;
use IGCMS\Core\TitleStrategyInterface;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMBuilder;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use Exception;
use SplObserver;
use SplSubject;

class Admin extends Plugin implements SplObserver, ContentStrategyInterface, TitleStrategyInterface {
  const STATUS_NEW = 0;
  const STATUS_ENABLED = 1;
  const STATUS_DISABLED = 2;
  const STATUS_INVALID = 3;
  const STATUS_UNKNOWN = 4;
  const FILE_DISABLE = "disable";
  const FILE_ENABLE = "enable";
  private $content = null;
  private $contentValue = null;
  private $scheme = null;
  private $type = "txt";
  private $title = null;
  private $redir = false;
  private $replace = true;
  private $error = false;
  private $dataFile = null;
  private $destFile = null;
  private $dataFileDisabled = null;
  private $dataFileStatus;
  private $defaultFile = null;
  private $statusChanged = false;
  private $dataFileStatuses;
  private $contentChanged = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 5);
    $this->dataFileStatuses = array(_("new file"), _("active file"),
      _("inactive file"), _("invalid file"), _("unknown status"));
    $this->dataFileStatus = self::STATUS_UNKNOWN;
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_PROCESS) {
      $os = Cms::getOutputStrategy()->addTransformation($this->pluginDir."/Admin.xsl");
      $this->checkCache();
      return;
    }
    if($subject->getStatus() != STATUS_INIT) return;
    if(!isset($_GET[$this->className])) {
      $subject->detach($this);
      return;
    }
    $this->requireActiveCms();
    try {
      $this->process();
    } catch (Exception $e) {
      Logger::user_error($e->getMessage());
      return;
    }
    if(!$this->isPost()) return;
    if(!$this->redir) return;
    $pLink["path"] = getCurLink();
    if(!isset($_POST["saveandgo"])) $pLink["query"] = $this->className."=".$_POST["filename"];
    redirTo(buildLocalUrl($pLink, true));
  }

  public function getTitle() {
    return $this->title;
  }

  private function process() {
    $this->setDefaultFile();
    $this->setDataFiles();
    if($this->isPost()) {
      if($_POST["userfilehash"] != $this->getDataFileHash())
        throw new Exception(sprintf(_("User file '%s' changed during administration"), $this->defaultFile));
      $this->processPost();
    } else {
      $this->setContent();
    }
    if($this->isPost() && !Cms::isSuperUser()) throw new Exception(_("Insufficient right to save changes"));
    if(!$this->isResource($this->type)) $this->processXml();
    if($this->isToEnable()) $this->enableDataFile();
    if($this->isPost()) {
      $this->destFile = USER_FOLDER."/".$this->getFilepath($_POST["filename"]);
      if($this->contentChanged || $this->dataFile != $this->destFile) {
        $this->savePost();
        $this->updateCache();
      } elseif(!$this->isToDisable() && !$this->statusChanged) {
        Logger::user_notice(_("No changes made"));
      }
    }
    if($this->isToDisable()) $this->disableDataFile();
    if($this->statusChanged) {
      $this->redir = true;
      Logger::user_success(_("File status successfully changed"));
    }
  }

  private function checkCache() {
    if(!$this->isResource($this->type)) {
      if(DOMBuilder::isCacheOutdated()) {
        Cms::notice(_("Saving changes will clear server cache"));
      }
      return;
    }
    if(getRealResDir() == RESOURCES_DIR
      && is_file($this->defaultFile)
      && isUptodate($this->dataFile, $this->defaultFile)) return;
    if(is_file(getRealResDir($this->defaultFile))
      && isUptodate($this->dataFile, getRealResDir($this->defaultFile))) return;
    Cms::notice(_("Saving changes will remove outdated file cache"));
  }

  private function updateCache() {
    if($this->isResource($this->type)) {
      $resFile = getRealResDir($this->defaultFile);
      if(is_file($resFile)) unlink($resFile);
      if(getRealResDir() != RESOURCES_DIR) return;
      if(is_file($this->defaultFile)) unlink($this->defaultFile);
      return;
    }
    #if(isset($_GET[DEBUG_PARAM]) && $_GET[DEBUG_PARAM] == DEBUG_ON) return;
    try {
      clearNginxCache();
    } catch(Exception $e) {
      Logger::critical($e->getMessage());
    }
  }

  private function isPost() {
    return isset($_POST["content"], $_POST["userfilehash"], $_POST["filename"]);
  }

  private function getFilesRecursive($folder, $prefix = "") {
    $files = array();
    foreach(scandir($folder) as $f) {
      if($f == "." || $f == "..") continue;
      if(is_dir($folder."/".$f)) {
        if(substr($f, 0, 1) == ".") continue;
        $files = array_merge($files, $this->getFilesRecursive($folder."/$f", $prefix."$f/"));
        continue;
      }
      if(!in_array(pathinfo($f, PATHINFO_EXTENSION), array("html", "xml", "xsl", "js", "css"))) continue;
      if(substr($f, 0, 1) == ".") $f = substr($f, 1);
      $files[$prefix.$f] = $prefix.$f;
    }
    return $files;
  }

  private function createFilepicker() {
    $paths = array(
      USER_FOLDER => "",
      CMS_FOLDER."/".THEMES_DIR => THEMES_DIR."/",
      PLUGINS_FOLDER => PLUGINS_DIR."/",
    );
    $files= array();
    foreach ($paths as $path => $prefix) {
      $files = array_unique(array_merge($files, $this->getFilesRecursive($path, $prefix)));
    }
    $dom = new DOMDocumentPlus();
    $var = $dom->createElement("var");
    usort($files, "strnatcmp");
    foreach($files as $f) {
      $option = $dom->createElement("option");
      $option->setAttribute("value", $f);
      $v = basename($f)." $f";
      if(is_file(CMS_FOLDER."/$f")) $v .= " #default";
      if(is_file(ADMIN_FOLDER."/$f")) $v .= " #admin";
      if(is_file(USER_FOLDER."/$f")) $v .= " #user";
      if(is_file(USER_FOLDER."/".dirname($f)."/.".basename($f))) $v .= " #user #disabled";
      $option->nodeValue = $v;
      $var->appendChild($option);
    }
    return $var;
  }

  public function getContent(HTMLPlus $content) {
    Cms::getOutputStrategy()->addJsFile($this->pluginDir.'/Admin.js', 100, "body");
    Cms::getOutputStrategy()->addJs("
      if(typeof IGCMS === \"undefined\") throw \"IGCMS is not defined\";
      IGCMS.Admin.init({
        saveInactive: '"._("Data file is inactive. Save anyways?")."'
      });
      ", 100, "body");
    $format = $this->type;
    if($this->type == "html") $format = "html+";
    if(!is_null($this->scheme)) $format .= " (".pathinfo($this->scheme, PATHINFO_BASENAME).")";

    $newContent = $this->getHTMLPlus();

    $la = "?".$this->className."=".$_GET[$this->className];
    $statusChanged = self::FILE_DISABLE;
    if($this->dataFileStatus == self::STATUS_DISABLED) {
      $vars["warning"] = "warning";
      $statusChanged = self::FILE_ENABLE;
    }
    $usrDestHash = $this->getDataFileHash();
    $mode = $this->replace ? _("replace") : _("modify");
    switch($this->type) {
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
    $vars["heading"] = _("Administration");
    if(!is_null($this->defaultFile))
      $vars["heading"] = sprintf(_("File %s Administration"), basename($this->defaultFile));
    $vars["link"] = getCurLink();
    $vars["linkadmin"] = $la;
    if($this->contentValue !== "" ) $vars["content"] = $this->contentValue;
    $vars["filename"] = $_GET[$this->className];
    $vars["filepathpattern"] = FILEPATH_PATTERN;
    $vars["schema"] = $format;
    $vars["mode"] = $mode;
    $vars["classtype"] = $type;
    if($this->dataFileStatus == self::STATUS_DISABLED)
      $vars["disabled"] = "disabled";
    $vars["defaultcontent"] = $this->showContent(false);
    $vars["resultcontent"] = $this->showContent(true);
    $vars["status"] = $this->dataFileStatuses[$this->dataFileStatus];
    $vars["userfilehash"] = $usrDestHash;
    if((!$this->isPost() && $this->dataFileStatus == self::STATUS_DISABLED))
      $vars["checked"] = "checked";
    if($this->dataFileStatus == self::STATUS_NEW) {
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
    $newContent->processVariables($vars);
    if(is_null($this->defaultFile)) $this->title = $vars["heading"];
    else $this->title = sprintf(_("%s (%s) - Administration"),
      basename($this->defaultFile), ROOT_URL.$this->defaultFile);
    return $newContent;
  }

  private function getDataFileHash() {
    if(is_file($this->dataFile)) return getFileHash($this->dataFile);
    return getFileHash($this->dataFileDisabled);
  }

  private function showContent($user) {
    if(is_null($this->defaultFile)) return null;
    try {
      $df = findFile($this->defaultFile, $user, true);
    } catch(Exception $e) {
      return null;
    }
    if($this->replace) return file_get_contents($df);
    $doc = new DOMDocumentPlus();
    $doc->load(($user ? USER_FOLDER : CMS_FOLDER)."/".$this->defaultFile);
    $doc->removeNodes("//*[@readonly]");
    $doc->formatOutput = true;
    return $doc->saveXML();
  }

  private function getHash($data) {
    return hash(FILE_HASH_ALGO, $data);
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
  private function setDefaultFile() {
    $fileName = $_GET[$this->className];
    $this->defaultFile = $this->getFilepath($fileName);
    $fLink = HTMLPlusBuilder::getIdToLink(HTMLPlusBuilder::getFileToId($this->defaultFile));
    if(is_null($fLink)) $fLink = getCurLink();
    if($this->defaultFile != $fileName || $fLink != getCurLink()) {
      redirTo(buildLocalUrl(array("path" => $fLink, "query" => $this->className."=".$this->defaultFile)));
    }
    $this->type = pathinfo($this->defaultFile, PATHINFO_EXTENSION);
  }

  private function getFilepath($f) {
    if(!strlen($f)) {
      if(getCurLink() == "") return INDEX_HTML;
      $path = HTMLPlusBuilder::getIdToFile(HTMLPlusBuilder::getLinkToId(getCurLink()));
      if(!is_null($path)) return $path;
      return INDEX_HTML;
    }
    $f = stripDataFolder($f);
    if(preg_match("~^[\w-]+$~", $f)) {
      $pluginFile = PLUGINS_DIR."/$f/$f.xml";
      if(is_file(CMS_FOLDER."/$pluginFile")) return $pluginFile;
    }
    if(!preg_match("/^".FILEPATH_PATTERN."$/", $f) || strpos($f, "..") !== false) {
      $this->dataFileStatus = self::STATUS_INVALID;
      throw new Exception(sprintf(_("Unsupported file name format '%s'"), $f));
    }
    if(strpos(basename($f), ".") === 0) {
      $f = dirname($f)."/".substr(basename($f), 1);
    }
    if(is_file(CMS_FOLDER."/".PLUGINS_DIR."/$f")) $f = PLUGINS_DIR."/$f";
    return $f;
  }

  private function setDataFiles() {
    $this->dataFile = USER_FOLDER."/".$this->defaultFile;
    $this->dataFileDisabled = dirname($this->dataFile)."/.".basename($this->dataFile);
    // disabled if.file or both files exist, else new
    $this->dataFileStatus = self::STATUS_NEW;
    if(file_exists($this->dataFile)) $this->dataFileStatus = self::STATUS_ENABLED;
    if(file_exists($this->dataFileDisabled)) $this->dataFileStatus = self::STATUS_DISABLED;
    #if(!file_exists($this->dataFile) && file_exists($this->dataFileDisabled)) $this->dataFile = $this->dataFileDisabled;
  }

  private function isToDisable() {
    if(!is_file($this->dataFile)) return false;
    if(isset($_GET[self::FILE_DISABLE])) return true;
    if(count($_POST) && isset($_POST["disable"])) return true;
    return false;
  }

  private function isToEnable() {
    if(!is_file($this->dataFileDisabled)) return false;
    if(isset($_GET[self::FILE_ENABLE])) return true;
    if(count($_POST) && !isset($_POST["disable"])) return true;
    return false;
  }

  private function isResource($type) {
    return !in_array($type, array("xml", "xsl", "html"));
  }

  private function processXml() {
    // get default schema
    try {
      $df = findFile($this->defaultFile, false);
      $this->scheme = $this->getScheme($df);
    } catch(Exception $e) {
      $df = false;
    }
    // get user schema if default schema not exists
    if(is_null($this->scheme) && file_exists($this->dataFile)) {
      $this->scheme = $this->getScheme($this->dataFile);
    }
    if(!$this->isPost() && $this->dataFileStatus == self::STATUS_NEW) {
      if($this->type == "html") {
        $doc = new HTMLPlus();
        $doc->load(CMS_FOLDER."/".INDEX_HTML);
      } else {
        $doc = new DOMDocumentPlus();
        $doc->formatOutput = true;
        if($this->type == "xsl") $rootName = "xslt";
        else $rootName = pathinfo($this->defaultFile, PATHINFO_FILENAME);
        $root = $doc->appendChild($doc->createElement($rootName));
        $root->appendChild($doc->createComment(" "._("user content")." "));
      }
      $this->contentValue = $doc->saveXML();
    } else {
      if($this->type == "html") {
        $doc = new HTMLPlus();
        $doc->defaultAuthor = Cms::getVariable("cms-author");
      } else $doc = new DOMDocumentPlus();
      $doc->loadXml($this->contentValue);
    }
    $repair = $this->isPost() && isset($_POST["repair"]);
    try {
      if($this->type == "html") $doc->validatePlus();
    } catch(Exception $e) {
      $doc->validatePlus(true);
      foreach($doc->getErrors() as $error) {
        Logger::user_notice("$error".($repair ? " (".$doc->getStatus().")" : ""));
      }
      if(!$repair) throw new Exception(_("Repairable error(s) occured"));
      $this->contentValue = $doc->saveXML();
    }
    if($this->type != "xml" || $this->isPost()) return;
    $this->replace = false;
    if($df && $doc->removeNodes("//*[@readonly]"))
      $this->contentValue = $doc->saveXML();
    $this->validateXml($doc);
  }

  private function processPost() {
    $post_n = str_replace("\r\n", "\n", $_POST["content"]);
    $post_rn = str_replace("\n", "\r\n", $post_n);
    $this->contentValue = $post_n;
    #if((isset($_POST["active"]) && $this->dataFileStatus == self::STATUS_DISABLED)
    #  || (!isset($_POST["active"]) && $this->dataFileStatus == self::STATUS_ENABLED)) {
    #  $this->dataFile = $this->changeStatus($this->dataFile);
    #  $this->statusChanged = true;
    #}
    if(!in_array($_POST["userfilehash"], array($this->getHash($post_n), $this->getHash($post_rn)))) {
      $this->contentChanged = true;
    }
  }

  private function setContent() {
    $f = $this->dataFile;
    if(!file_exists($f)) {
      $f = $this->dataFileDisabled;
      if(!file_exists($f)) return;
    }
    if(($this->contentValue = file_get_contents($f)) === false)
      throw new Exception(sprintf(_("Unable to get contents from '%s'"), $this->dataFile));
  }

  private function savePost() {
    mkdir_plus(dirname($this->destFile));
    $fp = lock_file($this->destFile);
    try {
      try {
        $destFileDisabled = dirname($this->destFile)."/.".basename($this->destFile);
        if($this->destFile != $this->dataFile && !isset($_POST["overwrite"])
          && (is_file($this->destFile) || is_file($destFileDisabled))) {
          throw new Exception(_("Destination file already exists"));
        }
        if(is_file($destFileDisabled)) if(!unlink($destFileDisabled)) throw new Exception(_("Unable to enable destination file"));
        file_put_contents_plus($this->destFile, $this->contentValue);
      } catch(Exception $e) {
        throw new Exception(sprintf(_("Unable to save changes to %s: %s"), $_POST["filename"], $e->getMessage()));
      }
      try {
        if(is_file($this->destFile.".old"))
          incrementalRename($this->destFile.".old", $this->destFile.".");
      } catch(Exception $e) {
        throw new Exception(sprintf(_("Unable to backup %s: %s"), $_POST["filename"], $e->getMessage()));
      }
      $this->redir = true;
      Logger::user_success(_("Changes successfully saved"));
    } finally {
      unlock_file($fp, $this->destFile);
    }
  }

  private function enableDataFile() {
    if(is_file($this->dataFile)) $status = unlink($this->dataFileDisabled);
    else $status = rename($this->dataFileDisabled, $this->dataFile);
    if(!$status) throw new Exception(_("Unable to enable file"));
    $this->statusChanged = true;
  }

  private function disableDataFile() {
    if(!rename($this->dataFile, $this->dataFileDisabled))
      throw new Exception(_("Unable to disable file"));
    $this->statusChanged = true;
  }

  private function validateXml(DOMDocumentPlus $doc) {
    if(is_null($this->scheme)) return;
    switch(pathinfo($this->scheme, PATHINFO_EXTENSION)) {
      case "rng":
      $doc->relaxNGValidatePlus($this->scheme);
      break;
      default:
      throw new Exception(sprintf(_("Unsupported schema '%s'"), $this->scheme));
    }
  }

  private function getScheme($f) {
    $h = fopen($f, "r");
    fgets($h); // skip first line
    $line = str_replace("'", '"', fgets($h));
    fclose($h);
    if(!preg_match('<\?xml-model href="([^"]+)" ?\?>', $line, $m)) return;
    try {
      $schema = findFile($m[1], false, false);
    } catch(Exception $e) {
      throw new Exception(sprintf(_("Schema file '%s' not found"), $schema));
    }
    return $schema;
  }

}



?>
