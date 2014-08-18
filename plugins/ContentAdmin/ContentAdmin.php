<?php

#TODO: specific schema validation support
#TODO: no schema support

class ContentAdmin implements SplObserver, ContentStrategyInterface {
  const HASH_ALGO = 'crc32b';
  const HTMLPLUS_SCHEMA = "lib/HTMLPlus.rng";
  private $subject; // SplSubject
  private $content = null;
  private $errors = array();
  private $contentValue = "";
  private $dataFile;
  private $schema = null;
  private $adminLink;
  private $type;

  public function update(SplSubject $subject) {
    if(!isset($_GET["admin"])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $fileName = "Content.xml";
      $this->adminLink = $subject->getCms()->getLink()."?admin";
      if(strlen($_GET["admin"])) {
        $fileName = $_GET["admin"];
        $this->adminLink .= "=" . $_GET["admin"];
      }
      #$fileName = "plugins/ContentAdmin/ContentAdmin.xml";
      if(!($defaultFile = findFile($fileName,false,false)))
        throw new Exception("Default file '$fileName' not found");

      $this->setSchema($defaultFile);
      $this->dataFile = USER_FOLDER . "/$fileName";
      $this->type = pathinfo($defaultFile,PATHINFO_EXTENSION);
      if(!in_array($this->type, array("xml","css","xsl","txt")))
        throw new Exception("Unsupported extension '{$this->type}'");
      if(isset($_GET["restore"])) {
        $this->restoreDefault($defaultFile);
        $this->errors[] = "Note: user file has been restored";
      }
      if(!file_exists($this->dataFile)) {
        $this->restoreDefault($defaultFile);
        $this->errors[] = "Note: user file has been created";
      }
      $subject->setPriority($this,1);
      try {
        $this->processAdmin();
      } catch (Exception $e) {
        $this->errors[] = $e->getMessage();
      }
    }
  }

  private function restoreDefault($file) {
    switch($this->type) {
      case "xml":
      case "xsl":
      $this->restoreXml($this->dataFile,$file);
      break;
      case "css":
      case "txt":
      $this->restoreFile($this->dataFile,false,$file);
      break;
      default:
      throw new Exception("Unsupported type '{$this->type}'");
    }
  }

  private function restoreXml($dest,$src) {
    if($this->schema != self::HTMLPLUS_SCHEMA) {
      $db = $this->subject->getCms()->getDomBuilder();
      $doc = $db->buildDOMPlus($src,false,false);
      if($doc->removeNodes("//*[@readonly]")) {
        $doc->formatOutput = true;
        $this->restoreFile($dest,$doc->saveXml());
        return;
      }
    }
    $this->restoreFile($dest,false,$src);
  }

  private function restoreFile($dest,$content=false,$src=false) {
    if(file_exists($dest)) {
      if(!@rename($dest, $dest.".old"))
        throw new Exception("Unable to replace current user file '$dest'");
    }
    if(!file_exists(dirname($dest))) {
      if(!@mkdir(dirname($dest),0755,true))
        throw new Exception("Unable to create directory structure");
    }
    if($src) {
      if(!file_exists($src))
        throw new Exception("Source file '$src' not found");
      if(!@copy($src,$dest)) {
        throw new Exception("Unable to copy default data file '$dest'");
      }
      return;
    }
    if(!@file_put_contents($dest,$content))
      throw new Exception("Unable to save content into '$dest'");
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $cms->getOutputStrategy()->addCssFile('ContentAdmin.css','ContentAdmin');
    $cms->getOutputStrategy()->addJsFile('ContentAdmin.js','ContentAdmin', 10, "body");

    #$this->errors = array("a","b","c");
    $format = $this->type;
    if(!is_null($this->schema)) $format .= " ({$this->schema})";

    $newContent = $cms->getDomBuilder()->buildHTMLPlus("ContentAdmin");
    $newContent->insertVar("heading",$cms->getTitle(),"ContentAdmin");
    $newContent->insertVar("errors",$this->errors,"ContentAdmin");
    $newContent->insertVar("link",$cms->getLink(),"ContentAdmin");
    $newContent->insertVar("linkAdminRestore",$this->adminLink . "&restore","ContentAdmin");
    $newContent->insertVar("linkAdmin",$this->adminLink,"ContentAdmin");
    $newContent->insertVar("content",$this->contentValue,"ContentAdmin");
    $newContent->insertVar("filename",$this->dataFile,"ContentAdmin");
    $newContent->insertVar("schema",$format,"ContentAdmin");
    #$newContent->insertVar("noparse","noparse","ContentAdmin");
    $newContent->insertVar("filehash",$this->getFileHash($this->dataFile),"ContentAdmin");

    return $newContent;
  }

