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

  private static $idToElement = array();
  private static $idToParentId = array();
  private static $include;

  public static function build($filePath, $rootId=null) {
    if(!is_null($rootId))
    self::$include = false;
    $doc = self::load($filePath);
    if(self::$include) $doc->validatePlus(true);
    self::registerStructure($doc->documentElement, $rootId);
    return $doc;
  }

  private static function load($filePath) {
    $doc = new HTMLPlus();
    if(!@$doc->load($filePath))
      throw new Exception(sprintf(_("Unable to load '%s'"), $filePath));
    self::validate($doc);
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

  private static function validate(HTMLPlus $doc) {
    try {
      $doc->validatePlus();
    } catch(Exception $e) {
      $doc->validatePlus(true);
      Logger::user_notice(sprintf(_("Invalid syntax fixed (%s times)"), count($doc->getErrors())));
    }
  }

  private static function registerStructure(DOMElementPlus $section, $rootId, $parentId) {
    $id = null;
    foreach($section->childElementsArray as $e) {
      if($e->nodeName == "h") {
        $id = $e->getAttribute("id");
        self::$idToParentId[$id] = $parentId;
        self::$idToElement[$id] = $e;
        continue;
      }
      if($e->nodeName == "section") {
        self::registerStructure($e, $id);
      }
    }
  }

}