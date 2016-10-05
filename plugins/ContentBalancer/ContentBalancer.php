<?php

namespace IGCMS\Plugins;

use DOMElement;
use Exception;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\ErrorPage;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use SplObserver;
use SplSubject;

class ContentBalancer extends Plugin implements SplObserver, ModifyContentStrategyInterface {
  private $tree = array();
  private $content = null;
  private $sets = array();
  private $defaultSet = null;

 public function __construct(SplSubject $s) {
   parent::__construct($s);
   $s->setPriority($this, 2);
 }

 public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    $this->setTree();
    $this->balanceLinks();
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

  private function balanceLinks() {
    $idToLink = HTMLPlusBuilder::getIdToLink();
    foreach($idToLink as $id => $void) {
      $link = $idToLink[$id];
      if(empty($this->tree[$id]) || $link == "") continue;
      $hashPos = strpos($link, "#");
      if(count($this->tree[$id]) == 1) {
        if($hashPos !== false) $link = substr($link, 0, $hashPos);
      } else {
        $link = $hashPos === 0 ? substr($link, 1) : str_replace("#", "/", $link);
      }
      foreach($this->tree[$id] as $childId) {
        $idToLink[$childId] = $link."#".basename($childId);
      }
      if(count($this->tree[$id]) != 1) $idToLink[$id] = $link;
    }
    HTMLPlusBuilder::setIdToLink($idToLink);
  }

  public function modifyContent(HTMLPlus $content) {
    // set vars
    $this->createVars();
    // check sets
    if(empty($this->sets)) {
      Logger::critical(_("No sets found"));
      return $content;
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
    $this->balance($content, $h1id);
  }

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

  private function getUntilSame(DOMElement $e) {
    $elements = array($e);
    $untilName = $e->nodeName;
    while(($e = $e->nextElement) !== null) {
      if($e->nodeName == $untilName) break;
      $elements[] = $e;
    }
    return $elements;
  }

  private function isRoot($link) {
    if($link == "") return true;
    return array_key_exists($link, HTMLPlusBuilder::getIdToLink());
  }

  private function handleAttribute(DOMElement $e, $aName, $anyElement=false) {
    if($e->hasAttribute($aName)) return;
    $eName = $anyElement ? null : $e->nodeName;
    $value = $e->getAncestorValue($aName, $eName);
    if(is_null($value)) return;
    $e->setAttribute($aName, $value);
  }

  private function createVars() {
    $cfg = $this->getXML();
    foreach($cfg->documentElement->childElementsArray as $e) {
      try {
        $id = $e->getAttribute("id");
        switch($e->nodeName) {
          case "var":
          if($id == "default") $this->defaultSet = $e->nodeValue;
          break;
          case "item":
          if($id == "") throw new Exception(_("Element item missing id"));
          $wrapper = $e->getRequiredAttribute("wrapper"); // only check
          $this->sets[$id] = $e;
          break;
        }
      } catch(Exception $ex) {
        Logger::user_warning(sprintf(_("Skipped element %s: %s"), $e->nodeName, $ex->getMessage()));
      }
    }
  }

  private function balance(HTMLPlus $content, $h1id) {
    if(!array_key_exists($h1id, $this->tree)) return;
    foreach($this->tree[$h1id] as $h2id) {
      $h3s = $this->tree[$h2id];
      if(count($h3s) < 2) {
        $this->balance($content, $h2id);
        continue;
      }
      $section = $content->getElementById(basename($h3s[0]), "h")->parentNode;
      $this->balanceHeading($section, $h3s);
    }
  }

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

  private function createDOMElement(Array $vars, DOMElementPlus $set) {
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($set, true));
    $doc->processVariables($vars);
    return $doc->documentElement;
  }

  private function getVariables($id) {
    $vars = array();
    $desc = HTMLPlusBuilder::getIdToDesc($id);
    $vars['heading'] = HTMLPlusBuilder::getIdToHeading($id);
    $vars['link'] = $id;
    $values = HTMLPlusBuilder::getHeadingValues($id);
    $vars['headingplus'] = $values[0];
    $vars['short'] = HTMLPlusBuilder::getIdToShort($id);
    $vars['desc'] = HTMLPlusBuilder::getIdToDesc($id);
    $vars['kw'] = HTMLPlusBuilder::getIdToKw($id);
    return $vars;
  }

  private function getParentHeading(DOMElement $e) {
    $h = $e;
    while( ($h = $h->previousElement) != null) {
      if($h->nodeName == "h") return $h;
    }
    throw new Exception(sprintf(_("Unable to find parent heading for %s"), $h->nodeValue));
  }

}

?>