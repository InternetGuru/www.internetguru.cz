<?php

namespace IGCMS\Plugins;

use IGCMS\Core\ContentStrategyInterface;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use Exception;
use DOMElement;
use DOMXPath;
use SplObserver;
use SplSubject;

class ContentBalancer extends Plugin implements SplObserver, ContentStrategyInterface {
  private $tree = array();


  private $content = null;
  private $sets = array();
  private $defaultSet = null;

 public function __construct(SplSubject $s) {
   parent::__construct($s);
   $s->setPriority($this, 3);
 }

 public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    $this->setTree();
    $this->balanceLinks();
  }

  private function setTree() {
    foreach(HTMLPlusBuilder::getIdToParentId() as $id => $parentId) {
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
      $newLink = str_replace("#", "/", $link);
      if(strpos($newLink, "/") === 0) $newLink = substr($newLink, 1);
      foreach($this->tree[$id] as $childId) {
        $idToLink[$childId] = $newLink.$idToLink[$childId];
      }
      $idToLink[$id] = $newLink;
    }
    var_dump($this->tree);
    HTMLPlusBuilder::setIdToLink($idToLink);
  }

  public function getContent(HTMLPlus $content) {
    #$curId = HTMLPlusBuilder::getLinkToId(getCurLink());
    #$id = substr($curId, strpos($curId, "#")+1);
    return $content;
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
    $this->filter($content);
    return $content;
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


  private function filter(HTMLPlus $content) {
    $xpath = new DOMXPath($content);
    $nodes = array();
    foreach($xpath->query("/body/section/section") as $e) $nodes[] = $e;
    foreach($nodes as $section) {
      $className = strtolower((new \ReflectionClass($this))->getShortName());
      $setId = null;
      foreach(explode(" ", $section->getAttribute("class")) as $c) {
        if(strpos($c, "$className-") !== 0) continue;
        $setId = substr($c, strlen("$className-"));
      }
      if($setId == "none") {
        $section->parentNode->removeChild($section);
        continue;
      }
      $set = $this->sets[$this->defaultSet];
      if(!is_null($setId)) {
        if(isset($this->sets[$setId])) $set = $this->sets[$setId];
        else Logger::user_warning(sprintf(_("Item id %s not found, using default"), $setId));
      }
      $hs = array();
      foreach($section->childElementsArray as $e) if($e->nodeName == "h") $hs[] = $e;
      $force = $section->getPreviousElement("h")->hasAttribute("link");
      $wrapper = $content->createElement($set->getAttribute("wrapper"));
      #if($set->getAttribute("id") != $this->defaultSet)
      $className .= "-".$set->getAttribute("id");
      $wrapper->setAttribute("class", $className);
      foreach($hs as $h) {
        if(!$force && !$h->hasAttribute("link")) continue 2;
        $vars = $this->getVariables($h);
        $root = $this->createDOMElement($vars, $set);
        foreach($root->childElementsArray as $e) {
          $wrapper->appendChild($content->importNode($e, true));
        }
      }
      $section->parentNode->replaceChild($wrapper, $section);
    }
    return $content;
  }

  private function createDOMElement(Array $vars, DOMElementPlus $set) {
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($set, true));
    $doc->processVariables($vars);
    return $doc->documentElement;
  }

  private function getVariables(DOMElementPlus $h) {
    $vars = array();
    $desc = $h->nextElement;
    $vars['heading'] = $h->nodeValue;
    $vars['link'] = $h->hasAttribute("link") ? $h->getAttribute("link") : $h->getAncestorValue("link", "h")."#".$h->getAttribute("id");
    $vars['headingplus'] = $h->hasAttribute("short") ? $h->getAttribute("short") : $h->nodeValue;
    $vars['short'] = $h->hasAttribute("short") ? $h->getAttribute("short") : null;
    $vars['desc'] = strlen($desc->nodeValue) ? $desc->nodeValue : null;
    $vars['kw'] = $desc->hasAttribute("kw") ? $desc->getAttribute("kw") : null;
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