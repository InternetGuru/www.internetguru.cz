<?php

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
    $menu->setAttribute("class", "globalmenu");
    $this->trimList($menu);
    if(!is_null($this->current)) $this->setCurrentClass($this->current);
    #var_dump($doc->saveXML());
    Cms::setVariable("globalmenu", $root);
  }

  private function trimList(DOMElement $ul) {
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
    $ul->parentNode->removeChild($ul);
    return false;
  }

  private function isProperLink(DOMElement $n) {
    if($n->nodeName != "a") return false;
    if($n->hasAttribute("class") && $n->getAttribute("class") == "fragment") return false;
    return true;
  }

  private function getMenu(DOMDocumentPlus $doc, DOMElement $section) {
    $ul = $doc->createElement("ul");
    $li = null;
    $prefix = Cms::getContentFull()->documentElement->firstElement->getAttribute("link");
    foreach($section->childElementsArray as $n) {
      if($n->nodeName == "section") {
        $menu = $this->getMenu($doc, $n);
        if(!is_null($menu)) {
          $li->appendChild($menu);
        }
        continue;
      }
      if($n->nodeName != "h") continue;
      $li = $doc->createElement("li");
      $link = null;
      if($n->hasAttribute("link")) $link = $n->getAttribute("link");
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