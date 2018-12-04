<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
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
    try {
      $this->createLinkList($content->documentElement);
    } catch (Exception $exc) {
      Logger::warning(sprintf(_("Unable to create LinkList: %s"), $exc->getMessage()));
    }
  }

  /**
   * @param DOMElementPlus $parent
   * @throws Exception
   */
  private function createLinkList (DOMElementPlus $parent) {
    $count = 0;
    $links = [];
    $linksArray = [];
    foreach ($parent->getElementsByTagName("a") as $link) {
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
    $cfg = $this::getXML();
    $templateId = $cfg->getElementById("template", "var")->nodeValue;
    $template = $cfg->getElementById($templateId, "item");
    if (is_null($template)) {
      throw new Exception(sprintf(_("Unable to find template id %s"), $templateId));
    }
    $var = $parent->ownerDocument->createElement("var");
    $wrapper = $template->getAttribute("wrapper");
    $root = $var;
    if (strlen($wrapper)) {
      $root = $parent->ownerDocument->createElement($wrapper);
      $var->appendChild($root);
    }
    $vars = Cms::getAllVariables();
    foreach ($linksArray as $href => $linkId) {
      $localVars = HTMLPlusBuilder::getIdToAll($href);
      foreach ($localVars as $varId => $varValue) {
        if (!isset($vars[$varId])) {
          continue;
        }
        $vars[$varId]["value"] = $varValue;
      }
      $vars["linkid"] = [
        "value" => "{$this->cssClass}-$linkId",
        "cacheable" => false,
      ];
      $vars["headingplus"] = [
        "value" => is_null($vars["heading"]["value"]) ? $href : $vars["heading"]["value"],
        "cacheable" => false,
      ];
      $item = $this->getItem(clone $template, $vars);
      foreach ($item->childElementsArray as $child) {
        $root->appendChild($root->ownerDocument->importNode($child, true));
      }
    }
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
   * @param DOMElementPlus $template
   * @param array $vars
   * @return DOMElementPlus
   */
  private function getItem (DOMElementPlus $template, Array $vars) {
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($template, true));
    $doc->processVariables($vars);
    return $doc->documentElement;
  }

}
