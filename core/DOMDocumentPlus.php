<?php

class DOMDocumentPlus extends DOMDocument {
  const DEBUG = false;

  function __construct($version="1.0", $encoding="utf-8") {
    if(self::DEBUG) new Logger("DEBUG");
    parent::__construct($version, $encoding);
    $r = $this->registerNodeClass("DOMElement", "DOMElementPlus");
  }

  public function createElement($name, $value=null) {
    if(is_null($value)) return parent::createElement($name);
    return parent::createElement($name, htmlspecialchars($value));
  }

  public function getElementById($id, $aName="id", $eName = null) {
    try {
      if(!is_null($eName)) {
        $element = null;
        foreach($this->getElementsByTagName($eName) as $e) {
          if(!$e->hasAttribute($aName)) continue;
          if($e->getAttribute($aName) != $id) continue;
          if(!is_null($element)) throw new Exception();
          $element = $e;
        }
        return $element;
      } else {
        $xpath = new DOMXPath($this);
        $q = $xpath->query("//*[@$aName='$id']");
        if($q->length == 0) return null;
        if($q->length > 1) throw new Exception();
        return $q->item(0);
      }
    } catch(Exception $e) {
      throw new Exception(sprintf(_("Duplicit %s found for value '%s'"), $aName, $id));
    }
  }

  public function insertVar($varName, $varValue, $element=null) {
    $elements = $this->parseAttr($varName, "var", $element);
    $type = gettype($varValue);
    foreach($elements as $item) {
      $e = $item[0];
      $attr = $item[1];
      if(is_null(@$e->getNodePath())) {
        new Logger(sprintf(_("Variable %s destination element %s no longer exists"), $varName, $item[2]), "warning");
        continue;
      }
      if(is_null($e->parentNode)) continue;
      switch($type) {
        case "NULL":
        #$this->removeVar($e, $attr);
        break;
        case "integer":
        $varValue = (string) $varValue;
        case "string":
        if(!strlen($varValue)) {
          $this->removeVar($e, $attr);
          continue;
        }
        $e = $this->prepareIfDl($e, $varName);
        $this->insertVarString($varValue, $e, $attr, $varName);
        break;
        case "array":
        if(empty($varValue)) {
          $this->emptyVarArray($e);
          continue;
        }
        $e = $this->prepareIfDl($e, $varName);
        $this->insertVarArray($varValue, $e, $varName);
        break;
        default:
        if($varValue instanceof DOMElement) {
          $this->insertVarDOMElement($varValue, $e, $attr, $varName);
          break;
        }
        new Logger(sprintf(_("Unsupported variable type '%s' in '%s'"), get_class($varValue), $varName), "error");
      }
    }
  }

  public function insertFn($varName, $varValue, $element=null) {
    $elements = $this->parseAttr($varName, "fn", $element);
    $type = gettype($varValue);
    foreach($elements as $item) {
      $e = $item[0];
      $attr = $item[1];
      if(is_null(@$e->getNodePath())) {
        new Logger(sprintf(_("Function %s destination element %s no longer exists"), $varName, $item[2]), "warning");
        continue;
      }
      if(is_null($e->parentNode)) continue;
      if(is_null($varValue)) {
        #$this->removeVar($e, $attr);
        continue;
      }
      if(!$varValue instanceof Closure) {
        new Logger(sprintf(_("Unable to insert function %s: not a function"), $varName), "warning");
        return;
      }
      $e->nodeValue = call_user_func($varValue, $e->nodeValue);
    }
  }

  private function parseAttr($varName, $attr="var", DOMElement $e=null) {
    $xpath = new DOMXPath($this);
    #$noparse = "*[not(contains(@class, 'noparse')) and (not(ancestor::*) or ancestor::*[not(contains(@class, 'noparse'))])]";
    $noparse = "*";
    // find elements with current var
    if(is_null($e))
      $matches = $xpath->query(sprintf("//%s[contains(@$attr, '%s')]", $noparse, $varName));
    else
      $matches = array($e);

    $replaceCont = array();
    $replaceAttr = array();
    // check for attributes and real match (xpath accepts substrings)
    foreach($matches as $e) {
      $vars = explode(" ", $e->getAttribute($attr));
      $keep = array();
      foreach($vars as $v) {
        $p = explode("@", $v);
        if($varName != $p[0]) {
          $keep[] = $v;
          continue;
        }
        if(isset($p[1])) {
          if($e->nodeName == "h" && in_array($p[1], array("id", "link"))) {
            new Logger(_("Variables cannot modify heading identifiers"), "warning");
            continue;
          }
          $replaceAttr[] = array($e, $p[1], $e->nodeName);
        } else $replaceCont[] = array($e, null, $e->nodeName);
      }
      if(empty($keep)) {
        $e->removeAttribute($attr);
        continue;
      }
      $e->setAttribute($attr, implode(" ", $keep));
    }
    return array_merge($replaceAttr, array_reverse($replaceCont, true)); // attributes first!
  }

