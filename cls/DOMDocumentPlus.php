<?php

class DOMDocumentPlus extends DOMDocument {

  function __construct($version="1.0",$encoding="utf-8") {
    parent::__construct($version,$encoding);
    $this->preserveWhiteSpace = false;
    $this->formatOutput = true;
  }

  public function getElementById($id,$attribute="id") {
    $xpath = new DOMXPath($this);
    $q = $xpath->query("//*[@$attribute='$id']");
    if($q->length == 0) return null;
    if($q->length > 1)
      throw new Exception("Duplicit $attribute found for value '$id'");
    return $q->item(0);
  }

  public function renameElement($node, $name) {
    $newnode = $this->createElement($name);
    $children = array();
    foreach ($node->childNodes as $child) {
      $children[] = $child;
    }
    foreach ($children as $child) {
      $child = $this->importNode($child, true);
      $newnode->appendChild($child);
    }
    foreach ($node->attributes as $attrName => $attrNode) {
      $newnode->setAttribute($attrName, $attrNode->nodeValue);
    }
    $node->parentNode->replaceChild($newnode, $node);
    return $newnode;
  }

  public function insertVar($varName,$varValue,$prefix="") {
    $xpath = new DOMXPath($this);
    $noparse = "*[not(contains(@class,'noparse')) and (not(ancestor::*) or ancestor::*[not(contains(@class,'noparse'))])]";
    #$noparse = "*";
    if($prefix != "") $varName = $prefix.":".$varName;
    // find elements with current var
    $matches = $xpath->query(sprintf("//%s[contains(@var,'%s')]",$noparse,$varName));
    $where = array();
    // check for attributes and real match (xpath accepts substrings)
    foreach($matches as $e) {
      $vars = explode(" ",$e->getAttribute("var"));
      $keep = array();
      foreach($vars as $v) {
        $p = explode("@",$v);
        if($varName != $p[0]) {
          $keep[] = $v;
          continue;
        }
        if(isset($p[1])) $where[$p[1]] = $e;
        else $where[] = $e;
      }
      if(empty($keep)) {
        $e->removeAttribute("var");
        continue;
      }
      $e->setAttribute("var",implode(" ",$keep));
    }
    if(!count($where)) return;
    $type = gettype($varValue);
    if($type == "object") $type = get_class($varValue);
    foreach($where as $a => $e) {
      switch($type) {
        case "string":
        $this->insertVarString($varValue,$e,$a);
        break;
        case "array":
        if(empty($varValue)) $this->emptyVarArray($e);
        else $this->insertVarArray($varValue,$e);
        break;
        case "DOMElement":
        $this->insertVarDOMElement($varValue,$e);
        break;
        default:
        throw new Exception("Unsupported type '$type'");
      }
    }
  }

  public function saveRewrite($filepath) {
    $b = $this->save("$filepath.new");
    if($b === false) return false;
    if(!copy($filepath,"$filepath.old")) return false;
    if(!rename("$filepath.new",$filepath)) return false;
    return $b;
  }

  private function insertVarString($varValue,DOMElement $e,$attr="") {
    if(strlen($attr) && !is_numeric($attr)) {
      if(!$e->hasAttribute($attr) || $e->getAttribute($attr) == "") {
        $e->setAttribute($attr,$varValue);
        return;
      }
      $e->setAttribute($attr,$e->getAttribute($attr)." ".$varValue);
      return;
    }
    $new = sprintf($e->nodeValue,$varValue);
    if($new != $e->nodeValue) $e->nodeValue = $new;
    else $e->nodeValue = $varValue;
  }

  private function insertVarArray(Array $varValue,DOMElement $e) {
    $p = $e->parentNode;
    foreach($varValue as $v) {
      $li = $p->appendChild($e->cloneNode());
      $li->nodeValue = $v;
    }
    $p->removeChild($e);
  }

  private function emptyVarArray(DOMElement $e) {
    if($e->nodeValue != "") return;
    $p = $e->parentNode;
    $p->removeChild($e);
    if($p->childNodes->length == 0)
      $p->parentNode->removeChild($p);
  }

  // full replace only
  private function insertVarDOMElement(DOMElement $varValue,DOMElement $e) {
    // clear destination element
    $children = array();
    foreach($e->childNodes as $n) $children[] = $n;
    foreach($children as $n) $e->removeChild($n);
    // fill destination element
    $var = $e->ownerDocument->importNode($varValue,true);
    $children = array();
    foreach($var->childNodes as $n) $children[] = $n;
    foreach($children as $n) $e->appendChild($n);
  }

  private function XinsertVarDOMElement(DOMElement $varValue,DOMElement $e) {
    $newParent = $e->parentNode->cloneNode();
    $e->ownerDocument->importNode($newParent);
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
        if((count($parts)-1) == $id) continue; // (not here) txt1 (here) txt2 (here) txt3 (not here)
        $append = array();
        foreach($varValue as $n) {
          if($n->nodeType === 1) $append[] = $n;
        }
        foreach($append as $n) {
          $newParent->appendChild($e->ownerDocument->importNode($n,true));
        }
      }
    }
    $e->parentNode->parentNode->replaceChild($newParent,$e->parentNode);
  }

}
?>