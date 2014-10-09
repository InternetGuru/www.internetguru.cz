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
    if(is_null($menu)) return array();
    $menu->setAttribute("class","cms-menu");
    $this->trimList($menu);
    $doc->appendChild($menu);
    $cms->setVariable("cms-menu", $doc);
  }

  private function trimList(DOMElement $ul) {
    $currentLink = false;
    $deepLink = false;
    foreach($ul->childNodes as $li) {
      foreach($li->childNodes as $n) {
        if($this->isProperLink($n)) $currentLink = true;
        if($n->nodeName == "ul") $deepLink = $this->trimList($n);
      }
    }
    if($currentLink || $deepLink) return true;
    $ul->parentNode->removeChild($ul);
    return false;
  }

  private function isProperLink(DOMElement $n) {
    if($n->nodeName != "a") return false;
    if($n->hasAttribute("class") && $n->getAttribute("class") == "fragment") return false;
    return true;
  }

  private function getMenu(DOMDocumentPlus $doc, DOMElement $section, $parentLink = null) {
    if(is_null($parentLink)) $parentLink = getRoot();
    $ul = $doc->createElement("ul");
    $li = null;
    foreach($section->childNodes as $n) {
      if($n->nodeType != 1) continue;
      if($n->nodeName == "section") {
        $menu = $this->getMenu($doc,$n,$parentLink);
        if(!is_null($menu)) {
          $li->appendChild($menu);
        }
        continue;
      }
      if($n->nodeName != "h") continue;
      $li = $doc->createElement("li");
      $parentLink = getRoot();
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
      if($this->subject->getCms()->getLink() === $link) {
        $a->setAttribute("class","current");
      } else {
        if(!is_null($link)) $a->setAttribute("href",$link);
        else {
          $a->setAttribute("href","$parentLink#".$n->getAttribute("id"));
          $a->setAttribute("class","fragment");
        }
      }
      $li->appendChild($a);
      $ul->appendChild($li);
    }
    if(is_null($li)) return null;
    return $ul;
  }

}

?>