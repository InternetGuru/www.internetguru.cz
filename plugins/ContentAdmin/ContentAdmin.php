<?php

#TODO: formatOutput
#TODO: uložit a zůstat
#TODO: dont save if the same

class ContentAdmin implements SplObserver, ContentStrategyInterface {
  const HASH_ALGO = 'crc32b';
  private $subject; // SplSubject
  private $content = null;

  public function update(SplSubject $subject) {
    if(!isset($_GET["admin"])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->getCms()->setContentStrategy($this,100);
    }
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $errors = array();
    if(isset($_POST["content"])) {
      $this->proceedPost($errors);
      if(empty($errors)) $this->redir();
      $contentValue = $_POST["content"];
    } else {
      $contentValue = $cms->getContentFull()->saveXML($cms->getContentFull()->documentElement);
    }
    $newContent = $cms->buildHTML("ContentAdmin",true);
    $this->setVar($newContent,"heading",$content->getElementsByTagName("h")->item(0)->nodeValue);
    $this->setVar($newContent,"errors",$errors);
    $this->setVar($newContent,"link",$cms->getLink());
    $this->setVar($newContent,"content",$contentValue);
    $this->setVar($newContent,"filehash",$this->getFileHash());
    return $newContent;
  }

  private function getFileHash() {
    return hash_file(self::HASH_ALGO,USER_FOLDER."/Content.xml");
  }

  private function proceedPost(Array &$errors) {
    try {
      if($_POST["filehash"] == hash(self::HASH_ALGO,$_POST["content"]))
        throw new Exception("No changes made");
      if($_POST["filehash"] != $this->getFileHash())
        throw new Exception("Source file has changed during administration");
      $doc = new HTMLPlus();
      if(!@$doc->loadXML($_POST["content"]))
        throw new Exception("String is not a valid XML");
      $this->savePost($doc);
      // further validation including non-blocking ... $error[] = xy
      #$errors[] = "test error";
      #throw new Exception("test exception");
    } catch (Exception $e) {
      $errors[] = $e->getMessage();
    }
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

  private function setVar(DOMDocument $doc,$varName,$varValue) {
    $xpath = new DOMXPath($doc);
    $var = "{ContentAdmin:$varName}";
    $where = $xpath->query("//text()[contains(.,'$var')]");
    foreach($where as $e) {
      if(is_string($varValue)) $e->nodeValue = str_replace($var, $varValue, $e->nodeValue);
      if(is_array($varValue)) {
        $e->nodeValue = str_replace($var, "", $e->nodeValue);
        if(!empty($varValue)) {
          $this->insertList($e, $varValue);
          continue;
        }
      }
      if($e->nodeValue == "") $e->parentNode->parentNode->removeChild($e->parentNode);
    }
    $where = $xpath->query("//@*[contains(.,'$var')]");
    foreach($where as $attr) {
      $attr->nodeValue = str_replace($var, $varValue, $attr->nodeValue);
    }
  }

  private function insertList(DOMNode $e, Array $items) {
    $ul = $e->parentNode->appendChild($e->ownerDocument->createElement("ul"));
    foreach($items as $i) $ul->appendChild($e->ownerDocument->createElement("li",$i));
  }

  public function getTitle(Array $q) {
    return $q;
  }

  public function getDescription($q) {
    return $q;
  }

}

?>