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
    new Logger(sprintf(METHOD_NA, __CLASS__.".".__FUNCTION__), Logger::LOGGER_ERROR);
    return;
  }

  public function insertFn($varName, $varValue, $element=null) {
    new Logger(sprintf(METHOD_NA, __CLASS__.".".__FUNCTION__), Logger::LOGGER_ERROR);
    return;
  }

  public function processVariables(Array $variables, $ignore = array()) {
    $this->documentElement->processVariables($variables, $ignore, true);
  }

  public function processFunctions(Array $functions, Array $variables = Array(), $ignore = array()) {
    $xpath = new DOMXPath($this);
    $elements = array();
    foreach($xpath->query("//*[@fn]") as $e) $elements[] = $e;
    foreach(array_reverse($elements) as $e) {
      if(isset($ignore[$e->nodeName]))
        $e->processFunctions($functions, $variables, $ignore[$e->nodeName]);
      else $e->processFunctions($functions, $variables, array());
    }
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
    new Logger(sprintf(METHOD_NA, __CLASS__.".".__FUNCTION__), Logger::LOGGER_ERROR);
    return;
  }

  public function relaxNGValidatePlus($f) {
    if(!file_exists($f))
      throw new Exception(sprintf(_("Unable to find HTML+ RNG schema '%s'"), $f));
    try {
      libxml_use_internal_errors(true);
      libxml_clear_errors();
      if(!$this->relaxNGValidate($f))
        throw new Exception(_("relaxNGValidate() internal error occured"));
    } catch (Exception $e) {
      $internal_errors = libxml_get_errors();
      if(count($internal_errors)) {
        $note = " ["._("Caution: this message may be misleading")."]";
        if(self::DEBUG) die($this->saveXML());
        throw new Exception(current($internal_errors)->message.$note);
      }
      throw $e;
    } finally {
      libxml_clear_errors();
      libxml_use_internal_errors(false);
    }
    return true;
  }

  private function removeVar($e, $attr) {
    if(!is_null($attr)) {
      if($e->hasAttribute($attr)) $e->removeAttribute($attr);
      return;
    }
    $e->parentNode->removeChild($e);
  }

}