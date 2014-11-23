<?php


class Auth extends Plugin implements SplObserver {

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,1);
  }

  public function update(SplSubject $subject) {
    if(isAtLocalhost()) return;
    if($subject->getStatus() != STATUS_PREINIT) return;
    $this->handleRequest();
  }

  private function handleRequest() {
    $cfg = $this->getDOMPlus();
    $url = getCurLink(true);
    $access = true;
    foreach($cfg->getElementsByTagName('url') as $e) {
      if(strpos($url, $e->nodeValue) === false) continue;
      if($e->hasAttribute("access") && $e->getAttribute("access") == "allow") $access = true;
      else $access = false;
    }
    $loggedUser = null;
    if(isset($_SERVER['REMOTE_USER'])
      && in_array($_SERVER['REMOTE_USER'], array(USER_ID,"admin"))) {
      $loggedUser = $_SERVER['REMOTE_USER'];
    }
    global $cms;
    $cms->insertVar("logged_user", $loggedUser);
    if($access || !is_null($loggedUser)) return;
    new ErrorPage(_("Authentication required"), 403);
  }

}

?>