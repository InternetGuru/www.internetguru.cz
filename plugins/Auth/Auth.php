<?php

namespace IGCMS\Plugins;

use Exception;
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
   * @throws Exception
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    if (Cms::isSuperUser()) {
      return;
    }
    $cfg = self::getXML();
    $url = ROOT_URL.get_link(true);
    $access = null;
    /** @var DOMElementPlus $urlElm */
    foreach ($cfg->getElementsByTagName('url') as $urlElm) {
      if (strpos($url, $urlElm->nodeValue) === false) {
        continue;
      }
      if ($urlElm->getAttribute("access") == "allow") {
        $access = true;
      } elseif ($urlElm->getAttribute("access") == "denyall") {
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

  /**
   * @param SplSubject $subject
   */
  public function update (SplSubject $subject) {}

}
