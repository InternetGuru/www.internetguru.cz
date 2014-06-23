<?php

#TODO: formatOutput at save

class ContentAdmin implements SplObserver, ContentStrategyInterface {
  const HASH_ALGO = 'crc32b';
  private $subject; // SplSubject
  private $content = null;
  private $errors = array();
  private $contentValue = null;
  private $dataFile;

  public function update(SplSubject $subject) {
    if(!isset($_GET["admin"])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $this->dataFile = USER_FOLDER . "/Content.xml";
      $subject->getCms()->setContentStrategy($this,100);
      $this->proceedPost();
      return;
    }
    if($subject->getStatus() == "postprocess") {
      $this->insertVars();
      return;
    }
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $cms->getOutputStrategy()->addCssFile("admin.css","ContentAdmin");
    return $cms->buildHTML("ContentAdmin",true);
  }

  private function insertVars() {
    $cms = $this->subject->getCms();
    if(is_null($this->contentValue)) {
      $this->contentValue = file_get_contents($this->dataFile);
    }
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

  private function proceedPost() {
    if(!isset($_POST["content"])) return;
    try {
      if($_POST["filehash"] == $this->getHash(str_replace("\r\n", "\n", $_POST["content"])))
        throw new Exception("No changes made");
      if($_POST["filehash"] != $this->getFileHash($this->dataFile))
        throw new Exception("Source file has changed during administration");
      $doc = new HTMLPlus();
      $doc->formatOutput = true;
      $doc->preserveWhiteSpace = false;
      if(!@$doc->loadXML($_POST["content"]))
        throw new Exception("String is not a valid XML");
      $this->savePost($doc);
      // validation may include non-blocking errors
      #$this->errors[] = "non-blocking error";
    } catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
    if(empty($this->errors)) $this->redir();
    $this->contentValue = $_POST["content"];
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