<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Plugin;
use Exception;
use SplObserver;
use SplSubject;

class LinkList extends Plugin implements SplObserver, ModifyContentStrategyInterface {

  private $cssClass;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $this->cssClass = strtolower($this->className);
    $s->setPriority($this, 200);
  }

  public function update(SplSubject $subject) {}

  public function modifyContent(HTMLPlus $content) {
    $sections = $content->documentElement->getElementsByTagName("section");
    if($content->documentElement->hasClass($this->cssClass)) {
      $this->createLinkList($content->documentElement);
      return $content;
    }
    foreach($sections as $s) {
      if(!$s->hasClass($this->cssClass)) continue;
      $this->createLinkList($s);
      break;
    }
    return $content;
  }

  private function createLinkList(DOMElementPlus $wrapper) {
    $count = 1;
    $links = array();
    $linksArray = array();
    $list = $wrapper->ownerDocument->createElement("ol");
    foreach($wrapper->getElementsByTagName("a") as $l) { $links[] = $l; }
    foreach($links as $l) {
      if(!$l->hasAttribute("href")) continue;
      if(!isset($linksArray[$l->getAttribute("href")])
        && !$this->addLi($list, $l, $count)) continue;
      $linksArray[$l->getAttribute("href")] = $l;
      $a = $l->ownerDocument->createElement("a");
      $a->nodeValue = $count;
      $a->setAttribute("class", "{$this->cssClass}-href print");
      $a->setAttribute("href", "#{$this->cssClass}-$count");
      if(!is_null($l->nextSibling)) $l->parentNode->insertBefore($a, $l->nextSibling);
      else $l->parentNode->appendChild($a);
      $count++;
    }
    if($count == 1) return;
    $var = $wrapper->ownerDocument->createElement("var");
    $var->appendChild($list);
    Cms::setVariable($this->cssClass, $var);
    Cms::getOutputStrategy()->addCssFile($this->pluginDir."/".$this->className.".css");
  }

  private function addLi(DOMElementPlus $list, DOMElementPlus $link, $i) {
    $href = $link->getAttribute("href");
    if(strpos($href, "#") === 0) return false; // local fragment
    if(preg_match("/^\w+:/", $href)) return false; // external
    if(preg_match("/".FILEPATH_PATTERN."$/", $href)) return false; // file
    $li = $list->ownerDocument->createElement("li");
    $list->appendChild($li);
    $a = $li->appendChild($li->ownerDocument->createElement("a"));
    if(is_null(HTMLPlusBuilder::getLinkToId($href))) {
      if(is_null(Cms::getLoggedUser())) return false; // nonexist local link
      $a->setAttribute("class", "invalid-local-link");
    }
    $a->setAttribute("id", "{$this->cssClass}-$i");
    $a->setAttribute("href", $link->getAttribute("href"));
    $text = HTMLPlusBuilder::getIdToTitle($href);
    if(!strlen($text)) $text = $link->getAttribute("title");
    if(!strlen($text)) $text = getShortString($href, 25, 35, "/");
    $a->nodeValue = trim($text, "/");
    return true;
  }

}

?>