  public function validateLinks($elName, $attName, $repair) {
    $toStrip = array();
    foreach($this->getElementsByTagName($elName) as $e) {
      if(!$e->hasAttribute($attName)) continue;
      try {
        $link = $this->repairLink($e->getAttribute($attName));
        if($link === $e->getAttribute($attName)) continue;
        if(!$repair)
          throw new Exception(sprintf(_("Invalid repairable link '%s'"), $e->getAttribute($attName)));
        $e->setAttribute($attName, $link);
      } catch(Exception $ex) {
        if(!$repair) throw $ex;
        $toStrip[] = array($e, $ex->getMessage());
      }
    }
    foreach($toStrip as $a) $a[0]->stripAttr($attName, $a[1]);
    return count($toStrip);
  }

  private function repairLink($link=null) {
    if(is_null($link)) $link = getCurLink(); // null -> currentLink
    if($link == "" || $link == "/") return "/";
    $pLink = parse_url($link);
    if($pLink === false) throw new LoggerException(sprintf(_("Unable to parse href '%s'"), $link)); // fail2parse
    if(isset($pLink["scheme"])) { // link is in absolute form
      $curDomain = $_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"].getRoot();
      if(strpos(str_replace(array("?", "#"), array("/", "/"), $link), $curDomain) !== 0) return $link; // link is external
    }
    $query = isset($pLink["query"]) ? "?".$pLink["query"] : "";
    if(isset($pLink["fragment"])) return $query."#".$pLink["fragment"];
    $path = isset($pLink["path"]) ? $pLink["path"] : "";
    while(strpos($path, ".") === 0) $path = substr($path, 1);
    if(IS_LOCALHOST && strpos($path, substr(getRoot(), 0, -1)) === 0)
      $path = substr($path, strlen(getRoot())-1);
    while(strpos($path, "/") === 0) $path = substr($path, 1);
    return $path.$query;
  }

  public function fragToLinks(HTMLPlus $src, $eName="a", $aName="href") {
    $toStrip = array();
    foreach($this->getElementsByTagName($eName) as $a) {
      if(!$a->hasAttribute($aName)) continue; // no link found
      if(is_file(FILES_FOLDER."/".$a->getAttribute($aName))) continue;
      $pLink = parse_url($a->getAttribute($aName));
      if(isset($pLink["scheme"])) continue; // link is absolute (suppose internal)
      $query = isset($pLink["query"]) ? $pLink["query"] : "";
      $queryUrl = strlen($query) ? "?$query" : "";
      if(isset($pLink["path"]) || isset($pLink["query"])) { // link is by path/query
        $path = isset($pLink["path"]) ? $pLink["path"] : getCurLink();
        if($path == "/") $path = "";
        if(strlen($path) && !DOMBuilder::isLink($path)) {
          $toStrip[] = array($a, sprintf(_("Link '%s' not found"), $path));
          continue; // link not exists
        }
        if($eName != "form" && getCurLink(true) == $path.$queryUrl) {
          $toStrip[] = array($a, _("Cyclic link found"));
          continue; // link is cyclic (except form@action)
        }
        $a->setAttribute($aName, getRoot().$path.$queryUrl);
        continue; // localize link
      }
      $frag = $pLink["fragment"];
      $linkedElement = $this->getElementById($frag);
      if(!is_null($linkedElement)) {
        $h1id = $this->getElementsByTagName("h1")->item(0)->getAttribute("id");
        if(getCurLink(true) == getCurLink().$queryUrl && $h1id == $frag) {
          $toStrip[] = array($a, _("Cyclic fragment found"));
        }
        continue; // ignore visible headings
      }
      $link = DOMBuilder::getLink($frag);
      if(is_null($link)) {
        $toStrip[] = array($a, sprintf(_("Identifier '%s' not found"), $frag));
        continue; // id not exists
      }
      $a->setAttribute($aName, $link);
    }
    foreach($toStrip as $a) $a[0]->stripAttr($aName, $a[1]);
  }

  private function prepareIfDl(DOMElement $e, $varName) {
    if($e->nodeName != "dl") return $e;
    $e->removeChildNodes();
    $e->appendChild($e->ownerDocument->createElement("dt", $varName));
    return $e->appendChild($e->ownerDocument->createElement("dd"));
  }

  public function removeNodes($query) {
    $xpath = new DOMXPath($this);
    $toRemove = array();
    foreach($xpath->query($query) as $n) $toRemove[] = $n;
    foreach($toRemove as $n) {
      $n->stripElement(_("Readonly element hidden"));
    }
    return count($toRemove);
  }

