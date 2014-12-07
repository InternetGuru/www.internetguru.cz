<?php


class Auth extends Plugin implements SplObserver {
  private $loggedUser = null;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 0);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PREINIT) return;
    $this->handleRequest();
    if(IS_LOCALHOST) {
      Cms::setVariable("logged_user", "admin");
      return;
    }
    if(!is_null($this->loggedUser)) {
      if(!session_regenerate_id()) throw new Exception(_("Unable to regenerate session ID"));
      $_SESSION[get_class($this)]["loggedUser"] = $this->loggedUser;
    }
    if(isset($_SESSION[get_class($this)]["loggedUser"]))
      $this->loggedUser = $_SESSION[get_class($this)]["loggedUser"];
    Cms::setVariable("logged_user", $this->loggedUser);
  }

  private function handleRequest() {
    if(IS_LOCALHOST) return;
    $cfg = $this->getDOMPlus();
    $url = getCurLink(true);
    $access = true;
    foreach($cfg->getElementsByTagName('url') as $e) {
      if(strpos($url, $e->nodeValue) === false) continue;
      if($e->hasAttribute("access") && $e->getAttribute("access") == "allow") $access = true;
      else $access = false;
    }
    if(isset($_SERVER['REMOTE_USER'])
      && in_array($_SERVER['REMOTE_USER'], array(USER_ID, "admin"))) {
      $this->loggedUser = $_SERVER['REMOTE_USER'];
    }
    if($access || !is_null($this->loggedUser)) return;
    new ErrorPage(_("Authorization Required"), 401);
  }

}

?>