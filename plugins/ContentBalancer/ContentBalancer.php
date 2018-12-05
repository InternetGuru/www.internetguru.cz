<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\ErrorPage;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class ContentBalancer
 * @package IGCMS\Plugins
 */
class ContentBalancer extends Plugin implements SplObserver, ModifyContentStrategyInterface {
  /**
   * @var int
   */
  const DEFAULT_LIMIT = 2;
  /**
   * @var int
   */
  const DEFAULT_LEVEL = 2;
  /**
   * @var string
   */
  const NOBALANCE_HEADING_CLASS = "nobalance";
  /**
   * @var array
   */
  private $tree = [];
  /**
   * @var array
   */
  private $sets = [];
  /**
   * @var string|null
   */
  private $defaultSet = null;
  /**
   * @var int
   * Balanced headings level. Zero value means no balancing at all.
   */
  private $level;
  /**
   * @var int
   * Minimum number of subheadings to be balanced. Zero value means no limit.
   */
  private $limit;
  /**
   * @var array
   */
  private $idToLink;
  /**
   * @var string
   */
  private $noBalanceClass;

  /**
   * ContentBalancer constructor.
   * @param SplSubject|Plugins $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 110);
  }

  /**
   * @param SplSubject|Plugins $subject
   * @throws Exception
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_PREINIT) {
      return;
    }
    $this->createVars();
    if ($this->level == 0) {
      return;
    }
    $this->setTree();
    $this->idToLink = HTMLPlusBuilder::getIdToLink();
    foreach (HTMLPlusBuilder::getFileToId() as $file => $fileId) {
      $body = HTMLPlusBuilder::getFileToDoc($file)->documentElement;
      if ($body->hasClass($this->noBalanceClass)) {
        continue;
      }
      $this->balanceLinks($fileId);
      $this->modifyFragmentLinks($fileId);
    }
    HTMLPlusBuilder::setIdToLink($this->idToLink);
  }

  /**
   * @throws Exception
   */
  private function createVars () {
    $cfg = self::getXML();
    foreach ($cfg->documentElement->childElementsArray as $element) {
      try {
        $eId = $element->getRequiredAttribute("id");
        if ($element->nodeName == "var") {
          $this->loadVar($eId, $element);
        } else if ($element->nodeName == "item") {
          $element->getRequiredAttribute("wrapper"); // only check
          $this->sets[$eId] = $element;
        }
      } catch (Exception $exc) {
        Logger::user_warning(sprintf(_("Skipped element %s: %s"), $element->nodeName, $exc->getMessage()));
      }
    }
  }

  /**
   * @param string $id
   * @param DOMElementPlus $e
   */
  private function loadVar ($id, DOMElementPlus $e) {
    switch ($id) {
      case "nobalance":
        $this->noBalanceClass = $e->nodeValue;
        break;
      case "default":
        $this->defaultSet = $e->nodeValue;
        break;
      case "limit":
      case "level":
        $this->{$id} = intval($e->nodeValue);
        break;
    }
  }

  private function setTree () {
    foreach (HTMLPlusBuilder::getIdToLink() as $hId => $void) {
      $parentId = HTMLPlusBuilder::getIdToParentId($hId);
      $this->tree[$hId] = [];
      if (is_null($parentId)) {
        continue;
      }
      if (HTMLPlusBuilder::getIdToFile($hId) != HTMLPlusBuilder::getIdToFile($parentId)) {
        continue;
      }
      $this->tree[$parentId][] = $hId;
    }
  }

  /**
   * @param string $hId
   * @return int
   */
  private function balanceLinks ($hId) {
    $depth = 0;
    foreach ($this->tree[$hId] as $childId) {
      $childDepth = $this->balanceLinks($childId);
      if ($childDepth > $depth) {
        $depth = $childDepth;
      }
    }
    $link = $this->idToLink[$hId];
    if ($depth + 1 >= $this->level && count($this->tree[$hId]) >= $this->limit) {
      if (strpos($link, "#") === 0) {
        $link = substr($link, 1);
      }
      $link = str_replace("#", "/", $link);
      $this->idToLink[$hId] = $link;
    }
    return ++$depth;
  }