  public function validatePlus($repair = false) {
    throw new Exception("Method no longer exists");
  }

  #UNUSED
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
      throw new Exception(sprintf(_("Unable to find HTML+ RNG schema '%s'"), $f));
    try {
      libxml_use_internal_errors(true);
      if(!$this->relaxNGValidate($f))
        throw new Exception(_("relaxNGValidate() internal error occured"));
    } catch (Exception $e) {
      $internal_errors = libxml_get_errors();
      if(count($internal_errors)) {
        $note = " ["._("Caution: this message may be misleading")."]";
        if(self::DEBUG) die($this->saveXML());
        $e = new Exception(current($internal_errors)->message.$note);
      }
    }
    // finally
    libxml_clear_errors();
    libxml_use_internal_errors(false);
    if(isset($e)) throw $e;
    return true;
  }

  public function setUniqueId(DOMElement $e) {
    $id = $e->nodeName.".".substr(md5(microtime().rand()), 0, 3);
    if(!$this->isValidId($id)) $this->setUniqueId($e);
    if(!is_null($this->getElementById($id))) $this->setUniqueId($e);
    $e->setAttribute("id", $id);
  }

  protected function isValidId($id) {
    return (bool) preg_match("/^[A-Za-z][A-Za-z0-9_:\.-]*$/", $id);
  }

  private function removeVar($e, $attr) {
    if(!is_null($attr)) {
      if($e->hasAttribute($attr)) $e->removeAttribute($attr);
      return;
    }
    $e->parentNode->removeChild($e);
  }

  private function insertVarString($varValue, DOMElement $e, $attr, $varName) {
    if(!is_null($attr)) {
      if(!$e->hasAttribute($attr) || $e->getAttribute($attr) == "") {
        if(strlen($varValue)) $e->setAttribute($attr, $varValue);
        elseif($e->hasAttribute($attr)) $e->removeAttribute($attr);
        return;
      }
      $temp = @sprintf($e->getAttribute($attr), $varValue);
      if($temp !== false && $temp != $e->getAttribute($attr)) {
        $e->setAttribute($attr, $temp);
        return;
      }
      if(!strlen($varValue)) {
        $e->removeAttribute($attr);
        return;
      }
      if($attr == "class") $varValue = $e->getAttribute($attr)." ".$varValue;
      $e->setAttribute($attr, $varValue);
      return;

    }
    $this->insertInnerHTML($varValue, $e, "", $varName);
    #$e->nodeValue = $varValue;
  }

  private function insertVarArray(Array $varValue, DOMElement $e, $varName) {
    $sep = null;
    switch($e->nodeName) {
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
      $e->removeChildNodes();
      $e = $e->appendChild($e->ownerDocument->createElement("li"));
      break;
      #case "body":
      #case "section":
      #case "dl":
      #case "form":
      #case "fieldset":
      default:
      new Logger(sprintf(_("Unable to insert array variable '%s' into '%s'"), $varName, $e->nodeName), "error");
      return;
    }
    $this->insertInnerHTML($varValue, $e, $sep, $varName);
  }

  private function insertInnerHTML($html, DOMElement $dest, $sep = "", $varName = "") {
    if(!is_array($html)) $html = array($html);
    $dom = new DOMDocumentPlus();
    $eNam = $dest->nodeName;
    $xml = "<var><$eNam>".implode("</$eNam>$sep<$eNam>", $html)."</$eNam></var>";
    if(!@$dom->loadXML($xml)) {
      $var = $dom->appendChild($dom->createElement("var"));
      foreach($html as $k => $v) {
        $e = $var->appendChild($dom->createElement($eNam));
        $e->nodeValue = htmlspecialchars($html[$k]);
      }
    }
    $this->insertVarDOMElement($dom->documentElement, $dest);
  }

  private function emptyVarArray(DOMElement $e) {
    $p = $e->parentNode;
    $p->removeChild($e);
    if($p->childElements->length == 0)
      $p->parentNode->removeChild($p);
  }

  private function insertVarDOMElement(DOMElement $varValue, DOMElement $e, $attr=null, $varName=null) {
    if(!is_null($attr)) {
      $this->insertVarstring($varValue->nodeValue, $e, $attr);
      return;
    }
    $var = $e->ownerDocument->importNode($varValue, true);
    $attributes = array();
    foreach($e->attributes as $attr) $attributes[$attr->nodeName] = $attr->nodeValue;
    $nodes = array();
    foreach($var->childNodes as $n) $nodes[] = $n;
    foreach($nodes as $n) {
      foreach($attributes as $aName => $aValue) $n->setAttribute($aName, $aValue);
      $e->parentNode->insertBefore($n, $e);
    }
    $e->parentNode->removeChild($e);
    $e->removeChildNodes();
  }

}