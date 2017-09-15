<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class LinkList
 * @package IGCMS\Plugins
 */
class LinkList extends Plugin implements SplObserver, ModifyContentStrategyInterface {

  /**
   * @var string
   */
  private $cssClass;

  /**
   * LinkList constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $this->cssClass = strtolower($this->className);
    $s->setPriority($this, 20);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
  }

  /**
   * @param HTMLPlus $content
   */
  public function modifyContent (HTMLPlus $content) {
    if (!$content->documentElement->hasClass($this->cssClass)) {
      return;
    }
    $this->createLinkList($content->documentElement);
  }

  /**
   * @param DOMElementPlus $wrapper
   */
  private function createLinkList (DOMElementPlus $wrapper) {
    $count = 1;
    $links = [];
    $linksArray = [];
    $list = $wrapper->ownerDocument->createElement("ol");
    foreach ($wrapper->getElementsByTagName("a") as $l) {
      $links[] = $l;
    }
    /** @var DOMElementPlus $l */
    foreach ($links as $l) {
      if (!$l->hasAttribute("href")) {
        continue;
      }
      if (!isset($linksArray[$l->getAttribute("href")])
        && !$this->addLi($list, $l, $count)
      ) {
        continue;
      }
      $linksArray[$l->getAttribute("href")] = $l;
      $a = $l->ownerDocument->createElement("a");
      $a->nodeValue = $count;
      $a->setAttribute("class", "{$this->cssClass}-href print");
      $a->setAttribute("href", "#{$this->cssClass}-$count");
      $l->parentNode->insertBefore($a, $l->nextSibling);
      $count++;
    }
    if ($count == 1) {
      return;
    }
    $var = $wrapper->ownerDocument->createElement("var");
    $var->appendChild($list);
    Cms::setVariable($this->cssClass, $var);
    Cms::getOutputStrategy()->addCssFile($this->pluginDir."/".$this->className.".css");
  }

  /**
   * @param DOMElementPlus $list
   * @param DOMElementPlus $link
   * @param int $i
   * @return bool
   */
  private function addLi (DOMElementPlus $list, DOMElementPlus $link, $i) {
    $href = $link->getAttribute("href");
    if (strpos($href, "#") === 0) {
      return false;
    } // local fragment
    if (preg_match('/^\w+:/', $href)) {
      return false;
    } // external
    if (preg_match("/".FILEPATH_PATTERN."$/", $href)) {
      return false;
    } // file
    $li = $list->ownerDocument->createElement("li");
    $list->appendChild($li);
    $a = $li->ownerDocument->createElement("a");
    $li->appendChild($a);
    if (is_null(HTMLPlusBuilder::getLinkToId($href))) {
      if (is_null(Cms::getLoggedUser())) {
        return false;
      } // nonexist local link
      $a->setAttribute("class", "invalid-local-link");
    }
    $a->setAttribute("id", "{$this->cssClass}-$i");
    $a->setAttribute("href", $link->getAttribute("href"));
    $text = HTMLPlusBuilder::getIdToTitle($href);
    if (!strlen($text)) {
      $text = $link->getAttribute("title");
    }
    if (!strlen($text)) {
      $text = getShortString($href, 25, 35, "/");
    }
    $a->nodeValue = trim($text, "/");
    return true;
  }

}

?>
