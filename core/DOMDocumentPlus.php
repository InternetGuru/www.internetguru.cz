<?php

namespace IGCMS\Core;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;

class DOMDocumentPlus extends DOMDocument {

  function __construct($version="1.0", $encoding="utf-8") {
    parent::__construct($version, $encoding);
    $r = parent::registerNodeClass("DOMElement", "IGCMS\Core\DOMElementPlus");
  }

  public function createElement($name, $value=null) {
    if(is_null($value)) return parent::createElement($name);
    return parent::createElement($name, htmlspecialchars($value));
  }

  public function load($filePath, $options=0) {
    if(!is_file($filePath) || file_exists(dirname($filePath)."/.".basename($filePath)))
      throw new NoFileException(_("File not found or disabled"));
    if(!@parent::load($filePath, $options))
      throw new Exception(_("Invalid XML file"));
  }

  public function loadXML($xml, $options=0) {
    if(!@parent::loadXML($xml, $options))
      throw new Exception(_("Invalid XML"));
  }

  public function getElementById($id, $eName=null, $aName="id") {
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

  public function processVariables(Array $variables, $ignore = array()) {
    return $this->elementProcessVariables($variables, $ignore, $this->documentElement, true);
  }

  public function elementProcessVariables(Array $variables, $ignore = array(), DOMElementPlus $element, $deep = false) {
    $toRemove = array();
    $res = $this->doProcessVariables($variables, $ignore, $element, $deep, $toRemove);
    if(is_null($res) || !$res->isSameNode($element)) $toRemove[] = $element;
    foreach($toRemove as $e) $e->emptyRecursive();
    return $res;
  }

  private function doProcessVariables(Array $variables, $ignore, DOMElementPlus $element, $deep, Array &$toRemove) {
    $res = $element;
    $ignoreAttr = isset($ignore[$this->nodeName]) ? $ignore[$this->nodeName] : array();
    foreach($element->getVariables("var", $ignoreAttr) as list($vName, $aName, $var)) {
      if(!isset($variables[$vName])) continue;
      try {
        $element->removeAttrVal("var", $var);
        if(!is_null($variables[$vName]) && !count($variables[$vName])) {
          if(!is_null($aName)) $this->removeAttribute($aName);
          else return null;
        }
        $res = $this->insertVariable($element, $variables[$vName], $aName);
      } catch(Exception $e) {
        Logger::user_error(sprintf(_("Unable to insert variable %s: %s"), $vName, $e->getMessage()));
      }
    }
    if($deep) foreach($element->childNodes as $e) {
      if($e->nodeType != XML_ELEMENT_NODE) continue;
      $r = $this->doProcessVariables($variables, $ignore, $e, $deep, $toRemove);
      if(is_null($r) || !$e->isSameNode($r)) $toRemove[] = $e;
    }
    return $res;
  }

  public function insertVariable(DOMElementPlus $element, $value, $aName=null) {
    if(is_null($element->parentNode)) return $element;
    switch(gettype($value)) {
      case "NULL":
      return $element;
      case "integer":
      case "boolean":
      $value = (string) $value;
      case "string":
      if(!strlen($value) && is_null($aName)) return null;
      return $element->insertVarString($value, $aName);
      case "array":
      #$this = $this->prepareIfDl($this, $varName);
      return $element->insertVarArray($value, $aName);
      default:
      if($value instanceof DOMDocumentPlus) {
        return $element->insertVarDOMElement($value->documentElement, $aName);
      }
      if($value instanceof DOMElement) {
        return $element->insertVarDOMElement($value, $aName);
      }
      throw new Exception(sprintf(_("Unsupported variable type %s"), get_class($value)));
    }
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

  public function relaxNGValidatePlus($f) {
    if(!file_exists($f))
      throw new Exception(sprintf(_("Unable to find HTML+ RNG schema '%s'"), $f));
    try {
      libxml_use_internal_errors(true);
      libxml_clear_errors();
      if(!$this->relaxNGValidate($f))
        throw new Exception(_("relaxNGValidate() internal error occurred"));
    } catch (Exception $e) {
      $internal_errors = libxml_get_errors();
      if(count($internal_errors)) {
        $note = " ["._("Caution: this message may be misleading")."]";
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