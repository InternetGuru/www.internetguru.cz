<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\ContentStrategyInterface;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Plugin;
use Exception;
use SplObserver;
use SplSubject;

class LinkList extends Plugin implements SplObserver, ContentStrategyInterface {

  private $cssClass = "linklist";

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 200);
  }

  public function update(SplSubject $subject) {}

  public function getContent(HTMLPlus $content) {
    $sections = $content->documentElement->getElementsByTagName("section");
    foreach($sections as $s) {
      if(!$s->hasClass($this->cssClass)) continue;
      $this->createLinkList($s);
    }
    if($content->documentElement->hasClass($this->cssClass)) {
      $this->createLinkList($content->documentElement);
    }
    Cms::getOutputStrategy()->addCssFile($this->pluginDir."/".(new \ReflectionClass($this))->getShortName().".css");
    #echo $content->saveXML($content);
    #die();
    return $content;
  }

  private function createLinkList(DOMElementPlus $wrapper) {
    $cfg = $this->getXML();
    foreach($cfg->documentElement->childElementsArray as $e) {
      if($e->nodeName != "var" || !$e->hasAttribute("id")) continue;
      $vars[$e->getAttribute("id")] = $e;
    }
    $i = 0;
    $count = 0;
    $links = array();
    $linksArray = array();
    $list = $wrapper->ownerDocument->createElement("ol");
    foreach($wrapper->getElementsByTagName("a") as $l) { $links[] = $l; }
    foreach($links as $l) {
      if(!$l->hasAttribute("href")) continue;
      $i++;
      if(!isset($linksArray[$l->getAttribute("href")])) {
        $count++;
        if(!$this->addLi($list, $l, $count)) continue;
      }
      $linksArray[$l->getAttribute("href")] = $l;
      $a = $l->ownerDocument->createElement("a");
      $a->nodeValue = "[$count]";
      $a->setAttribute("class", "{$this->cssClass}-href print");
      $a->setAttribute("href", "#{$this->cssClass}-$count");
      if(!is_null($l->nextSibling)) $l->parentNode->insertBefore($a, $l->nextSibling);
      else $l->parentNode->appendChild($a);
    }
    if($i == 0) return;
    $section = $wrapper;
    if($wrapper->nodeName == "body") {
      $section = $wrapper->getElementsByTagName("section")->item(0);
    }
    $h = $section->appendChild($section->ownerDocument->createElement("h"));
    $section->appendChild($section->ownerDocument->createElement("desc"));
    $h->nodeValue = $vars["heading"]->nodeValue;
    $h->setAttribute("id", $this->cssClass);
    $section->appendChild($list);
  }

  private function addLi(DOMElementPlus $list, DOMElementPlus $link, $i) {
    $li = $list->ownerDocument->createElement("li");
    $text = $link->getAttribute("title");
    if(!$link->hasAttribute("title")) {
      $href = $link->getAttribute("href");
      $text = HTMLPlusBuilder::getIdToTitle($href);
      if(is_null($text)) {
        $text = $href;
        $c = 0;
        $text = preg_replace("/^\w+:\/\//", "", $text, -1, $c);
        if($c == 0) { // nonexist local link
          if(is_null(Cms::getLoggedUser())) return false;
        }
        $text = getShortString($text, 25, 35, "/");
      }
    }
    $list->appendChild($li);
    $a = $li->appendChild($li->ownerDocument->createElement("a"));
    $a->setAttribute("id", "{$this->cssClass}-$i");
    $a->setAttribute("href", $link->getAttribute("href"));
    $a->nodeValue = trim($text, "/");
    return true;
  }

}

?>