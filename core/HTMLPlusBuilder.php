<?php

namespace IGCMS\Core;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use Exception;
use DOMXPath;
use DOMDocument;
use DOMElement;
use DOMComment;
use DateTime;

class HTMLPlusBuilder {

  private static $fileToId = array();
  private static $fileToDoc = array();

  private static $idToParentId = array();
  private static $idToFile = array();
  private static $idToFileMtime = array();
  private static $idToShort = array();
  private static $idToHeading = array();
  private static $idToTitle = array();
  private static $idToDesc = array();
  private static $idToKw = array();
  private static $idToAuthor = array();
  private static $idToAuthorId = array();
  private static $idToResp = array();
  private static $idToRespId = array();
  private static $idToCtime = array();
  private static $idToMtime = array();

  private static $include;

  private static function getRegister($id) {
    var_dump($id);
    $register = array();
    $properties = (new \ReflectionClass(get_called_class()))->getStaticProperties();
    foreach(array_keys($properties) as $p) {
      if(strpos($p, "idTo") !== 0) continue;
      $register[strtolower(substr($p, 4))] = self::${$p}[$id];
    }
    return $register;
  }

  public static function __callStatic($methodName, $arguments) {
    if(strpos($methodName, "get") !== 0) {
      throw new Exception("Undefined method $methodName");
    }
    $propertyName = strtolower(substr($methodName, 3, 1)).substr($methodName, 4);
    if(!property_exists(get_called_class(), $propertyName)) {
      throw new Exception("Undefined property $propertyName");
    }
    if(count($arguments)) {
      if(!array_key_exists($arguments[0], self::$$propertyName))
        throw new Exception("Undefined id {$arguments[0]} in property $propertyName");
      return self::${$propertyName}[$arguments[0]];
    }
    return self::$$propertyName;
  }

  public static function build($filePath, $parentId='', $prefixId='') {
    #register iff not registered
    if(!array_key_exists($filePath, self::$fileToDoc))
      self::register($filePath, $parentId, $prefixId);
    $doc = self::$fileToDoc[$filePath];
    #load iff not loaded
    if(!is_null($doc)) return $doc;
    $doc = self::load($filePath);
    self::$fileToDoc[$filePath] = $doc;
    return $doc;
  }

  public static function register($filePath, $parentId='', $prefixId='') {
    $doc = self::load($filePath);
    self::$fileToDoc[$filePath] = $doc;
    self::$include = false;
    $prefix = '';
    if(count(self::$idToParentId)) {
      $prefix = $doc->documentElement->firstElement->getAttribute("id");
      if(!strlen($prefixId)) $prefix = "$prefixId/$prefix";
    }
    if(self::$include) {
      $doc->repairIds();
      if(count($doc->getErrors()))
        Logger::user_notice(sprintf(_("Duplicit identifiers fixed %s times after includes in %s"),
          count($doc->getErrors()), $filePath));
    }
    self::registerStructure($doc->documentElement, $parentId, $prefix, $filePath);
    return self::getRegister(self::$fileToId[$filePath]);
  }

  private static function load($filePath) {
    $doc = new HTMLPlus();
    if(!@$doc->load($filePath))
      throw new Exception(sprintf(_("Unable to load '%s'"), $filePath));
    $doc->validatePlus(true);
    if(count($doc->getErrors()))
      Logger::user_notice(sprintf(_("Invalid HTML+ syntax fixed %s times: %s"),
        count($doc->getErrors()), $filePath));
    self::insertIncludes($doc, dirname($filePath));
    return $doc;
  }

  private static function insertIncludes(HTMLPlus $doc, $workingDir) {
    $includes = array();
    foreach($doc->getElementsByTagName("include") as $include) $includes[] = $include;
    foreach($includes as $include) {
      try {
        self::$include = true;
        self::insert($include, $workingDir);
      } catch(Exception $e) {
        $msg = sprintf(_("Unable to import: %s"), $e->getMessage());
        $c = new DOMComment(" $msg ");
        $include->parentNode->insertBefore($c, $include);
        Logger::user_error($msg);
        $include->stripTag();
      }
    }
  }

  private static function getIncludeSrc($src, $workingDir) {
    if(pathinfo($src, PATHINFO_EXTENSION) != "html")
      throw new Exception(sprintf(_("Included file '%s' extension must be html"), $src));
    $file = realpath("$workingDir/$src");
    if($file === false)
      throw new Exception(sprintf(_("Included file '%s' not found"), $src));
    if(strpos($file, realpath("$workingDir/")) !== 0)
      throw new Exception(sprintf(_("Included file '%s' is out of working directory"), $src));
    return "$workingDir/$src";
  }

  private static function insert(DOMElement $include, $workingDir) {
    $src = $include->getAttribute("src");
    $includeFile = self::getIncludeSrc($src, $workingDir);
    $doc = self::load($includeFile);
    $lang = $doc->documentElement->getAttribute("xml:lang");
    foreach($doc->documentElement->childElementsArray as $n) {
      $e = $include->parentNode->insertBefore($include->ownerDocument->importNode($n, true), $include);
      if(strlen($e->getAttribute("xml:lang"))) continue;
      $e->setAttribute("xml:lang", $lang);
    }
    $include->parentNode->removeChild($include);
  }

  private static function registerStructure(DOMElementPlus $section, $parentId, $prefix, $filePath) {
    foreach($section->childElementsArray as $e) {
      if($e->nodeName == "h") {
        $id = count(self::$idToFile) ? $e->getAttribute("id") : '';
        if($id != $prefix) $id = "$prefix#$id";
        if(!array_key_exists($filePath, self::$fileToId))
          self::$fileToId[$filePath] = $id;
        self::$idToParentId[$id] = $parentId; // skip '' => ''
        self::$idToFile[$id] = $filePath;
        self::$idToFileMtime[$id] = filemtime($filePath);
        self::setHeadingInfo($id, $e);
        continue;
      }
      if($e->nodeName == "section") {
        self::registerStructure($e, $id, $prefix, $filePath);
      }
    }
  }

  private static function setHeadingInfo($id, DOMElementPlus $h) {
    self::$idToShort[$id] = $h->getAttribute("short");
    self::$idToHeading[$id] = $h->nodeValue;
    self::$idToTitle[$id] = $h->getAttribute("title");
    self::$idToDesc[$id] = $h->nextElement->nodeValue;
    self::$idToKw[$id] = $h->nextElement->getAttribute("kw");
    self::$idToAuthor[$id] = $h->getAttribute("author");
    self::$idToAuthorId[$id] = $h->getAttribute("authorid");
    self::$idToResp[$id] = $h->getAttribute("resp");
    self::$idToRespId[$id] = $h->getAttribute("respid");
    self::$idToCtime[$id] = $h->getAttribute("ctime");
    self::$idToMtime[$id] = $h->getAttribute("mtime");
  }

}