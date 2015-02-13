<?php

class Auth extends Plugin implements SplObserver {
  private $loggedUser = null;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $cfg = $this->getDOMPlus();
    $url = ROOT_URL.getCurLink(true);
    $access = null;
    foreach($cfg->getElementsByTagName('url') as $e) {
      if(strpos($url, $e->nodeValue) === false) continue;
      if($e->getAttribute("access") == "allow") $access = true;
      else $access = false;
    }
    if(is_null($access)) return;
    if($access && !file_exists(FORBIDDEN_FILE)) {
      Cms::setLoggedUser("anonymous");
      return;
    }
    // url is restricted
    if(in_array(Cms::getLoggedUser(true), array(USER_ID, "admin"))) return;
    loginRedir();
  }

  public function update(SplSubject $subject) {}

}

?>