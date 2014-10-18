<?php

class DOMDocumentPlus extends DOMDocument {
  const DEBUG = false;

  function __construct($version="1.0",$encoding="utf-8") {
    if(self::DEBUG) new Logger("DEBUG");
    parent::__construct($version,$encoding);
    $r = $this->registerNodeClass("DOMElement","DOMElementPlus");
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
    if($prefix != "") {
      new Logger("DEPRECATED: Using variable prefix","warning");
      $varName = $prefix.":".$varName;
    }
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
      if(is_null($e->parentNode)) continue;
      switch($type) {
        case "NULL":
        $this->removeVarElement($e);
        break;
        case "string":
        $e = $this->prepareIfDl($e,$varName);
        $this->insertVarString($varValue,$e,$attr,$varName);
        break;
        case "array":
        if(empty($varValue)) {
          $this->emptyVarArray($e);
          continue;
        }
        $e = $this->prepareIfDl($e,$varName);
        $this->insertVarArray($varValue,$e,$varName);
        break;
        default:
        if($varValue instanceof DOMElement) {
          $this->insertVarDOMElement($varValue,$e,$attr);
          break;
        }
        new Logger("Unsupported variable type '$type' for '$varName'","error");
      }
    }
  }

  public function validateLinks($elName,$attName,$repair) {
    $toStrip = array();
    foreach($this->getElementsByTagName($elName) as $e) {
      if(!$e->hasAttribute($attName)) continue;
      try {
        $link = $this->repairLink($e->getAttribute($attName));
        if($link === $e->getAttribute($attName)) continue;
        if(!$repair)
          throw new Exception("Invalid repairable link '".$e->getAttribute($attName)."'");
        $e->setAttribute($attName,$link);
      } catch(Exception $ex) {
        if(!$repair) throw $ex;
        $toStrip[] = array($e,$ex->getMessage());
      }
    }
    foreach($toStrip as $a) $a[0]->stripAttr($attName,$a[1]);
  }

  private function repairLink($link=null) {
    if(is_null($link)) $link = getCurLink(); // null -> currentLink
    $pLink = parse_url($link);
    if($pLink === false) throw new LoggerException("Unable to parse href '$link'"); // fail2parse
    if(isset($pLink["scheme"])) { // link is in absolute form
      $curDomain = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . getRoot();
      if(strpos($link,$curDomain) !== 0) return $link; // link is external
    }
    if(isset($pLink["fragment"])) return "#" . $pLink["fragment"];
    $path = isset($pLink["path"]) ? $pLink["path"] : "";
    while(strpos($path,".") === 0) $path = substr($path,1);
    if(strpos($path,getRoot()) === 0) $path = substr($path,strlen(getRoot()));
    $query = isset($pLink["query"]) ? "?" . $pLink["query"] : "";
    if(strlen($path . $query)) return $path . $query;
    return "#".$this->documentElement->firstElement->getAttribute("id");
  }

  public function fragToLinks(HTMLPlus $src,$root="/") {
    $toStrip = array();
    foreach($this->getElementsByTagName("a") as $a) {
      if(!$a->hasAttribute("href")) continue; // no link found
      $pLink = parse_url($a->getAttribute("href"));
      // expecting only 3 variables:
      // 1) is absolute link
      // 2) is internal fragment
      // 3) is internal link
      // 4) is internal query
      if(isset($pLink["scheme"])) continue;
      if(isset($pLink["path"]) || isset($pLink["query"])) {
        $path = isset($pLink["path"]) ? $pLink["path"] : getCurLink();
        $query = isset($pLink["query"]) ? "?".$pLink["query"] : "";
        $linkedElement = $src->getElementById($path,"link");
        if(is_null($linkedElement)) {
          $toStrip[] = array($a,"link '".$pLink["path"]."' not found");
          continue; // link not exists
        }
        if(getCurLink(true) == $path.$query) {
          $toStrip[] = array($a,"cyclic link found");
          continue;
        }
        $a->setAttribute("href",$root.$path.$query);
        continue;
      }
      $frag = $pLink["fragment"];
      $linkedElement = $this->getElementById($frag);
      if(!is_null($linkedElement)) {
        if($this->getElementsByTagName("h1")->item(0)->isSameNode($linkedElement)) {
          $toStrip[] = array($a,"cyclic fragment found");
        }
        continue; // ignore visible headings
      }
      $linkedElement = $src->getElementById($frag);
      if(is_null($linkedElement)) {
        $toStrip[] = array($a,"id '$frag' not found");
        continue; // id not exists
      }
      if($linkedElement->nodeName == "h" && $linkedElement->hasAttribute("link")) {
        $a->setAttribute("href",$root.$linkedElement->getAttribute("link"));
        continue; // is outter h1
      }
      $h = $linkedElement->parentNode->getPreviousElement("h");
      while(!is_null($h) && !$h->hasAttribute("link")) {
        $h = $h->parentNode->getPreviousElement("h");
      }
      if(is_null($h)) {
        $h1 = $src->documentElement->firstElement;
        if($h1->getAttribute("id") == $frag) {
          $a->setAttribute("href",$root);
          continue; // link to root heading
        }
        $a->setAttribute("href",$root."#".$frag);
        continue; // no link attribute until root heading
      }
      $a->setAttribute("href",$root.$h->getAttribute("link")."#".$frag);
    }
    foreach($toStrip as $a) $a[0]->stripAttr("href",$a[1]);
  }

  private function prepareIfDl(DOMElement $e,$varName) {
    if($e->nodeName != "dl") return $e;
    $e->removeChildNodes();
    $e->appendChild($e->ownerDocument->createElement("dt",$varName));
    return $e->appendChild($e->ownerDocument->createElement("dd"));
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
        $i = 1;
        while(array_key_exists($id.$i, $ids)) $i++;
        $id = $id.$i;
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
        if(self::DEBUG) die($this->saveXML());
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

  private function insertVarString($varValue,DOMElement $e,$attr,$varName) {
    if(!validateXMLMarkup($varValue,$varName)) return;
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

  private function insertVarArray(Array $varValue,DOMElement $e,$varName) {
    $sep = null;
    switch($e->nodeName) {
      case "body":
      case "section":
      case "dl":
      case "form":
      case "fieldset":
      return;
      case "li":
      case "dd":
      break;
      case "em":
      case "strong":
      case "samp":
      case "del":
      case "ins":
      $sep = $e->ownerDocument->createTextNode(", ");
      break;
      case "ul":
      case "ol":
      $e->removeChildNodes();
      $e = $e->appendChild($e->ownerDocument->createElement("li"));
      break;
      default:
      $dom = new DOMDocument();
      $dom->loadXML("<var><em>".implode("</em>, <em>",$varValue)."</em></var>");
      $this->insertVarDOMElement($dom->documentElement,$e,null,$varName);
      return;
    }
    $p = $e->parentNode;
    foreach($varValue as $v) {
      if(!validateXMLMarkup($v,$varName)) continue;
      $i = $p->insertBefore($e->cloneNode(),$e);
      $i->nodeValue = $v;
      if(!is_null($sep)) $i = $p->insertBefore($sep->cloneNode(),$e);
    }
    if(!is_null($sep)) $p->removeChild($i);
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