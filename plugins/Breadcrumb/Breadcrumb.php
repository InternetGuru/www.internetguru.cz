<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\TitleStrategyInterface;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use Exception;
use SplObserver;
use SplSubject;
use DateTime;

class Breadcrumb extends Plugin implements SplObserver, TitleStrategyInterface {
  private $vars = array();
  private $title = null;

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_POSTPROCESS) return;
    foreach($this->getXML()->documentElement->childElementsArray as $e) {
      if($e->nodeName != "var" || !$e->hasAttribute("id")) continue;
      $this->vars[$e->getAttribute("id")] = $e;
    }
    $this->generateBc();
  }

  public function getTitle() {
    return $this->title;
  }

  private function generateBc() {
    #var_dump(HTMLPlusBuilder::getLinkToId());
    $parentId = HTMLPlusBuilder::getLinkToId(getCurLink());
    if(is_null($parentId)) throw new Exception(sprintf(_("Link %s not found"), getCurLink()));
    $bcLang = HTMLPlusBuilder::getIdToLang($parentId);
    while(!is_null($parentId)) {
      $path[] = $parentId;
      $parentId = HTMLPlusBuilder::getIdToParentId($parentId);
    }
    #var_dump($path); die("die");
    $bc = new DOMDocumentPlus();
    $root = $bc->appendChild($bc->createElement("root"));
    $ol = $root->appendChild($bc->createElement("ol"));
    $ol->setAttribute("class", "contentlink-bc"); // TODO: rename
    $ol->setAttribute("lang", $bcLang);
    $title = array();
    foreach(array_reverse($path) as $id) {
      $li = $ol->appendChild($bc->createElement("li"));
      $lang = HTMLPlusBuilder::getIdToLang($id);
      if($lang != $bcLang) $li->setAttribute("lang", $lang);
      $a = $li->appendChild($bc->createElement("a"));
      $a->setAttribute("href", $id);
      $values = HTMLPlusBuilder::getHeadingValues($id, !strlen(getCurLink()));
      $aValue = $values[0];
      if(empty($title) && array_key_exists("logo", $this->vars)) {
        $this->insertLogo($this->vars["logo"], $a, $id);
        if(!strlen(getCurLink()))
          $a->parentNode->appendChild($bc->createElement("span", $aValue));
      } else {
        $a->nodeValue = $aValue;
      }
      if(!empty($title) && strlen(HTMLPlusBuilder::getIdToShort($id)))
        $title[] = HTMLPlusBuilder::getIdToShort($id);
      else
        $title[] = HTMLPlusBuilder::getIdToHeading($id);
    }
    if(HTMLPlusBuilder::getIdToLink($a->getAttribute("href")) != getCurLink(true)) {
      $a->setAttribute("title", $this->vars["reset"]->nodeValue);
    }
    $this->title = implode(" - ", array_reverse($title));
    Cms::setVariable("", $bc->documentElement);
  }

  private function insertLogo(DOMElementPlus $logo, DOMElementPlus $a, $id) {
    $doc = $a->ownerDocument;
    $o = $doc->createElement("object");
    $o->nodeValue = HTMLPlusBuilder::getIdToHeading($id);
    $o->setAttribute("data", $logo->nodeValue);
    try {
      $o->setAttribute("type", $logo->getRequiredAttribute("type"));
    } catch(Exception $e) {
      Logger::user_warning($e->getMessage());
    }
    $a->addClass("logo");
    $a->appendChild($o);
  }


}

?>