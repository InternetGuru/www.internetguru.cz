<?php

class Admin extends Plugin implements SplObserver, ContentStrategyInterface {
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
      return;
    }
    if($subject->getStatus() != STATUS_INIT) return;
    if(!isset($_GET[get_class($this)])) {
      $subject->detach($this);
      return;
    }
    try {
      $this->setDefaultFile();
      $this->setDataFiles();
      if($this->isPost()) {
        if($_POST["userfilehash"] != $this->getDataFileHash())
          throw new Exception(sprintf(_("User file '%s' changed during administration"), $this->defaultFile));
        $this->processPost();
      } else {
        $this->setContent();
      }
      if($this->isResource($this->type)) {
        try {
          if(getRealResDir() == RESOURCES_DIR) checkFileCache($this->dataFile, $this->defaultFile); // check /file
          checkFileCache($this->dataFile, getRealResDir($this->defaultFile)); // always check [resdir]/file
        } catch(Exception $e) {
          Cms::addMessage(_("Saving changes will update edited file cache"), Cms::MSG_INFO); // "deleted"
        }
      } else {
        $newestCacheMtime = getNewestCacheMtime();
        if(!is_null($newestCacheMtime) && DOMBuilder::getNewestFileMtime() > $newestCacheMtime) {
          Cms::addMessage(_("Saving changes will update server cache"), Cms::MSG_INFO); // "delete"
        }
        $this->processXml();
      }
      if($this->isPost() && !Cms::isSuperUser()) throw new Exception(_("Insufficient right to save changes"));
      if($this->isToEnable()) $this->enableDataFile();
      if($this->isPost()) {
        $this->destFile = USER_FOLDER."/".$this->getFilepath($_POST["filename"]);
        if($this->contentChanged || $this->dataFile != $this->destFile) {
          $this->savePost();
        } elseif(!$this->isToDisable() && !$this->statusChanged) {
          Cms::addMessage(_("No changes made"), Cms::MSG_INFO);
        }
      }
      if($this->isToDisable()) $this->disableDataFile();
      if($this->statusChanged) {
        $this->redir = true;
        Cms::addMessage(_("File status successfully changed"), Cms::MSG_SUCCESS);
      }
    } catch (Exception $e) {
      Cms::addMessage($e->getMessage(), Cms::MSG_ERROR);
      return;
    }
    if(!$this->isPost()) return;
    $this->updateCache();
    if(!$this->redir) return;
    $pLink["path"] = getCurLink();
    if(!isset($_POST["saveandgo"])) $pLink["query"] = get_class($this)."=".$_POST["filename"];
    redirTo(buildLocalUrl($pLink, true));
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
      Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
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

    $la = "?".get_class($this)."=".$_GET[get_class($this)];
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
    $vars["filename"] = $_GET[get_class($this)];
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
    if(is_null($this->defaultFile)) Cms::setVariable("title", $vars["heading"]);
    else Cms::setVariable("title", sprintf(_("%s (%s) - Administration"),
      basename($this->defaultFile), ROOT_URL.$this->defaultFile));
    return $newContent;
  }

  private function getDataFileHash() {
    if(is_file($this->dataFile)) return getFileHash($this->dataFile);
    return getFileHash($this->dataFileDisabled);
  }

  private function showContent($user) {
    if(is_null($this->defaultFile)) return null;
    $df = findFile($this->defaultFile, $user, true, false);
    if(is_null($df)) return null;
    if($this->replace) return file_get_contents($df);
    $doc = $this->getDOMPlus($this->defaultFile, false, $user);
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
    $fileName = $_GET[get_class($this)];
    $this->defaultFile = $this->getFilepath($fileName);
    $fLink = DOMBuilder::getLink(findFile($fileName));
    if(is_null($fLink)) $fLink = getCurLink();
    if($this->defaultFile != $fileName || $fLink != getCurLink()) {
      redirTo(buildLocalUrl(array("path" => $fLink, "query" => get_class($this)."=".$this->defaultFile)));
    }
    $this->type = pathinfo($this->defaultFile, PATHINFO_EXTENSION);
  }

  private function getFilepath($f) {
    if(!strlen($f)) {
      if(getCurLink() == "") return INDEX_HTML;
      $path = DOMBuilder::getFile(getCurLink());
      if(!is_null($path)) return $path;
      return INDEX_HTML;
    }
    if(strpos($f, USER_FOLDER."/") === 0) {
      $f = substr($f, strlen(USER_FOLDER)+1);
    }
    if(strpos($f, ADMIN_FOLDER."/") === 0) {
      $f = substr($f, strlen(ADMIN_FOLDER)+1);
    }
    if(preg_match("~^[\w-]+$~", $f)) {
      $pluginFile = PLUGINS_DIR."/$f/$f.xml";
      if(!is_null(findFile($pluginFile))) return $pluginFile;
    }
    if(!preg_match("/^".FILEPATH_PATTERN."$/", $f) || strpos($f, "..") !== false) {
      $this->dataFileStatus = self::STATUS_INVALID;
      throw new Exception(sprintf(_("Unsupported file name format '%s'"), $f));
    }
    if(strpos(basename($f), ".") === 0) {
      $f = dirname($f)."/".substr(basename($f), 1);
    }
    if(!is_null(findFile(PLUGINS_DIR."/$f", false))) {
      $f = PLUGINS_DIR."/$f";
    }
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
    $df = findFile($this->defaultFile, false);
    if(!is_null($df)) $this->scheme = $this->getScheme($df);
    // get user schema if default schema not exists
    if(is_null($this->scheme) && file_exists($this->dataFile)) {
      $this->scheme = $this->getScheme($this->dataFile);
    }
    if($this->type == "html") {
      $doc = new HTMLPlus();
      $doc->defaultLink = normalize(pathinfo($this->defaultFile, PATHINFO_FILENAME), "a-zA-Z0-9/_-");
      $doc->defaultAuthor = Cms::getVariable("cms-author");
    } else $doc = new DOMDocumentPlus();
    $doc->formatOutput = true;
    if(!$this->isPost() && $this->dataFileStatus == self::STATUS_NEW) {
      $rootName = "body";
      if($this->type != "html") {
        $rootName = pathinfo($this->defaultFile, PATHINFO_FILENAME);
        $root = $doc->appendChild($doc->createElement($rootName));
        $root->appendChild($doc->createComment(" "._("user content")." "));
      } else {
        $doc->appendChild($doc->createElement("body"));
        $doc->defaultHeading = _("My Heading");
        $doc->defaultDesc = _("My Content Description");
        $doc->defaultKw = _("my, comma, separated, keywords");
      }
      $this->contentValue = $doc->saveXML();
    } elseif(!@$doc->loadXml($this->contentValue)) {
      throw new Exception(_("Invalid XML syntax"));
    }
    try {
      if($this->type == "html") $doc->validatePlus();
    } catch(Exception $e) {
      $doc->validatePlus(true);
      foreach($doc->getErrors() as $error) {
        Cms::addMessage($error, $doc->getStatus());
      }
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
    $fp = lockFile($this->destFile);
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
      Cms::addMessage(_("Changes successfully saved"), Cms::MSG_SUCCESS);
    } catch(Exception $e) {
      throw $e;
    } finally {
      unlockFile($fp, $this->destFile);
    }
  }

  private function enableDataFile() {
    if(is_file($this->dataFile)) $status = unlink($this->dataFileDisabled);
    else $status = rename($this->dataFileDisabled, $this->dataFile);
    if(!$status) throw new Excepiton(_("Unable to enable file"));
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
    $schema = findFile($m[1], false, false);
    if(!file_exists($schema))
      throw new Exception(sprintf(_("Schema file '%s' not found"), $schema));
    return $schema;
  }

}



?>
