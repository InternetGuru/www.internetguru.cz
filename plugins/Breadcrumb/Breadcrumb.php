<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use Exception;
use SplObserver;
use SplSubject;
use DateTime;


class Breadcrumb extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_POSTPROCESS) return;
    $this->vars = array();
    foreach($this->getXML()->documentElement->childElementsArray as $e) {
      if($e->nodeName != "var" || !$e->hasAttribute("id")) continue;
      $this->vars[$e->getAttribute("id")] = $e;
    }
    $this->generateBc();
    $this->generateMenu();
  }

  private function generateMenu() {
    $menu = new DOMDocumentPlus();
    $root = $menu->appendChild($menu->createElement("root"));
    $curLink = getCurLink();
    $idToLi = array();
    $idToLevel = array();
    foreach(HTMLPlusBuilder::getIdToFile() as $id => $file) {
      if($file != INDEX_HTML) break;
      $parentId = HTMLPlusBuilder::getIdToParentId($id);
      if(is_null($parentId)) {
        $idToLi[$id] = $root;
        $idToLevel[$id] = 0;
        continue;
      }
      $values = $this->getHeadingValues($id);
      $parentUl = $idToLi[$parentId]->lastElement;
      if(is_null($parentUl) || $parentUl->nodeName != "ul") {
        $parentUl = $idToLi[$parentId]->appendChild($menu->createElement("ul"));
      }
      $li = $parentUl->appendChild($menu->createElement("li"));
      $a = $li->appendChild($menu->createElement("a", $values[0]));
      $link = HTMLPlusBuilder::getIdToLink($id);
      if($link == $curLink) {
        $p = $li;
        while(!is_null($p)) {
          if($p->nodeName == "li") $p->firstElement->setAttribute("class", "current"); // TODO: li.current
          $p = $p->parentNode->parentNode;
        }
      }
      $a->setAttribute("href", $link);
      $a->setAttribute("title", $values[1]);
      $idToLi[$id] = $li;
      $idToLevel[$id] = $idToLevel[$parentId]+1;
    }
    $maxLevel = $this->vars["menudepth"]->nodeValue;
    if(!is_numeric($maxLevel)) $maxLevel = 1;
    foreach($idToLi as $id => $li) {
      if($idToLevel[$id] != $maxLevel) continue;
      $ul = $li->lastElement;
      if($ul->nodeName == "ul") $li->removeChild($ul);
    }
    Cms::setVariable("menu", $menu->documentElement);
  }

  private function getHeadingValues($id) {
    $values = array();
    if(strlen(HTMLPlusBuilder::getIdToShort($id))) {
      $values[] = HTMLPlusBuilder::getIdToShort($id);
    }
    $values[] = HTMLPlusBuilder::getIdToHeading($id);
    $values[] = getShortString(HTMLPlusBuilder::getIdToDesc($id));
    return $values;
  }

  private function generateBc() {
    #var_dump(HTMLPlusBuilder::getLinkToId());
    $parentId = HTMLPlusBuilder::getLinkToId(getCurLink());
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
      $a->setAttribute("href", HTMLPlusBuilder::getIdToLink($id));
      if(strlen(HTMLPlusBuilder::getIdToTitle($id)))
        $aValue = HTMLPlusBuilder::getIdToTitle($id);
      elseif(strlen(HTMLPlusBuilder::getIdToShort($id)))
        $aValue = HTMLPlusBuilder::getIdToShort($id);
      else
        $aValue = HTMLPlusBuilder::getIdToHeading($id);
      if(empty($title) && array_key_exists("logo", $this->vars)) {
        $this->insertLogo($this->vars["logo"], $a, $id);
        $a->parentNode->appendChild($doc->createElement("span", $aValue));
      } else {
        $a->nodeValue = $aValue;
      }
      if(!empty($title) && strlen(HTMLPlusBuilder::getIdToShort($id)))
        $title[] = HTMLPlusBuilder::getIdToShort($id);
      else
        $title[] = HTMLPlusBuilder::getIdToHeading($id);
    }
    if($a->getAttribute("href") != getCurLink(true)) {
      $a->setAttribute("title", $this->vars["reset"]->nodeValue);
    }
    Cms::setVariable("bc", $bc->documentElement);
    Cms::setVariable("title", implode(" - ", array_reverse($title)));
  }

  private function insertLogo(DOMElementPlus $logo, DOMElementPlus $a, $id) {
    $doc = $a->documentElement;
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