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
    $galleryClasses = $config->getElementsByTagName("galleryClass");
    $selector = [];
    /** @var DOMElementPlus $el */
    foreach ($galleryClasses as $el) {
      $selector[] = ".".$el->nodeValue;
    }
    $selector = implode(",", $selector);
    $xpath = new DOMXPath($content);
    $r = @$xpath->query($selector);
    if ($r === false) {
      Logger::user_warning(sprintf(_("Invalid galleryClass '%s'"), $selector));
      return;
    }
    if ($r->length === 0) {
      return;
    }
    $libDir = $this->pluginDir . "/lib";
    $os = Cms::getOutputStrategy();
    $os->addCssFile("$libDir/lib/photoswipe/photoswipe.css");
    $os->addCssFile("$libDir/lib/photoswipe/default-skin.css");
    $os->addJsFile("$libDir/lib/photoswipe/photoswipe.min.js", 1, "body");
    $os->addJsFile("$libDir/lib/photoswipe/photoswipe-ui-default.min.js", 1, "body");
    $os->addJsFile($this->pluginDir."/Photoswipe.js", 1, "body");
  }

}

?>
