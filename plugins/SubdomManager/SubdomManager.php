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

    #TODO: run script

    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("errors", $this->err);
    $newContent->insertVar("curlink", getCurLink(true));
    $newContent->insertVar("domain", getDomain());

    #todo: cycle thru subdomains
    $fset = $newContent->getElementsByTagName("fieldset")->item(0);
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($fset, true));
    $this->modifyDOM($doc);
    $fset->parentNode->insertBefore($newContent->importNode($doc->documentElement, true), $fset);

    $fset->parentNode->removeChild($fset);
    return $newContent;
  }

  private function modifyDOM(DOMDocumentPlus $doc) {
    global $var;
    global $cms;
    global $plugins;
    // version
    $d = new DOMDocumentPlus();
    $select = $d->appendChild($d->createElement("var"));
    foreach(scandir(CMS_FOLDER ."/..") as $cmsVer) {
      if(!is_dir(CMS_FOLDER ."/../$cmsVer") || strpos($cmsVer, ".") === 0) continue;
      $o = $select->appendChild($d->createElement("option", $cmsVer));
      if($var["CMS_VER"] == $cmsVer) $o->setAttribute("selected", "selected");
      $o->setAttribute("value", $cmsVer);
    }
    $doc->insertVar("versions", $select);

    #TODO: select user_dir
    $doc->insertVar("USER_DIR", $var["USER_DIR"]);

    #TODO: select files_dir
    $doc->insertVar("FILES_DIR", $var["FILES_DIR"]);

    // plugins
    $pInput = array();
    foreach($cms->getVariable("cms-plugins_available") as $p) {
      $c = in_array($p, $cms->getVariable("cms-plugins")) ? " checked='checked'" : "";
      $pInput[] = "<input type='checkbox' id='i.$p' name='plugins'$c/><label for='i.$p'> $p</label>";
    }
    $doc->insertVar("plugins",$pInput);
  }

}

?>