  /**
   * @param string $parentId
   * @param string $link
   */
  private function modifyFragmentLinks ($parentId, $link = "") {
    foreach ($this->tree[$parentId] as $childId) {
      if (strpos($this->idToLink[$childId], "#") === 0) {
        $this->idToLink[$childId] = $link.$this->idToLink[$childId];
        $this->modifyFragmentLinks($childId, $link);
        continue;
      }
      $this->modifyFragmentLinks($childId, $this->idToLink[$childId]);
    }
  }

  /**
   * @param HTMLPlus $content
   * @throws Exception
   */
  public function modifyContent (HTMLPlus $content) {
    if ($content->documentElement->hasClass($this->noBalanceClass)) {
      return;
    }
    if ($this->level == 0) {
      return;
    }
    // check sets
    if (empty($this->sets)) {
      Logger::critical(_("No sets found"));
      return;
    }
    // check default set
    if (is_null($this->defaultSet) || !isset($this->sets[$this->defaultSet])) {
      reset($this->sets);
      $this->defaultSet = key($this->sets);
    }
    // proceed
    $prefixId = $content->documentElement->firstElement->getAttribute("id");
    $content = $this->strip($content);
    $h1id = $content->documentElement->firstElement->getAttribute("id");
    Cms::setVariable('h1id', $h1id);
    if ($h1id != $prefixId) {
      $h1id = "$prefixId/$h1id";
    }
    $this->balanceContent($content, $h1id);
  }

  /**
   * @param HTMLPlus $content
   * @return HTMLPlus
   * @throws Exception
   */
  private function strip (HTMLPlus $content) {
    $link = basename(get_link());
    if ($this->isRoot($link)) {
      return $content;
    }
    $h1Elm = $content->getElementById($link, "h");
    if (is_null($h1Elm)) {
      // Redirect encoded # (%23) in url to decoded url
      if (strpos($link, "#") != -1) {
        redir_to($link);
      }
      new ErrorPage(_("Unable to find requested section"), 500);
    }
    $this->handleAttribute($h1Elm, "ctime");
    $this->handleAttribute($h1Elm, "mtime");
    $this->handleAttribute($h1Elm, "author");
    $this->handleAttribute($h1Elm, "authorid");
    $this->handleAttribute($h1Elm, "resp");
    $this->handleAttribute($h1Elm, "respid");
    $this->handleAttribute($h1Elm->parentNode, "xml:lang", true);
    $body = $content->documentElement;
    foreach ($h1Elm->parentNode->attributes as $attNode) {
      $body->setAttribute($attNode->nodeName, $attNode->nodeValue);
    }
    $elements = $this->getUntilSame($h1Elm);
    //    $body->removeChildNodes(); # not working
    $toRemove = [];
    foreach ($body->childNodes as $node) {
      $toRemove[] = $node;
    }
    foreach ($toRemove as $node) {
      $body->removeChild($node);
    }
    foreach ($elements as $element) {
      $body->appendChild($element);
    }
    return $content;
  }

  /**
   * @param string $link
   * @return bool
   */
  private function isRoot ($link) {
    if ($link == "") {
      return true;
    }
    return array_key_exists($link, HTMLPlusBuilder::getIdToLink());
  }

  /**
   * @param DOMElementPlus $e
   * @param string $aName
   * @param bool $anyElement
   */
  private function handleAttribute (DOMElementPlus $e, $aName, $anyElement = false) {
    if ($e->hasAttribute($aName)) {
      return;
    }
    $eName = $anyElement ? null : $e->nodeName;
    $value = $e->getAncestorValue($aName, $eName);
    if (is_null($value)) {
      return;
    }
    $e->setAttribute($aName, $value);
  }

