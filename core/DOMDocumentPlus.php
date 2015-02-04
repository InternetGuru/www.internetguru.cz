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
    new Logger(sprintf(METHOD_NA, __FUNCTION__), Logger::LOGGER_ERROR);
    return;
  }

  public function insertFn($varName, $varValue, $element=null) {
    new Logger(sprintf(METHOD_NA, __FUNCTION__), Logger::LOGGER_ERROR);
    return;
  }

  public function processVariables(Array $variables, $ignore = array()) {
    $xpath = new DOMXPath($this);
    $elements = array();
    foreach($xpath->query("//*[@var]") as $e) $elements[] = $e;
    foreach(array_reverse($elements) as $e) {
      if(array_key_exists($e->nodeName, $ignore))
        $e->processVariables($variables, $ignore[$e->nodeName]);
      else $e->processVariables($variables);
    }
  }

  public function processFunctions(Array $functions, $ignore = array()) {
    $xpath = new DOMXPath($this);
    $elements = array();
    foreach($xpath->query("//*[@fn]") as $e) $elements[] = $e;
    foreach(array_reverse($elements) as $e) {
      if(array_key_exists($e->nodeName, $ignore))
        $e->processFunctions($functions, $ignore[$e->nodeName]);
      else $e->processFunctions($functions);
    }
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
    if(strpos($path, "/") === 0) {
      while(strpos($path, "/") === 0) $path = substr($path, 1);
      $path = getRoot().$path;
    }
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
    new Logger(sprintf(METHOD_NA, __FUNCTION__), Logger::LOGGER_ERROR);
    return;
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

}