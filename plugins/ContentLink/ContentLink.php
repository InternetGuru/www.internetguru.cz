<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\ContentStrategyInterface;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\HTMLPlusBuilder;
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
  private $hPath;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 4);
  }

  public function update(SplSubject $subject) {
    if($this->detachIfNotAttached("HtmlOutput")) return;
  }

  public function getContent(HTMLPlus $c) {
    $link = basename(getCurLink());
    if($this->isRoot($link)) return $c;
    $h1 = $c->getElementById($link, "id", "h");
    if(is_null($h1)) new ErrorPage(sprintf(_("Page '%s' not found"), getCurLink()), 404);
    $this->handleAttribute($h1, "ctime");
    $this->handleAttribute($h1, "mtime");
    $this->handleAttribute($h1, "author");
    $this->handleAttribute($h1, "authorid");
    $this->handleAttribute($h1, "resp");
    $this->handleAttribute($h1, "respid");
    $this->handleAttribute($h1->parentNode, "xml:lang", true);
    $content = new HTMLPlus();
    $content->formatOutput = true;
    $body = $content->appendChild($content->createElement("body"));
    $body->setAttribute("ns", $c->documentElement->getAttribute("ns"));
    foreach($h1->parentNode->attributes as $attName => $attNode) {
      $body->setAttributeNode($content->importNode($attNode));
    }
    $this->appendUntilSame($h1, $body);
    return $content;
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