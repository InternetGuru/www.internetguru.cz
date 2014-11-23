<?php


class Auth extends Plugin implements SplObserver {
  private $loggedUser;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,0);
  }

  public function update(SplSubject $subject) {
    if(isAtLocalhost()) return;
    if($subject->getStatus() == STATUS_PREINIT) $this->handleRequest();
    if($subject->getStatus() != STATUS_INIT) return;
    global $cms;
    $cms->insertVar("logged_user", $this->loggedUser);
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
    $this->loggedUser = null;
    if(isset($_SERVER['REMOTE_USER'])
      && in_array($_SERVER['REMOTE_USER'], array(USER_ID,"admin"))) {
      $this->loggedUser = $_SERVER['REMOTE_USER'];
    }
    if($access || !is_null($this->loggedUser)) return;
    new ErrorPage(_("Authentication required"), 403);
  }

}

?>