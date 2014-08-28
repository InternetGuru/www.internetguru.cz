<?php

#TODO: ?superadmin
#TODO: de/activate
#TODO: success message
#TODO: select file

class ContentAdmin extends Plugin implements SplObserver, ContentStrategyInterface {
  const HTMLPLUS_SCHEMA = "lib/HTMLPlus.rng";
  const DEFAULT_FILE = "Content.xml";
  private $content = null;
  private $errors = array();
  private $contentValue = "";
  private $schema = null;
  private $adminLink;
  private $type;
  private $replace = true;
  private $dataFile = null;
  private $disabled = false;

  public function update(SplSubject $subject) {
    if(!isset($_GET["admin"])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "preinit") {
      $subject->setPriority($this,1);
    }
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    $this->adminLink = $subject->getCms()->getLink()."?admin";
    try {
      $this->setDefaultFile();
      $this->setDataFile();
      $this->processAdmin();
    } catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $cms->getOutputStrategy()->addCssFile($this->getDir() . '/ContentAdmin.css');
    $cms->getOutputStrategy()->addJsFile($this->getDir() . '/ContentAdmin.js', 100, "body");

    #$this->errors = array("a","b","c");
    $format = $this->type;
    if(!is_null($this->schema)) $format .= " ({$this->schema})";

    $la = $this->adminLink ."=". $this->defaultFile;
    $las = $la ."&". ($this->disabled ? "activate" : "deactivate");
    $usrDestHash = $this->getFileHash($this->dataFile);
    $mode = $this->replace ? "replace" : "modify";
    $status = $this->disabled ? "inactive" : "active";

    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("heading",$cms->getTitle(),"ContentAdmin");
    $newContent->insertVar("errors",$this->errors,"ContentAdmin");
    $newContent->insertVar("link",$cms->getLink(),"ContentAdmin");
    $newContent->insertVar("linkAdmin",$la,"ContentAdmin");
    $newContent->insertVar("linkAdminStatus",$las,"ContentAdmin");
    $newContent->insertVar("content",$this->contentValue,"ContentAdmin");
    $newContent->insertVar("filename",$this->defaultFile,"ContentAdmin");
    $newContent->insertVar("schema",$format,"ContentAdmin");
    $newContent->insertVar("mode",$mode,"ContentAdmin");
    $newContent->insertVar("type",$this->type,"ContentAdmin");
    $newContent->insertVar("defaultContent",$this->getDefContent(),"ContentAdmin");
    $newContent->insertVar("resultContent",$this->getResContent(),"ContentAdmin");
    $newContent->insertVar("status",$status,"ContentAdmin");
    #$newContent->insertVar("noparse","noparse","ContentAdmin");
    $newContent->insertVar("userfilehash",$usrDestHash,"ContentAdmin");

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
    return hash(self::HASH_ALGO,$data);
  }

  private function getFileHash($filePath) {
    if(!file_exists($filePath)) return "";
    return hash_file(self::HASH_ALGO,$filePath);
  }

  /**
   * LOGIC (-> == redir if exists)
   * F -> plugins/$0/$0.xml -> $0.xml
   * [dir/]+F -> $0.xml
   * [dir/]*F.ext (direct match) -> plugins/$0
   *
   * EXAMPLES
   * Xhtml11 -> plugins/Xhtml11/Xhtml11.xml (F default plugin config)
   * Xhtml11/Xhtml11.xsl -> plugins/Xhtml11/Xhtml11.xsl (dir/F.ext plugin)
   * Cms.xml -> Cms.xml (F.ext direct match)
   * themes/SimpleLayout.css -> themes/SimpleLayout.css (dir/F.ext direct)
   * themes/userFile.css -> usr/themes/userFile.css (dir/F.ext user)
   */
  private function setDefaultFile() {

    $f = self::DEFAULT_FILE;
    if(strlen($_GET["admin"])) $f = $_GET["admin"];
    if(strpos($f,"/") === 0) $f = substr($f,1); // remove trailing slash

    // direct user/admin file input is disabled
    if(strpos($f,USER_FOLDER."/") === 0) {
      $this->redir(substr($f,strlen(USER_FOLDER)+1));
    }
    if(strpos($f,ADMIN_FOLDER."/") === 0) {
      $this->redir(substr($f,strlen(ADMIN_FOLDER)+1));
    }

    $this->defaultFile = $f;

    // no extension
    $this->type = pathinfo($f,PATHINFO_EXTENSION);
    if($this->type == "") {
      $pluginFile = PLUGIN_FOLDER."/$f/$f.xml";
      if(!findFile($pluginFile)) $this->redir("$f.xml");
      $this->redir($pluginFile);
    }

    // no direct match with extension [and path]
    if(!findFile($f,false)) {
      // check/redir to plugin dir
      if(findFile(PLUGIN_FOLDER . "/$f",false))
        $this->redir(PLUGIN_FOLDER . "/$f");
      // file not found. create user file?
      throw new Exception("File '$f' not found. Cannot create user file.");
    }
  }

