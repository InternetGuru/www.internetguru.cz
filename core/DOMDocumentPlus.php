<?php

namespace IGCMS\Core;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;

/**
 * Class DOMDocumentPlus
 * @package IGCMS\Core
 *
 * @property DOMElementPlus documentElement
 * @property DOMDocumentPlus $ownerDocument
 */
class DOMDocumentPlus extends DOMDocument {

  /**
   * DOMDocumentPlus constructor.
   * @param string $version
   * @param string $encoding
   */
  function __construct ($version = "1.0", $encoding = "utf-8") {
    parent::__construct($version, $encoding);
    parent::registerNodeClass("DOMElement", "IGCMS\\Core\\DOMElementPlus");
  }

  /**
   * @param string $eName
   * @param string $aMatch
   * @param string $to
   * @return DOMElementPlus|null
   */
  public function matchElement ($eName, $aMatch, $to) {
    $lastMatch = null;
    /** @var DOMElementPlus $element */
    foreach ($this->getElementsByTagName($eName) as $element) {
      if ($element->hasAttribute($aMatch)) {
        $aValue = $element->getAttribute($aMatch);
        if (!preg_match("/^[a-z0-9.*-]+$/", $aValue)) {
          Logger::user_error(sprintf(_("Invalid attribute %s value '%s'"), $aMatch, $aValue));
          continue;
        }
        $pattern = str_replace([".", "*"], ["\.", "[a-z0-9-]+"], $aValue);
        if (!preg_match("/^$pattern$/", $to)) {
          continue;
        }
      }
      $lastMatch = $element;
    }
    return $lastMatch;
  }

  /**
   * @param string $name
   * @param string|null $value
   * @return DOMElementPlus|DOMElement
   */
  public function createElement ($name, $value = null) {
    if (is_null($value)) {
      return parent::createElement($name);
    }
    return parent::createElement($name, htmlspecialchars($value));
  }

  /**
   * @param string $filePath
   * @param int $options
   * @return void
   * @throws Exception
   * @throws NoFileException
   */
  public function load ($filePath, $options = 0) {
    if (!stream_resolve_include_path($filePath) || stream_resolve_include_path(dirname($filePath)."/.".basename($filePath))) {
      throw new NoFileException(_("File not found or disabled"));
    }
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (!@parent::load($filePath, $options)) {
      throw new Exception(_("Invalid XML file"));
    }
  }

  /**
   * @param string $xml
   * @param int $options
   * @return void
   * @throws Exception
   */
  public function loadXML ($xml, $options = 0) {
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (!@parent::loadXML($xml, $options)) {
      throw new Exception(_("Invalid XML"));
    }
  }

  /**
   * @param string $id
   * @param string|null $eName
   * @param string $aName
   * @return DOMElementPlus|null
   * @throws Exception
   */
  public function getElementById ($id, $eName = null, $aName = "id") {
    try {
      if (!is_null($eName)) {
        $element = null;
        /** @var DOMElementPlus $candidate */
        foreach ($this->getElementsByTagName($eName) as $candidate) {
          if (!$candidate->hasAttribute($aName)) {
            continue;
          }
          if ($candidate->getAttribute($aName) != $id) {
            continue;
          }
          if (!is_null($element)) {
            throw new Exception();
          }
          $element = $candidate;
        }
        return $element;
      }
      $xpath = new DOMXPath($this);
      /** @var \DOMNodeList $candidate */
      $candidate = $xpath->query("//*[@$aName='$id']");
      if ($candidate->length == 0) {
        return null;
      }
      if ($candidate->length > 1) {
        throw new Exception();
      }
      /** @var DOMElementPlus $element */
      $element = $candidate->item(0);
      return $element;
    } catch (Exception $candidate) {
      throw new Exception(sprintf(_("Duplicit %s found for value '%s'"), $aName, $id));
    }
  }

  /**
   * TODO return?
   * @param array $variables
   * @param array $ignore
   * @return DOMDocumentPlus|DOMElementPlus|mixed|null
   */
  public function processVariables (Array $variables, $ignore = []) {
    return $this->elementProcessVars($variables, $ignore, $this->documentElement, true);
  }

  /**
   * TODO return?
   * @param array $variables
   * @param array $ignore
   * @param DOMElementPlus $element
   * @param bool $deep
   * @return DOMDocumentPlus|DOMElementPlus|mixed|null
   */
  public function elementProcessVars (Array $variables, $ignore = [], DOMElementPlus $element, $deep = false) {
    $toRemove = [];
    $result = $this->doProcessVariables($variables, $ignore, $element, $deep, $toRemove);
    if (is_null($result) || !$result->isSameNode($element)) {
      $toRemove[] = $element;
    }
    foreach ($toRemove as $eToRemove) {
      $eToRemove->emptyRecursive();
    }
    return $result;
  }

