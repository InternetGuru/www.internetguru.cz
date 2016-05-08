<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\ErrorPage;
use IGCMS\Core\Plugin;
use SplObserver;
use SplSubject;

class Auth extends Plugin implements SplObserver {
  private $loggedUser = null;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    if(Cms::isSuperUser()) return;
    $cfg = $this->getXML();
    $url = ROOT_URL.getCurLink(true);
    $access = null;
    foreach($cfg->getElementsByTagName('url') as $e) {
      if(strpos($url, $e->nodeValue) === false) continue;
      if($e->getAttribute("access") == "allow") $access = true;
      elseif($e->getAttribute("access") == "denyall") $access = false;
      else $access = !is_null(Cms::getLoggedUser()); // default = deny for anonymous users
    }
    if($access !== false) return;
    if(is_null(Cms::getLoggedUser())) loginRedir();
    new ErrorPage(_("Insufficient rights to view this content"), 403);
  }

  public function update(SplSubject $subject) {}

}

?>