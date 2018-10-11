<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class GoogleAnalytics
 * @package IGCMS\Plugins
 */
class Canonical extends Plugin implements SplObserver, ModifyContentStrategyInterface {

  /**
   * @param Plugins|SplSubject $subject
   * @throws Exception
   */
  public function update (SplSubject $subject) {
    if ($this->detachIfNotAttached("HtmlOutput")) {
      return;
    }
  }

  /**
   * @param HTMLPlus $content
   * @throws Exception
   */
  public function modifyContent (HTMLPlus $content) {
    $cfg = self::getXML();
    $ns = $content->documentElement->getAttribute('ns');
    if ($ns === HTTP_URL) {
      return;
    }
    $nsDomain = parse_url($ns);
    $matches = null;
    if (isset($nsDomain['host'])) {
      $matches = $cfg->matchElement("ns", "domain", $nsDomain['host']);
    }
    if (is_null($matches)) {
      Logger::warning(sprintf(_("Unregistered namespace for %s"), $ns));
      return;
    }
    Cms::getOutputStrategy()->addLinkElement($ns, 'canonical');
  }
}