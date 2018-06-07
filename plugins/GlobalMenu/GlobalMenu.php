<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class GlobalMenu
 * @package IGCMS\Plugins
 */
class GlobalMenu extends Plugin implements SplObserver {
  /**
   * @var array
   */
  private $vars = [];

  /**
   * GlobalMenu constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 70);
  }

  /**
   * @param Plugins|SplSubject $subject
   * @throws \Exception
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_PROCESS) {
      return;
    }
    foreach ($this->getXML()->documentElement->childElementsArray as $e) {
      if ($e->nodeName != "var" || !$e->hasAttribute("id")) {
        continue;
      }
      $this->vars[$e->getAttribute("id")] = $e;
    }
    $this->generateMenu();
    $os = Cms::getOutputStrategy();
    $os->addCssFile($this->pluginDir."/".$this->className.".css");
    $xsl = $this->vars["xsl"]->nodeValue;
    if (!strlen($xsl)) {
      return;
    }
    $os->addTransformation($xsl);
  }

  private function generateMenu () {
    $menu = new DOMDocumentPlus();
    $root = $menu->createElement("root");
    $menu->appendChild($root);
    $curLink = get_link();
    $idToLi = [];
    $idToLevel = [];
    foreach (HTMLPlusBuilder::getIdToFile() as $id => $file) {
      if ($file != INDEX_HTML) {
        break;
      }
      $parentId = HTMLPlusBuilder::getIdToParentId($id);
      if (is_null($parentId)) {
        $idToLi[$id] = $root;
        $idToLevel[$id] = 0;
        continue;
      }
      $parentUl = $idToLi[$parentId]->lastElement;
      if (is_null($parentUl) || $parentUl->nodeName != "ul") {
        $parentUl = $menu->createElement("ul");
        $idToLi[$parentId]->appendChild($parentUl);
      }
      $parentUl->appendChild($menu->createTextNode("\n"));
      $li = $parentUl->appendChild($menu->createElement("li"));
      $a = $menu->createElement("a", HTMLPlusBuilder::getHeading($id));
      $li->appendChild($a);
      $link = HTMLPlusBuilder::getIdToLink($id);
      if ($link === $curLink) {
        $p = $li;
        while (!is_null($p)) {
          if ($p->nodeName == "li") {
            $p->firstElement->setAttribute("class", "current");
          } // TODO: li.current
          $p = $p->parentNode->parentNode;
        }
      } else {
        $a->setAttribute("href", $id);
      }
      $idToLi[$id] = $li;
      $idToLevel[$id] = $idToLevel[$parentId] + 1;
    }
    $maxLevel = $this->vars["menudepth"]->nodeValue;
    if (!is_numeric($maxLevel)) {
      $maxLevel = 1;
    }
    foreach ($idToLi as $id => $li) {
      if ($idToLevel[$id] != $maxLevel) {
        continue;
      }
      $ul = $li->lastElement;
      if ($ul->nodeName == "ul") {
        $li->removeChild($ul);
      }
    }
    if (is_null($root->firstElement)) {
      return;
    }
    $root->firstElement->setAttribute("class", "globalmenu noprint");
    Cms::setVariable("", $menu->documentElement);
  }

}
