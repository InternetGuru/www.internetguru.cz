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

  public function removeAllAttributes(Array $except = array()) {
    $toRemove = array();
    foreach($this->attributes as $a) {
      if(in_array($a->nodeName, $except)) continue;
      $toRemove[] = $a;
    }
    foreach($toRemove as $a) {
      $this->removeAttribute($a->nodeName);
    }
  }

  public function hasClass($class) {
    return in_array($class, explode(" ", $this->getAttribute("class")));
  }

  public function addClass($class) {
    if($this->hasClass($class)) return;
    if(!$this->hasAttribute("class")) $this->setAttribute("class", $class);
    else $this->setAttribute("class", $this->getAttribute("class")." $class");
  }

  public function processVariables(Array $variables, $ignore = array()) {
    foreach($this->getVariables("var", $ignore) as list($vName, $aName)) {
      $v = array_key_exists($vName, $variables) ? $variables[$vName] : null;
      try {
        $this->insertVariable($v, $aName);
      } catch(Exception $e) {
        new Logger(sprintf(_("Unable to insert variable %s: %s"), $vName, $e->getMessage()), Logger::LOGGER_ERROR);
      }
      if(is_null($aName)) return;
    }
  }

  public function processFunctions(Array $functions, $ignore = array()) {
    foreach($this->getVariables("fn", $ignore) as list($vName, $aName)) {
      $f = array_key_exists($vName, $functions) ? $functions[$vName] : null;
      if(is_null($f)) continue;
      $v = call_user_func($f, is_null($aName) ? $this->nodeValue : $this->getAttribute($aName));
      try {
        $this->insertVariable($v, $aName);
      } catch(Exception $e) {
        new Logger(sprintf(_("Unable to insert function %s: %s"), $vName, $e->getMessage()), Logger::LOGGER_ERROR);
      }
      if(is_null($aName)) return;
    }
  }

  private function insertVariable($value, $aName) {
    if(is_null($this->parentNode)) return;
    switch(gettype($value)) {
      case "NULL":
      break;
      case "integer":
      case "boolean":
      $value = (string) $value;
      case "string":
      if(!strlen($value)) $this->removeSelf($aName);
      $this->insertVarString($value, $aName);
      break;
      case "array":
      if(empty($value)) $this->removeSelf($aName);
      #$this = $this->prepareIfDl($this, $varName);
      $this->insertVarArray($value, $aName);
      break;
      default:
      if($value instanceof DOMDocumentPlus) {
        $this->insertVarDOMElement($value->documentElement, $aName);
        break;
      }
      if($value instanceof DOMElement) {
        $this->insertVarDOMElement($value, $aName);
        break;
      }
      throw new Exception(sprintf(_("Unsupported variable type %s"), get_class($value)));
    }
  }

  private function removeSelf($aName) {
    if(is_null($aName)) {
      $this->emptyRecursive();
      return;
    }
    $this->removeAttribute($aName);
  }

  private function emptyRecursive() {
    $p = $this->parentNode;
    $p->removeChild($this);
    if($p->nodeType != XML_ELEMENT_NODE) return;
    if($p->childElements->length != 0) return;
    $p->emptyRecursive();
  }

  private function insertVarString($value, $aName) {
    if(is_null($aName)) {
      $this->insertInnerHTML($value, "");
      return;
    }
    if(!$this->hasAttribute($aName) || $this->getAttribute($aName) == "") {
      if(strlen($value)) $this->setAttribute($aName, $value);
      elseif($this->hasAttribute($aName)) $this->removeAttribute($aName);
      return;
    }
    $temp = @sprintf($this->getAttribute($aName), $value);
    if($temp !== false && $temp != $this->getAttribute($aName)) {
      $this->setAttribute($aName, $temp);
      return;
    }
    if(!strlen($value)) {
      $this->removeAttribute($aName);
      return;
    }
    if($aName == "class") $this->addClass($value);
    else $this->setAttribute($aName, $value);
  }

  private function insertVarArray(Array $value, $aName) {
    if(!is_null($aName)) {
      $this->insertVarString(implode(" ", $value), $aName);
      return;
    }
    $sep = null;
    switch($this->nodeName) {
      case "li":
      case "dd":
      break;
      case "em":
      case "strong":
      case "samp":
      case "span":
      case "del":
      case "ins":
      case "sub":
      case "sup":
      $sep = ", ";
      break;
      case "ul":
      case "ol":
      $this->removeChildNodes();
      $e = $this->appendChild($this->ownerDocument->createElement("li"));
      $e->insertInnerHTML($value, $sep);
      return;
      #case "body":
      #case "section":
      #case "dl":
      #case "form":
      #case "fieldset":
      default:
      throw new Exception(sprintf(_("Unable to insert array into '%s'"), $this->nodeName));
      return;
    }
    $this->insertInnerHTML($value, $sep);
  }

  private function insertInnerHTML($html, $sep) {
    if(!is_array($html)) $html = array($html);
    $dom = new DOMDocumentPlus();
    $eNam = $this->nodeName;
    $xml = "<var><$eNam>".implode("</$eNam>$sep<$eNam>", $html)."</$eNam></var>";
    if(!@$dom->loadXML($xml)) {
      $var = $dom->appendChild($dom->createElement("var"));
      foreach($html as $k => $v) {
        $e = $var->appendChild($dom->createElement($eNam));
        $e->nodeValue = htmlspecialchars($html[$k]);
      }
    }
    $this->insertVarDOMElement($dom->documentElement, null);
  }

  private function insertVarDOMElement(DOMElement $element, $aName) {
    if(!is_null($aName)) {
      $this->insertVarString($element->nodeValue, $aName);
    }

    $var = $this->ownerDocument->importNode($element, true);
    $attributes = array();
    foreach($this->attributes as $attr) $attributes[$attr->nodeName] = $attr->nodeValue;
    $nodes = array();
    foreach($var->childNodes as $n) $nodes[] = $n;
    if(is_null($this->parentNode)) return;
    foreach($nodes as $n) {
      $this->parentNode->insertBefore($n, $this);
      if($n->nodeType != XML_ELEMENT_NODE) continue;
      foreach($attributes as $aName => $aValue) $n->setAttribute($aName, $aValue);
    }
    $this->parentNode->removeChild($this);
    # ??? $this->removeChildNodes();
  }

  private function getVariables($attr, Array $ignore) {
    $variables = array();
    if(!$this->hasAttribute($attr)) return $variables;
    foreach(explode(" ", $this->getAttribute($attr)) as $var) {
      list($vName, $aName) = array_pad(explode("@", $var), 2, null);
      if(in_array($aName, $ignore)) {
        new Logger(sprintf(_("Cannot modify attribute %s in element %s"), $aName, $this->nodeName), Logger::LOGGER_WARNING);
        continue;
      }
      if(is_null($aName)) $variables[] = array($vName, $aName);
      else array_unshift($variables, array($vName, $aName));
    }
    return $variables;
  }

  public function insertVar($varName, $varValue) {
    new Logger(sprintf(METHOD_NA, __FUNCTION__), Logger::LOGGER_ERROR);
    return;
    $this->ownerDocument->insertVar($varName, $varValue, $this);
  }

  public function insertFn($varName, $varValue) {
    new Logger(sprintf(METHOD_NA, __FUNCTION__), Logger::LOGGER_ERROR);
    return;
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

  private function removeChildNodes() {
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