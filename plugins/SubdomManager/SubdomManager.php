<?php

class SubdomManager extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEBUG = false;
  private $err = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,3);
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function update(SplSubject $subject) {
    if(isAtLocalhost() || !isset($_GET[get_class($this)])) {
      $subject->detach($this);
    }
  }

  public function getContent(HTMLPlus $content) {
    global $var;
    global $cms;
    global $plugins;

    #TODO: run script

    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("errors", $this->err);
    $newContent->insertVar("curlink", getCurLink(true));
    $newContent->insertVar("domain", getDomain());

    $doc = new DOMDocumentPlus();
    $select = $doc->appendChild($doc->createElement("var"));
    foreach(scandir(CMS_FOLDER ."/..") as $cmsVer) {
      if(!is_dir(CMS_FOLDER ."/../$cmsVer") || strpos($cmsVer, ".") === 0) continue;
      $o = $select->appendChild($doc->createElement("option", $cmsVer));
      if($var["CMS_VER"] == $cmsVer) $o->setAttribute("selected", "selected");
      $o->setAttribute("value", $cmsVer);
    }
    $newContent->insertVar("versions", $select);

    $newContent->insertVar("USER_DIR", $var["USER_DIR"]);
    $newContent->insertVar("FILES_DIR", $var["FILES_DIR"]);

    $pInput = array();
    foreach($cms->getVariable("cms-plugins_available") as $p) {
      $c = in_array($p, $cms->getVariable("cms-plugins")) ? " checked='checked'" : "";
      $pInput[] = "<input type='checkbox' id='i.$p' name='plugins'$c/><label for='i.$p'> $p</label>";
    }
    $newContent->insertVar("plugins",$pInput);

    return $newContent;
  }


}

?>
