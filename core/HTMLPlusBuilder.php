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

  private static $uriToInt = array();
  private static $intToElement = array();
  private static $intToParentInt = array();
  private static $include;

  public static function build($filePath, $parentUri=null, $prefixUri=null) {
    $parentInt = 0;
    if(!is_null($parentUri)) {
      if(array_key_exists($parentUri, self::$uriToInt)) {
        $parentInt = self::$uriToInt[$parentUri];
      } else {
        #Logger::user_warning(sprintf(_("File %s root URI '%s' not found"), $filePath, $parentUri));
        $prefixUri = null;
      }
    }
    self::$include = false;
    $doc = self::load($filePath);
    if(self::$include) {
      $doc->repairIds();
      if(count($doc->getErrors()))
        Logger::user_notice(sprintf(_("Duplicit identifiers fixed %s times after includes in %s"),
          count($doc->getErrors()), $filePath));
    }
    self::registerStructure($doc->documentElement, $parentInt, $prefixUri);
    return $doc;
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

  private static function registerStructure(DOMElementPlus $section, $parentInt, $prefix) {
    $int = count(self::$uriToInt);
    foreach($section->childElementsArray as $e) {
      if($e->nodeName == "h") {
        $id = $e->getAttribute("id");
        if(empty(self::$uriToInt)) self::$uriToInt[""] = $int;
        else self::$uriToInt["$prefix#$id"] = $int;
        self::$intToParentInt[$int] = $parentInt;
        self::$intToElement[$int] = $e;
        continue;
      }
      if($e->nodeName == "section") {
        self::registerStructure($e, $parentInt, $prefix);
      }
    }
  }

}