<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\Plugin;
use Exception;
use DOMXPath;
use DOMElement;
use SplObserver;
use SplSubject;

class GlobalMenu extends Plugin implements SplObserver {
  private $current = null;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;
    $this->setVariables();
  }

  public function setVariables() {
    $doc = new DOMDocumentPlus();
    $xpath = new DOMXPath(Cms::getContentFull());
    $sect = $xpath->query("/body/section")->item(0);
    if(is_null($sect)) return;
    $menu = $this->getMenu($doc, $sect);
    $root = $doc->appendChild($doc->createElement("root"));
    $root->appendChild($menu);
    $menu->setAttribute("class", "globalmenu noprint");
    $this->trimList($menu, true);
    if(!is_null($this->current)) $this->setCurrentClass($this->current);
    #var_dump($doc->saveXML());
    Cms::setVariable("globalmenu", $root);
  }

  private function trimList(DOMElement $ul, $root=false) {
    $currentLink = false;
    $deepLink = false;
    foreach($ul->childElementsArray as $li) {
      foreach($li->childElementsArray as $n) {
        if($this->isProperLink($n)) $currentLink = true;
        if($n->nodeName == "ul") $deepLink = $this->trimList($n);
      }
    }
    if($currentLink || $deepLink) return true;
    if($ul->isSameNode($ul->ownerDocument->documentElement)) return true;
    if(!$root) $ul->parentNode->removeChild($ul);
    return false;
  }

  private function isProperLink(DOMElement $n) {
    if($n->nodeName != "a") return false;
    if($n->hasAttribute("class") && $n->getAttribute("class") == "fragment") return false;
    return true;
  }

  private function getMenu(DOMDocumentPlus $doc, DOMElement $section, $lang=null) {
    $ul = $doc->createElement("ul");
    if(is_null($lang)) {
      $lang = Cms::getVariable("cms-lang");
      $ul->setAttribute("lang", $lang); //?
    }
    $li = null;
    $prefix = Cms::getContentFull()->documentElement->firstElement->getAttribute("id");
    foreach($section->childElementsArray as $n) {
      if($n->nodeName == "section") {
        $menu = $this->getMenu($doc, $n, $lang);
        if(is_null($menu)) continue;
        $curLang = $n->firstElement->getParentValue("xml:lang");
        if($curLang != $lang) $menu->setAttribute("lang", $curLang);
        $li->appendChild($menu);
      }
      if($n->nodeName != "h") continue;
      $li = $doc->createElement("li");
      #$link = null;
      #if($n->hasAttribute("link")) $link = $n->getAttribute("link");
      $link = $n->getAttribute("id");
      $a = $doc->createElement("a", $n->nodeValue);
      if($n->hasAttribute("short")) {
        $a->nodeValue = htmlspecialchars($n->getAttribute("short"));
        #$a->setAttribute("title", $n->nodeValue);
      }
      if(getCurLink() === $link) $this->current = $a;
      if(!is_null($link)) $a->setAttribute("href", $link);
      else $a->setAttribute("href", "$prefix#".$n->getAttribute("id"));
      if(is_null($link)) $a->setAttribute("class", "fragment");
      $li->appendChild($a);
      $ul->appendChild($li);
    }
    if(is_null($li)) return null;
    return $ul;
  }

  private function setCurrentClass(DOMElementPlus $a) {
    $a->setAttribute("class", $a->getAttribute("class")." current");
    $parentLi = $a->parentNode->parentNode->parentNode;
    if(is_null($parentLi) || $parentLi->nodeName != "li") return;
    $this->setCurrentClass($parentLi->firstElement);
  }

}

?>