<?php

class ContentAdmin implements SplObserver, ContentStrategyInterface {
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
    if(isset($_POST["content"])) {
      if(!$this->isValidPost()) $contentValue = $_POST["content"];
      else {
        $this->savePost();
        header("Location: " . $this->subject->getCms()->getLink());
        exit;
      }
    } else $contentValue = $cms->getContentFull()->saveXML($cms->getContentFull()->documentElement);
    $newContent = $cms->buildDOM("ContentAdmin");
    $this->setVar($newContent,"heading",$content->getElementsByTagName("h")->item(0)->nodeValue);
    $this->setVar($newContent,"link",$cms->getLink());
    $this->setVar($newContent,"content",$contentValue);
    return $newContent;
  }

  private function isValidPost() {
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
      $e->nodeValue = str_replace($var, $varValue, $e->nodeValue);
    }
    $where = $xpath->query("//*[contains(@*,'$var')]");
    foreach($where as $e) {
      foreach($e->attributes as $attrName => $attrNode) {
        if(strpos($attrNode->nodeValue,$var) === false) continue;
        $attrNode->nodeValue = str_replace($var, $varValue, $attrNode->nodeValue);
      }
    }
  }

  public function getTitle(Array $q) {
    return $q;
  }

  public function getDescription($q) {
    return $q;
  }

}

?>