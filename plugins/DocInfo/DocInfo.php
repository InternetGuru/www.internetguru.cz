<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use SplObserver;
use SplSubject;

class DocInfo extends Plugin implements SplObserver, ModifyContentStrategyInterface {

  private $vars = array();

  public function update(SplSubject $subject) {}

  public function modifyContent(HTMLPlus $content) {
    $filePath = HTMLPlusBuilder::getCurFile();
    $id = HTMLPlusBuilder::getFileToId($filePath);
    $globalInfo = array();
    $globalInfo["ctime"] = HTMLPlusBuilder::getIdToCtime($id);
    $globalInfo["mtime"] = HTMLPlusBuilder::getIdToMtime($id);
    $globalInfo["author"] = HTMLPlusBuilder::getIdToAuthor($id);
    $globalInfo["authorid"] = HTMLPlusBuilder::getIdToAuthorId($id);
    $globalInfo["resp"] = HTMLPlusBuilder::getIdToResp($id);
    $globalInfo["respid"] = HTMLPlusBuilder::getIdToRespId($id);
    $this->insertDocInfo($content, $globalInfo, $filePath);
  }

  private function insertDocInfo(HTMLPlus $doc, Array $globalInfo, $filePath) {
    $this->vars = array();
    foreach($this->getXML()->getElementsByTagName("var") as $var) {
      $this->vars[$var->getAttribute("id")] = $var;
    }
    foreach($doc->getElementsByTagName("h") as $h) {
      if($h->parentNode->nodeName == "body") {
        if($filePath == INDEX_HTML) continue;
        $ul = $this->createGlobalDocInfo($globalInfo);
      } else {
        $ul = $this->createLocalDocInfo($h, $globalInfo);
      }
      if(!$ul->childNodes->length) continue;
      $e = $h->nextElement;
      while(!is_null($e)) {
        if($e->nodeName == "h") break;
        $e = $e->nextElement;
      }
      $ul = $doc->importNode($ul, true);
      if(is_null($e)) $section = $doc->getElementsByTagName("section")->item(0);
      foreach($ul->childElementsArray as $child) {
        if(is_null($e)) $section->appendChild($child);
        else $h->parentNode->insertBefore($child, $e);
      }
      echo $doc->saveXML(); die();
    }
  }

  private function createDOM(DOMElementPlus $set) {
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($set, true));
    return $doc;
  }

  private function createGlobalDocInfo(Array $globalInfo) {
    $doc = $this->createDOM($this->vars["docinfo"]);
    $lists = array(
      "created" => $this->vars["created"],
      "edit" => "",
      "modified" => "",
      "responsible" => "",
    );
    if(strlen($globalInfo["mtime"]) && substr($globalInfo["ctime"], 0, 10) != substr($globalInfo["mtime"], 0, 10)) {
      $lists["modified"] = $this->vars["modified"];
    }
    if(strlen($globalInfo["resp"])) {
      $lists["responsible"] = $this->vars["responsible"];
    }
    if(Cms::isSuperUser()) {
      $lists["modified"] = $this->vars["modified"];
    }
    $doc->processVariables($lists);
    $doc->processVariables($globalInfo);
    return $doc->documentElement;
  }

  private function createLocalDocInfo(DOMElementPlus $h, Array $globalInfo) {
    $doc = $this->createDOM($this->vars["partinfo"]);
    $vars = array();
    $lists = array(
      "part_created" => "",
      "part_modified" => "",
    );
    if($h->hasAttribute("ctime") && substr($globalInfo["ctime"], 0, 10) != substr($h->getAttribute("ctime"), 0, 10)) {
      $vars["ctime"] = $h->getAttribute("ctime");
      $lists["part_created"] = $this->vars["part_created"];
    }
    if($h->hasAttribute("mtime") && (!strlen($globalInfo["mtime"]) || substr($globalInfo["mtime"], 0, 10) != substr($h->getAttribute("mtime"), 0, 10))) {
      $vars["mtime"] = $h->getAttribute("mtime");
      $lists["part_modified"] = $this->vars["part_modified"];
    }
    $doc->processVariables($lists);
    $doc->processVariables($vars);
    return $doc->documentElement;
  }
}

?>