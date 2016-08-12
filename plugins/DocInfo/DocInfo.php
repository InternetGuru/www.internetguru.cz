<?php

namespace IGCMS\Plugins;

use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Cms;
use Exception;
use SplObserver;
use SplSubject;

class DocInfo extends Plugin implements SplObserver, ModifyContentStrategyInterface {

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
    $vars = array();
    foreach($this->getXML()->getElementsByTagName("var") as $var) {
      $vars[$var->getAttribute("id")] = $var;
    }
    foreach($doc->getElementsByTagName("h") as $h) {
      $ul = $this->createDocInfo($h, $vars, $globalInfo, $filePath);
      if(!$ul->childNodes->length) continue;
      $e = $h->nextElement;
      while(!is_null($e)) {
        if($e->nodeName == "h") break;
        $e = $e->nextElement;
      }
      if(is_null($e)) $h->parentNode->appendChild($ul);
      else $h->parentNode->insertBefore($ul, $e);
    }
  }

  private function createDocInfo(DOMElementPlus $h, Array $vars, Array $globalInfo, $filePath) {
    $doc = $h->ownerDocument;
    $ul = $doc->createElement("ul");
    // first heading
    if($h->parentNode->nodeName == "body") {
      if(HTMLPlusBuilder::getCurFile() != INDEX_HTML) {
        $this->createGlobalDocInfo($ul, $vars, $globalInfo, $filePath);
        $vars = $globalInfo;
      }
    } else {
      $vars = $this->createLocalDocInfo($h, $ul, $vars, $globalInfo, $filePath);
      if(empty($vars)) return $ul;
    }
    $ul->processVariables($vars, array(), true);
    return $ul;
  }

  private function addLi($ul, $class, $e) {
    $doc = $ul->ownerDocument;
    $li = $ul->appendChild($doc->createElement("li"));
    if(strlen($class)) $li->setAttribute("class", $class);
    foreach($e->childNodes as $n) {
      $li->appendChild($doc->importNode($n, true));
    }
  }

  private function createGlobalDocInfo(DOMElementPlus $ul, Array $vars, Array $globalInfo, $filePath) {
    $ul->setAttribute("class", "docinfo nomultiple global");
    $doc = $ul->ownerDocument;
    $this->addLi($ul, "creation", $vars["creation"]);
    // global modification
    if(strlen($globalInfo["mtime"]) && substr($globalInfo["ctime"], 0, 10) != substr($globalInfo["mtime"], 0, 10)) {
      $this->addLi($ul, "modified", $vars["modified"]);
    }
    // global responsibility
    if(strlen($globalInfo["resp"])) {
      $this->addLi($ul, "responsible", $vars["responsible"]);
    }
    // edit link
    if(Cms::isSuperUser()) {
      $li = $ul->appendChild($doc->createElement("li"));
      $li->setAttribute("class", "edit noprint");
      $a = $li->appendChild($doc->createElement("a", _("Edit")));
      $a->setAttribute("href", "?Admin=".$filePath);
      $a->setAttribute("title", $filePath);
    }
  }

  private function createLocalDocInfo(DOMElementPlus $h, DOMElementPlus $ul, Array $vars, Array $globalInfo, $filePath) {
    $ul->setAttribute("class", "docinfo nomultiple partial");
    $partinfo = array();
    // local author (?)
    // local responsibility (?)
    // local creation
    if($h->hasAttribute("ctime") && substr($globalInfo["ctime"], 0, 10) != substr($h->getAttribute("ctime"), 0, 10)) {
      $partinfo["ctime"] = $h->getAttribute("ctime");
      $this->addLi($ul, null, $vars["part_created"]);
    }
    // local modification
    if($h->hasAttribute("mtime") && (!strlen($globalInfo["mtime"]) || substr($globalInfo["mtime"], 0, 10) != substr($h->getAttribute("mtime"), 0, 10))) {
      $partinfo["mtime"] = $h->getAttribute("mtime");
      $this->addLi($ul, null, $vars["part_modified"]);
    }
    return $partinfo;
  }
}

?>