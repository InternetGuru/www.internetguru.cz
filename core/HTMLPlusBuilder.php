<?php

namespace IGCMS\Core;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMBuilder;
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

class HTMLPlusBuilder extends DOMBuilder {

  private static $fileToId = array();
  private static $fileToDoc = array();
  private static $fileToInclude = array();
  private static $fileToMtime = array();

  private static $idToParentId = array();
  private static $idToFile = array();
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
  private static $idToLang = array();

  private static $idToLink = array();
  private static $linkToId = array();

  private static $storeCache;
  private static $currentFileTo;
  private static $currentIdTo;

  public static function getIdToAll($id) {
    $register = array();
    $properties = (new \ReflectionClass(get_called_class()))->getStaticProperties();
    foreach(array_keys($properties) as $p) {
      if(strpos($p, "idTo") !== 0) continue;
      #if($p == "idToParentId") continue;
      $register[strtolower(substr($p, 4))] = self::${$p}[$id];
    }
    return $register;
  }

  public static function __callStatic($methodName, $arguments) {
    $className = (new \ReflectionClass(self::class))->getShortName();
    if(strpos($methodName, "get") !== 0) {
      throw new Exception("Undefined $className method $methodName");
    }
    $propertyName = strtolower(substr($methodName, 3, 1)).substr($methodName, 4);
    if(!property_exists(get_called_class(), $propertyName)) {
      throw new Exception("Undefined $className property $propertyName");
    }
    if(count($arguments)) {
      if(!array_key_exists($arguments[0], self::$$propertyName)) return null;
      return self::${$propertyName}[$arguments[0]];
    }
    return self::$$propertyName;
  }

  public static function build($filePath, $parentId='', $prefixId='') {
    # register iff not registered
    if(!array_key_exists($filePath, self::$fileToId))
      self::register($filePath, $parentId, $prefixId);
    # return iff loaded
    if(array_key_exists($filePath, self::$fileToDoc))
      return self::$fileToDoc[$filePath];
    # load iff not loaded
    $doc = self::load($filePath);
    self::$fileToDoc[$filePath] = $doc;
    return $doc;
  }

  public static function setIdToLink(Array $idToLink) {
    self::$idToLink = $idToLink;
    self::$linkToId = array();
    foreach($idToLink as $id => $link) {
      self::$linkToId[$link] = $id;
    }
  }

  public static function register($filePath, $parentId=null, $linkPrefix='') {
    self::$currentFileTo = array();
    self::$currentIdTo = array();
    #self::$storeCache = true;
    #$cacheKey = apc_get_key($filePath);
    #if(apc_is_valid_cache($cacheKey, $fileToMtime)) {
      # load $current
      # register $current
      # self::$storeCache = false
      # return $current
    #}

    $doc = self::load($filePath);
    self::$currentFileTo["fileToDoc"] = $doc;
    $id = $doc->documentElement->firstElement->getAttribute("id");
    self::$currentFileTo["fileToId"] = $id;
    self::registerStructure($doc->documentElement, $parentId, $id, $linkPrefix, $filePath);
    self::addToRegister($filePath);

    #if(self::$storeCache) self::setApc($cacheKey);
    return $id;
  }

  public static function isLink($link) {
    return array_key_exists($link, self::$linkToId);
  }

  public static function getCurFile() {
    return self::getIdToFile(self::getLinkToId(getCurLink()));
  }

  public static function getRootId() {
    return key(self::$idToParentId);
  }

  public static function getHeadingValues($id, $title=false) {
    $values = array();
    if($title && strlen(self::getIdToTitle($id))) {
      $values[] = self::getIdToTitle($id);
    }
    if(strlen(self::getIdToShort($id))) {
      $values[] = self::getIdToShort($id);
    }
    $values[] = self::getIdToHeading($id);
    $values[] = getShortString(self::getIdToDesc($id));
    return $values;
  }

  private static function addToRegister($filePath) {
    foreach(self::$currentFileTo as $name => $value) {
      self::${$name}[$filePath] = $value;
    }
    foreach(self::$currentIdTo as $name => $value) {
      foreach($value as $id => $v) self::${$name}[$id] = $v;
    }
    foreach(self::$currentIdTo["idToLink"] as $name => $value) {
      self::$linkToId[$name] = $value;
    }
  }

