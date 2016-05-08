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

class XMLBuilder {

  public static function build($fileName) {
    $doc = new DOMDocumentPlus();

    $fp = CMS_FOLDER."/$fileName";
    $doc->load($fp);

    $fp = ADMIN_FOLDER."/$fileName";
    try {
      $adminDoc = new DOMDocumentPlus();
      $adminDoc->load($fp);
      self::updateDOM($doc, $adminDoc);
    } catch(NoFileException $e) {
      // skip
    } catch(Exception $e) {
      Logger::error(sprintf(_("Unable load admin XML file %s: %s"), $fileName, $e->getMessage()));
    }

    $fp = USER_FOLDER."/$fileName";
    try {
      $userDoc = new DOMDocumentPlus();
      $userDoc->load($fp);
      self::updateDOM($doc, $userDoc);
    } catch(NoFileException $e) {
      // skip
    } catch(Exception $e) {
      Logger::error(sprintf(_("Unable load user XML file %s: %s"), $fileName, $e->getMessage()));
    }

    return $doc;
  }

  private function updateDOM(DOMDocumentPlus $doc, DOMDocumentPlus $newDoc) {
    $docId = null;
    foreach($newDoc->documentElement->childElementsArray as $n) {
      // if empty && readonly => user cannot modify
      foreach($doc->getElementsByTagName($n->nodeName) as $d) {
        if($d->hasAttribute("readonly") && $d->nodeValue == "")
          continue 2;
      }
      if(self::doRemove($n)) {
        $remove = array();
        foreach($doc->documentElement->childElementsArray as $d) {
          if($d->nodeName != $n->nodeName) continue;
          if($d->hasAttribute("modifyonly")) continue;
          if(!$d->hasAttribute("readonly")) $remove[] = $d;
        }
        foreach($remove as $d) $d->parentNode->removeChild($d);
      } elseif($n->hasAttribute("id")) {
        if(is_null($docId)) $docId = self::getIds($doc);
        $curId = $n->getAttribute("id");
        if(!array_key_exists($curId, $docId)) {
          $doc->documentElement->appendChild($doc->importNode($n, true));
          continue;
        }
        $sameIdElement = $docId[$curId];
        if($sameIdElement->nodeName != $n->nodeName)
          throw new Exception(sprintf(_("ID '%s' conflicts with element '%s'"), $curId, $n->nodeName));
        if($sameIdElement->hasAttribute("readonly")) continue;
        $doc->documentElement->replaceChild($doc->importNode($n, true), $sameIdElement);
      } else {
        $doc->documentElement->appendChild($doc->importNode($n, true));
      }
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

  private static function doRemove(DOMElement $n) {
    if($n->nodeValue != "") return false;
    if($n->attributes->length > 1) return false;
    if($n->attributes->length == 1 && !$n->hasAttribute("readonly")) return false;
    return true;
  }

}
