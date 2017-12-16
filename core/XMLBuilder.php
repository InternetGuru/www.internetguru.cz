<?php

namespace IGCMS\Core;

use Exception;

/**
 * Class XMLBuilder
 * @package IGCMS\Core
 */
class XMLBuilder extends DOMBuilder {
  /**
   * @param string $fileName
   * @return DOMDocumentPlus
   * @throws Exception
   */
  public static function load ($fileName) {
    $doc = new DOMDocumentPlus();
    $filePath = find_file($fileName);
    $doc->load($filePath);
    self::setNewestFileMtime(filemtime($filePath));
    return $doc;
  }

  /**
   * @param string $fileName
   * @param bool $user
   * @return DOMDocumentPlus
   * @throws Exception
   */
  public static function build ($fileName, $user = true) {
    $doc = new DOMDocumentPlus();
    $filePath = CMS_FOLDER."/$fileName";
    $doc->load($filePath);
    self::setNewestFileMtime(filemtime($filePath));

    $filePath = ADMIN_FOLDER."/$fileName";
    try {
      $adminDoc = new DOMDocumentPlus();
      $adminDoc->load($filePath);
      self::updateDOM($doc, $adminDoc);
      self::setNewestFileMtime(filemtime($filePath));
    } catch (NoFileException $exc) {
      // skip
    } catch (Exception $exc) {
      Logger::error(sprintf(_("Unable load admin XML file %s: %s"), $fileName, $exc->getMessage()));
    }

    if (!$user) {
      return $doc;
    }

    $filePath = USER_FOLDER."/$fileName";
    try {
      $userDoc = new DOMDocumentPlus();
      $userDoc->load($filePath);
      self::updateDOM($doc, $userDoc);
      self::setNewestFileMtime(filemtime($filePath));
    } catch (NoFileException $exc) {
      // skip
    } catch (Exception $exc) {
      Logger::error(sprintf(_("Unable load user XML file %s: %s"), $fileName, $exc->getMessage()));
    }

    return $doc;
  }

  /**
   * @param DOMDocumentPlus $doc
   * @param DOMDocumentPlus $newDoc
   * @throws Exception
   */
  private static function updateDOM (DOMDocumentPlus $doc, DOMDocumentPlus $newDoc) {
    $docId = null;
    foreach ($newDoc->documentElement->childElementsArray as $child) {
      if (!$child->hasAttribute("id")) {
        self::appendChildFormat($doc->documentElement, $child);
        continue;
      }
      if (is_null($docId)) {
        $docId = self::getIds($doc);
      }
      $curId = $child->getAttribute("id");
      if (!array_key_exists($curId, $docId)) {
        self::appendChildFormat($doc->documentElement, $child);
        continue;
      }
      if ($docId[$curId]->nodeName != $child->nodeName) {
        throw new Exception(sprintf(_("Element id '%s' names differ"), $curId));
      }
      if ($docId[$curId]->hasAttribute("readonly")) {
        throw new Exception(sprintf(_("Element id '%s' is readonly"), $curId));
      }
      $pattern = $docId[$curId]->getAttribute("pattern");
      if (strlen($pattern) && !preg_match("/^$pattern$/", $child->nodeValue)) {
        throw new Exception(sprintf(_("Element id '%s' pattern mismatch"), $curId));
      }
      $doc->documentElement->replaceChild($doc->importNode($child, true), $docId[$curId]);
    }
  }

  /**
   * @param DOMElementPlus $parent
   * @param DOMElementPlus $child
   */
  private static function appendChildFormat (DOMElementPlus $parent, DOMElementPlus $child) {
    $parent->appendChild($parent->ownerDocument->createTextNode("  "));
    $parent->appendChild($parent->ownerDocument->importNode($child, true));
    $parent->appendChild($parent->ownerDocument->createTextNode("\n"));
  }

  /**
   * @param DOMDocumentPlus $doc
   * @return array
   */
  private static function getIds (DOMDocumentPlus $doc) {
    $ids = [];
    foreach ($doc->documentElement->childElementsArray as $child) {
      if (!$child->hasAttribute("id")) {
        continue;
      }
      $ids[$child->getAttribute("id")] = $child;
    }
    return $ids;
  }

}
