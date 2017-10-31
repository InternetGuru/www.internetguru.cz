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
    $count = 0;
    $links = [];
    $linksArray = [];
    foreach ($wrapper->getElementsByTagName("a") as $link) {
      $links[] = $link;
    }
    /** @var DOMElementPlus $link */
    foreach ($links as $link) {
      // remove fragment
      $href = strtok($link->getAttribute("href"), "#");
      if (!$this->isLocalLink($href)) {
        continue;
      }
      if (!isset($linksArray[$href])) {
        $linksArray[$href] = ++$count;
      }
      $a = $link->ownerDocument->createElement("a");
      $a->nodeValue = $linksArray[$href];
      $a->setAttribute("class", "{$this->cssClass}-href print");
      $a->setAttribute("href", "#{$this->cssClass}-".$a->nodeValue);
      $link->parentNode->insertBefore($a, $link->nextSibling);
    }
    if ($count == 0) {
      return;
    }
    $var = $wrapper->ownerDocument->createElement("var");
    $list = $wrapper->ownerDocument->createElement("ol");
    foreach ($linksArray as $href => $linkId) {
      $this->addLinkItem($list, $href, $linkId);
    }
    $var->appendChild($list);
    Cms::setVariable($this->cssClass, $var);
    Cms::getOutputStrategy()->addCssFile($this->pluginDir."/".$this->className.".css");
  }

  /**
   * @param String $href
   * @return bool
   */
  private function isLocalLink ($href) {
    // empty
    if (!strlen(trim($href))) {
      return false;
    }
    // external
    if (preg_match('/^\w+:/', $href)) {
      return false;
    }
    // local file
    if (preg_match("/".FILEPATH_PATTERN."$/", $href)) {
      return false;
    }
    // non-existing local link
    if (is_null(HTMLPlusBuilder::getLinkToId($href))) {
      // proceed iff logged user
      return is_string(Cms::getLoggedUser());
    }
    return true;
  }

  /**
   * @param DOMElementPlus $list
   * @param String $href
   * @param int $linkId
   */
  private function addLinkItem (DOMElementPlus $list, $href, $linkId) {
    $li = $list->ownerDocument->createElement("li");
    $list->appendChild($li);
    $a = $li->ownerDocument->createElement("a");
    $li->appendChild($a);
    $a->setAttribute("id", "{$this->cssClass}-$linkId");
    $a->setAttribute("href", $href);
    $a->nodeValue = is_null(HTMLPlusBuilder::getIdToHeading($href))
      ? $href
      : HTMLPlusBuilder::getIdToHeading($href);
  }

}

?>