  /**
   * TODO return?
   * @param array $variables
   * @param array $ignore
   * @param DOMElementPlus $element
   * @param bool $deep
   * @param array $toRemove
   * @return DOMDocumentPlus|DOMElementPlus|mixed|null
   */
  private function doProcessVariables (Array $variables, $ignore, DOMElementPlus $element, $deep, Array &$toRemove) {
    $result = $element;
    $ignoreAttr = isset($ignore[$this->nodeName]) ? $ignore[$this->nodeName] : [];
    foreach ($element->getVariables("var", $ignoreAttr) as list($vName, $aName, $vValue)) {
      if (!isset($variables[$vName])) {
        continue;
      }
      try {
        $element->removeAttrVal("var", $vValue);
        if (!is_null($variables[$vName]) && !count($variables[$vName])) {
          if (!is_null($aName)) {
            $element->removeAttribute($aName);
          } else {
            return null;
          }
        }
        $result = $this->insertVariable($element, $variables[$vName], $aName);
        if ($aName == "var") {
          if (++$element->varRecursionLvl >= DOMElementPlus::MAX_VAR_RECURSION_LEVEL) {
            throw new Exception(_("Max variable recursion level exceeded"));
          }
          $result = $this->doProcessVariables($variables, $ignore, $element, false, $toRemove);
        }
      } catch (Exception $exc) {
        Logger::user_error(sprintf(_("Unable to insert variable %s: %s"), $vName, $exc->getMessage()));
      }
    }
    if ($deep) {
      /** @var DOMElementPlus $element */
      foreach ($element->childNodes as $element) {
        if ($element->nodeType != XML_ELEMENT_NODE) {
          continue;
        }
        $deepResult = $this->doProcessVariables($variables, $ignore, $element, $deep, $toRemove);
        if (is_null($deepResult) || !$element->isSameNode($deepResult)) {
          $toRemove[] = $element;
        }
      }
    }
    return $result;
  }

  /**
   * TODO return?
   * @param DOMElementPlus $element
   * @param mixed $value
   * @param string|null $aName
   * @return DOMDocumentPlus|DOMElementPlus|mixed|null
   * @throws Exception
   */
  public function insertVariable (DOMElementPlus $element, $value, $aName = null) {
    if (is_null($element->parentNode)) {
      return $element;
    }
    switch (gettype($value)) {
      case "NULL":
        return $element;
      case "integer":
      /** @noinspection PhpMissingBreakStatementInspection */
      case "boolean":
        $value = (string) $value;
      case "string":
        if (!strlen($value) && is_null($aName)) {
          return null;
        }
        return $element->insertVarString($value, $aName);
      case "array":
        #$this = $this->prepareIfDl($this, $varName);
        return $element->insertVarArray($value, $aName);
      default:
        if ($value instanceof DOMDocumentPlus) {
          return $element->insertVarDOMElement($value->documentElement, $aName);
        }
        if ($value instanceof DOMElement) {
          return $element->insertVarDOMElement($value, $aName);
        }
        throw new Exception(sprintf(_("Unsupported variable type %s"), get_class($value)));
    }
  }

  /**
   * @param array $functions
   * @param array $ignore
   */
  public function processFunctions (Array $functions, $ignore = []) {
    $xpath = new DOMXPath($this);
    $elements = [];
    foreach ($xpath->query("//*[@fn]") as $element) {
      $elements[] = $element;
    }
    /** @var DOMElementPlus $element */
    foreach (array_reverse($elements) as $element) {
      if (isset($ignore[$element->nodeName])) {
        $element->processFunctions($functions, $ignore[$element->nodeName]);
      } else {
        $element->processFunctions($functions, []);
      }
    }
  }

  /**
   * @param string $query
   * @return int
   */
  public function removeNodes ($query) {
    $xpath = new DOMXPath($this);
    $toRemove = [];
    foreach ($xpath->query($query) as $node) {
      $toRemove[] = $node;
    }
    foreach ($toRemove as $node) {
      $node->stripElement(_("Readonly element hidden"));
    }
    return count($toRemove);
  }

  /**
   * @param string $f
   * @param DOMDocument|null $doc
   * @return bool
   * @throws Exception
   */
  public function relaxNGValidatePlus ($f, DOMDocument $doc = null) {
    if (!stream_resolve_include_path($f)) {
      throw new Exception(sprintf(_("Unable to find HTML+ RNG schema '%s'"), $f));
    }
    $docToValidate = is_null($doc) ? $this : $doc;
    try {
      libxml_use_internal_errors(true);
      libxml_clear_errors();
      if (!$docToValidate->relaxNGValidate($f)) {
        throw new Exception(_("relaxNGValidate() internal error occurred"));
      }
    } catch (Exception $exc) {
      $internal_errors = libxml_get_errors();
      if (count($internal_errors)) {
        $note = " ["._("Caution: this message may be misleading")."]";
        throw new Exception(current($internal_errors)->message.$note);
      }
      throw $exc;
    } finally {
      libxml_clear_errors();
      libxml_use_internal_errors(false);
    }
    return true;
  }

}