  private function getHash($data) {
    return hash(self::HASH_ALGO,$data);
  }

  private function getFileHash($filePath) {
    return hash_file(self::HASH_ALGO,$filePath);
  }

  private function createDoc($s) {
    switch($s) {
      case self::HTMLPLUS_SCHEMA:
      case "../cms/" . self::HTMLPLUS_SCHEMA:
      return new HTMLPlus();
      case null:
      return new DOMDocumentPlus();
      default:
      throw new Exception("Unsupported XML schema '$s'");
    }
  }

  private function processAdmin() {

    $post = false;
    if(isset($_POST["content"],$_POST["filehash"])) {
      $post = true;
      $this->contentValue = $_POST["content"];
      if($_POST["filehash"] == $this->getHash(str_replace("\r\n", "\n", $_POST["content"])))
        throw new Exception("No changes made");
      if(!is_writable(dirname($this->dataFile)) || !is_writable($this->dataFile))
        throw new Exception("Unable to save changes. File is probably locked (update in progress).");
      if($_POST["filehash"] != $this->getFileHash($this->dataFile))
        throw new Exception("Source file has changed during administration");
    } else {
      if(!($this->contentValue = @file_get_contents($this->dataFile)))
        throw new Exception("Unable to load content from '{$this->dataFile}'");
    }

    switch($this->type) {
      case "xml":
      case "xsl":
      $this->validateXml();
      case "css":
      case "txt":
      if($post) $this->saveAndRedir();
      break;
      default:
      throw new Exception("Unsupported type '{$this->type}'");
    }

  }

  private function saveAndRedir() {
    if(saveRewrite($this->dataFile,$this->contentValue) === false)
      throw new Exception("Unable to save changes");
    if(empty($this->errors)) $this->redir();
  }

  private function validateXml() {
    $doc = $this->createDoc($this->schema);
    if(!@$doc->loadXML($this->contentValue))
      throw new Exception("Invalid XML syntax");
    // format every xml file
    #$doc->formatOutput = true;
    #$this->contentValue = $doc->saveXML();
    if(is_null($this->schema)) return;
    if(get_class($doc) == "HTMLPlus") {
      // format only HTMLPlus file
      $doc->formatOutput = true;
      $this->contentValue = $doc->saveXML();
      $doc->validate(true);
      if($doc->isAutocorrected()) {
        $this->contentValue = $doc->saveXML();
        $this->errors[] = "Note: file has been autocorrected";
      }
      return;
    }
    switch(pathinfo($this->schema,PATHINFO_EXTENSION)) {
      case "rng":
      $doc->relaxNGValidatePlus($this->schema);
      break;
      default:
      throw new Exception("Unsupported schema '{$this->schema}'");
    }
  }

  private function setSchema($f) {
    $h = fopen($f,"r");
    fgets($h); // skip first line
    $line = str_replace("'",'"',fgets($h));
    fclose($h);
    if(!preg_match('<\?xml-model href="([^"]+)" ?\?>',$line,$m)) return;
    $this->schema = findFile($m[1],false,false);
    if(!file_exists($this->schema))
      throw new Exception("Schema file '{$this->schema}' not found");
  }

  private function redir() {
    $redir = $this->subject->getCms()->getLink();
    if(isset($_POST["saveandstay"])) $redir = $this->adminLink;
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
