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

  public function insertVar($varName,$varValue,$plugin="") {
    $xpath = new DOMXPath($this);
    if($plugin == "") $plugin = "Cms";
    $var = "{".$plugin.":".$varName."}";
    if(is_string($varValue)) {
      $where = $xpath->query("//@*[contains(.,'$var')]");
      $this->insertVarString($var,$varValue,$where);
    }
    $where = $xpath->query("//text()[contains(.,'$var')]");
    if($where->length == 0) return;
    $type = gettype($varValue);
    if($type == "object") $type = get_class($varValue);
    switch($type) {
      case "string":
      $this->insertVarString($var,$varValue,$where);
      break;
      case "array":
      $this->insertVarArray($var,$varValue,$where);
      break;
      case "DOMElement":
      $this->insertVarDOMElement($var,$varValue,$where);
      break;
      default:
      throw new Exception("Unsupported type '$type'");
    }
  }

  private function insertVarString($varName,$varValue,DOMNodeList $where) {
    foreach($where as $e) {
      $e->nodeValue = str_replace($varName, $varValue, $e->nodeValue);
    }
  }

  private function insertVarArray($varName,Array $varValue,DOMNodeList $where) {
    if(empty($varValue)) {
      $this->insertVarString($varName,"",$where);
      return;
    }
    $doc = new DOMDocument();
    $ul = $doc->createElement("ul");
    foreach($varValue as $i) $ul->appendChild($doc->createElement("li",$i));
    $this->insertVarDOMElement($varName,$ul,$where);
  }

  private function insertVarDOMElement($varName,DOMElement $varValue,DOMNodeList $where) {
    $into = array();
    foreach($where as $e) $into[] = $e;
    foreach($into as $e) {
      $newParent = $e->parentNode->cloneNode();
      $children = array();
      foreach($e->parentNode->childNodes as $ch) $children[] = $ch;
      foreach($children as $ch) {
        if(!$ch->isSameNode($e)) {
          $newParent->appendChild($ch);
          continue;
        }
        $parts = explode($varName,$ch->nodeValue);
        foreach($parts as $id => $part) {
          $newParent->appendChild($e->ownerDocument->createTextNode($part));
          if((count($parts)-1) == $id) continue;
          $appendInto = array();
          foreach($varValue->childNodes as $n) $appendInto[] = $n;
          foreach($appendInto as $n) {
            $newParent->appendChild($e->ownerDocument->importNode($n,true));
          }
        }
      }
      $e->parentNode->parentNode->replaceChild($e->ownerDocument->importNode($newParent),$e->parentNode);
    }
  }

}
?>