  private function setDataFile() {
    $f = USER_FOLDER ."/". $this->defaultFile;
    $fd = pathinfo($f,PATHINFO_DIRNAME) ."/.". pathinfo($f,PATHINFO_BASENAME);
    if(isset($_GET["activate"]) && file_exists($fd)) rename($fd,$f);
    if(isset($_GET["deactivate"]) && file_exists($f)) rename($f,$fd);
    if(!file_exists($f) && file_exists($fd)) {
      $f = $fd;
      $this->disabled = true;
    }
    $this->dataFile = $f;
  }

  private function processXml($post) {
    if(!in_array($this->type,array("xml","xsl"))) return;

    // get default schema
    if($df = findFile($this->defaultFile,false)) {
      $this->schema = $this->getSchema($df);
    }
    // get user schema if default schema not exists
    if(is_null($this->schema) && file_exists($this->dataFile)) {
      $this->schema = $this->getSchema($this->dataFile);
    }

    if($post) {
      if($this->isHtmlPlus()) $doc = new HTMLPlus();
      else $doc = new DOMDocumentPlus();
      if(!@$doc->loadXml($this->contentValue))
        throw new Exception("Invalid XML syntax");
    } else $doc = $this->loadXml($this->dataFile);

    $doc->formatOutput = true;
    if($this->isHtmlPlus()) {
      $doc->validatePlus(true);
      if($doc->isAutocorrected()) $this->contentValue = $doc->saveXML();
    } else {
      $this->replace = false;
      $doc->validatePlus();
      if($df && $doc->removeNodes("//*[@readonly]"))
        $this->contentValue = $doc->saveXML();
      $this->validateXml($doc);
    }
  }

  private function processAdmin() {

    $post = false;
    if(isset($_POST["content"],$_POST["userfilehash"])) {
      $post = true;
      $post_n = str_replace("\r\n", "\n", $_POST["content"]);
      $post_rn = str_replace("\n", "\r\n", $post_n);
      $this->contentValue = $post_n;
      if(in_array($_POST["userfilehash"],array($this->getHash($post_n),$this->getHash($post_rn))))
        throw new Exception("No changes made");
      if($_POST["userfilehash"] != $this->getFileHash($this->dataFile))
        throw new Exception("User file '{$this->defaultFile}' has changed during administration");
    } else {
      $this->contentValue = $this->loadFile($this->dataFile);
    }

    $this->processXml($post);

    if($post) $this->save($this->dataFile, $this->contentValue);
  }

  private function loadFile($file) {
    if(!file_exists($file)) return "";
    if(!($s = @file_get_contents($file)))
      throw new Exception ("Unable to get contents from '$file'");
    return $s;
  }

  private function loadXml($file) {
    if(!file_exists($file)) new DOMDocumentPlus();
    if($this->isHtmlPlus()) return $this->getHTMLPlus($file,false);
    return $this->getDOMPlus($file,false,false);
  }

  private function save($dest,$content) {
    if(saveRewrite($dest,$content) === false)
      throw new Exception("Unable to save changes, administration may be locked (update in progress)");
    if(empty($this->errors)) $this->redir($this->defaultFile);
  }

  private function isHtmlPlus() {
    return in_array($this->schema,array(self::HTMLPLUS_SCHEMA, "../cms/" . self::HTMLPLUS_SCHEMA));
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
    $redir = $this->subject->getCms()->getLink();
    #FIXME: different admin variations (admin, superadmin, viewonly)
    if(!isset($_POST["saveandgo"])) $redir .= "?admin" . $f;
    header("Location: " . (strlen($redir) ? $redir : "."));
    exit;
  }

  public function getTitle(Array $q) {
    return $q;
  }

  public function getDescription($q) {
    return $q;
  }

}

?>
