<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\ErrorPage;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class Auth
 * @package IGCMS\Plugins
 */
class Auth extends Plugin implements SplObserver {
  /**
   * Auth constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    if (Cms::isSuperUser()) {
      return;
    }
    $cfg = $this->getXML();
    $url = ROOT_URL.get_link(true);
    $access = null;
    /** @var DOMElementPlus $e */
    foreach ($cfg->getElementsByTagName('url') as $e) {
      if (strpos($url, $e->nodeValue) === false) {
        continue;
      }
      if ($e->getAttribute("access") == "allow") {
        $access = true;
      } elseif ($e->getAttribute("access") == "denyall") {
        $access = false;
      } else {
        $access = !is_null(Cms::getLoggedUser());
      } // default = deny for anonymous users
    }
    if ($access !== false) {
      return;
    }
    if (is_null(Cms::getLoggedUser())) {
      login_redir();
    }
    new ErrorPage(_("Insufficient rights to view this content"), 403);
  }

  public function update (SplSubject $subject) {
  }

}

?>
