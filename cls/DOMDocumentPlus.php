<?php

class DOMDocumentPlus extends DOMDocument {

  function __construct($version="1.0",$encoding="utf-8") {
    parent::__construct($version,$encoding);
    $r = $this->registerNodeClass("DOMElement","DOMElementPlus");
    #$this->preserveWhiteSpace = false;
    #$this->formatOutput = true;
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
    foreach ($node->childElements as $child) {
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
    foreach($where as $attr => $e) {
      switch($type) {
        case "NULL":
        $this->removeVarElement($e);
        break;
        case "string":
        $this->insertVarString($varValue,$e,$attr);
        break;
        case "array":
        if(empty($varValue)) $this->emptyVarArray($e);
        else $this->insertVarArray($varValue,$e);
        break;
        default:
        if($varValue instanceof DOMElement) {
          $this->insertVarDOMElement($varValue,$e,$attr);
          break;
        }
        throw new Exception("Unsupported variable type '$type' for '$varName'");
      }
    }
  }

  public function removeNodes($query) {
    $xpath = new DOMXPath($this);
    $toRemove = array();
    foreach($xpath->query($query) as $n) $toRemove[] = $n;
    foreach($toRemove as $n) {
      $n->parentNode->removeChild($n);
    }
    return count($toRemove);
  }

  public function validatePlus() {
    $this->validateId();
    return true;
  }

  public function validateId($attr="id",$repair=false) {
    $xpath = new DOMXPath($this);
    $ids = array();
    foreach($xpath->query("//*[@$attr]") as $e) {
      $id = $e->getAttribute($attr);
      if(array_key_exists($id, $ids)) {
        if(!$repair) throw new Exception("Duplicit $attr attribute '$id' found");
        $id = $id."1";
        $e->setAttribute("id",$id);
      }
      $ids[$id] = null;
    }
    return true;
  }

  public function removeUntilSame(DOMElement $e) {
    $name = $e->nodeName;
    $toRemove = array();
    while(true) {
      $toRemove[] = $e;
      $e = $e->nextElement;
      if(is_null($e)) break;
      if($e->nodeName == $name) break;
    }
    foreach($toRemove as $e) {
      $e->parentNode->removeChild($e);
    }
  }

  public function relaxNGValidatePlus($f) {
    if(!file_exists($f))
      throw new Exception ("Unable to find HTMLPlus RNG schema '$f'");
    try {
      libxml_use_internal_errors(true);
      if(!$this->relaxNGValidate($f))
        throw new Exception("relaxNGValidate internal error occured");
    } catch (Exception $e) {
      $internal_errors = libxml_get_errors();
      if(count($internal_errors)) {
        $note = " [Caution: this message may be misleading]";
        $e = new Exception(current($internal_errors)->message . $note);
      }
    }
    // finally
    libxml_clear_errors();
    libxml_use_internal_errors(false);
    if(isset($e)) throw $e;
    return true;
  }

  public function setUniqueId(DOMElement $e) {
    $id = $e->nodeName .".". substr(md5(microtime()),0,3);
    if(!$this->isValidId($id)) $this->setUniqueId($e);
    if(!is_null($this->getElementById($id))) $this->setUniqueId($e);
    $e->setAttribute("id",$id);
  }

  protected function isValidId($id) {
    return (bool) preg_match("/^[A-Za-z][A-Za-z0-9_:\.-]*$/",$id);
  }

  private function removeVarElement($e) {
    $e->parentNode->removeChild($e);
  }

  private function insertVarString($varValue,DOMElement $e,$attr=null) {
    if(!is_null($attr) && !is_numeric($attr)) {
      if(!$e->hasAttribute($attr) || $e->getAttribute($attr) == "") {
        $e->setAttribute($attr,$varValue);
        return;
      }
      if($attr == "class") $varValue = $e->getAttribute($attr)." ".$varValue;
      $e->setAttribute($attr,$varValue);
      return;
    }
    $varValue = htmlspecialchars($varValue);
    $replaced = false;
    foreach($e->childNodes as $n) {
      if($n->nodeType != 3) continue;
      $new = sprintf($n->nodeValue,$varValue);
      if($new == $n->nodeValue) continue;
      $n->nodeValue = $new;
      $replaced = true;
      break;
    }
    if(!$replaced) $e->nodeValue = $varValue;
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
    if($p->childElements->length == 0)
      $p->parentNode->removeChild($p);
  }

  // full replace only
  private function insertVarDOMElement(DOMElement $varValue,DOMElement $e,$attr=null) {
    if(!is_null($attr) && !is_numeric($attr)) {
      $this->insertVarstring($varValue->nodeValue,$e,$attr);
      return;
    }
    // clear destination element
    $e->removeChildNodes();
    // fill destination element
    $var = $e->ownerDocument->importNode($varValue,true);
    $children = array();
    foreach($var->childNodes as $n) $children[] = $n;
    foreach($children as $n) $e->appendChild($n);
  }

}
?>