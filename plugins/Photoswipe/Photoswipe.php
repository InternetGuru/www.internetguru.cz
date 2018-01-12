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
    $config = $this->getXML();
    $vendorDir = VENDOR_DIR . "/internetguru/photoswipe/dist";
    $os = Cms::getOutputStrategy();
    $ifxpath = "//*[contains(@class, '".strtolower($this->className)."')]";
    $os->addCssFile("$vendorDir/photoswipe.css", false, 1, true, null, $ifxpath);
    $os->addCssFile("$vendorDir/default-skin/default-skin.css", false, 1, true, null, $ifxpath);
    $os->addJsFile("$vendorDir/photoswipe.min.js", 1, "body", false, null, $ifxpath);
    $os->addJsFile("$vendorDir/photoswipe-ui-default.min.js", 1, "body", false, null, $ifxpath);
    $os->addJsFile($this->pluginDir."/Photoswipe.js", 1, "body", false, null, $ifxpath);
    $socialEl = $config->getElementById("social", "var");
    $social = $socialEl && $socialEl->nodeValue == "enabled" ? "true" : "false";
    $os->addJs("if(typeof IGCMS === \"undefined\") throw \"IGCMS is not defined\";
if (IGCMS.Pswp) {
  IGCMS.Pswp.init({
    galleryClassSelector: \".".strtolower($this->className)."\",
    shareEl: ".$social."
  });
}", 1, "body");
  }
}

?>
