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
    if($subject->getStatus() == STATUS_POSTPROCESS) {
      $this->createFilepicker();
      if(!IS_LOCALHOST) $this->checkCacheAge();
    }
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
      $fileName = $_GET[get_class($this)];
      if($this->isPost()) $fileName = $_POST["filename"];
      $this->setDefaultFile($fileName);
      $this->setDataFiles();
      if($this->isPost()) {
        if($this->dataFile == $fileName && $_POST["userfilehash"] != getFileHash($this->dataFile))
          throw new Exception(sprintf(_("User file '%s' changed during administration"), $this->defaultFile));
        $this->processPost();
        $_SESSION["clearcache"] = isset($_POST["clearcache"]) ? true : false;
      } else $this->setContent();

      if(!$this->isResource($this->type)) $this->processXml();
      if($this->isPost() && !Cms::isSuperUser()) throw new Exception(_("Insufficient right to save changes"));
      if($this->isToEnable()) $this->enableDataFile();
      if($this->isPost() && ($this->contentChanged || $_GET[get_class($this)] != $fileName)) {
        $this->savePost($this->dataFile);
      } elseif(!$this->isToDisable() && !$this->isToEnable() && $this->isPost()) {
        throw new Exception(_("No changes made"), 1);
      }
      if($this->isToDisable()) $this->disableDataFile();
      if(!$this->contentChanged && $this->statusChanged) {
        $this->redir = true;
        Cms::addMessage(_("File status successfully changed"), Cms::MSG_SUCCESS);
      }
    } catch (Exception $e) {
      if($e->getCode() === 1) $type = Cms::MSG_INFO;
      else $type = Cms::MSG_ERROR;
      Cms::addMessage($e->getMessage(), $type);
      return;
    }
    if(!$this->isPost()) return;
    if($this->isResource($this->type)) {
      if(is_file(RESOURCES_DIR."/".$this->defaultFile)) unlink(RESOURCES_DIR."/".$this->defaultFile);
      if(is_file($this->defaultFile)) unlink($this->defaultFile);
    } else {
      try {
        if(!IS_LOCALHOST && isset($_POST["clearcache"])) clearNginxCache();
      } catch(Exception $e) {
        Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
      }
    }
    if(!$this->redir) return;
    $pLink["path"] = getCurLink();
    if(!isset($_POST["saveandgo"])) $pLink["query"] = get_class($this)."=".$_POST["filename"];
    redirTo(buildLocalUrl($pLink, true));
  }

  private function isPost() {
    return isset($_POST["content"], $_POST["userfilehash"], $_POST["filename"]);
  }

  private function checkCacheAge() {
    foreach(Cms::getVariable("dombuilder-html") as $fileName) {
      $fPath = findFile($fileName);
      $link = DOMBuilder::getLink($fPath);
      $newestCacheFilePath = null;
      $newestCacheFileMod = 0;
      foreach(getNginxCacheFiles(null, $link) as $cacheFilePath) {
        $cacheMtime = filemtime($cacheFilePath);
        if($cacheMtime < $newestCacheFileMod) continue;
        $newestCacheFilePath = $cacheFilePath;
        $newestCacheFileMod = $cacheMtime;
      }
      if(is_null($newestCacheFilePath)) continue; // no cache
      if(filemtime($fPath) <= $newestCacheFileMod) continue; // cache is younger then file
      Cms::addMessage(sprintf(_("Cache is older than file %s"), $fileName), Cms::MSG_WARNING);
    }
  }

  private function getFilesRecursive($folder, $prefix = "") {
    $files = array();
    foreach(scandir($folder) as $f) {
      if($f == "." || $f == "..") continue;
      if(is_dir($folder."/".$f))
        $files = array_merge($files, $this->getFilesRecursive($folder."/$f", $prefix."$f/"));
      else
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
    foreach ($paths as $path => $prefix) $files = array_unique(array_merge($files, $this->getFilesRecursive($path, $prefix)));
    foreach ($files as $k => $f) {
      if(!preg_match("/(\.html|\.xml|\.xsl)$/", $f)) unset($files[$k]);
      if(isset($files[".$f"])) unset($files[$k]);
    }
    $files = array_merge($files, Cms::getVariable("htmloutput-javascripts"));
    $files = array_merge($files, Cms::getVariable("htmloutput-styles"));

    $dom = new DOMDocumentPlus();
    $var = $dom->createElement("var");
    usort($files, "strnatcmp");
    foreach($files as $f) {
      $option = $dom->createElement("option");
      $disabled = false;
      $fName = $f;
      if(strpos(basename($f), ".") === 0) {
        $disabled = true;
        $dirname = dirname($f) == "." ? "" : dirname($f)."/";
        $fName = $dirname.substr(basename($f), 1);
      }
      $option->setAttribute("value", $fName);
      $v = basename($fName)." $fName";
      if(is_file(CMS_FOLDER."/$f")) $v .= " #default";
      if(is_file(ADMIN_FOLDER."/$f")) $v .= " #admin";
      if(is_file(USER_FOLDER."/$f")) $v .= " #user";
      if($disabled) $v .= " #disabled";
      $option->nodeValue = $v;
      $var->appendChild($option);
    }
    Cms::setVariable("navigfiles", $var);
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
    $usrDestHash = getFileHash($this->dataFile);
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
    if((!$this->isPost() && $this->dataFileStatus == self::STATUS_DISABLED)
      || isset($_POST["disabled"])) $vars["checked"] = "checked";

    $cachechecked = "checked";
    if(isset($_SESSION["clearcache"])) {
      $cachechecked = $_SESSION["clearcache"] ? "checked" : null;
    }
    $vars["cachechecked"] = $cachechecked;

    if($this->dataFileStatus == self::STATUS_NEW) {
      $vars["warning"] = "warning";
      $vars["nohide"] = "nohide";
    }
    $newContent->processVariables($vars);
    if(is_null($this->defaultFile)) Cms::setVariable("title", $vars["heading"]);
    else Cms::setVariable("title", sprintf(_("%s (%s) - Administration"),
      basename($this->defaultFile), ROOT_URL.$this->defaultFile));
    return $newContent;
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
  private function setDefaultFile($fileName) {
    $this->defaultFile = $this->getFilepath($fileName);
    $redirPath = null;
    $fLink = DOMBuilder::getLink(findFile($fileName));
    if($this->defaultFile != $fileName) { $redirPath = getCurLink(); }
    else if(getCurLink() != $fLink) { $redirPath = $fLink; }
    if(!is_null($redirPath)) redirTo(buildLocalUrl(array("path" => $redirPath, "query" => get_class($this)."=".$this->defaultFile)));
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
      return substr($f, strlen(USER_FOLDER)+1);
    }
    if(strpos($f, ADMIN_FOLDER."/") === 0) {
      return substr($f, strlen(ADMIN_FOLDER)+1);
    }
    if(preg_match("~^[\w-]+$~", $f)) {
      $pluginFile = PLUGINS_DIR."/$f/$f.xml";
      if(is_null(findFile($pluginFile))) return "$f.xml";
      return $pluginFile;
    }
    if(!preg_match("/^".FILEPATH_PATTERN."$/", $f)) {
      $this->dataFileStatus = self::STATUS_INVALID;
      throw new Exception(sprintf(_("Unsupported file name format '%s'"), $f));
    }
    if(!is_null(findFile(PLUGINS_DIR."/$f", false))) PLUGINS_DIR."/$f";
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
    if(count($_POST) && isset($_POST["disabled"])) return true;
    return false;
  }

  private function isToEnable() {
    if(!is_file($this->dataFileDisabled)) return false;
    if(isset($_GET[self::FILE_ENABLE])) return true;
    if(count($_POST) && !isset($_POST["disabled"])) return true;
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
      #TODO: log autocorrected
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
    if(!file_exists($f)) $f = $this->dataFileDisabled;
    if(!file_exists($f)) return;
    if(($this->contentValue = file_get_contents($f)) === false)
      throw new Exception(sprintf(_("Unable to get contents from '%s'"), $this->dataFile));
  }

  private function savePost($filePath) {
    mkdir_plus(dirname($filePath));
    $fp = lockFile("$filePath.lock");
    try {
      try {
        if($_POST["filename"] != $_GET[get_class($this)] && is_file($filePath) && !isset($_POST["overwrite"]))
          throw new Exception(_("Destination file already exists"));
        file_put_contents_plus($filePath, $this->contentValue);
      } catch(Exception $e) {
        throw new Exception(sprintf(_("Unable to save changes to %s: %s"), $_POST["filename"], $e->getMessage()));
      }
      try {
        if(is_file("$filePath.old"))
          incrementalRename("$filePath.old", "$filePath.");
      } catch(Exception $e) {
        throw new Exception(sprintf(_("Unable to backup %s: %s"), $_POST["filename"], $e->getMessage()));
      }
      $this->redir = true;
      Cms::addMessage(_("Changes successfully saved"), Cms::MSG_SUCCESS);
    } catch(Exception $e) {
      throw $e;
    } finally {
      unlockFile($fp);
      unlink("$filePath.lock");
    }
  }

  private function enableDataFile() {
    #if(($this->dataFile == $this->dataFileDisabled
    #  && !rename($this->dataFileDisabled, $this->dataFile))
    #  || !unlink($this->dataFileDisabled))
    if(!rename($this->dataFileDisabled, $this->dataFile))
      throw new Excepiton(_("Unable to enable file"));
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
