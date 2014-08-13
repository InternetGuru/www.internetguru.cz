<?php

#TODO: $_GET["restore"] restore without readonly
#TODO: specific schema validation support
#TODO: no schema support

class ContentAdmin implements SplObserver, ContentStrategyInterface {
  const HASH_ALGO = 'crc32b';
  private $subject; // SplSubject
  private $content = null;
  private $errors = array();
  private $contentValue = "";
  private $dataFile;
  private $scheme = null;
  private $adminLink;

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
      if(!($defaultFile = findFilePath($fileName,"",false,false)))
        throw new Exception("Default file '$fileName' not found");
      $this->dataFile = USER_FOLDER . "/$fileName";
      if(!file_exists($this->dataFile))
        throw new Exception("User file '{$this->dataFile}' not found");
      $extension = pathinfo($defaultFile,PATHINFO_EXTENSION);
      if(!in_array($extension, array("xml")))
        throw new Extension("Unsupported extension '$extension'");
      $subject->setPriority($this,1);
      $this->setScheme($defaultFile);
      $this->validateAndRepair();
      return;
    }
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $cms->getOutputStrategy()->addCssFile('ContentAdmin.css','ContentAdmin');
    $cms->getOutputStrategy()->addJsFile('ContentAdmin.js','ContentAdmin', 10, "body");

    #$this->errors = array("a","b","c");

    $newContent = $cms->buildHTML("ContentAdmin");
    $newContent->insertVar("heading",$cms->getTitle(),"ContentAdmin");
    $newContent->insertVar("errors",$this->errors,"ContentAdmin");
    $newContent->insertVar("link",$cms->getLink(),"ContentAdmin");
    $newContent->insertVar("linkAdmin",$this->adminLink,"ContentAdmin");
    $newContent->insertVar("content",$this->contentValue,"ContentAdmin");
    $newContent->insertVar("filename",$this->dataFile,"ContentAdmin");
    $newContent->insertVar("scheme",$this->scheme,"ContentAdmin");
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

  private function validateAndRepair() {

    $post = false;
    if(isset($_POST["content"],$_POST["filehash"])) $post = true;

    if($this->scheme == "lib/HTMLPlus.rng") {
      $doc = new HTMLPlus();
    } else {
      throw new Exception("Unsupported or missing XML scheme");
      #$doc = new DOMDocumentPlus();
    }

    try {

      if($post && $_POST["filehash"] == $this->getHash(str_replace("\r\n", "\n", $_POST["content"])))
        throw new Exception("No changes made");
      if($post && (!is_writable(dirname($this->dataFile)) || !is_writable($this->dataFile)))
        throw new Exception("Unable to save changes. File is probably locked (update in progress).");
      if($post && $_POST["filehash"] != $this->getFileHash($this->dataFile))
        throw new Exception("Source file has changed during administration");

      if($post) {
        if(!@$doc->loadXML($_POST["content"])) throw new Exception("String is not valid XML");
      } elseif(!@$doc->load($this->dataFile)) {
        if(!($this->contentValue = @file_get_contents($this->dataFile)))
          throw new Exception("Unable to load content from '{$this->dataFile}'");
        $this->errors[] = "File is not valid XML";
        return;
      }
      $doc->validate(true);
      if($post) $doc->saveRewrite($this->dataFile);

      if($doc->isAutocorrected())
        $this->errors[] = "Note: file has been autocorrected";
      // validation may include non-blocking errors
      #$this->errors[] = "non-blocking error";

    } catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }

    if($post) {
      if(empty($this->errors)) $this->redir();
      $this->contentValue = $_POST["content"];
    } else $this->contentValue = $doc->saveXML();

  }

  private function setScheme($f) {
    $h = fopen($f,"r");
    fgets($h); // skip first line
    $line = str_replace("'",'"',fgets($h));
    fclose($h);
    if(!preg_match('<\?xml-model href="([^"]+)" ?\?>',$line,$m)) return;
    $this->scheme = findFilePath($m[1],"",false,false);
    if(!file_exists($this->scheme))
      throw new Exception("Schema file '{$this->scheme}' not found");
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
