<?php

namespace IGCMS\Plugins;

use DOMXPath;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class Photoswipe
 * @package IGCMS\Plugins
 */
class Photoswipe extends Plugin implements SplObserver, ModifyContentStrategyInterface {

  /**
   * Photoswipe constructor.
   * @param SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 30);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() == STATUS_INIT) {
      $this->detachIfNotAttached("HtmlOutput");
      return;
    }
  }

  /**
   * @param HTMLPlus $content
   */
  public function modifyContent (HTMLPlus $content) {
    $xpath = new DOMXPath($content);
    $r = @$xpath->query("//*[contains(@class, '".strtolower($this->className)."')]");
    if (!$r->length) {
      return;
    }
    $config = $this->getXML();
    $vendorDir = VENDOR_DIR . "/internetguru/photoswipe/dist";
    $os = Cms::getOutputStrategy();
    $os->addCssFile("$vendorDir/photoswipe.css");
    $os->addCssFile("$vendorDir/default-skin/default-skin.css");
    $os->addJsFile("$vendorDir/photoswipe.min.js", 1, "body");
    $os->addJsFile("$vendorDir/photoswipe-ui-default.min.js", 1, "body");
    $os->addJsFile($this->pluginDir."/Photoswipe.js", 1, "body");
    $socialEl = $config->getElementById("social", "var");
    $social = $socialEl && $socialEl->nodeValue == "enabled" ? "true" : "false";
    $os->addJs("if(typeof IGCMS === \"undefined\") throw \"IGCMS is not defined\";
IGCMS.Pswp.init({
  galleryClassSelector: \".".strtolower($this->className)."\",
  shareEl: ".$social."
});", 1, "body");
  }
}

?>
