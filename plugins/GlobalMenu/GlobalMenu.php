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

class GlobalMenu extends Plugin implements SplObserver {
  private $vars = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 5);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PROCESS) return;
    foreach($this->getXML()->documentElement->childElementsArray as $e) {
      if($e->nodeName != "var" || !$e->hasAttribute("id")) continue;
      $this->vars[$e->getAttribute("id")] = $e;
    }
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
      $values = HTMLPlusBuilder::getHeadingValues($id);
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
      $a->setAttribute("href", $id);
      #$a->setAttribute("title", $values[1]);
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
    $root->firstElement->setAttribute("class", "globalmenu noprint");
    Cms::setVariable("", $menu->documentElement);
  }

}

?>
