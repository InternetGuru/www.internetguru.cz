<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\HTMLPlus;
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
  public function update (SplSubject $subject) {}

  /**
   * @param HTMLPlus $content
   * @throws Exception
   */
  public function modifyContent (HTMLPlus $content) {
    $cfg = self::getXML();
    $ns = $content->documentElement->getAttribute('ns');
    if ($ns === DOMAIN) {
      return;
    }
    $nsDomain = parse_url($ns);
    $matches = $cfg->matchElement("ns", "domain", $nsDomain['host']);
    if (is_null($matches)) {
      Logger::warning(_("Using outer..."));
      return;
    }
    Cms::getOutputStrategy()->addLinkElement($ns, 'canonical');
  }
}
