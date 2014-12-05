<?php

class SubdomManager extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEBUG = false;
  private $cmsVersions;
  private $cmsVariants;
  private $cmsPlugins = array();
  private $userDirs;
  private $filesDirs;
  private $successMsg;
  private $subdoms = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 3);
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
    $this->syncSubdomsToUser();
    $this->userSubdoms = $this->getSubdirs(SUBDOM_ROOT_FOLDER, "/^".SUBDOM_PATTERN."$/");
    foreach($this->userSubdoms as $subdom => $null) {
      if(!isset($this->userDirs[$subdom])) $this->userDirs[$subdom] = true;
      if(!isset($this->filesDirs[$subdom])) $this->filesDirs[$subdom] = true;
    }
    ksort($this->userDirs);
    ksort($this->filesDirs);
  }

  /**
   * sync real owned subdoms to user subdom folder
   * delete if .usersubdom
   * @return void
   */
  private function syncSubdomsToUser() {
    foreach($this->getSubdirs("..", "/^".SUBDOM_PATTERN."$/") as $subdom => $null) {
      if(!is_file("../$subdom/USER_ID.". USER_ID)) continue;
      try {
        new InitServer($subdom, file_exists(SUBDOM_ROOT_FOLDER."/.$subdom"));
      } catch(Exception $e) {
        new Logger($e->getMessage());
        Cms::addMessage($e->getMessage(), Cms::MSG_WARNING);
      }
    }
  }

  private function processPost() {
    try {
      $subdom = $this->getSubdomFromRequest();
      if(is_null($subdom)) throw new Exception(_("Invalid POST request"));
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
      else Cms::addMessage($this->successMsg, Cms::MSG_SUCCESS, true);
      redirTo($link, null, true);
    } catch(Exception $e) {
      new Logger($e->getMessage());
      Cms::addMessage($e->getMessage(), Cms::MSG_WARNING);
    }
  }

  private function getSubdomFromRequest() {
    if(isset($_POST["new_subdom"]))
      return $this->processCreateSubdom(USER_ID.$_POST["new_subdom"]);
    if(!strlen($_GET[get_class($this)])) return null;
    if(!is_dir($_GET[get_class($this)]) && !isset($_POST["delete"]))
      return $this->processCreateSubdom($_GET[get_class($this)]);
    if(!is_file("../".$_GET[get_class($this)]."/USER_ID.".USER_ID))
      throw new Exception(sprintf(_("Unauthorized subdom '%s' modification by '%s'"), $_GET[get_class($this)], USER_ID));
    if(isset($_POST["delete"]))
      return $this->processDeleteSubdom($_GET[get_class($this)]);
    return $this->processUpdateSubdom($_GET[get_class($this)]);
  }

  private function getSubdomVar($varName, $subdom) {
    if(is_dir("../$subdom")) foreach(scandir("../$subdom") as $f) {
      if(strpos($f, "$varName.") !== 0) continue;
      return substr($f, strlen($varName)+1);
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
    $this->successMsg = sprintf(_("Subdom %s successfully deleted"), $subdom);
    return $subdom;
  }

  private function processCreateSubdom($subdom) {
    if(!preg_match("/^".USER_ID.SUBDOM_PATTERN."$/", $subdom))
      throw new Exception(_("Invalid subdom format"));
    if(is_dir(SUBDOM_ROOT_FOLDER."/$subdom") && is_dir("../$subdom"))
      throw new Exception(sprintf(_("Subdom '%s' already exists"), $subdom));
    $this->successMsg = sprintf(_("Subdom %s successfully created"), $subdom);
    return $subdom;
  }

  // process post (changes) into USER_ID/subdom
  private function processUpdateSubdom($subdom) {
    $subdomFolder = SUBDOM_ROOT_FOLDER."/$subdom";
    if(!is_dir($subdomFolder) && !mkdir($subdomFolder, 0755, true))
      throw new Exception(sprintf(_("Unable to create user subdom directory '%s'"), $subdom));
    if(!isset($_POST["PLUGINS"]) || !is_array($_POST["PLUGINS"]))
      throw new Exception(_("Missing POST data 'PLUGINS'"));
    $origHash = getDirHash($subdomFolder);
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
    if($origHash == getDirHash($subdomFolder))
      throw new Exception(sprintf(_("No changes made in '%s'"), $subdom));
    $this->successMsg = sprintf(_("Subdom %s successfully updated"), $subdom);
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
    foreach($this->userSubdoms as $subdom => $null) {
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
    foreach($this->userSubdoms as $subdom => $null) {
      $doc = new DOMDocumentPlus();
      $doc->appendChild($doc->importNode($form, true));
      $this->modifyDOM($doc, $subdom);
      $form->parentNode->insertBefore($newContent->importNode($doc->documentElement, true), $form);
    }

    $form->parentNode->removeChild($form);
    return $newContent;
  }

  private function modifyDOM(DOMDocumentPlus $doc, $subdom) {
    $doc->insertVar("linkName", "<strong>$subdom</strong>.".getDomain());
    $doc->insertVar("cmsVerId", "$subdom-CMS_VER");
    $doc->insertVar("userDirId", "$subdom-USER_DIR");
    $doc->insertVar("filesDirId", "$subdom-FILES_DIR");
    $doc->insertVar("curlinkSubdom", getCurLink()."?".get_class($this)."=$subdom");

    $showSubdom = basename(dirname($_SERVER["PHP_SELF"]));
    if(strlen($_GET[get_class($this)])) $showSubdom = $_GET[get_class($this)];
    if($subdom == $showSubdom) $doc->insertVar("nohide", "nohide");

    // versions
    $current = $this->getSubdomVar("CMS_VER", $subdom);
    if(!is_null($current)) {
      $doc->insertVar("linkHref", "http://$subdom.".getDomain());
      $doc->insertVar("version", "$current/".$this->cmsVersions[$current]);
      $doc->insertVar("CMS_VER", "CMS_VER.$current");
    } else $current = CMS_BEST_RELEASE;
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    $deprecated = false;
    foreach($this->cmsVariants as $cmsVer => $active) {
      if(!$active) {
        if($current == $cmsVer) $deprecated = true;
        continue; // silently skip disabled versions
      }
      $o = $d->createElement("option", "$cmsVer/".$this->cmsVersions[$cmsVer]);
      if($cmsVer == $current) $o->setAttribute("selected", "selected");
      $o->setAttribute("value", $cmsVer);
      $set->appendChild($o);
    }
    if(!$deprecated) $doc->insertVar("deprecated", null);
    if($set->childNodes->length) $doc->insertVar("cmsVers", $set);

    // user directories
    $current = $this->getSubdomVar("USER_DIR", $subdom);
    if(!is_null($current)) $doc->insertVar("USER_DIR", "USER_DIR.$current");
    else $current = $subdom;
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->userDirs as $dir => $active) {
      if(!$active) continue; // silently skip dirs to del and mirrors
      $o = $set->appendChild($d->createElement("option", $dir));
      if($dir == $current) $o->setAttribute("selected", "selected");
    }
    $doc->insertVar("userDirs", $set);

    // files directories
    $current = $this->getSubdomVar("FILES_DIR", $subdom);
    if(!is_null($current)) $doc->insertVar("FILES_DIR", "FILES_DIR.$current");
    else $current = $subdom;
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->filesDirs as $dir => $active) {
      if(!$active) continue; // silently skip disabled dirs
      $o = $set->appendChild($d->createElement("option", $dir));
      if($dir == $current) $o->setAttribute("selected", "selected");
    }
    $doc->insertVar("filesDirs", $set);

    // plugins
    $current = $this->getSubdomVar("CMS_VER", $subdom);
    if(is_null($current)) return;
    $userSubdomFolder = SUBDOM_ROOT_FOLDER."/$subdom";
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
