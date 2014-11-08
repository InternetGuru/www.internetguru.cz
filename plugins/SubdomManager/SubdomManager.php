<?php

class SubdomManager extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEBUG = false;
  private $err = array();
  private $cmsVersions;
  private $cmsPlugins;
  private $userDirs;
  private $filesDirs;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,3);
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function update(SplSubject $subject) {
    if(!SUBDOM_FOLDER || !isset($_GET[get_class($this)])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() != "preinit") return;
    $this->cmsVersions = $this->getSubdirs(CMS_FOLDER ."/..");
    $this->cmsPlugins = $this->getSubdirs(CMS_FOLDER ."/". PLUGIN_FOLDER);
    $this->userDirs = $this->getSubdirs(USER_FOLDER ."/..", "/^[^~]/");
    $this->filesDirs = $this->getSubdirs(FILES_FOLDER ."/..");
    $this->syncUserSubdoms();
    if(!empty($_POST)) $this->processPost();
  }

  // process post (changes) into USER_ID/subdom
  private function processPost() {
    /*
    foreach($_POST as $k => $v) {
      $k = explode("-", $k);
      if(!is_file("../{$k[0]}/USER_ID.". USER_ID)) continue;
      if(is_file("../{$k[0]}/{$k[1]}.$v")) continue;
      $userFilePath = SUBDOM_FOLDER ."/../{$k[0]}/{$k[1]}.$v";
      switch($k[1]) {
        case "CMS_VER":
        case "USER_DIR":
        case "FILES_DIR":
        if(!is_dir(dirname($userFilePath))) mkdir(dirname($userFilePath), 0755, true);
        if(touch($userFilePath, 0644)) continue;
        $this->err[] = "Unable to create configuration file '$userFilePath'";
        new Logger(end($this->err), "error");
        break;
        case "PLUGINS":
        #todo
      }
    }
    */
  }

  public function getContent(HTMLPlus $content) {
    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("errors", $this->err);
    $newContent->insertVar("curlink", getCurLink(true));
    $newContent->insertVar("domain", getDomain());
    $newContent->insertVar("user", USER_ID);

    $fset = $newContent->getElementsByTagName("fieldset")->item(0);
    foreach($this->getSubdirs(SUBDOM_FOLDER ."/..", "/^[a-z][a-z0-9]*$/") as $subdom => $null) {
      $doc = new DOMDocumentPlus();
      $doc->appendChild($doc->importNode($fset, true));
      $this->modifyDOM($doc, $subdom);
      $fset->parentNode->insertBefore($newContent->importNode($doc->documentElement, true), $fset);
    }

    $fset->parentNode->removeChild($fset);
    return $newContent;
  }

  private function syncUserSubdoms() {
    foreach($this->getSubdirs("..", "/^[a-z][a-z0-9]*$/") as $subdom => $null) {
      if(!is_file("../$subdom/USER_ID.". USER_ID)) continue;
      try {
        init_server($subdom, CMS_FOLDER ."/.."); // clone existing subdoms (no update)
      } catch(Exception $e) {
        $this->err[] = $e->getMessage();
      }
    }
  }

  private function modifyDOM(DOMDocumentPlus $doc, $subdom) {
    $doc->insertVar("subdom", $subdom);
    $doc->insertVar("cmsVerId", "$subdom-CMS_VER");
    $doc->insertVar("userDirId", "$subdom-USER_DIR");
    $doc->insertVar("filesDirId", "$subdom-FILES_DIR");
    if($subdom == basename(dirname($_SERVER["PHP_SELF"]))) $doc->insertVar("nohide", "nohide");

    // versions
    $d = new DOMDocumentPlus();
    $curVer = null;
    $deprecated = false;
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->cmsVersions as $cmsVer => $active) {
      $o = $d->createElement("option", $cmsVer);
      if(is_file("../$subdom/CMS_VER.$cmsVer")) {
        $o->setAttribute("selected", "selected");
        $curVer = $cmsVer;
      }
      if(!$active) {
        if($curVer == $cmsVer) $deprecated = true;
        continue; // silently skip disabled versions
      }
      $o->setAttribute("value", $cmsVer);
      $set->appendChild($o);
    }
    if(!is_null($curVer)) $doc->insertVar("version", $curVer);
    if(!$deprecated) $doc->insertVar("deprecated", null);
    if($set->childNodes->length) $doc->insertVar("cmsVers", $set);

    // user directories
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->userDirs as $dir => $active) {
      if(!$active) continue; // silently skip dirs to del and mirrors
      $o = $set->appendChild($d->createElement("option", $dir));
      if(is_file("../$subdom/USER_DIR.$dir")) $o->setAttribute("selected", "selected");
      $o->setAttribute("value", $dir);
    }
    $doc->insertVar("userDirs", $set);

    // files directories
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->filesDirs as $dir => $active) {
      if(!$active) continue; // silently skip disabled dirs
      $o = $set->appendChild($d->createElement("option", $dir));
      if(is_file("../$subdom/FILES_DIR.$dir")) $o->setAttribute("selected", "selected");
      $o->setAttribute("value", $dir);
    }
    $doc->insertVar("filesDirs", $set);

    // plugins
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->cmsPlugins as $pName => $default) {
      if(is_file("../$subdom/.PLUGIN.$pName")) continue; // silently skip forbidden plugins
      $i = $set->appendChild($d->createElement("input"));
      $i->setAttribute("type", "checkbox");
      $i->setAttribute("id", "$subdom-$pName");
      $i->setAttribute("name", "PLUGIN-$pName");
      if(file_exists("../$subdom/PLUGIN.$pName")) $i->setAttribute("checked", "checked");
      $l = $set->appendChild($d->createElement("label", " $pName"));
      $l->setAttribute("for","$subdom-$pName");
      $set->appendChild($d->createTextNode(", "));
    }
    $doc->insertVar("plugins", $set);
  }

  private function getSubdirs($dir, $filter = null) {
    $subdirs = array();
    foreach(scandir($dir) as $f) {
      if(strpos($f, ".") === 0 || !is_dir("$dir/$f")) continue;
      if(!is_null($filter) && !preg_match($filter, $f)) continue;
      $subdirs[$f] = !file_exists("$dir/.$f");
    }
    return $subdirs;
  }

}

?>
