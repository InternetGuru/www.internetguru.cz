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
    if(!empty($_POST)) try {
      $this->processPost();
    } catch(Exception $e) { // this should never happen
      new Logger($e->getMessage(), "error");
      $this->err[] = $e->getMessage();
    }
  }

  // process post (changes) into USER_ID/subdom
  private function processPost() {
    if(!is_file("../{$_POST["subdom"]}/USER_ID.". USER_ID))
      throw new Exception(sprintf("Unauthorized subdom '%s' modification by '%s'", $_POST["subdom"], USER_ID));
    $subdomFolder = SUBDOM_FOLDER ."/../{$_POST["subdom"]}";
    if(!is_dir($subdomFolder) && !mkdir($subdomFolder, 0755, true))
      throw new Exception("Unable to create user subdom directory '{$_POST["subdom"]}'");
    if(!isset($_POST["PLUGINS"]) || !is_array($_POST["PLUGINS"]))
      throw new Exception("Missing POST data 'PLUGINS'");
    foreach(scandir($subdomFolder) as $f) {
      if(strpos($f, ".") === 0) continue;
      $var = explode(".", $f, 2);
      switch($var[0]) {
        case "CMS_VER":
        case "USER_DIR":
        case "FILES_DIR":
        if(!isset($_POST[$var[0]])) throw new Exception("Missing POST data '{$var[0]}'");
        if(!rename("$subdomFolder/$f", "$subdomFolder/{$var[0]}.". $_POST[$var[0]]))
          throw new Exception(sprintf("Unable to set '%s' to '%s'",$var[0],$_POST[$var[0]]));
        break;
        case "PLUGIN":
        if(!in_array($var[1], $_POST["PLUGINS"]) && !unlink("$subdomFolder/$f"))
          throw new Exception("Unable to disable plugin from subdom '{$_POST["subdom"]}'");
      }
    }
    foreach($_POST["PLUGINS"] as $p) {
      if(touch("$subdomFolder/PLUGIN.$p", 0644)) continue;
      throw new Exception("Unable to enable plugin from subdom '{$_POST["subdom"]}'");
    }
    if(!isset($_POST["apply"])) return;
    init_server($_POST["subdom"], CMS_FOLDER ."/..", true);
    #todo: redir
  }

  public function getContent(HTMLPlus $content) {
    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("errors", $this->err);
    $newContent->insertVar("curlink", getCurLink(true));
    $newContent->insertVar("domain", getDomain());
    $newContent->insertVar("user", USER_ID);

    $form = $newContent->getElementsByTagName("form")->item(0);
    foreach($this->getSubdirs(SUBDOM_FOLDER ."/..", "/^[a-z][a-z0-9]*$/") as $subdom => $null) {
      $doc = new DOMDocumentPlus();
      $doc->appendChild($doc->importNode($form, true));
      $this->modifyDOM($doc, $subdom);
      $form->parentNode->insertBefore($newContent->importNode($doc->documentElement, true), $form);
    }

    $form->parentNode->removeChild($form);
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
    $current = null;
    $deprecated = false;
    $set = $d->appendChild($d->createElement("var"));
    $userSubdomFolder = SUBDOM_FOLDER ."/../$subdom";
    foreach($this->cmsVersions as $cmsVer => $active) {
      if(is_file("../$subdom/CMS_VER.$cmsVer")) $current = $cmsVer;
      if(!$active) {
        if($current == $cmsVer) $deprecated = true;
        continue; // silently skip disabled versions
      }
      $o = $d->createElement("option", $cmsVer);
      if(is_file("$userSubdomFolder/CMS_VER.$cmsVer")) {
        $o->setAttribute("selected", "selected");
      }
      $o->setAttribute("value", $cmsVer);
      $set->appendChild($o);
    }
    if(!is_null($current)) {
      $doc->insertVar("version", $current);
      $doc->insertVar("CMS_VER", "CMS_VER.$current");
    }
    if(!$deprecated) $doc->insertVar("deprecated", null);
    if($set->childNodes->length) $doc->insertVar("cmsVers", $set);

    // user directories
    $d = new DOMDocumentPlus();
    $current = null;
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->userDirs as $dir => $active) {
      if(!$active) continue; // silently skip dirs to del and mirrors
      $o = $set->appendChild($d->createElement("option", $dir));
      if(is_file("../$subdom/USER_DIR.$dir")) $current = $dir;
      if(is_file("$userSubdomFolder/USER_DIR.$dir")) $o->setAttribute("selected", "selected");
      $o->setAttribute("value", $dir);
    }
    if(!is_null($current)) $doc->insertVar("USER_DIR", "USER_DIR.$current");
    $doc->insertVar("userDirs", $set);

    // files directories
    $d = new DOMDocumentPlus();
    $current = null;
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->filesDirs as $dir => $active) {
      if(!$active) continue; // silently skip disabled dirs
      $o = $set->appendChild($d->createElement("option", $dir));
      if(is_file("../$subdom/FILES_DIR.$dir")) $current = $dir;
      if(is_file("$userSubdomFolder/FILES_DIR.$dir")) $o->setAttribute("selected", "selected");
      $o->setAttribute("value", $dir);
    }
    if(!is_null($current)) $doc->insertVar("FILES_DIR", "FILES_DIR.$current");
    $doc->insertVar("filesDirs", $set);

    // plugins
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->cmsPlugins as $pName => $default) {
      if(is_file("../$subdom/.PLUGIN.$pName")) continue; // silently skip forbidden plugins
      $i = $set->appendChild($d->createElement("input"));
      $i->setAttribute("type", "checkbox");
      $i->setAttribute("id", "$subdom-$pName");
      $i->setAttribute("name", "PLUGINS[]");
      $i->setAttribute("value", $pName);
      if(file_exists("$userSubdomFolder/PLUGIN.$pName")) $i->setAttribute("checked", "checked");
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
