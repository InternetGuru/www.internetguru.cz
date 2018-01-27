<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use IGCMS\Core\TitleStrategyInterface;
use SplObserver;
use SplSubject;

/**
 * Class Breadcrumb
 * @package IGCMS\Plugins
 */
class Breadcrumb extends Plugin implements SplObserver, TitleStrategyInterface {
  /**
   * @var array
   */
  private $vars = [];
  /**
   * @var string|null
   */
  private $title = null;

  /**
   * @param Plugins|SplSubject $subject
   * @throws Exception
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_POSTPROCESS) {
      return;
    }
    foreach (self::getXML()->documentElement->childElementsArray as $childElm) {
      if ($childElm->nodeName != "var" || !$childElm->hasAttribute("id")) {
        continue;
      }
      $this->vars[$childElm->getAttribute("id")] = $childElm;
    }
    $this->generateBc();
  }

  /**
   * @throws Exception
   */
  private function generateBc () {
    #var_dump(HTMLPlusBuilder::getLinkToId());
    $parentId = HTMLPlusBuilder::getLinkToId(get_link());
    if (is_null($parentId)) {
      throw new Exception(sprintf(_("Link %s not found"), get_link()));
    }
    $bcLang = HTMLPlusBuilder::getIdToLang($parentId);
    $path = [];
    while (!is_null($parentId)) {
      $path[] = $parentId;
      $parentId = HTMLPlusBuilder::getIdToParentId($parentId);
    }
    #var_dump($path); die("die");
    $bcDoc = new DOMDocumentPlus();
    $root = $bcDoc->appendChild($bcDoc->createElement("root"));
    $olElm = $bcDoc->createElement("ol");
    $root->appendChild($olElm);
    $olElm->setAttribute("class", "contentlink-bc"); // TODO: rename
    $olElm->setAttribute("lang", $bcLang);
    $title = [];
    $pageId = null;
    foreach (array_reverse($path) as $pageId) {
      $liElm = $bcDoc->createElement("li");
      $olElm->appendChild($liElm);
      $lang = HTMLPlusBuilder::getIdToLang($pageId);
      if ($lang != $bcLang) {
        $liElm->setAttribute("lang", $lang);
      }
      $aElm = $bcDoc->createElement("a");
      $liElm->appendChild($aElm);
      $pLink["path"] = HTMLPlusBuilder::getIdToLink($pageId);
      add_perm_param($pLink);
      if (implode_link($pLink) != get_link(true)) {
        $aElm->setAttribute("href", $pageId);
        if ($pLink["path"] == get_link()) {
          $aElm->setAttribute("title", $this->vars["reset"]->nodeValue);
        }
      }
      $aValue = HTMLPlusBuilder::getHeading($pageId, !strlen(get_link()));
      if (empty($title) && array_key_exists("logo", $this->vars)) {
        $this->insertLogo($this->vars["logo"], $aElm, $pageId);
        if (!strlen(get_link())) {
          $aElm->parentNode->appendChild($bcDoc->createElement("span", $aValue));
        }
      } else {
        $aElm->nodeValue = $aValue;
      }
      $title[] = HTMLPlusBuilder::getHeading($pageId);
    }
    array_pop($title);
    $title[] = HTMLPlusBuilder::getIdToHeading($pageId);
    $this->title = implode(" - ", array_reverse($title));
    Cms::setVariable("", $bcDoc->documentElement);
  }

  /**
   * @param DOMElementPlus $logo
   * @param DOMElementPlus $a
   * @param string $id
   * @throws Exception
   */
  private function insertLogo (DOMElementPlus $logo, DOMElementPlus $a, $id) {
    $doc = $a->ownerDocument;
    $img = $doc->createElement("img");
    $img->setAttribute("alt", HTMLPlusBuilder::getIdToHeading($id));
    $img->setAttribute("src", $logo->nodeValue);
    $a->addClass("logo");
    $a->appendChild($img);
  }

  /**
   * @return string|null
   */
  public function getTitle () {
    return $this->title;
  }

}
