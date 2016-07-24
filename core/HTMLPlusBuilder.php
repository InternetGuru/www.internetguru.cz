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

  private static $storeCache = true;
  private static $currentFileTo;
  private static $currentIdTo;

  const APC_ID = 0;

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

  private static function isValidApc($mtimes) {
    foreach($mtimes as $file => $mtime) {
      try {
        if($mtime == filemtime(findFile($file))) continue;
      } catch(Exception $e) {}
      return false;
    }
    return true;
  }

  public static function build($filePath) {
    if(array_key_exists($filePath, self::$fileToDoc))
      throw new Exception("File $filePath already built");
    $cacheKey = apc_get_key(self::APC_ID."/".__FUNCTION__."/".$filePath);
    $useCache = false;
    if(apc_exists($cacheKey)) {
      $cache = apc_fetch($cacheKey);
      $useCache = self::isValidApc($cache["fileToMtime"]);
    }
    if(!$useCache) {
      try {
        $doc = self::load($filePath);
        self::$fileToDoc[$filePath] = $doc;
        if(self::$storeCache) {
          $value = array(
            "fileToMtime" => self::$currentFileTo["fileToMtime"],
            "xml" => $doc->saveXML(),
          );
          apc_store_cache($cacheKey, $value, $filePath);
        }
        return $doc;
      } catch(Exception $e) {
        if(!apc_exists($cacheKey)) throw $e;
        Logger::error($e->getMessage());
        $useCache = true;
      }
    }
    if($useCache) {
      $doc = new HTMLPlus();
      $doc->loadXML($cache["xml"]);
      self::$fileToDoc[$filePath] = $doc;
      return $doc;
    }
  }

  public static function setIdToLink(Array $idToLink) {
    self::$idToLink = $idToLink;
    self::$linkToId = array();
    foreach($idToLink as $id => $link) {
      self::$linkToId[$link] = $id;
    }
  }

  public static function register($filePath, $prefix='') {
    if(strlen($prefix) && !array_key_exists($prefix, self::$linkToId)) {
      throw new Exception(sprintf(_("Undefined link '%s'"), $prefix));
    }
    self::$currentFileTo = array();
    self::$currentIdTo = array();
    self::$storeCache = true;
    $cacheKey = apc_get_key(self::APC_ID."/".__FUNCTION__."/".$filePath);
    $useCache = false;
    $cache = null;
    if(apc_exists($cacheKey)) {
      $cache = apc_fetch($cacheKey);
      $useCache = self::isValidApc($cache["currentFileTo"]["fileToMtime"]);
    }
    if($useCache) {
      self::$currentFileTo = $cache["currentFileTo"];
      self::setNewestFileMtime(current($cache["currentFileTo"]["fileToMtime"]));
      $doc = self::build($filePath);
      self::$currentIdTo = $cache["currentIdTo"];
      self::$storeCache = false;
    } else {
      $doc = self::build($filePath);
      $id = $doc->documentElement->firstElement->getAttribute("id");
      self::registerStructure($doc->documentElement, $prefix, $id, $prefix, $filePath);
      self::$currentFileTo["fileToId"] = $id;
    }
    self::addToRegister($filePath);
    if(self::$storeCache) {
      $value = array(
        "currentIdTo" => self::$currentIdTo,
        "currentFileTo" => self::$currentFileTo,
      );
      apc_store_cache($cacheKey, $value, $filePath);
    }
    return self::$currentFileTo["fileToId"];
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
      switch($name) {
        case "fileToMtime":
        foreach($value as $file => $mtime) {
          self::${$name}[$file] = $mtime;
        }
        break;
        default:
        self::${$name}[$filePath] = $value;
      }
    }
    foreach(self::$currentIdTo as $name => $value) {
      foreach($value as $id => $v) self::${$name}[$id] = $v;
    }
    foreach(self::$currentIdTo["idToLink"] as $name => $value) {
      self::$linkToId[$name] = $value;
    }
  }

  private static function load($filePath) {
    $doc = new HTMLPlus();
    $fp = findFile($filePath);
    try {
      $doc->load($fp);
      self::validateHtml($doc, $filePath, false);
      self::insertIncludes($doc, dirname($filePath));
      if(array_key_exists("fileToMtime", self::$currentFileTo)
        && count(self::$currentFileTo["fileToMtime"]) > 1) {
        self::validateHtml($doc, $filePath, true);
      }
    } catch(Exception $e) {
      throw new Exception(sprintf(_("Unable to load %s: %s"), $fp, $e->getMessage()));
    }
    self::$currentFileTo["fileToMtime"][$filePath] = filemtime($fp);
    self::setNewestFileMtime(self::$currentFileTo["fileToMtime"][$filePath]);
    return $doc;
  }

  private static function validateHtml(HTMLPlus $doc, $filePath, $included) {
    $doc->validatePlus(true);
    if(empty($doc->getErrors())) return;
    if($included) $msg = _("File %s invalid syntax caused by includes fixed %s times");
    else $msg = _("File %s invalid syntax fixed %s times");
    Logger::user_notice(sprintf($msg, $filePath, count($doc->getErrors())));
    self::$storeCache = false;
  }

  private static function insertIncludes(HTMLPlus $doc, $workingDir) {
    foreach($doc->getElementsByTagName("h") as $h) {
      if(!$h->hasAttribute("src")) continue;
      try {
        $file = self::insert($h, $workingDir);
      } catch(Exception $e) {
        $msg = sprintf(_("Unable to import: %s"), $e->getMessage());
        Logger::user_error($msg);
        self::$storeCache = false;
      }
    }
  }

  private static function getIncludeSrc($src, $workingDir) {
    if(pathinfo($src, PATHINFO_EXTENSION) != "html")
      throw new Exception(sprintf(_("Included file '%s' extension must be .html"), $src));
    $file = findFile("$workingDir/$src");
    if(strpos($file, realpath("$workingDir/")) !== 0)
      throw new Exception(sprintf(_("Included file '%s' is out of working directory"), $src));
    if($workingDir == ".") return $src;
    return "$workingDir/$src";
  }

  private static function insert(DOMElement $h, $workingDir) {
    $src = $h->getAttribute("src");
    $includeFile = self::getIncludeSrc($src, $workingDir);
    $doc = self::load($includeFile);
    $lang = $doc->documentElement->getAttribute("xml:lang");
    foreach($doc->documentElement->childElementsArray as $n) {
      $e = $h->parentNode->insertBefore($h->ownerDocument->importNode($n, true), $h);
      if(strlen($e->getAttribute("xml:lang"))) continue;
      $e->setAttribute("xml:lang", $lang);
    }
    while(!is_null($h)) {
      $next = $h->nextElement;
      $h->parentNode->removeChild($h);
      $h = $next;
      if($h->nodeName == "h") break;
    }
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
    $link = strlen($linkPrefix) ? "$linkPrefix/$id" : $id;
    if(!strlen($parentId)) {
      $parentId = null;
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
    if($e->hasAttribute("title")) {
      self::$currentIdTo["idToTitle"][$id] = $e->getAttribute("title");
    }
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