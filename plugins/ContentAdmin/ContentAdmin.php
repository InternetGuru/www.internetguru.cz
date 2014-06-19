<?php

#TODO: formatOutput at save
#TODO: dont save if the same

class ContentAdmin implements SplObserver, ContentStrategyInterface {
  const HASH_ALGO = 'crc32b';
  private $subject; // SplSubject
  private $content = null;
  private $errors = array();
  private $contentValue = null;

  public function update(SplSubject $subject) {
    if(!isset($_GET["admin"])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->getCms()->setContentStrategy($this,100);
      $this->proceedPost();
      return;
    }
    if($subject->getStatus() == "process") {
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
      $this->contentValue = $cms->getContentFull()->saveXML($cms->getContentFull()->documentElement);
    }
    $cms->insertVar("heading",$cms->getTitle(),"ContentAdmin");
    $cms->insertVar("errors",$this->errors,"ContentAdmin");
    $cms->insertVar("link",$cms->getLink(),"ContentAdmin");
    $cms->insertVar("content",$this->contentValue,"ContentAdmin");
    $cms->insertVar("filehash",$this->getFileHash(),"ContentAdmin");
  }

  private function getFileHash() {
    return hash_file(self::HASH_ALGO,USER_FOLDER."/Content.xml");
  }

  private function proceedPost() {
    if(!isset($_POST["content"])) return;
    try {
      if($_POST["filehash"] == hash(self::HASH_ALGO,$_POST["content"]))
        throw new Exception("No changes made");
      if($_POST["filehash"] != $this->getFileHash())
        throw new Exception("Source file has changed during administration");
      $doc = new HTMLPlus();
      if(!@$doc->loadXML($_POST["content"]))
        throw new Exception("String is not a valid XML");
      $this->savePost($doc);
      // further validation including non-blocking ... $this->error[] = xy
      #$this->errors[] = "test error";
      #throw new Exception("test exception");
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

  private function savePost() {
    $file = USER_FOLDER."/Content.xml";
    $content = '<?xml version="1.0" encoding="utf-8" ?>'.$_POST["content"];
    if(file_put_contents("$file.new",$content) === false)
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