  /**
   * @param DOMElementPlus $element
   * @return array
   */
  private function getUntilSame (DOMElementPlus $element) {
    $elements = [$element];
    $untilName = $element->nodeName;
    while (($element = $element->nextElement) !== null) {
      if ($element->nodeName == $untilName) {
        break;
      }
      $elements[] = $element;
    }
    return $elements;
  }

  /**
   * @param HTMLPlus $content
   * @param string $hId
   * @param int $level
   * @throws Exception
   */
  private function balanceContent (HTMLPlus $content, $hId, $level = 1) {
    if (!array_key_exists($hId, $this->tree)) {
      return;
    }

    if ($level < $this->level || count($this->tree[$hId]) < $this->limit) {
      foreach ($this->tree[$hId] as $childId) {
        $this->balanceContent($content, $childId, $level + 1);
      }
      return;
    }
    $section = $content->getElementById(basename($this->tree[$hId][0]), "h")->parentNode;
    $this->balanceHeading($section, $this->tree[$hId]);
  }

  /**
   * @param DOMElementPlus $section
   * @param array $h3ids
   */
  private function balanceHeading (DOMElementPlus $section, $h3ids) {
    $setId = null;
    foreach (explode(" ", $section->getAttribute("class")) as $class) {
      if (strpos($class, strtolower("$this->className-")) !== 0) {
        continue;
      }
      $setId = substr($class, strlen("$this->className-"));
    }
    if ($setId == "none") {
      $section->parentNode->removeChild($section);
      return;
    }
    $set = $this->sets[$this->defaultSet];
    if (!is_null($setId)) {
      if (isset($this->sets[$setId])) {
        $set = $this->sets[$setId];
      } else {
        Logger::user_warning(sprintf(_("Item id %s not found, using default"), $setId));
      }
    }
    $wrapper = $section->ownerDocument->createElement($set->getAttribute("wrapper"));
    $wrapper->setAttribute("class", strtolower($this->className)."-".$set->getAttribute("id"));
    $newSection = null;
    foreach ($h3ids as $h3id) {
      $id = substr($h3id, strpos($h3id, "/") + 1);
      $h3 = $section->ownerDocument->getElementById($id);
      if (is_null($h3)) {
        continue;
      }
      if ($h3->hasClass(self::NOBALANCE_HEADING_CLASS)) {
        if (is_null($newSection)) {
          $newSection = $section->ownerDocument->createElement("section");
          $section->parentNode->insertBefore($newSection, $section);
        }
        $elements = $this->getUntilSame($h3);
        foreach ($elements as $element) {
          $newSection->appendChild($element);
        }
        continue;
      }
      $vars = $this->getVariables($h3id);
      $root = $this->createDOMElement($vars, $set);
      foreach ($root->childElementsArray as $element) {
        $wrapper->appendChild($section->ownerDocument->importNode($element, true));
      }
    }
    $section->parentNode->replaceChild($wrapper, $section);
  }

  /**
   * @param string $id
   * @return array
   */
  private function getVariables ($id) {
    $vars = [];
    $vars['heading'] = [
      "value" => HTMLPlusBuilder::getIdToHeading($id),
      "cacheable" => true,
    ];
    $vars['link'] = [
      "value" => $id,
      "cacheable" => true,
    ];
    $vars['headingplus'] = [
      "value" => HTMLPlusBuilder::getHeading($id),
      "cacheable" => true,
    ];
    $vars['short'] = [
      "value" => HTMLPlusBuilder::getIdToShort($id),
      "cacheable" => true,
    ];
    $vars['desc'] = [
      "value" => HTMLPlusBuilder::getIdToDesc($id),
      "cacheable" => true,
    ];
    $vars['kw'] = [
      "value" => HTMLPlusBuilder::getIdToKw($id),
      "cacheable" => true,
    ];
    return $vars;
  }

  /**
   * @param array $vars
   * @param DOMElementPlus $set
   * @return DOMElementPlus
   */
  private function createDOMElement (Array $vars, DOMElementPlus $set) {
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($set, true));
    $doc->processVariables($vars);
    return $doc->documentElement;
  }

}