  private static function load($filePath) {
    try {
      $doc = new HTMLPlus();
      $fp = findFile($filePath);
      $doc->load($fp);
      $doc->validatePlus(true);
      if(count($doc->getErrors()))
        Logger::user_notice(sprintf(_("Invalid HTML+ syntax fixed %s times: %s"),
          count($doc->getErrors()), $filePath));
      self::insertIncludes($doc, dirname($fp));
      self::$currentFileTo["fileToMtime"][$filePath] = filemtime($fp);
      self::setNewestFileMtime(self::$currentFileTo["fileToMtime"][$filePath]);
      return $doc;
    } catch(Exception $e) {
      throw new Exception(sprintf(_("Unable to load %s: %s"), $filePath, $e->getMessage()));
    }
  }

  private static function insertIncludes(HTMLPlus $doc, $workingDir) {
    $includes = array();
    foreach($doc->getElementsByTagName("include") as $include) $includes[] = $include;
    foreach($includes as $include) {
      try {
        $file = self::insert($include, $workingDir);
        self::$currentFileTo["fileToInclude"][] = $file;
      } catch(Exception $e) {
        $msg = sprintf(_("Unable to import: %s"), $e->getMessage());
        $c = new DOMComment(" $msg ");
        $include->parentNode->insertBefore($c, $include);
        Logger::user_error($msg);
        $include->stripTag();
        self::$storeCache = false;
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
    return $includeFile;
  }

  private static function registerStructure(DOMElementPlus $e, $parentId, $prefixId, $linkPrefix, $filePath) {
    $hId = $parentId;
    foreach($e->childElementsArray as $child) {
      if(strlen($child->getAttribute("id"))) {
        if($child->nodeName == "h") {
          $hId = self::registerElement($child, $parentId, $prefixId, $linkPrefix, $filePath);
        } else {
          self::registerElement($child, $hId, $prefixId, $linkPrefix, $filePath);
        }
      }
      self::registerStructure($child, $hId, $prefixId, $linkPrefix, $filePath);
    }
  }

  private static function registerElement(DOMElementPlus $e, $parentId, $prefixId, $linkPrefix, $filePath) {
    $id = $e->getAttribute("id");
    $link = "$linkPrefix/$id";
    if(is_null($parentId)) {
      if($filePath == INDEX_HTML) $link = "";
      else $parentId = current(self::$fileToId);
    }
    if($id != $prefixId) {
      $link = self::$currentIdTo["idToLink"][$prefixId]."#$id";
      $id = "$prefixId/$id";
    }
    if($e->nodeName == "h") {
      self::$currentIdTo["idToLink"][$id] = $link;
      #self::$currentIdTo["linkToId"][$link] = $id;
      self::setHeadingInfo($id, $e);
    }
    self::$currentIdTo["idToFile"][$id] = $filePath;
    self::$currentIdTo["idToTitle"][$id] = $e->getAttribute("title");
    self::$currentIdTo["idToParentId"][$id] = $parentId;
    return $id;
  }

  private static function setHeadingInfo($id, DOMElementPlus $h) {
    self::$currentIdTo["idToShort"][$id] = $h->getAttribute("short");
    self::$currentIdTo["idToHeading"][$id] = $h->nodeValue;
    self::$currentIdTo["idToDesc"][$id] = $h->nextElement->nodeValue;
    self::$currentIdTo["idToKw"][$id] = $h->nextElement->getAttribute("kw");
    self::$currentIdTo["idToAuthor"][$id] = $h->getAttribute("author");
    self::$currentIdTo["idToAuthorId"][$id] = $h->getAttribute("authorid");
    self::$currentIdTo["idToResp"][$id] = $h->getAttribute("resp");
    self::$currentIdTo["idToRespId"][$id] = $h->getAttribute("respid");
    self::$currentIdTo["idToCtime"][$id] = $h->getAttribute("ctime");
    self::$currentIdTo["idToMtime"][$id] = $h->getAttribute("mtime");
    self::$currentIdTo["idToLang"][$id] = $h->getSelfOrParentValue("xml:lang");
  }

}