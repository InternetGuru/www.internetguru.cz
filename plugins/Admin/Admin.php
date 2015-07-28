<?php

class Admin extends Plugin implements SplObserver, ContentStrategyInterface {
  const STATUS_NEW = 0;
  const STATUS_ENABLED = 1;
  const STATUS_DISABLED = 2;
  const FILE_DISABLE = "disable";
  const FILE_ENABLE = "enable";
  private $content = null;
  private $contentValue = null;
  private $schema = null;
  private $type = "n/a";
  private $redir = false;
  private $replace = true;
  private $error = false;
  private $dataFile = null;
  private $dataFileDisabled = null;
  private $dataFileStatus;
  private $defaultFile = "n/a";
  private $statusChanged = false;
  private $dataFileStatuses;
  private $contentChanged = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 5);
    $this->dataFileStatuses = array(_("new file"), _("active file"), _("inactive file"));
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_POSTPROCESS) {
      $this->createFilepicker();
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
      $this->setDefaultFile();
      $this->setDataFiles();
      if($this->isPost()) {
        $fileName = USER_FOLDER."/".$_POST["filename"];
        if($this->dataFile == $fileName && $_POST["userfilehash"] != getFileHash($this->dataFile))
          throw new Exception(sprintf(_("User file '%s' changed during administration"), $this->defaultFile));
        $this->processPost();
      } else $this->setContent();

      if(!$this->isResource($this->type)) $this->processXml();
      if($this->isPost() && !Cms::isSuperUser()) throw new Exception(_("Insufficient right to save changes"));
      if($this->isToEnable()) $this->enableDataFile();
      if($this->isPost() && ($this->contentChanged || $this->dataFile != $fileName)) {
        $this->savePost($fileName);
      } elseif(!$this->isToDisable() && !$this->isToEnable() && $this->isPost()) {
        throw new Exception(_("No changes made"), 1);
      }
      if($this->isToDisable()) $this->disableDataFile();
      if(!$this->contentChanged && $this->statusChanged) {
        $this->redir = true;
        Cms::addMessage(_("File status successfully changed"), Cms::MSG_SUCCESS, $this->redir);
      }
    } catch (Exception $e) {
      if($e->getCode() === 1) $type = Cms::MSG_INFO;
      else $type = Cms::MSG_ERROR;
      Cms::addMessage($e->getMessage(), $type, $this->redir);
      return;
    }
    if(!$this->isPost()) return;
    if($this->isResource($this->type)) {
      if(is_file(RESOURCES_DIR."/".$this->defaultFile)) unlink(RESOURCES_DIR."/".$this->defaultFile);
      if(is_file($this->defaultFile)) unlink($this->defaultFile);
    } else {
      try {
        if(!IS_LOCALHOST) clearNginxCache();
      } catch(Exception $e) {
        Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
      }
    }
    if(!$this->redir) return;
    $pLink = array("path" => $this->getDestPath($_POST["filename"]));
    if(!isset($_POST["saveandgo"])) $pLink["query"] = get_class($this)."=".$_POST["filename"];
    redirTo(buildLocalUrl($pLink, true));
  }

  private function getDestPath($f) {
    if(pathinfo($_POST["filename"], PATHINFO_EXTENSION) != "html") return getCurLink();
    $path = pathinfo($f, PATHINFO_FILENAME);
    if($this->dataFileStatus != self::STATUS_NEW && !DOMBuilder::isLink($path)) return getCurLink();
    return $path;
  }

  private function isPost() {
    return isset($_POST["content"], $_POST["userfilehash"], $_POST["filename"]);
  }

  private function getFilesRecursive($folder, $prefix = "") {
    $files = array();
    foreach(scandir($folder) as $f) {
      if(strpos($f, ".") === 0) continue;
      if(is_dir($folder."/".$f))
        $files = array_merge($files, $this->getFilesRecursive($folder."/$f", $prefix."$f/"));
      else
        $files[] = $prefix.$f;
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
    foreach ($files as $k => $f) if(!preg_match("/(\.html|\.xml|\.xsl)$/", $f)) unset($files[$k]);
    $files = array_merge($files, Cms::getVariable("htmloutput-javascripts"));
    $files = array_merge($files, Cms::getVariable("htmloutput-styles"));

    $dom = new DOMDocumentPlus();
    $var = $dom->createElement("var");
    foreach ($files as $f) {
      $option = $dom->createElement("option");
      $option->setAttribute("value", $f);
      $v = $f;
      if(is_file(CMS_FOLDER."/$f")) $v .= " #default";
      if(is_file(ADMIN_FOLDER."/$f")) $v .= " #admin";
      if(is_file(USER_FOLDER."/$f")) $v .= " #user";
      $option->nodeValue = $v;
      $var->appendChild($option);
    }
    Cms::setVariable("navigfiles", $var);
  }

  public function getContent(HTMLPlus $content) {
    Cms::getOutputStrategy()->addJsFile($this->pluginDir.'/Admin.js', 100, "body");
    Cms::getOutputStrategy()->addJs("
      Admin.init({
        saveInactive: '"._("Data file is inactive. Save anyways?")."'
      });
      ", 100, "body");
    $format = $this->type;
    if($this->type == "html") $format = "html+";
    if(!is_null($this->schema)) $format .= " (".pathinfo($this->schema, PATHINFO_BASENAME).")";

    $newContent = $this->getHTMLPlus();

    $la = "?".get_class($this)."=".$this->defaultFile;
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
    $vars["heading"] = sprintf(_("File %s Administration"), $this->defaultFile);
    $vars["link"] = getCurLink();
    $vars["linkadmin"] = $la;
    if($this->contentValue !== "" ) $vars["content"] = $this->contentValue;
    $vars["filename"] = $this->defaultFile;
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
    if($this->dataFileStatus == self::STATUS_NEW) {
      $vars["warning"] = "warning";
      $vars["nohide"] = "nohide";
    }
    $newContent->processVariables($vars);
    Cms::setVariable("title", sprintf(_("%s - Administration"), $this->defaultFile));
    return $newContent;
  }

  private function showContent($user) {
    $df = findFile($this->defaultFile, $user, true, false);
    if(!$df) return "n/a";
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
   * null -> [current_link].html -> INDEX_HTML
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
    $this->defaultFile = $this->getFilepath($_GET[get_class($this)]);
    if($this->defaultFile != $_GET[get_class($this)]) {
      redirTo(buildLocalUrl(array("path" => getCurLink(), "query" => get_class($this)."=".$this->defaultFile)));
    }
    $this->type = pathinfo($this->defaultFile, PATHINFO_EXTENSION);
  }

  private function getFilepath($f) {
    if(!strlen($f)) {
      return findFile(getCurLink().".html") ? getCurLink().".html" : INDEX_HTML;
    }
    if(strpos($f, USER_FOLDER."/") === 0) {
      return substr($f, strlen(USER_FOLDER)+1);
    }
    if(strpos($f, ADMIN_FOLDER."/") === 0) {
      return substr($f, strlen(ADMIN_FOLDER)+1);
    }
    if(preg_match("~^[\w-]+$~", $f)) {
      $pluginFile = PLUGINS_DIR."/$f/$f.xml";
      if(!findFile($pluginFile)) return "$f.xml";
      return $pluginFile;
    }
    if(!preg_match("~^([\w.-]+/)*([\w-]+\.)+[A-Za-z]{2,4}$~", $f)) {
      throw new Exception(sprintf(_("Unsupported file name format '%s'"), $f));
    }
    if(findFile(PLUGINS_DIR."/$f", false)) PLUGINS_DIR."/$f";
    return $f;
  }

  private function setDataFiles() {
    $this->dataFile = USER_FOLDER."/".$this->defaultFile;
    $this->dataFileDisabled = dirname($this->dataFile)."/.".basename($this->dataFile);
    // disabled if.file or both files exist, else new
    $this->dataFileStatus = self::STATUS_NEW;
    if(file_exists($this->dataFileDisabled)) $this->dataFileStatus = self::STATUS_DISABLED;
    if(file_exists($this->dataFile)) $this->dataFileStatus = self::STATUS_ENABLED;
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
    if($df = findFile($this->defaultFile, false)) {
      $this->schema = $this->getSchema($df);
    }
    // get user schema if default schema not exists
    if(is_null($this->schema) && file_exists($this->dataFile)) {
      $this->schema = $this->getSchema($this->dataFile);
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

  private function savePost($fileName) {
    mkdir_plus(dirname($fileName));
    $fp = lockFile("$fileName.lock");
    try {
      try {
        if($fileName != $this->dataFile && is_file($fileName) && !isset($_POST["overwrite"]))
          throw new Exception(_("Destination file already exists"));
        file_put_contents_plus($fileName, $this->contentValue);
      } catch(Exception $e) {
        throw new Exception(sprintf(_("Unable to save changes to %s: %s"), $_POST["filename"], $e->getMessage()));
      }
      try {
        if(is_file("$fileName.old"))
          incrementalRename("$fileName.old", "$fileName.");
      } catch(Exception $e) {
        throw new Exception(sprintf(_("Unable to backup %s: %s"), $_POST["filename"], $e->getMessage()));
      }
      $this->redir = true;
      Cms::addMessage(_("Changes successfully saved"), Cms::MSG_SUCCESS, $this->redir);
    } catch(Exception $e) {
      throw $e;
    } finally {
      unlockFile($fp);
      unlink("$fileName.lock");
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
    if(is_null($this->schema)) return;
    switch(pathinfo($this->schema, PATHINFO_EXTENSION)) {
      case "rng":
      $doc->relaxNGValidatePlus($this->schema);
      break;
      default:
      throw new Exception(sprintf(_("Unsupported schema '%s'"), $this->schema));
    }
  }

  private function getSchema($f) {
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
