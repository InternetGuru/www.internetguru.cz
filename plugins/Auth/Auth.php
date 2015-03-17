<?php

class Auth extends Plugin implements SplObserver {
  private $loggedUser = null;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    if(Cms::isSuperUser()) return;
    $cfg = $this->getDOMPlus();
    $url = ROOT_URL.getCurLink(true);
    $access = null;
    foreach($cfg->getElementsByTagName('url') as $e) {
      if(strpos($url, $e->nodeValue) === false) continue;
      if($e->getAttribute("access") == "allow") $access = true;
      elseif($e->getAttribute("access") == "denyall") $access = false;
      else $access = !is_null(Cms::getLoggedUser()); // default = deny for anonymous users
    }
    if($access === false) loginRedir();
  }

  public function update(SplSubject $subject) {}

}

?>