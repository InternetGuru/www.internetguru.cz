<?php

namespace IGCMS\Core;

use Cz\Git\GitException;
use Cz\Git\GitRepository;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Exception;

/**
 * Class DOMDocumentPlus
 * @package IGCMS\Core
 *
 * @property DOMElementPlus documentElement
 * @property DOMDocumentPlus $ownerDocument
 */
class DOMDocumentPlus extends DOMDocument implements \Serializable {

  /**
   * @var int
   */
  const APC_ID = 1;

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
   * String representation of object
   * @link http://php.net/manual/en/serializable.serialize.php
   * @return string the string representation of the object or null
   * @since 5.1.0
   */
  public function serialize () {
    return $this->saveXML();
  }

  /**
   * Constructs the object
   * @link http://php.net/manual/en/serializable.unserialize.php
   * @param string $serialized <p>
   * The string representation of the object.
   * </p>
   * @return void
   * @since 5.1.0
   */
  public function unserialize ($serialized) {
    // TODO: Implement unserialize() method.
  }

  /**
   * @param string $eName
   * @param string|null $aMatch
   * @param string $to
   * @return DOMElementPlus|null
   */
  public function matchElement ($eName, $aMatch, $to) {
    $lastMatch = null;
    /** @var DOMElementPlus $element */
    foreach ($this->getElementsByTagName($eName) as $element) {
      if ($element->hasAttribute($aMatch) || is_null($aMatch)) {
        $aValue = is_null($aMatch)
          ? $element->nodeValue
          : $element->getAttribute($aMatch);
        if (!preg_match("/^[a-z0-9.*-]+$/", $aValue)) {
          Logger::user_error(sprintf(_("Invalid element value '%s'"), $aValue));
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
    if (!stream_resolve_include_path($filePath)
      || stream_resolve_include_path(
        dirname($filePath)."/.".basename($filePath)
      )
    ) {
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
   * @param string $filename
   * @param null $options
   * @param null $message
   * @param null $author
   * @param null $email
   * @return int|void
   */
  public function save ($filename, $options = null, $message=null, $author=null, $email=null) {
    parent::save($filename, $options);
    // commit only iff repo exists
    try {
      $gitRepo = Git::Instance();
      $gitRepo->commitFile($filename, $message, $author, $email);
    } catch (GitException $exc) { }
  }

  /**
   * @param string $elementId
   * @param string|null $eName
   * @param string $aName
   * @return DOMElementPlus|null
   * @throws Exception
   */
  public function getElementById ($elementId, $eName = null, $aName = "id") {
    try {
      if (!is_null($eName)) {
        $element = null;
        /** @var DOMElementPlus $candidate */
        foreach ($this->getElementsByTagName($eName) as $candidate) {
          if (!$candidate->hasAttribute($aName)) {
            continue;
          }
          if ($candidate->getAttribute($aName) != $elementId) {
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
      $candidate = $xpath->query("//*[@$aName='$elementId']");
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
      throw new Exception(sprintf(_("Duplicit %s found for value '%s'"), $aName, $elementId));
    }
  }

  /**
   * TODO return?
   * @param array $variables
   * @param array $ignore
   * @return DOMDocumentPlus|DOMElementPlus|mixed|null
   * @throws Exception
   */
  public function processVariables (Array $variables, $ignore = []) {
    return $this->elementProcessVars($variables, $ignore, $this->documentElement, true);
  }

  /**
   * @param array $variables
   * @param array $ignore
   * @param DOMElementPlus $element
   * @param bool $deep
   * @return DOMDocumentPlus|DOMElementPlus|mixed|null
   * @throws Exception
   */
  public function elementProcessVars (Array $variables, $ignore = [], DOMElementPlus $element, $deep = false) {
    $cacheKey = apc_get_key(__FUNCTION__."/"
      .self::APC_ID."/"
      .$element->getNodePath()."/"
      .hash("sha1", serialize($variables))."/"
      .hash("sha1", serialize($element))."/"
      .$deep
    );
    $newestFileMtime = HTMLPlusBuilder::getNewestFileMtime();
    $cacheExists = apc_exists($cacheKey);
    $cacheUpToDate = false;
    $cache = null;
    $result = null;
    if (Cms::getLoggedUser() != SERVER_USER && $cacheExists) {
      $cache = apc_fetch($cacheKey);
      $cacheUpToDate = $cache["newestFileMtime"] == $newestFileMtime;
    }
    if ($cacheUpToDate) {
      $doc = new DOMDocumentPlus();
      $doc->loadXML($cache["data"]);
      $element->removeChildNodes();
      foreach ($doc->documentElement->childNodes as $childNode) {
        $element->appendChild($element->ownerDocument->importNode($childNode, true));
      }
      $result = $element;
    } else {
      $cacheableVariables = array_filter(
        $variables,
        function($value) {
          return $value['cacheable'] === true;
        }
      );
      $result = $this->elementDoProcessVars($cacheableVariables, $ignore, $element, $deep);
    }

    if (!$cacheExists || !$cacheUpToDate) {
      $cache = [
        "data" => $result->ownerDocument->saveXML($result),
        "newestFileMtime" => $newestFileMtime,
      ];
      apc_store_cache($cacheKey, $cache, __FUNCTION__);
    }

    $notCacheableVariables = array_filter(
      $variables,
      function($value) {
        return $value['cacheable'] === false;
      }
    );

    if (!count($notCacheableVariables)) {
      return $result;
    }

    return $this->elementDoProcessVars($notCacheableVariables, $ignore, $result, $deep);
  }

  /**
   * TODO return?
   * @param array $variables
   * @param array $ignore
   * @param DOMElementPlus $element
   * @param bool $deep
   * @return DOMDocumentPlus|DOMElementPlus|mixed|null
   */
  public function elementDoProcessVars (Array $variables, $ignore = [], DOMElementPlus $element, $deep = false) {
    $toRemove = [];
    $result = $this->doProcessVars($variables, $ignore, $element, $deep, $toRemove);
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
  private function doProcessVars (Array $variables, $ignore, DOMElementPlus $element, $deep, Array &$toRemove) {
    $result = $element;
    $ignoreAttr = isset($ignore[$this->nodeName]) ? $ignore[$this->nodeName] : [];
    foreach ($element->getVariables("var", $ignoreAttr) as list($vName, $aName, $vValue)) {
      if (!isset($variables[$vName])) {
        continue;
      }
      try {
        $element->removeAttrVal("var", $vValue);
        if (!is_null($variables[$vName]["value"]) && !count($variables[$vName]["value"])) {
          if (!is_null($aName)) {
            $element->removeAttribute($aName);
          } else {
            return null;
          }
        }
        $result = $this->insertVariable($element, $variables[$vName]["value"], $aName);
        if ($aName == "var") {
          if (++$element->varRecursionLvl >= DOMElementPlus::MAX_VAR_RECURSION_LEVEL) {
            throw new Exception(_("Max variable recursion level exceeded"));
          }
          $result = $this->doProcessVars($variables, $ignore, $element, false, $toRemove);
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
        $deepResult = $this->doProcessVars($variables, $ignore, $element, $deep, $toRemove);
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
