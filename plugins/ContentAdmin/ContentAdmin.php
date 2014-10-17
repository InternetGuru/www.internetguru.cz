<?php

#TODO: ?superadmin
#TODO: success message
#TODO: select file
#TODO: validateLinks (add into validateplus(true)?) invalid/absolute/root (.,"" to "/")

class ContentAdmin extends Plugin implements SplObserver, ContentStrategyInterface {
  const HTMLPLUS_SCHEMA = "lib/HTMLPlus.rng";
  const DEFAULT_FILE = "Content.html";
  const FILE_NEW = "new file";
  const FILE_DISABLED = "inactive";
  const FILE_ENABLED = "active";
  const FILE_DISABLE = "disable";
  const FILE_ENABLE = "enable";
  private $content = null;
  private $errors = array();
  private $contentValue = "n/a";
  private $schema = null;
  private $adminLink;
  private $type = "unknown";
  private $replace = true;
  private $dataFile = null;
  private $dataFileStatus = "unknown";
  private $defaultFile = "n/a";

  public function update(SplSubject $subject) {
    if(!isset($_GET["admin"])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "preinit") {
      $subject->setPriority($this,3);
    }
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    $this->adminLink = "?admin";
    try {
      $this->setDefaultFile();
      $this->dataFile = $this->getDataFile();
      if($this->isPost()) $this->processPost();
      else $this->setContent();
      $this->processXml();
      if(!$this->isPost()) return;
      $this->savePost();
    } catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  private function isPost() {
    return isset($_POST["content"],$_POST["userfilehash"]);
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $cms->getOutputStrategy()->addCssFile($this->getDir() . '/ContentAdmin.css');
    $cms->getOutputStrategy()->addJsFile($this->getDir() . '/ContentAdmin.js', 100, "body");

    #$this->errors = array("a","b","c");
    $format = $this->type;
    if($this->type == "html") $format = "html+";
    if(!is_null($this->schema)) $format .= " (" . pathinfo($this->schema,PATHINFO_BASENAME) . ")";

    $la = $this->adminLink ."=". $this->defaultFile;
    $statusChange = $this->dataFileStatus == self::FILE_DISABLED ? self::FILE_ENABLE : self::FILE_DISABLE;
    $usrDestHash = getFileHash($this->dataFile);
    $mode = $this->replace ? "replace" : "modify";
    $type = (in_array($this->type,array("html","xsl")) ? "xml" : $this->type);

    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("contentadmin-heading", $cms->getVariable("cms-title"));
    $newContent->insertVar("contentadmin-errors", $this->errors);
    $newContent->insertVar("contentadmin-link", "");
    $newContent->insertVar("contentadmin-linkadmin", $la);
    $newContent->insertVar("contentadmin-linkadminstatus", "$la&amp;$statusChange");

    if($this->dataFileStatus == self::FILE_NEW || $this->dataFileStatus == "unknown")
      $newContent->insertVar("contentadmin-statuschange", null);

    $newContent->insertVar("contentadmin-content", $this->contentValue);
    $newContent->insertVar("contentadmin-filename", $this->defaultFile);
    $newContent->insertVar("contentadmin-schema", $format);
    $newContent->insertVar("contentadmin-mode", $mode);
    $newContent->insertVar("contentadmin-classtype", $type);
    $newContent->insertVar("contentadmin-defaultcontent", $this->getDefContent());
    $newContent->insertVar("contentadmin-resultcontent", $this->getResContent());
    $newContent->insertVar("contentadmin-status", $this->dataFileStatus);
    $newContent->insertVar("contentadmin-userfilehash", $usrDestHash);

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

    $f = $_GET["admin"];
    if(strpos($f,"/") === 0) $f = substr($f,1); // remove trailing slash
    if(!strlen($f)) {
      $l = getCurLink() . ".html";
      if(findFile($l)) $f = $l;
      else $f = self::DEFAULT_FILE;
    }

    // direct user/admin file input is disallowed
    if(strpos($f,USER_FOLDER."/") === 0) {
      $this->redir(substr($f,strlen(USER_FOLDER)+1));
    }
    if(strpos($f,ADMIN_FOLDER."/") === 0) {
      $this->redir(substr($f,strlen(ADMIN_FOLDER)+1));
    }

    // redir to plugin if no path or extension
    if(preg_match("~^[\w-]+$~", $f)) {
      $pluginFile = PLUGIN_FOLDER."/$f/$f.xml";
      if(!findFile($pluginFile)) $this->redir("$f.xml");
      $this->redir($pluginFile);
    }

    if(!preg_match("~^([\w.-]+/)*([\w-]+\.)+[A-Za-z]{3,4}$~", $f))
      throw new Exception("Unsupported file name format '$f'");

    $this->defaultFile = $f;
    $this->type = pathinfo($f,PATHINFO_EXTENSION);

    // no direct match with extension [and path]
    if(findFile($f,false)) return;
    // check/redir to plugin dir
    if(!findFile(PLUGIN_FOLDER . "/$f",false)) return;
    // found plugin file
    $this->redir(PLUGIN_FOLDER . "/$f");

  }

  private function getDataFile() {
    $f = USER_FOLDER ."/". $this->defaultFile;
    $fd = pathinfo($f,PATHINFO_DIRNAME) ."/.". pathinfo($f,PATHINFO_BASENAME);
    if(isset($_GET[self::FILE_ENABLE]) && file_exists($fd)) rename($fd,$f);
    if(isset($_GET[self::FILE_DISABLE]) && file_exists($f)) rename($f,$fd);
    $this->dataFileStatus = self::FILE_ENABLED;
    if(file_exists($f)) return $f;
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

    if($this->isHtmlPlus()) {
      $doc = new HTMLPlus();
      $doc->load(findFile("Content.html",false,false));
      #$doc->formatOutput = true;
    } else $doc = new DOMDocumentPlus();
    if($this->contentValue == "n/a") {
      $this->contentValue = $doc->saveXML();
      return;
    }
    if(!@$doc->loadXml($this->contentValue))
      throw new Exception("Invalid XML syntax");

    if($this->isHtmlPlus()) {
      #$doc->formatOutput = true;
      $doc->validatePlus(true);
      if($doc->isAutocorrected()) $this->contentValue = $doc->saveXML();
      return;
    }

    $doc->validatePlus();
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
      throw new Exception("No changes made");
    if($_POST["userfilehash"] != getFileHash($this->dataFile))
      throw new Exception("User file '{$this->defaultFile}' has changed during administration");
  }

  private function setContent() {
    $f = $this->dataFile;
    if(!file_exists($f)) $f = findFile($this->defaultFile);
    if($f && !($this->contentValue = file_get_contents($f)))
      throw new Exception ("Unable to get contents from '{$this->dataFile}'");
  }

  private function savePost() {
    if(saveRewrite($this->dataFile, $this->contentValue) === false)
      throw new Exception("Unable to save changes, administration may be locked (update in progress)");
    if(empty($this->errors)) $this->redir($this->defaultFile);
  }

  private function isHtmlPlus() {
    return $this->type == "html";
    #return in_array($this->schema,array(self::HTMLPLUS_SCHEMA, CMS_FOLDER ."/". self::HTMLPLUS_SCHEMA));
  }

  private function validateXml(DOMDocumentPlus $doc) {
    if(is_null($this->schema)) return;
    switch(pathinfo($this->schema,PATHINFO_EXTENSION)) {
      case "rng":
      $doc->relaxNGValidatePlus($this->schema);
      break;
      default:
      throw new Exception("Unsupported schema '{$this->schema}'");
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
      throw new Exception("Schema file '$schema' not found");
    return $schema;
  }

  private function redir($f="") {
    if(strlen($f)) $f = "=$f";
    $os = $this->subject->getCms()->getOutputStrategy();
    $redir = $os->getRoot().getCurLink();
    #FIXME: different admin variations (admin, superadmin, viewonly)
    if(!isset($_POST["saveandgo"])) $redir .= "?admin" . $f;
    redirTo($redir,null,true);
  }

}

?>