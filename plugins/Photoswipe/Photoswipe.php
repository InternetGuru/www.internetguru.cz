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
    $xpath = new DOMXPath($content);
    $found = false;
    $selector = [];
    /** @var DOMElementPlus $el */
    foreach ($galleryClasses as $el) {
      $r = @$xpath->query("*[contains(@class, '".$el->nodeValue."')]");
      if ($r === false) {
        Logger::user_warning(sprintf(_("Invalid galleryClass '%s'"), $el->nodeValue));
        return;
      }
      $selector[] = ".".$el->nodeValue;
      $found = true; // do not break => check all galleryClass elements
    }
    if (!$found) {
      return;
    }
    $libDir = $this->pluginDir . "/lib";
    $os = Cms::getOutputStrategy();
    $os->addCssFile("$libDir/photoswipe.css");
    $os->addCssFile("$libDir/default-skin.css");
    $os->addJsFile("$libDir/photoswipe.min.js", 1, "body");
    $os->addJsFile("$libDir/photoswipe-ui-default.min.js", 1, "body");
    $os->addJsFile($this->pluginDir."/Photoswipe.js", 1, "body");
    $selector = implode(",", $selector);
    $os->addJs("if(typeof IGCMS === \"undefined\") throw \"IGCMS is not defined\";
IGCMS.Pswp.init({
  galleryClassSelector: \"$selector\"
});", 1, "body");
  }
}

?>
