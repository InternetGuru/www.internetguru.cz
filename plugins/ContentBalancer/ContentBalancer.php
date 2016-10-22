<?php

namespace IGCMS\Plugins;

use Exception;
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
   * @var array
   */
  private $tree = array();
  /**
   * @var array
   */
  private $sets = array();
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
   * @var int
   */
  const DEFAULT_LIMIT = 2;
  /**
   * @var int
   */
  const DEFAULT_LEVEL = 2;

  /**
   * ContentBalancer constructor.
   * @param SplSubject|Plugins $s
   */
  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 2);
  }

  /**
   * @param SplSubject|Plugins $subject
   */
  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PREINIT) return;
    try {
      $this->createVars();
    } catch(Exception $ex) {
      Logger::user_warning(sprintf(_("Skipped element: %s"), $ex->getMessage()));
    }
    if($this->level == 0) return;
    $this->setTree();
    $this->idToLink = HTMLPlusBuilder::getIdToLink();
    foreach(HTMLPlusBuilder::getFileToId() as $file => $id) {
      $body = HTMLPlusBuilder::getFileToDoc($file)->documentElement;
      if($body->hasClass($this->noBalanceClass)) continue;
      $this->balanceLinks($id);
      $this->modifyFragmentLinks($id);
    }
    HTMLPlusBuilder::setIdToLink($this->idToLink);
  }

  /**
   * @throws Exception
   */
  private function createVars() {
    $cfg = $this->getXML();
    foreach($cfg->documentElement->childElementsArray as $e) {
      $id = $e->getAttribute("id");
      if($e->nodeName == "var") {
        $this->loadVar($id, $e);
      }
      else if($e->nodeName == "item") {
        if(!strlen($id)) throw new Exception(_("Element item missing id"));
        $e->getRequiredAttribute("wrapper"); // only check
        $this->sets[$id] = $e;
      }
    }
  }

  /**
   * @param string $id
   * @param DOMElementPlus $e
   */
  private function loadVar($id, DOMElementPlus $e) {
    switch($id) {
      case "nobalance":
        $this->noBalanceClass = $e->nodeValue;
        break;
      case "default":
        $this->defaultSet = $e->nodeValue;
        break;
      case "limit":
      case "level":
        // TODO validation
        $this->{$id} = $e->nodeValue;
        break;
    }
  }

  private function setTree() {
    foreach(HTMLPlusBuilder::getIdToLink() as $id => $void) {
      $parentId = HTMLPlusBuilder::getIdToParentId($id);
      $this->tree[$id] = array();
      if(is_null($parentId)) continue;
      if(HTMLPlusBuilder::getIdToFile($id) != HTMLPlusBuilder::getIdToFile($parentId)) continue;
      $this->tree[$parentId][] = $id;
    }
  }

  /**
   * @param string $id
   * @param int $siblings
   * @return int
   */
  private function balanceLinks($id, $siblings=1) {
    $deep = 0;
    foreach($this->tree[$id] as $childId) {
      $d = $this->balanceLinks($childId, count($this->tree[$id]));
      if($d > $deep) $deep = $d;
    }
    $link = $this->idToLink[$id];
    if($deep + 1 >= $this->level && $siblings >= $this->limit) {
      if(strpos($link, "#") === 0) $link = substr($link, 1);
      $link = str_replace("#", "/", $link);
      $this->idToLink[$id] = $link;
    }
    return ++$deep;
  }

  /**
   * @param string $parentId
   * @param string $link
   */
  private function modifyFragmentLinks($parentId, $link="") {
    foreach($this->tree[$parentId] as $childId) {
      if(strpos($this->idToLink[$childId], "#") === 0) {
        $this->idToLink[$childId] = $link.$this->idToLink[$childId];
        $this->modifyFragmentLinks($childId, $link);
        continue;
      }
      $this->modifyFragmentLinks($childId, $this->idToLink[$childId]);
    }
  }

  /**
   * @param HTMLPlus $content
   */
  public function modifyContent(HTMLPlus $content) {
    if($content->documentElement->hasClass($this->noBalanceClass)) return;
    if($this->level == 0) return;
    // check sets
    if(empty($this->sets)) {
      Logger::critical(_("No sets found"));
      return;
    }
    // check default set
    if(is_null($this->defaultSet) || !isset($this->sets[$this->defaultSet])) {
      reset($this->sets);
      $this->defaultSet = key($this->sets);
    }
    // proceed
    $prefixId = $content->documentElement->firstElement->getAttribute("id");
    $content = $this->strip($content);
    $h1id = $content->documentElement->firstElement->getAttribute("id");
    if($h1id != $prefixId) $h1id = "$prefixId/$h1id";
    $this->balanceContent($content, $h1id);
  }

  /**
   * @param HTMLPlus $content
   * @return HTMLPlus
   */
  private function strip(HTMLPlus $content) {
    $link = basename(getCurLink());
    if($this->isRoot($link)) return $content;
    $h1 = $content->getElementById($link, "h");
    if(is_null($h1)) new ErrorPage(sprintf(_("Page '%s' not found"), getCurLink()), 404);
    $this->handleAttribute($h1, "ctime");
    $this->handleAttribute($h1, "mtime");
    $this->handleAttribute($h1, "author");
    $this->handleAttribute($h1, "authorid");
    $this->handleAttribute($h1, "resp");
    $this->handleAttribute($h1, "respid");
    $this->handleAttribute($h1->parentNode, "xml:lang", true);
    $body = $content->documentElement;
    foreach($h1->parentNode->attributes as $attNode) {
      $body->setAttribute($attNode->nodeName, $attNode->nodeValue);
    }
    $elements = $this->getUntilSame($h1);
    foreach($body->childElementsArray as $child) {
      $body->removeChild($child);
    }
    foreach($elements as $e) {
      $body->appendChild($e);
    }
    return $content;
  }

  /**
   * @param DOMElementPlus $e
   * @return array
   */
  private function getUntilSame(DOMElementPlus $e) {
    $elements = array($e);
    $untilName = $e->nodeName;
    while(($e = $e->nextElement) !== null) {
      if($e->nodeName == $untilName) break;
      $elements[] = $e;
    }
    return $elements;
  }

  /**
   * @param string $link
   * @return bool
   */
  private function isRoot($link) {
    if($link == "") return true;
    return array_key_exists($link, HTMLPlusBuilder::getIdToLink());
  }

  /**
   * @param DOMElementPlus $e
   * @param string $aName
   * @param bool $anyElement
   */
  private function handleAttribute(DOMElementPlus $e, $aName, $anyElement=false) {
    if($e->hasAttribute($aName)) return;
    $eName = $anyElement ? null : $e->nodeName;
    $value = $e->getAncestorValue($aName, $eName);
    if(is_null($value)) return;
    $e->setAttribute($aName, $value);
  }

  /**
   * @param HTMLPlus $content
   * @param string $hId
   * @param int $level
   */
  private function balanceContent(HTMLPlus $content, $hId, $level=1) {
    if(!array_key_exists($hId, $this->tree)) return;
    if($level < $this->level || count($this->tree[$hId]) < $this->limit) {
      foreach($this->tree[$hId] as $childId) {
        $this->balanceContent($content, $childId, $level+1);
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
  private function balanceHeading(DOMElementPlus $section, $h3ids) {
    $setId = null;
    foreach(explode(" ", $section->getAttribute("class")) as $c) {
      if(strpos($c, strtolower("$this->className-")) !== 0) continue;
      $setId = substr($c, strlen("$this->className-"));
    }
    if($setId == "none") {
      $section->parentNode->removeChild($section);
      return;
    }
    $set = $this->sets[$this->defaultSet];
    if(!is_null($setId)) {
      if(isset($this->sets[$setId])) $set = $this->sets[$setId];
      else Logger::user_warning(sprintf(_("Item id %s not found, using default"), $setId));
    }
    $wrapper = $section->ownerDocument->createElement($set->getAttribute("wrapper"));
    $wrapper->setAttribute("class", strtolower($this->className)."-".$set->getAttribute("id"));
    foreach($h3ids as $h3id) {
      $vars = $this->getVariables($h3id);
      $root = $this->createDOMElement($vars, $set);
      foreach($root->childElementsArray as $e) {
        $wrapper->appendChild($section->ownerDocument->importNode($e, true));
      }
    }
    $section->parentNode->replaceChild($wrapper, $section);
  }

  /**
   * @param array $vars
   * @param DOMElementPlus $set
   * @return DOMElementPlus
   */
  private function createDOMElement(Array $vars, DOMElementPlus $set) {
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($set, true));
    $doc->processVariables($vars);
    return $doc->documentElement;
  }

  /**
   * @param string $id
   * @return array
   */
  private function getVariables($id) {
    $vars = array();
    $vars['heading'] = HTMLPlusBuilder::getIdToHeading($id);
    $vars['link'] = $id;
    $values = HTMLPlusBuilder::getHeadingValues($id);
    $vars['headingplus'] = $values[0];
    $vars['short'] = HTMLPlusBuilder::getIdToShort($id);
    $vars['desc'] = HTMLPlusBuilder::getIdToDesc($id);
    $vars['kw'] = HTMLPlusBuilder::getIdToKw($id);
    return $vars;
  }

}

?>