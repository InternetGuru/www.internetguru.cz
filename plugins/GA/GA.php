<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class GA
 * @package IGCMS\Plugins
 */
class GA extends Plugin implements SplObserver {

  /**
   * @param Plugins|SplSubject $subject
   * @throws Exception
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_PROCESS) {
      return;
    }
    if ($this->detachIfNotAttached("HtmlOutput")) {
      return;
    }
    $this->init();
  }

  /**
   * @throws Exception
   */
  private function init () {
    $cfg = $this->getXML();
    $ga_id = $cfg->matchElement("ga_id", "domain", HOST);
    if (is_null($ga_id)) {
      throw new Exception("Unable to match ga_id element to domain");
    }
    if (!$ga_id->hasAttribute("domain")) {
      Logger::user_warning(_("Using default Google Analytics ID value (without domain match)"));
    }
    if (!preg_match("/^UA-\d+-\d+$/", $ga_id->nodeValue)) {
      Logger::user_error(_("Google Analytics ID does not match typical pattern"));
    }
    // disable if logged user
    if (is_null(Cms::getLoggedUser())) {
      Cms::getOutputStrategy()->addJs("// ".sprintf(_("GA is disabled for logged users (ga_id = %s)"), $ga_id->nodeValue), 1, "body");
      return;
    }
    Cms::getOutputStrategy()->addJs(sprintf("var ga_id = '%s';", $ga_id->nodeValue), 1, "body");
    foreach ($cfg->getElementsByTagName("jsfile") as $jsfile) {
      Cms::getOutputStrategy()->addJsFile($this->pluginDir."/".$jsfile->nodeValue, 1, "body");
    }
  }

}
