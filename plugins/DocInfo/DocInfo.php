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

  /**
   * @param HTMLPlus $doc
   * @param array $globalInfo
   * @param $filePath
   */
  private function insertDocInfo(HTMLPlus $doc, Array $globalInfo, $filePath) {
    $this->vars = array();
    foreach($this->getXML()->getElementsByTagName("var") as $var) {
      $this->vars[$var->getAttribute("id")] = $var;
    }
    foreach($doc->getElementsByTagName("h") as $h) {
      $before = null;
      if($h->parentNode->nodeName == "body") {
        if($filePath == INDEX_HTML) continue;
        $info = $this->createGlobalDocInfo($globalInfo, $filePath);
      } else {
        $info = $this->createLocalDocInfo($h, $globalInfo);
        if(!$info->childNodes->length) continue;
        $before = $h->nextElement;
        while(!is_null($before)) {
          if($before->nodeName == "h") break;
          $before = $before->nextElement;
        }
      }
      $info = $doc->importNode($info, true);
      if(is_null($before)) {
        $section = $doc->getElementsByTagName("section")->item(0);
        foreach($info->childElementsArray as $child) {
          $section->appendChild($child);
        }
        return;
      }
      foreach($info->childElementsArray as $child) {
        $before->parentNode->insertBefore($child, $before);
      }
      //echo $doc->saveXML(); die();
    }
  }

  private function createDOM(DOMElementPlus $set) {
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($set, true));
    return $doc;
  }

  private function createGlobalDocInfo(Array $globalInfo, $filePath) {
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
      $globalInfo["editurl"] = "?Admin=".$filePath;
      $lists["edit"] = $this->vars["edit"];
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