<?php

class DOMElementPlus extends DOMElement {

  public function rename($name) {
    $newnode = $this->ownerDocument->createElement($name);
    $children = array();
    foreach ($this->childElements as $child) {
      $children[] = $child;
    }
    foreach ($children as $child) {
      $child = $this->ownerDocument->importNode($child, true);
      $newnode->appendChild($child);
    }
    foreach ($this->attributes as $attrName => $attrNode) {
      $newnode->setAttribute($attrName, $attrNode->nodeValue);
    }
    $this->parentNode->replaceChild($newnode, $this);
    return $newnode;
  }

  public function insertVar($varName, $varValue) {
    $this->ownerDocument->insertVar($varName, $varValue, $this);
  }

  public function insertFn($varName, $varValue) {
    $this->ownerDocument->insertFn($varName, $varValue, $this);
  }

  public function stripElement($comment = null) {
    $this->stripTag($comment, false);
  }

  public function stripTag($comment = null, $keepContent = true) {
    if(!is_null($comment)) {
      $cmt = $this->ownerDocument->createComment(" $comment ");
      $this->parentNode->insertBefore($cmt, $this);
    }
    if($keepContent) {
      foreach($this->childNodes as $n) $children[] = $n;
      foreach($children as $n) $this->parentNode->insertBefore($n, $this);
    }
    $this->parentNode->removeChild($this);
  }

  public function stripAttr($attr, $comment = null) {
    if(!$this->hasAttribute($attr)) return;
    $this->removeAttribute($attr);
    if(is_null($comment)) $comment = sprintf(_("Attribute '%s' stripped"), $attr);
    $cmt = $this->ownerDocument->createComment(" $comment ");
    $this->parentNode->insertBefore($cmt, $this);
  }

  public function getPreviousElement($eName=null) {
    if(is_null($eName)) $eName = $this->nodeName;
    $e = $this->previousElement;
    if(is_null($e)) $e = $this->parentNode;
    while($e instanceof DOMElement) {
      if($e->nodeName == $eName) return $e;
      if(!is_null($e->previousElement)) $e = $e->previousElement;
      else $e = $e->parentNode;
    }
    return null;
  }

  public function getAncestorValue($attName=null, $eName=null) {
    if(is_null($eName)) $eName = $this->nodeName;
    $ancestor = $this->parentNode;
    while(!is_null($ancestor)) {
      if(!is_null($attName) && $ancestor->hasAttribute($attName)) {
        return $ancestor->getAttribute($attName);
      } elseif(is_null($attName) && strlen($ancestor->nodeValue)) {
        return htmlspecialchars($ancestor->nodeValue);
      }
      $ancestor = $ancestor->getPreviousElement($eName);
      if(is_null($ancestor)) return null;
    }
    return null;
  }

  public function removeChildNodes() {
    $r = array();
    foreach($this->childNodes as $n) $r[] = $n;
    foreach($r as $n) $this->removeChild($n);
  }

  public function __get($name) {
    switch($name) {
      case "nextElement":
      return $this->getNextSiblingElement();
      break;
      case "previousElement":
      return $this->getPreviousSiblingElement();
      break;
      case "childElements":
      return $this->getChildElements();
      break;
      case "firstElement":
      return $this->getFirstElement();
      break;
      #default:
      #return parent::__get($name);
    }
  }

  private function getNextSiblingElement() {
    $e = $this->nextSibling;
    while(!is_null($e) && $e->nodeType != XML_ELEMENT_NODE) $e = $e->nextSibling;
    return $e;
  }

  private function getPreviousSiblingElement() {
    $e = $this->previousSibling;
    while(!is_null($e) && $e->nodeType != XML_ELEMENT_NODE) $e = $e->previousSibling;
    return $e;
  }

  private function getFirstElement() {
    if(!$this->childElements->length) return null;
    return $this->childElements->item(0);
  }

  private function getChildElements() {
    $xpath = new DOMXPath($this->ownerDocument);
    return $xpath->query($this->getNodePath()."/node()[not(self::text() or self::comment() or self::processing-instruction())]");
  }

}
?>