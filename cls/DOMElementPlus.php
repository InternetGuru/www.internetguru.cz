<?php

class DOMElementPlus extends DOMElement {

  public function stripTag($comment = null) {
    $text = $this->ownerDocument->createTextNode($this->nodeValue);
    $this->parentNode->insertBefore($text,$this);
    $this->parentNode->removeChild($this);
    if(is_null($comment)) return;
    $cmt = $this->ownerDocument->createComment(" $comment ");
    $text->parentNode->insertBefore($cmt,$text);
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
      default:
      parent::__get($name);
    }
  }

  private function getNextSiblingElement() {
    $e = $this;
    while(!is_null($e->nextSibling) && $e->nextSibling->nodeType != XML_ELEMENT_NODE) $e = $e->nextSibling;
    return $e->nextSibling;
  }

  private function getPreviousSiblingElement() {
    $e = $this;
    while(!is_null($e->previousSibling) && $e->previousSibling->nodeType != XML_ELEMENT_NODE) $e = $e->previousSibling;
    return $e->previousSibling;
  }

  private function getChildElements() {
    $xpath = new DOMXPath($this->ownerDocument);
    return $xpath->query($this->getNodePath() . "/node()[not(self::text())]");
  }

}
?>