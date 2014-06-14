<?php

class ContentAdmin implements SplObserver, ContentStrategyInterface {
  const HASH_FILE_ALGO = 'crc32b';
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

  public function getContent(DOMDocument $content) {
    $cms = $this->subject->getCms();
    $errors = array();
    if(isset($_POST["content"])) {
      if($this->isValidPost($errors)) {
        $this->savePost();
        $redir = $this->subject->getCms()->getLink();
        header("Location: " . (strlen($redir) ? $redir : "."));
        exit;
      }
      $contentValue = $_POST["content"];
    } else {
      $contentValue = $cms->getContentFull()->saveXML($cms->getContentFull()->documentElement);
    }
    $newContent = $cms->buildDOM("ContentAdmin",true);
    $this->setVar($newContent,"heading",$content->getElementsByTagName("h")->item(0)->nodeValue);
    $this->setVar($newContent,"errors",$errors);
    $this->setVar($newContent,"link",$cms->getLink());
    $this->setVar($newContent,"content",$contentValue);
    $this->setVar($newContent,"filehash",$this->getFileHash());
    return $newContent;
  }

  private function getFileHash() {
    return hash_file(self::HASH_FILE_ALGO,USER_FOLDER."/Content.xml");
  }

  private function isValidPost(Array &$errors) {
    $doc = new DOMDocument();
    if(!@$doc->loadXML($_POST["content"]))
      $errors[] = "Document is not valid";
    if($_POST["filehash"] != $this->getFileHash())
      $errors[] = "File has changed during your administration";
    if(!empty($errors)) return false;
    return true;
  }

  private function savePost() {
    $file = USER_FOLDER."/Content.xml";
    $content = '<?xml version="1.0" encoding="utf-8" ?><Content>'.$_POST["content"].'</Content>';
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