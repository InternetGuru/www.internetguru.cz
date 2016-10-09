<?php

namespace IGCMS\Core;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMBuilder;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\NoFileException;
use Exception;
use DOMXPath;
use DOMDocument;
use DOMElement;
use DOMComment;
use DateTime;

class XMLBuilder extends DOMBuilder {

  public static function load($fileName) {
    $doc = new DOMDocumentPlus();
    $fp = findFile($fileName);
    $doc->load($fp);
    self::setNewestFileMtime(filemtime($fp));
    return $doc;
  }

  public static function build($fileName, $user=true) {
    $doc = new DOMDocumentPlus();
    $fp = CMS_FOLDER."/$fileName";
    $doc->load($fp);
    self::setNewestFileMtime(filemtime($fp));

    $fp = ADMIN_FOLDER."/$fileName";
    try {
      $adminDoc = new DOMDocumentPlus();
      $adminDoc->load($fp);
      self::updateDOM($doc, $adminDoc);
      self::setNewestFileMtime(filemtime($fp));
    } catch(NoFileException $e) {
      // skip
    } catch(Exception $e) {
      Logger::error(sprintf(_("Unable load admin XML file %s: %s"), $fileName, $e->getMessage()));
    }

    if(!$user) return $doc;

    $fp = USER_FOLDER."/$fileName";
    try {
      $userDoc = new DOMDocumentPlus();
      $userDoc->load($fp);
      self::updateDOM($doc, $userDoc);
      self::setNewestFileMtime(filemtime($fp));
    } catch(NoFileException $e) {
      // skip
    } catch(Exception $e) {
      Logger::error(sprintf(_("Unable load user XML file %s: %s"), $fileName, $e->getMessage()));
    }

    return $doc;
  }

  private static function updateDOM(DOMDocumentPlus $doc, DOMDocumentPlus $newDoc) {
    $docId = null;
    foreach($newDoc->documentElement->childElementsArray as $n) {
      if(!$n->hasAttribute("id")) {
        $doc->documentElement->appendChild($doc->importNode($n, true));
        continue;
      }
      if(is_null($docId)) $docId = self::getIds($doc);
      $curId = $n->getAttribute("id");
      if(!array_key_exists($curId, $docId)) {
        $doc->documentElement->appendChild($doc->importNode($n, true));
        continue;
      }
      if($docId[$curId]->nodeName != $n->nodeName)
        throw new Exception(sprintf(_("Element id '%s' names differ"), $curId));
      if($docId[$curId]->hasAttribute("readonly"))
        throw new Exception(sprintf(_("Element id '%s' is readonly"), $curId));
      $pattern = $docId[$curId]->getAttribute("pattern");
      if(strlen($pattern) && !preg_match("/^$pattern$/", $n->nodeValue))
        throw new Exception(sprintf(_("Element id '%s' pattern mismatch"), $curId));
      $doc->documentElement->replaceChild($doc->importNode($n, true), $docId[$curId]);
    }
  }

  private static function getIds(DOMDocumentPlus $doc) {
    $ids = array();
    foreach($doc->documentElement->childElementsArray as $n) {
      if(!$n->hasAttribute("id")) continue;
      $ids[$n->getAttribute("id")] = $n;
    }
    return $ids;
  }

}
