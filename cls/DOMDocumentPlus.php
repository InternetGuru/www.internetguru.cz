<?php

class DOMDocumentPlus extends DOMDocument {

  function __construct($version="1.0",$encoding="utf-8") {
    parent::__construct($version,$encoding);
    $this->preserveWhiteSpace = false;
    $this->formatOutput = true;
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

  public function insertVar($varName,$varValue,$plugin="") {
    $xpath = new DOMXPath($this);
    $noparse = "*[not(contains(@class,'noparse')) and (not(ancestor::*) or ancestor::*[not(contains(@class,'noparse'))])]/";
    #$noparse = "*/";
    if($plugin == "") $plugin = "Cms";
    $var = "{".$plugin.":".$varName."}";
    if(is_string($varValue)) {
      $where = $xpath->query(sprintf("//%s@*[contains(.,'%s')]",$noparse,$var));
      $this->insertVarString($var,$varValue,$where);
    }
    $where = $xpath->query(sprintf("//%stext()[contains(.,'%s')]",$noparse,$var));
    if($where->length == 0) return;
    $type = gettype($varValue);
    if($type == "object") $type = get_class($varValue);
    switch($type) {
      case "string":
      $this->insertVarString($var,$varValue,$where);
      break;
      case "array":
      $this->insertVarArray($var,$varValue,$where,$varName);
      break;
      case "DOMElement":
      $varxpath = new DOMXPath($varValue->ownerDocument);
      $varValue = $varxpath->query("/*");
      case "DOMNodeList":
      $this->insertVarDOMNodeList($var,$varValue,$where);
      break;
      default:
      throw new Exception("Unsupported type '$type'");
    }
  }

  public function saveRewrite($filepath) {
    $b = $this->save("$filepath.new");
    if($b === false) return false;
    if(!copy($filepath,"$filepath.old")) return false;
    if(!rename("$filepath.new",$filepath)) return false;
    return $b;
  }

  private function insertVarString($varName,$varValue,DOMNodeList $where) {
    foreach($where as $e) {
      $e->nodeValue = str_replace($varName, $varValue, $e->nodeValue);
    }
  }

  private function insertVarArray($varName,Array $varValue,DOMNodeList $where, $var) {
    if(empty($varValue)) {
      $this->insertVarString($varName,"",$where);
      return;
    }
    $doc = new DOMDocument();
    $list = $doc->appendChild($doc->createElement("ol"));
    $list->setAttribute("class",$var);
    foreach($varValue as $i) $list->appendChild($doc->createElement("li",$i));
    $varxpath = new DOMXPath($doc);
    $varValue = $varxpath->query("/*");
    $this->insertVarDOMNodeList($varName,$varValue,$where);
  }

  private function insertVarDOMNodeList($varName,DOMNodeList $varValue,DOMNodeList $where) {
    $into = array();
    foreach($where as $e) $into[] = $e;
    foreach($into as $e) {
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

}
?>