<?php

#TODO: success message
#TODO: js button 'copy default to user'
#TODO: nohide default if no user file

class Admin extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEFAULT_FILE = "Content.html";
  const FILE_NEW = "new file";
  const FILE_DISABLED = "inactive";
  const FILE_ENABLED = "active";
  const FILE_DISABLE = "disable";
  const FILE_ENABLE = "enable";
  private $content = null;
  private $contentValue = "n/a";
  private $schema = null;
  private $type = "unknown";
  private $replace = true;
  private $error = false;
  private $dataFile = null;
  private $dataFileStatus = "unknown";
  private $defaultFile = "n/a";

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,5);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_PROCESS) {
      $os = Cms::getOutputStrategy()->addTransformation($this->getDir()."/Admin.xsl");
    }
    if($subject->getStatus() != STATUS_INIT) return;
    if(!isset($_GET[get_class($this)])) {
      $subject->detach($this);
      return;
    }
    try {
      $this->setDefaultFile();
      $this->dataFile = $this->getDataFile();
      if($this->isPost()) $this->processPost();
      else $this->setContent();
      $this->processXml();
      if(!$this->isPost()) return;
      $this->savePost();
    } catch (Exception $e) {
      Cms::addMessage($e->getMessage(), Cms::MSG_WARNING);
      $this->error = true;
    }
  }

  private function isPost() {
    return isset($_POST["content"],$_POST["userfilehash"]);
  }

  public function getContent(HTMLPlus $content) {
    Cms::getOutputStrategy()->addJsFile($this->getDir() . '/Admin.js', 100, "body");

    $format = $this->type;
    if($this->type == "html") $format = "html+";
    if(!is_null($this->schema)) $format .= " (" . pathinfo($this->schema,PATHINFO_BASENAME) . ")";

    $newContent = $this->getHTMLPlus();

    $la = "?" . get_class($this) . "=" . $this->defaultFile;
    $statusChange = self::FILE_DISABLE;
    if($this->dataFileStatus == self::FILE_DISABLED) {
      $newContent->insertVar("warning", "warning");
      $statusChange = self::FILE_ENABLE;
    }
    $usrDestHash = getFileHash($this->dataFile);
    $mode = $this->replace ? "replace" : "modify";
    switch($this->type) {
      case "html":
      case "xsl":
      $type = "xml";
      break;
      case "js":
      $type = "javascript";
      break;
      default:
      $type = $this->type;
    }

    $d = new DOMDocumentPlus();
    $v = $d->appendChild($d->createElement("var"));
    $v->appendChild($d->importNode($content->getElementsByTagName("h")->item(0), true));

    $newContent->insertVar("heading", $d->documentElement);
    $newContent->insertVar("link", getCurLink());
    $newContent->insertVar("linkadmin", $la);
    $newContent->insertVar("linkadminstatus", "$la&$statusChange");
    $newContent->insertVar("content", $this->contentValue);
    $newContent->insertVar("filename", $this->defaultFile);
    $newContent->insertVar("schema", $format);
    $newContent->insertVar("mode", $mode);
    $newContent->insertVar("classtype", $type);
    $newContent->insertVar("defaultcontent", $this->getDefContent());
    $newContent->insertVar("resultcontent", $this->getResContent());
    $newContent->insertVar("status", $this->dataFileStatus);
    $newContent->insertVar("userfilehash", $usrDestHash);

    if($this->dataFileStatus == self::FILE_NEW || $this->dataFileStatus == "unknown") {
      $newContent->insertVar("statuschange", null);
      $newContent->insertVar("warning", "warning");
      $newContent->insertVar("nohide", "nohide");
    }

    return $newContent;
  }

  private function getResContent() {
    return $this->showContent(true);
  }

  private function getDefContent() {
    return $this->showContent(false);
  }

  private function showContent($user) {

    if($this->replace) {
      $df = findFile($this->defaultFile,$user);
      if(!$df) return "n/a";
      return file_get_contents($df);
    }

    $doc = $this->getDOMPlus($this->defaultFile,false,$user);
    $doc->removeNodes("//*[@readonly]");
    $doc->formatOutput = true;
    return $doc->saveXML();

  }

  private function getHash($data) {
    return hash(FILE_HASH_ALGO,$data);
  }

  /**
   * LOGIC (-> == redir if exists)
   * null -> [current_link].html -> Content.html
   * F -> plugins/$0/$0.xml -> $0.xml
   * [dir/]+F -> $0.xml
   * [dir/]*F.ext (direct match) -> plugins/$0
   *
   * EXAMPLES
   * /about?admin -> /about?admin=about.html -> /about?admin=Content.html
   * Xhtml11 -> plugins/Xhtml11/Xhtml11.xml (F default plugin config)
   * Xhtml11/Xhtml11.xsl -> plugins/Xhtml11/Xhtml11.xsl (dir/F.ext plugin)
   * Cms.xml -> Cms.xml (F.ext direct match)
   * themes/simpleLayout.css -> themes/simpleLayout.css (dir/F.ext direct)
   * themes/userFile.css -> usr/themes/userFile.css (dir/F.ext user)
   */
  private function setDefaultFile() {

    $f = $_GET[get_class($this)];
    if(strpos($f,"/") === 0) $f = substr($f,1); // remove trailing slash
    if(!strlen($f)) {
      $l = getCurLink() . ".html";
      if(findFile($l)) $f = $l;
      else $f = self::DEFAULT_FILE;
      $this->redir($f);
    }

    // direct user/admin file input is disallowed
    if(strpos($f,USER_ROOT_DIR."/") === 0) {
      $this->redir(substr($f,strlen(USER_ROOT_DIR)+1));
    }
    if(strpos($f,ADMIN_ROOT_DIR."/") === 0) {
      $this->redir(substr($f,strlen(ADMIN_ROOT_DIR)+1));
    }

    // redir to plugin if no path or extension
    if(preg_match("~^[\w-]+$~", $f)) {
      $pluginFile = PLUGINS_DIR."/$f/$f.xml";
      if(!findFile($pluginFile)) $this->redir("$f.xml");
      $this->redir($pluginFile);
    }

    if(!preg_match("~^([\w.-]+/)*([\w-]+\.)+[A-Za-z]{2,4}$~", $f))
      throw new Exception(sprintf(_("Unsupported file name format '%s'"), $f));

    $this->defaultFile = $f;
    $this->type = pathinfo($f,PATHINFO_EXTENSION);

    // no direct match with extension [and path]
    if(findFile($f,false)) return;
    // check/redir to plugin dir
    if(!findFile(PLUGINS_DIR . "/$f",false)) return;
    // found plugin file
    $this->redir(PLUGINS_DIR . "/$f");

  }

  private function getDataFile() {
    $f = USER_FOLDER ."/". $this->defaultFile;
    $fd = pathinfo($f,PATHINFO_DIRNAME) ."/.". pathinfo($f,PATHINFO_BASENAME);
    if(isset($_GET[self::FILE_ENABLE]) && file_exists($fd)) rename($fd,$f);
    if(isset($_GET[self::FILE_DISABLE]) && file_exists($f)) rename($f,$fd);
    if(file_exists($f)) {
      $this->dataFileStatus = self::FILE_ENABLED;
      return $f;
    }
    if(file_exists($fd)) {
      $this->dataFileStatus = self::FILE_DISABLED;
      return $fd;
    }
    $this->dataFileStatus = self::FILE_NEW;
    return $f;
  }

  private function processXml() {
    if(!in_array($this->type,array("xml","xsl","html"))) return;

    // get default schema
    if($df = findFile($this->defaultFile,false)) {
      $this->schema = $this->getSchema($df);
    }
    // get user schema if default schema not exists
    if(is_null($this->schema) && file_exists($this->dataFile)) {
      $this->schema = $this->getSchema($this->dataFile);
    }

    if($this->type == "html") {
      $doc = new HTMLPlus();
      $doc->load(CMS_FOLDER."/".self::DEFAULT_FILE);
    } else $doc = new DOMDocumentPlus();
    if($this->contentValue == "n/a") {
      $this->contentValue = $doc->saveXML();
      return;
    }
    if(!@$doc->loadXml($this->contentValue))
      throw new Exception(_("Invalid XML syntax"));
    try {
      $doc->validatePlus();
    } catch(Exception $e) {
      $doc->validatePlus(true);
      $this->contentValue = $doc->saveXML();
    }
    if($this->type != "xml" || $this->isPost()) return;
    $this->replace = false;
    if($this->dataFileStatus == self::FILE_NEW) {
      $doc->documentElement->removeChildNodes();
      $this->contentValue = $doc->saveXML();
    }
    if($df && $doc->removeNodes("//*[@readonly]"))
      $this->contentValue = $doc->saveXML();
    $this->validateXml($doc);
  }

  private function processPost() {
    $post_n = str_replace("\r\n", "\n", $_POST["content"]);
    $post_rn = str_replace("\n", "\r\n", $post_n);
    $this->contentValue = $post_n;
    if(in_array($_POST["userfilehash"],array($this->getHash($post_n),$this->getHash($post_rn))))
      throw new Exception(_("No changes made"));
    if($_POST["userfilehash"] != getFileHash($this->dataFile))
      throw new Exception(sprintf(_("User file '%s' changed during administration"), $this->defaultFile));
  }

  private function setContent() {
    $f = $this->dataFile;
    if(!file_exists($f)) return;
    #if(!file_exists($f)) $f = findFile($this->defaultFile);
    if(!($this->contentValue = file_get_contents($f)))
      throw new Exception(sprintf(_("Unable to get contents from '%s'"), $this->dataFile));
  }

  private function savePost() {
    if(saveRewrite($this->dataFile, $this->contentValue) === false)
      throw new Exception(_("Unable to save changes, administration may be locked"));
    if($this->error) return;
    Cms::addMessage(_("Content successfully saved"), Cms::MSG_SUCCESS, true);
    $this->redir($this->defaultFile);
  }

  private function validateXml(DOMDocumentPlus $doc) {
    if(is_null($this->schema)) return;
    switch(pathinfo($this->schema,PATHINFO_EXTENSION)) {
      case "rng":
      $doc->relaxNGValidatePlus($this->schema);
      break;
      default:
      throw new Exception(sprintf(_("Unsupported schema '%s'"), $this->schema));
    }
  }

  private function getSchema($f) {
    $h = fopen($f,"r");
    fgets($h); // skip first line
    $line = str_replace("'",'"',fgets($h));
    fclose($h);
    if(!preg_match('<\?xml-model href="([^"]+)" ?\?>',$line,$m)) return;
    $schema = findFile($m[1],false,false);
    if(!file_exists($schema))
      throw new Exception(sprintf(_("Schema file '%s' not found"), $schema));
    return $schema;
  }

  private function redir($f="") {
    $redir = getRoot().getCurLink();
    if(!isset($_POST["saveandgo"]))
      $redir .= "?" . get_class($this) . (strlen($f) ? "=$f" : "");
    redirTo($redir,null,true);
  }

}

?>