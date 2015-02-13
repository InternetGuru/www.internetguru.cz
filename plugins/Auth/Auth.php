<?php


class Auth extends Plugin implements SplObserver {
  private $loggedUser = null;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 0);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PREINIT) return;
    if(IS_LOCALHOST) {
      Cms::setVariable("logged_user", "localhost");
      return;
    } elseif(isset($_SERVER["REMOTE_ADDR"]) && $_SERVER["REMOTE_ADDR"] == "46.28.109.142") {
      Cms::setVariable("logged_user", "server");
      return;
    } else {
      $this->handleRequest();
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
    $cfg = $this->getDOMPlus();
    $url = ROOT_URL.getCurLink(true);
    $access = !file_exists(FORBIDDEN);
    if($access) foreach($cfg->getElementsByTagName('url') as $e) {
      if(strpos($url, $e->nodeValue) === false) continue;
      if($e->getAttribute("access") == "allow") $access = true;
      else $access = false;
    }
    if(isset($_SERVER['REMOTE_USER'])) {
      $this->loggedUser = $_SERVER['REMOTE_USER'];
    } elseif(isset($_SESSION[get_class($this)]["loggedUser"])) {
      $this->loggedUser = $_SESSION[get_class($this)]["loggedUser"];
    }
    if($access || in_array($this->loggedUser, array(USER_ID, "admin"))) return;
    redirTo("?login", 401, true, _("Authorization required"));
  }

}

?>