<?php

namespace IGCMS\Plugins;

use Exception;
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
   * @throws Exception
   */
  public function modifyContent (HTMLPlus $content) {
    if (!$content->documentElement->hasClass($this->cssClass)) {
      return;
    }
    $this->createLinkList($content->documentElement);
  }

  /**
   * @param DOMElementPlus $wrapper
   * @throws Exception
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
      $link->setAttribute("data-linklist-href", "#{$this->cssClass}-{$linksArray[$href]}");
      $link->setAttribute("data-linklist-value", $linksArray[$href]);
      $link->setAttribute("data-linklist-class", "{$this->cssClass}-href print");
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
    Cms::getOutputStrategy()->addJsFile($this->pluginDir."/".$this->className.".js", 10, "body");
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
    $liElm = $list->ownerDocument->createElement("li");
    $list->appendChild($liElm);
    $aElm = $liElm->ownerDocument->createElement("a");
    $liElm->appendChild($aElm);
    $aElm->setAttribute("id", "{$this->cssClass}-$linkId");
    $aElm->setAttribute("href", $href);
    $aElm->nodeValue = is_null(HTMLPlusBuilder::getIdToHeading($href))
      ? $href
      : HTMLPlusBuilder::getIdToHeading($href);
  }

}
