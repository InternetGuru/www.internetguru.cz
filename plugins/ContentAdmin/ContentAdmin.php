<?php

#TODO: formatOutput at save
#TODO: admin=Cms etc...

class ContentAdmin implements SplObserver, ContentStrategyInterface {
  const HASH_ALGO = 'crc32b';
  private $subject; // SplSubject
  private $content = null;
  private $errors = array();
  private $contentValue = "";
  private $dataFile;

  public function update(SplSubject $subject) {
    if(!isset($_GET["admin"])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $this->dataFile = USER_FOLDER . "/Content.xml";
      $subject->setPriority($this,100);
      $this->validateAndRepair();
      return;
    }
    if($subject->getStatus() == "postprocess") {
      $this->insertVars();
      return;
    }
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $cms->getOutputStrategy()->addJsFile('ContentAdmin.js','ContentAdmin', 10, "body");
    return $cms->buildHTML("ContentAdmin",true);
  }

  private function insertVars() {
    $cms = $this->subject->getCms();
    $cms->insertVar("heading",$cms->getTitle(),"ContentAdmin");
    $cms->insertVar("errors",$this->errors,"ContentAdmin");
    $cms->insertVar("link",$cms->getLink(),"ContentAdmin");
    $cms->insertVar("content",$this->contentValue,"ContentAdmin");
    $cms->insertVar("filehash",$this->getFileHash($this->dataFile),"ContentAdmin");
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

    try {

      if($post && $_POST["filehash"] == $this->getHash(str_replace("\r\n", "\n", $_POST["content"])))
        throw new Exception("No changes made");
      if($post && $_POST["filehash"] != $this->getFileHash($this->dataFile))
        throw new Exception("Source file has changed during administration");

      $doc = new HTMLPlus();
      $doc->formatOutput = true;
      $doc->preserveWhiteSpace = false;

      if($post) {
        if(!@$doc->loadXML($_POST["content"])) throw new Exception("String is not valid XML");
      } elseif(!@$doc->load($this->dataFile)) {
        if(!($this->contentValue = @file_get_contents($this->dataFile)))
          throw new Exception("Unable to load content from '{$this->dataFile}'");
        $this->errors[] = "File is not valid XML";
        return;
      }

      libxml_use_internal_errors(true);
      $i = $doc->validate(true);
      if($post) $this->savePost($doc);
      elseif($i > 0) $this->errors[] = "Note: file has been autocorrected";
      // validation may include non-blocking errors
      #$this->errors[] = "non-blocking error";

    } catch (Exception $e) {
      $internal_errors = libxml_get_errors();
      if(count($internal_errors)) {
        foreach($internal_errors as $e) $this->errors[] = $e->message;
        libxml_clear_errors();
      } else {
        $this->errors[] = $e->getMessage();
      }
    }

    libxml_use_internal_errors(false);
    if($post) {
      if(empty($this->errors)) $this->redir();
      $this->contentValue = $_POST["content"];
    } else $this->contentValue = $doc->saveXML();

  }

  private function redir() {
    $redir = $this->subject->getCms()->getLink();
    if(isset($_POST["saveandstay"])) $redir .= "?admin";
    header("Location: " . (strlen($redir) ? $redir : "."));
    exit;
  }

  private function savePost($doc) {
    $file = USER_FOLDER."/Content.xml";
    if(file_put_contents("$file.new",$doc->saveXML()) === false)
      throw new Exception("Unable to save content");
    if(!copy($file,"$file.old") || !rename("$file.new",$file))
      throw new Exception("Unable to rename data file");
  }

  public function getTitle(Array $q) {
    return $q;
  }

  public function getDescription($q) {
    return $q;
  }

}

?>
