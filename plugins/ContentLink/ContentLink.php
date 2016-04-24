<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\ContentStrategyInterface;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\ErrorPage;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use Exception;
use DOMElement;
use SplObserver;
use SplSubject;

class ContentLink extends Plugin implements SplObserver, ContentStrategyInterface {
  private $lang = null;
  private $isRoot;
  private $hPath;
  private $vars = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 4);
  }

  public function update(SplSubject $subject) {
    $this->isRoot = getCurLink() == "";
    if($this->isRoot) return;
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;
  }

  public function getContent(HTMLPlus $c) {
    $cf = Cms::getContentFull();
    $link = getCurLink();
    if($this->isRoot) $h1 = $cf->documentElement->firstElement;
    else {
      $h1 = $cf->getElementById($link, "id", "h");
      if(is_null($h1)) new ErrorPage(sprintf(_("Page '%s' not found"), $link), 404);
    }
    $cfg = $this->getDOMPlus();
    foreach($cfg->documentElement->childElementsArray as $e) {
      if($e->nodeName != "var" || !$e->hasAttribute("id")) continue;
      $this->vars[$e->getAttribute("id")] = $e;
    }

    $this->setPath($h1);
    $this->generateBc($c);
    if($this->isRoot) return $c;

    $desc = $h1->nextElement;
    if(!strlen($desc->nodeValue)) $desc->nodeValue = $desc->getAncestorValue(null, "desc");
    if(!$desc->hasAttribute("kw")) $desc->setAttribute("kw", $desc->getAncestorValue("kw", "desc"));

    $this->handleAttribute($h1, "ctime");
    $this->handleAttribute($h1, "mtime");
    $this->handleAttribute($h1, "author");
    $this->handleAttribute($h1, "authorid");
    $this->handleAttribute($h1, "resp");
    $this->handleAttribute($h1, "respid");
    $this->handleAttribute($h1->parentNode, "xml:lang", "lang", true);

    $content = new HTMLPlus();
    $content->formatOutput = true;
    $body = $content->appendChild($content->createElement("body"));
    $body->setAttribute("ns", $cf->documentElement->getAttribute("ns"));
    foreach($h1->parentNode->attributes as $attName => $attNode) {
      $body->setAttributeNode($content->importNode($attNode));
    }
    $this->appendUntilSame($h1, $body);

    return $content;
  }

  private function handleAttribute(DOMElement $e, $aName, $vName=null, $anyElement=false) {
    if(is_null($vName)) $vName = $aName;
    if($e->hasAttribute($aName)) {
      Cms::setVariable($vName, $e->getAttribute($aName));
      return;
    }
    $eName = $anyElement ? null : $e->nodeName;
    $value = $e->getAncestorValue($aName, $eName);
    if(is_null($value)) return;
    $e->setAttribute($aName, $value);
    if($value == Cms::getVariable("cms-$vName")) return;
    Cms::setVariable($vName, $value);
  }

  private function setPath(DOMElement $h) {
    while(!is_null($h)) {
      $this->hPath[$h->getAttribute("id")] = $h;
      #if($h->hasAttribute("link")) $this->hPath[$h->getAttribute("link")] = $h;
      $h = $h->parentNode->getPreviousElement("h");
    }
  }

  private function generateBc(HTMLPlus $src) {
    $bc = new DOMDocumentPlus();
    $root = $bc->appendChild($bc->createElement("root"));
    $ol = $root->appendChild($bc->createElement("ol"));
    $ol->setAttribute("class", "contentlink-bc");
    $cmsLang = Cms::getVariable("cms-lang");
    $ol->setAttribute("lang", $cmsLang);
    $subtitles = array();
    $lastA = null;
    $a = null;
    $li = null;
    $h = null;
    foreach($this->hPath as $link => $h) {
      $li = $ol->insertBefore($bc->createElement("li"), $ol->firstElement);
      $lang = $h->getParentValue("xml:lang");
      if($lang != $cmsLang) $li->setAttribute("lang", $lang);
      $a = $li->appendChild($bc->createElement("a", $h->nodeValue));
      if(is_null($lastA)) $lastA = $a;
      if($h->hasAttribute("short")) $a->nodeValue = $h->getAttribute("short");
      $a->setAttribute("href", $link);
      if(empty($subtitles)) {
        if($h->hasAttribute("title")) $a->nodeValue = $h->getAttribute("title");
        $subtitles[] = $h->nodeValue;
      } else $subtitles[] = $h->hasAttribute("short") ? $h->getAttribute("short") : $h->nodeValue;
    }
    if(array_key_exists("logo", $this->vars)) {
      $o = $bc->createElement("object");
      $o->setAttribute("data", $this->vars["logo"]->nodeValue);
      try {
        $type = $this->vars["logo"]->getRequiredAttribute("type");
        $o->setAttribute("type", $type);
      } catch(Exception $e) {
        Logger::user_warning($e->getMessage());
      }
      $o->nodeValue = $h->nodeValue;
      $a->nodeValue = null;
      $a->addClass("logo");
      $a->appendChild($o);
      if($this->isRoot && $h->hasAttribute("short"))
        $a->parentNode->appendChild($bc->createElement("span", $h->getAttribute("short")));
    }
    if(getCurLink(true) != "" && $lastA->getAttribute("href") != getCurLink(true))
      $lastA->setAttribute("title", $this->vars["reset"]->nodeValue);
    Cms::setVariable("bc", $bc->documentElement);
    Cms::setVariable("title", implode(" - ", $subtitles));
  }

  private function appendUntilSame(DOMElement $e, DOMElement $into) {
    $doc = $into->ownerDocument;
    $into->appendChild($doc->importNode($e, true));
    $untilName = $e->nodeName;
    while(($e = $e->nextElement) !== null) {
      if($e->nodeName == $untilName) break;
      $into->appendChild($doc->importNode($e, true));
    }
  }

}

?>