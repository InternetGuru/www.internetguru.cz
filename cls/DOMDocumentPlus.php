<?php

class DOMDocumentPlus extends DOMDocument {

  function __construct($version="1.0",$encoding="utf-8") {
    parent::__construct($version,$encoding);
  }

  public function getElementById($id) {
    $xpath = new DOMXPath($this);
    $q = $xpath->query("//*[@id='$id']");
    if($q->length == 0) return null;
    if($q->length > 1)
      throw new Exception("Duplicit ID found for value '$id'");
    return $q->item(0);
  }

  public function renameElement($node, $name) {
    $newnode = $this->createElement($name);
    foreach ($node->childNodes as $child) {
      $child = $this->importNode($child, true);
      $newnode->appendChild($child);
    }
    foreach ($node->attributes as $attrName => $attrNode) {
      $newnode->setAttribute($attrName, $attrNode);
    }
    $node->parentNode->replaceChild($newnode, $node);
    return $newnode;
  }

}
?>