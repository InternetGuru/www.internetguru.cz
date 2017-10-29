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
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_POSTPROCESS) {
      return;
    }
    foreach ($this->getXML()->documentElement->childElementsArray as $e) {
      if ($e->nodeName != "var" || !$e->hasAttribute("id")) {
        continue;
      }
      $this->vars[$e->getAttribute("id")] = $e;
    }
    $this->generateBc();
  }

  /**
   * @throws Exception
   */
  private function generateBc () {
    #var_dump(HTMLPlusBuilder::getLinkToId());
    $parentId = HTMLPlusBuilder::getLinkToId(getCurLink());
    if (is_null($parentId)) {
      throw new Exception(sprintf(_("Link %s not found"), getCurLink()));
    }
    $bcLang = HTMLPlusBuilder::getIdToLang($parentId);
    $path = [];
    while (!is_null($parentId)) {
      $path[] = $parentId;
      $parentId = HTMLPlusBuilder::getIdToParentId($parentId);
    }
    #var_dump($path); die("die");
    $bc = new DOMDocumentPlus();
    $root = $bc->appendChild($bc->createElement("root"));
    $ol = $bc->createElement("ol");
    $root->appendChild($ol);
    $ol->setAttribute("class", "contentlink-bc"); // TODO: rename
    $ol->setAttribute("lang", $bcLang);
    $title = [];
    $id = $a = null;
    foreach (array_reverse($path) as $id) {
      $li = $bc->createElement("li");
      $ol->appendChild($li);
      $lang = HTMLPlusBuilder::getIdToLang($id);
      if ($lang != $bcLang) {
        $li->setAttribute("lang", $lang);
      }
      $a = $bc->createElement("a");
      $li->appendChild($a);
      $a->setAttribute("href", $id);
      $aValue = HTMLPlusBuilder::getHeading($id, !strlen(getCurLink()));
      if (empty($title) && array_key_exists("logo", $this->vars)) {
        $this->insertLogo($this->vars["logo"], $a, $id);
        if (!strlen(getCurLink())) {
          $a->parentNode->appendChild($bc->createElement("span", $aValue));
        }
      } else {
        $a->nodeValue = $aValue;
      }
      $title[] = HTMLPlusBuilder::getHeading($id);
    }
    array_pop($title);
    $title[] = HTMLPlusBuilder::getIdToHeading($id);
    $pLink["path"] = HTMLPlusBuilder::getIdToLink($a->getAttribute("href"));
    addPermParams($pLink);
    if (implodeLink($pLink) != getCurLink(true)) {
      $a->setAttribute("title", $this->vars["reset"]->nodeValue);
    }
    $this->title = implode(" - ", array_reverse($title));
    Cms::setVariable("", $bc->documentElement);
  }

  /**
   * @param DOMElementPlus $logo
   * @param DOMElementPlus $a
   * @param string $id
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

?>
