<?php

#todo: cmsPlugins according to cmsVersion

class SubdomManager extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEBUG = false;
  private $err = array();
  private $cmsVersions;
  private $cmsPlugins;
  private $userDirs;
  private $filesDirs;
  private $subdoms = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,3);
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function update(SplSubject $subject) {
    if(isAtLocalhost() || !isset($_GET[get_class($this)])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() != STATUS_PREINIT) return;
    $this->cmsVersions = $this->getSubdirs(CMS_ROOT_FOLDER);
    $this->cmsPlugins = $this->getSubdirs(PLUGINS_FOLDER);
    $this->userDirs = $this->getSubdirs(USER_ROOT_FOLDER, "/^[^~]/");
    $this->filesDirs = $this->getSubdirs(FILES_ROOT_FOLDER);
    $this->syncUserSubdoms();
    if(empty($_POST)) return;
    try {
      $subdom = null;
      if(isset($_POST["new_subdom"])) $subdom = $this->processCreateSubdom($_POST["new_subdom"]);
      if(isset($_POST["subdom"])) {
        #if(!is_file("../{$_POST["subdom"]}/USER_ID.". USER_ID))
        #  throw new Exception(sprintf("Unauthorized subdom '%s' modification by '%s'", $_POST["subdom"], USER_ID));
        if(isset($_POST["delete"])) {
          $subdom = $this->processDeleteSubdom($_POST["subdom"]);
        } else {
          $subdom = $this->processUpdateSubdom($_POST["subdom"]);
          if(!isset($_POST["apply"]) && !isset($_POST["redir"])) return;
        }
      }
      if(is_null($subdom)) throw new Exception("Unrecognized POST");
      new InitServer($subdom, false, true);
      $link = getCurLink(true);
      if(isset($_POST["redir"])) $link = "http://$subdom.". getDomain();
      redirTo($link, null, true);
    } catch(Exception $e) { // this should never happen
      new Logger($e->getMessage(), "error");
      $this->err[] = $e->getMessage();
    }
  }

  private function processDeleteSubdom($subdom) {
    if(self::DEBUG) throw new Exception("Deleting DISABLED");
    $activeDir = SUBDOM_ROOT_FOLDER."/$subdom";
    $inactiveDir = SUBDOM_ROOT_FOLDER."/.$subdom";
    if(is_dir($activeDir)) {
      $newSubdom = "~$subdom";
      while(file_exists(SUBDOM_ROOT_FOLDER."/$newSubdom")) $newSubdom = "~$newSubdom";
      if(!rename($activeDir, SUBDOM_ROOT_FOLDER."/$newSubdom"))
        throw new Exception("Unable to backup subdom '$subdom' setup");
    }
    if(!is_dir($inactiveDir) && !mkdir($inactiveDir)) {
      throw new Exception("Unable to create '.$subdom' dir");
    }
    return $subdom;
  }

  private function processCreateSubdom($subdom) {
    if(strpos($subdom, USER_ID) !== 0) $subdom = USER_ID . $subdom;
    if(!preg_match("/^".USER_ID.SUBDOM_PATTERN."$/", $subdom))
      throw new Exception("Invalid subdom format");
    if(is_dir("../$subdom"))
      throw new Exception("Subdom '$subdom' already exists");
    return $subdom;
  }

  // process post (changes) into USER_ID/subdom
  private function processUpdateSubdom($subdom) {
    $subdomFolder = SUBDOM_ROOT_FOLDER."/$subdom";
    if(!is_dir($subdomFolder) && !mkdir($subdomFolder, 0755, true))
      throw new Exception("Unable to create user subdom directory '$subdom'");
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
          throw new Exception("Unable to disable plugin from subdom '$subdom'");
      }
    }
    foreach($_POST["PLUGINS"] as $p) {
      if(touch("$subdomFolder/PLUGIN.$p", 0644)) continue;
      throw new Exception("Unable to enable plugin from subdom '$subdom'");
    }
    return $subdom;
  }

  public function getContent(HTMLPlus $content) {
    global $cms;
    $cms->getOutputStrategy()->addCssFile($this->getDir() ."/SubdomManager.css");
    $cms->getOutputStrategy()->addJs("
    var forms = document.getElementsByTagName('form');
    for(var i=0; i<forms.length; i++) {
      if(typeof forms[i]['delete'] !== 'object') continue;
      forms[i]['delete'].addEventListener('click', function(e){
        if(confirm('Delete subdom \"' + e.target.form['subdom'].value + '\"?')) return;
        e.preventDefault();
      }, false);
    }");

    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("errors", $this->err);
    $newContent->insertVar("curlink", getCurLink(true));
    $newContent->insertVar("domain", getDomain());

    if(isset($_POST["new_subdom"])) {
      $newContent->insertVar("new_nohide", "nohide");
      $newContent->insertVar("new_subdom", $_POST["new_subdom"]);
    }

    $form = $newContent->getElementsByTagName("form")->item(1);
    foreach($this->subdoms as $subdom) {
      $doc = new DOMDocumentPlus();
      $doc->appendChild($doc->importNode($form, true));
      $this->modifyDOM($doc, $subdom);
      $form->parentNode->insertBefore($newContent->importNode($doc->documentElement, true), $form);
    }

    $form->parentNode->removeChild($form);
    return $newContent;
  }

  private function syncUserSubdoms() {
    foreach($this->getSubdirs("..", "/^".SUBDOM_PATTERN."$/") as $subdom => $null) {
      if(!is_file("../$subdom/USER_ID.". USER_ID)) continue;
      try {
        $this->subdoms[] = $subdom;
        new InitServer($subdom); // clone existing subdoms (no update)
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

    $showSubdom = basename(dirname($_SERVER["PHP_SELF"]));
    if(isset($_POST["subdom"])) $showSubdom = $_POST["subdom"];
    if($subdom == $showSubdom) $doc->insertVar("nohide", "nohide");

    // versions
    $d = new DOMDocumentPlus();
    $current = null;
    $deprecated = false;
    $set = $d->appendChild($d->createElement("var"));
    $userSubdomFolder = SUBDOM_ROOT_FOLDER ."/$subdom";
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
      $i = $d->createElement("input");
      $i->setAttribute("type", "checkbox");
      $i->setAttribute("name", "PLUGINS[]");
      $i->setAttribute("value", $pName);
      $changed = "";
      if(is_file("../$subdom/PLUGIN.$pName") != is_file("$userSubdomFolder/PLUGIN.$pName"))
        $changed = "*";
      if(is_file("$userSubdomFolder/PLUGIN.$pName")) $i->setAttribute("checked", "checked");
      $l = $set->appendChild($d->createElement("label"));
      $l->appendChild($i);
      $l->appendChild(new DOMText(" $pName$changed"));
      #$set->appendChild($d->createTextNode(", "));
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
