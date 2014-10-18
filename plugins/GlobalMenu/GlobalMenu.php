<?php

#TODO: title

class GlobalMenu extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $subject->setPriority($this,60);
    $this->setVariables();
  }

  public function setVariables() {
    $doc = new DOMDocumentPlus();
    $cms = $this->subject->getCms();
    $xpath = new DOMXPath($cms->getContentFull());
    $menu = $this->getMenu($doc,$xpath->query("/body/section")->item(0));
    $doc->appendChild($menu);
    $menu->setAttribute("class","globalmenu");
    $this->trimList($menu);
    $cms->setVariable($doc);
  }

  private function trimList(DOMElement $ul) {
    $currentLink = false;
    $deepLink = false;
    foreach($ul->childElements as $li) {
      foreach($li->childElements as $n) {
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

  private function getMenu(DOMDocumentPlus $doc, DOMElement $section, $parentLink = null) {
    if(is_null($parentLink)) $parentLink = "";
    $ul = $doc->createElement("ul");
    $li = null;
    foreach($section->childElements as $n) {
      if($n->nodeName == "section") {
        $menu = $this->getMenu($doc,$n,$parentLink);
        if(!is_null($menu)) {
          $li->appendChild($menu);
        }
        continue;
      }
      if($n->nodeName != "h") continue;
      $li = $doc->createElement("li");
      $parentLink = "";
      $link = null;
      if($n->hasAttribute("link")) {
        $link = $n->getAttribute("link");
        $parentLink = $link;
      }
      $a = $doc->createElement("a",$n->nodeValue);
      if($n->hasAttribute("short")) {
        $a->nodeValue = $n->getAttribute("short");
        $a->setAttribute("title",$n->nodeValue);
      }
      if(getCurLink() === $link) $a->setAttribute("class","current");
      $a->setAttribute("href","#".$n->getAttribute("id"));
      if(is_null($link)) $a->setAttribute("class","fragment");
      $li->appendChild($a);
      $ul->appendChild($li);
    }
    if(is_null($li)) return null;
    return $ul;
  }

}

?>