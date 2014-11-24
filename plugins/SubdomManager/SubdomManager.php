<?php

class SubdomManager extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEBUG = false;
  private $cmsVersions;
  private $cmsVariants;
  private $cmsPlugins = array();
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
    if(!empty($_POST)) $this->processPost();
    $this->cmsVariants = $this->getSubdirs(CMS_ROOT_FOLDER, "/^[a-z0-9.]+$/");
    foreach($this->cmsVariants as $verDir => $active) { // including deprecated
      $verFile = CMS_ROOT_FOLDER."/$verDir/".CMS_VERSION_FILENAME;
      $this->cmsVersions[$verDir] = is_file($verFile) ? file_get_contents($verFile) : "unknown";
      $this->cmsPlugins[$verDir] = $this->getSubdirs(CMS_ROOT_FOLDER."/$verDir/".PLUGINS_DIR);
    }
    $this->userDirs = $this->getSubdirs(USER_ROOT_FOLDER, "/^".SUBDOM_PATTERN."$/");
    $this->filesDirs = $this->getSubdirs(FILES_ROOT_FOLDER);
    $this->syncUserSubdoms();
  }

  private function processPost() {
    try {
      $subdom = null;
      $succes = null;
      if(isset($_POST["new_subdom"])) {
        $subdom = $this->processCreateSubdom($_POST["new_subdom"]);
        $succes = sprintf(_("Subdom %s successfully created"), $subdom);
      } elseif(strlen($_GET[get_class($this)])) {
        if(!is_file("../".$_GET[get_class($this)]."/USER_ID.".USER_ID))
          throw new Exception(sprintf(_("Unauthorized subdom '%s' modification by '%s'"), $_GET[get_class($this)], USER_ID));
        if(isset($_POST["delete"])) {
          $subdom = $this->processDeleteSubdom($_GET[get_class($this)]);
          $succes = sprintf(_("Subdom %s successfully deleted"), $subdom);
        } else {
          $subdom = $this->processUpdateSubdom($_GET[get_class($this)]);
          if(!isset($_POST["apply"]) && !isset($_POST["redir"])) return;
          $succes = sprintf(_("Subdom %s successfully updated"), $subdom);
        }
      }
      if(is_null($subdom)) throw new Exception(_("Unrecognized POST request"));
      if(isset($_POST["mirror"]) && strlen($_POST["mirror"])) {
        if(!preg_match("/^".SUBDOM_PATTERN."$/", $_POST["mirror"]))
          throw new Exception(_("Invalid mirror subdom format"));
        $userDir = $this->getSubdomVar("USER_DIR", $_POST["mirror"]);
        if(is_null($userDir))
          throw new Exception(sprintf(_("Subdom '%s' configuration file not found"), $_POST["mirror"]));
        if(is_dir(USER_ROOT_FOLDER."/$subdom"))
          throw new Exception(sprintf(_("Subdom '%s' user folder exists"), $subdom));
        duplicateDir(SUBDOM_ROOT_FOLDER."/".$_POST["mirror"], false);
        duplicateDir(USER_ROOT_FOLDER."/$userDir");
        $sFolder = SUBDOM_ROOT_FOLDER."/$subdom";
        if(!rename(SUBDOM_ROOT_FOLDER."/~".$_POST["mirror"], $sFolder)
          || !rename(USER_ROOT_FOLDER."/~$userDir", USER_ROOT_FOLDER."/$subdom")
          || !rename("$sFolder/USER_DIR.$userDir", "$sFolder/USER_DIR.$subdom")
          ) throw new Exception(_("Unable to create subdom mirror"));
      }
      new InitServer($subdom, false, true);
      $link = getCurLink()."?".get_class($this)."=$subdom";
      if(isset($_POST["redir"])) $link = "http://$subdom.". getDomain();
      else Cms::addMessage($succes, Cms::MSG_SUCCESS, true);
      redirTo($link, null, true);
    } catch(Exception $e) {
      Cms::addMessage($e->getMessage(), Cms::MSG_WARNING);
    }
  }

  private function getSubdomVar($varName, $subdom) {
    foreach(scandir("../$subdom") as $f) {
      if(strpos($f, "$varName.") !== 0) continue;
      return substr($f,strlen($varName)+1);
    }
    return null;
  }

  private function processDeleteSubdom($subdom) {
    $activeDir = SUBDOM_ROOT_FOLDER."/$subdom";
    $inactiveDir = SUBDOM_ROOT_FOLDER."/.$subdom";
    if(is_dir($activeDir)) {
      $i = 0;
      $newSubdom = "$subdom~";
      while(file_exists(SUBDOM_ROOT_FOLDER."/$newSubdom")) $newSubdom = "$subdom~".++$i;
      if(!rename($activeDir, SUBDOM_ROOT_FOLDER."/$newSubdom"))
        throw new Exception(sprintf(_("Unable to backup subdom '%s' setup"), $subdom));
    }
    if(!is_dir($inactiveDir) && !mkdir($inactiveDir)) {
      throw new Exception(sprintf(_("Unable to create '%s' dir"), ".$subdom"));
    }
    return $subdom;
  }

  private function processCreateSubdom($subdom) {
    if(strpos($subdom, USER_ID) !== 0) $subdom = USER_ID . $subdom;
    if(!preg_match("/^".USER_ID.SUBDOM_PATTERN."$/", $subdom))
      throw new Exception(_("Invalid subdom format"));
    if(is_dir(SUBDOM_ROOT_FOLDER."/$subdom"))
      throw new Exception(sprintf(_("Subdom '%s' already exists"), $subdom));
    return $subdom;
  }

  // process post (changes) into USER_ID/subdom
  private function processUpdateSubdom($subdom) {
    $subdomFolder = SUBDOM_ROOT_FOLDER."/$subdom";
    if(!is_dir($subdomFolder) && !mkdir($subdomFolder, 0755, true))
      throw new Exception(sprintf(_("Unable to create user subdom directory '%s'"), $subdom));
    if(!isset($_POST["PLUGINS"]) || !is_array($_POST["PLUGINS"]))
      throw new Exception(_("Missing POST data 'PLUGINS'"));
    foreach(scandir($subdomFolder) as $f) {
      if(strpos($f, ".") === 0) continue;
      $var = explode(".", $f, 2);
      switch($var[0]) {
        case "CMS_VER":
        case "USER_DIR":
        case "FILES_DIR":
        if(!isset($_POST[$var[0]])) throw new Exception(sprintf(_("Missing POST data '%s'"), $var[0]));
        if(!rename("$subdomFolder/$f", "$subdomFolder/{$var[0]}.". $_POST[$var[0]]))
          throw new Exception(sprintf(_("Unable to set '%s' to '%s'"), $var[0], $_POST[$var[0]]));
        break;
        case "PLUGIN":
        if(!in_array($var[1], $_POST["PLUGINS"]) && !unlink("$subdomFolder/$f"))
          throw new Exception(sprintf(_("Unable to disable plugin from subdom '%s'"), $subdom));
      }
    }
    foreach($_POST["PLUGINS"] as $p) {
      if(touch("$subdomFolder/PLUGIN.$p", 0644)) continue;
      throw new Exception(sprintf(_("Unable to enable plugin from subdom '%s'"), $subdom));
    }
    return $subdom;
  }

  public function getContent(HTMLPlus $content) {
    Cms::getOutputStrategy()->addCssFile($this->getDir() ."/SubdomManager.css");
    Cms::getOutputStrategy()->addJs("
    var forms = document.getElementsByTagName('form');
    for(var i=0; i<forms.length; i++) {
      if(typeof forms[i]['delete'] !== 'object') continue;
      forms[i]['delete'].addEventListener('click', function(e){
        var subdom = e.target.form['action'].split('=')[1];
        if(confirm('Delete subdom \"' + subdom + '\"?')) return;
        e.preventDefault();
      }, false);
    }");

    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("domain", getDomain());
    $newContent->insertVar("curlink", getCurLink()."?".get_class($this));

    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    $o = $d->createElement("option", "n/a");
    $o->setAttribute("value", "");
    $set->appendChild($o);
    foreach($this->subdoms as $subdom) {
      $o = $d->createElement("option", $subdom);
      if(isset($_POST["mirror"]) && $_POST["mirror"] == $subdom) {
        $o->setAttribute("selected", "selected");
      }
      #$o->setAttribute("value", $subdom);
      $set->appendChild($o);
    }
    if($set->childNodes->length) $newContent->insertVar("subdoms", $set);

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
        Cms::addMessage($e->getMessage(), Cms::MSG_WARNING);
      }
    }
  }

  private function modifyDOM(DOMDocumentPlus $doc, $subdom) {
    #$doc->insertVar("subdom", $subdom);
    $doc->insertVar("linkName", "<strong>$subdom</strong>.".getDomain());
    $doc->insertVar("linkHref", "http://$subdom.".getDomain());
    $doc->insertVar("cmsVerId", "$subdom-CMS_VER");
    $doc->insertVar("userDirId", "$subdom-USER_DIR");
    $doc->insertVar("filesDirId", "$subdom-FILES_DIR");
    $doc->insertVar("curlinkSubdom", getCurLink()."?".get_class($this)."=$subdom");

    $showSubdom = basename(dirname($_SERVER["PHP_SELF"]));
    if(strlen($_GET[get_class($this)])) $showSubdom = $_GET[get_class($this)];
    if($subdom == $showSubdom) $doc->insertVar("nohide", "nohide");

    // versions
    $d = new DOMDocumentPlus();
    $current = null;
    $deprecated = false;
    $set = $d->appendChild($d->createElement("var"));
    $userSubdomFolder = SUBDOM_ROOT_FOLDER ."/$subdom";
    foreach($this->cmsVariants as $cmsVer => $active) {
      if(is_file("../$subdom/CMS_VER.$cmsVer")) $current = $cmsVer;
      if(!$active) {
        if($current == $cmsVer) $deprecated = true;
        continue; // silently skip disabled versions
      }
      $o = $d->createElement("option", "$cmsVer/".$this->cmsVersions[$cmsVer]);
      if(is_file("$userSubdomFolder/CMS_VER.$cmsVer")) {
        $o->setAttribute("selected", "selected");
      }
      $o->setAttribute("value", $cmsVer);
      $set->appendChild($o);
    }
    if(!is_null($current)) {
      $doc->insertVar("version", "$current/".$this->cmsVersions[$current]);
      $doc->insertVar("CMS_VER", "CMS_VER.$current");
    }
    if(!$deprecated) $doc->insertVar("deprecated", null);
    if($set->childNodes->length) $doc->insertVar("cmsVers", $set);

    // plugins
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->cmsPlugins[$current] as $pName => $default) {
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
