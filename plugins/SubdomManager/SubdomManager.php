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
    if(IS_LOCALHOST || !isset($_GET[get_class($this)])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() != STATUS_PREINIT) return;
    if(!empty($_POST)) $this->processPost();
    $this->cmsVariants = $this->getSubdirs(CMS_ROOT_FOLDER, "/^[a-z0-9][a-z0-9.]+$/");
    foreach($this->cmsVariants as $verDir => $active) { // including deprecated
      $verFile = CMS_ROOT_FOLDER."/$verDir/".CMS_VERSION_FILENAME;
      $this->cmsVersions[$verDir] = is_file($verFile) ? file_get_contents($verFile) : "unknown";
      $this->cmsPlugins[$verDir] = $this->getSubdirs(CMS_ROOT_FOLDER."/$verDir/".PLUGINS_DIR);
    }
    $this->userDirs = $this->getSubdirs(USER_ROOT_FOLDER, "/^".SUBDOM_PATTERN."$/");
    $this->filesDirs = $this->getSubdirs(FILES_ROOT_FOLDER);
    $this->syncSubdomsToUser();
    $this->userSubdoms = $this->getSubdirs(SUBDOM_ROOT_FOLDER, "/^\.?".SUBDOM_PATTERN."$/");
    foreach($this->userSubdoms as $subdom => $null) {
      if(!isset($this->userDirs[$subdom])) $this->userDirs[$subdom] = true;
      if(!isset($this->filesDirs[$subdom])) $this->filesDirs[$subdom] = true;
    }
    ksort($this->userDirs);
    ksort($this->filesDirs);
  }

  /**
   * sync real owned subdoms to user subdom folder
   * delete if.usersubdom
   * @return void
   */
  private function syncSubdomsToUser() {
    foreach($this->getSubdirs("..", "/^".SUBDOM_PATTERN."$/") as $subdom => $null) {
      if(!is_file("../$subdom/USER_ID.".USER_ID)) continue;
      try {
        new InitServer($subdom, file_exists(SUBDOM_ROOT_FOLDER."/.$subdom"));
      } catch(Exception $e) {
        new Logger($e->getMessage(), Logger::LOGGER_ERROR);
      }
    }
  }

  private function processPost() {
    try {
      $subdom = $this->getSubdomFromRequest();
      if(is_null($subdom)) throw new Exception(_("Invalid POST request"));
      new InitServer($subdom, false, true);
      $link = getCurLink()."?".get_class($this)."=$subdom";
      if(isset($_POST["redir"])) $link = "http://$subdom.".getDomain();
      else Cms::addMessage($this->successMsg, Cms::MSG_SUCCESS, true);
      redirTo($link, null, true);
    } catch(Exception $e) {
      new Logger($e->getMessage(), Logger::LOGGER_ERROR);
    }
  }

  private function getSubdomFromRequest() {
    if(isset($_POST["new_subdom"]))
      return $this->processCreateSubdom($_POST["new_subdom"]);
    if(!strlen($_GET[get_class($this)])) return null;
    if(isset($_POST["activate"])) {
      return $this->processActivateSubdom($_GET[get_class($this)]);
    }
    if(!is_dir("../".$_GET[get_class($this)]) && !isset($_POST["deactivate"])) {
      return $this->processCreateSubdom($_GET[get_class($this)]);
    }
    $this->authorizeSubdom($_GET[get_class($this)]);
    if(isset($_POST["deactivate"]))
      return $this->processDeactivateSubdom($_GET[get_class($this)]);
    return $this->processUpdateSubdom($_GET[get_class($this)]);
  }

  private function authorizeSubdom($subdom) {
    if(count(scandir("../$subdom")) == 2 || is_file("../$subdom/USER_ID.".USER_ID)) return;
    throw new Exception(sprintf(_("Unauthorized subdom '%s' modification by '%s'"), $subdom, USER_ID));
  }

  private function processActivateSubdom($subdom) {
    $aDir = SUBDOM_ROOT_FOLDER."/".$subdom;
    $dDir = SUBDOM_ROOT_FOLDER."/.".$subdom;
    if(!is_dir($aDir)) {
      if(!is_dir($dDir) || !rename($dDir, $aDir))
        throw new Exception(sprintf(_("Unable to activate subdom '%s'"), $subdom));
    }
    $this->successMsg = sprintf(_("Subdom %s successfully activated"), $subdom);
    return $subdom;
  }

  private function getSubdomVar($varName, $subdom) {
    if(is_dir("../$subdom")) foreach(scandir("../$subdom") as $f) {
      if(strpos($f, "$varName.") !== 0) continue;
      return substr($f, strlen($varName)+1);
    }
    return null;
  }

  private function processDeactivateSubdom($subdom) {
    $activeDir = SUBDOM_ROOT_FOLDER."/$subdom";
    $inactiveDir = SUBDOM_ROOT_FOLDER."/.$subdom";
    if(is_dir($activeDir)) {
      if(is_dir($inactiveDir))
        throw new Exception(sprintf(_("Deactivate directory '%s' already exists"), ".$subdom"));
      if(!rename($activeDir, $inactiveDir))
        throw new Exception(sprintf(_("Unable to rename '%s' dir to '%s'"), $subom, ".$subdom"));
    } elseif(!is_dir($inactiveDir) && !mkdir($inactiveDir)) {
      throw new Exception(sprintf(_("Unable to create '%s' dir"), ".$subdom"));
    }
    $this->successMsg = sprintf(_("Subdom %s successfully deactivated"), $subdom);
    return $subdom;
  }

  private function processCreateSubdom($subdom) {
    if(!preg_match("/^".SUBDOM_PATTERN."$/", $subdom))
      throw new Exception(_("Invalid subdom format"));
    if(!isset($_POST["new_subdom_option"])) $action = "new";
    else $action = $_POST["new_subdom_option"];
    switch($action) {
      case "new":
      $this->prepareSubdomFolder($subdom);
      if(!mkdir("../$subdom"))
        throw new Exception(_("Unable to create new subdom '%s'"));
      break;
      case "copy":
      if(!isset($_POST["copy_subdom_dir"]) || !is_dir("../".$_POST["copy_subdom_dir"]))
        throw new Exception(_("Subdom copy parameter missing or invalid"));
      $this->authorizeSubdom($_POST["copy_subdom_dir"]);
      if(!(safeRemoveDir(SUBDOM_ROOT_FOLDER."/$subdom"))
        || !(safeRemoveDir(SUBDOM_ROOT_FOLDER."/".$_POST["copy_subdom_dir"])))
        throw new Exception(_("Unable to remove old subdom setup"));
      $this->prepareSubdomFolder($subdom);
      copyFiles("../".$_POST["copy_subdom_dir"], "../$subdom");
      break;
      case "rename":
      if(!isset($_POST["rename_subdom_dir"]) || !is_dir("../".$_POST["rename_subdom_dir"]))
        throw new Exception(_("Subdom rename parameter missing or invalid"));
      $this->authorizeSubdom($_POST["rename_subdom_dir"]);
      if(!(safeRemoveDir(SUBDOM_ROOT_FOLDER."/$subdom"))
        || !(safeRemoveDir(SUBDOM_ROOT_FOLDER."/".$_POST["rename_subdom_dir"])))
        throw new Exception(_("Unable to remove old subdom setup"));
      $this->prepareSubdomFolder($subdom);
      rename("../".$_POST["rename_subdom_dir"], "../$subdom");
      break;
      default:
      throw new Exception(_("Unrecognized new subdom request"));
    }
    $this->successMsg = sprintf(_("Subdom %s successfully created"), $subdom);
    return $subdom;
  }

  private function prepareSubdomFolder($subdom) {
    if(is_dir("../$subdom")) {
      if(!isset($_POST["force"]))
        throw new Exception(sprintf(_("Subdom '%s' already exists"), $subdom));
      $this->authorizeSubdom($subdom);
      if(!(safeRemoveDir("../$subdom")))
        throw new Exception(_("Unable to remove old subdom"));
    } else {
      if(!preg_match("/^".USER_ID."(".SUBDOM_PATTERN.")?$/", $subdom))
        throw new Exception(sprintf(_("Invalid user subdom format; must start with '%s'"), USER_ID));
    }
  }

  // process post (changes) into USER_ID/subdom
  private function processUpdateSubdom($subdom) {
    $subdomFolder = SUBDOM_ROOT_FOLDER."/$subdom";
    if(!is_dir($subdomFolder) && !mkdir($subdomFolder, 0775, true))
      throw new Exception(sprintf(_("Unable to create user subdom directory '%s'"), $subdom));
    if(!isset($_POST["PLUGINS"]) || !is_array($_POST["PLUGINS"]))
      throw new Exception(_("Missing POST data 'PLUGINS'"));
    $origHash = getDirHash($subdomFolder);
    foreach(scandir($subdomFolder) as $f) {
      if(strpos($f, ".") === 0) continue;
      $var = explode(".", $f, 2);
      switch($var[0]) {
        case "CMS_VER":
        case "FILES_DIR":
        $this->updateVarFile($subdomFolder, $f, $var[0]);
        break;
        case "USER_DIR":
        $this->updateVarFile($subdomFolder, $f, $var[0], isset($_POST["duplicate"]) ? USER_ROOT_FOLDER : null);
        break;
        case "PLUGIN":
        if(!in_array($var[1], $_POST["PLUGINS"]) && !unlink("$subdomFolder/$f"))
          throw new Exception(sprintf(_("Unable to disable plugin from subdom '%s'"), $subdom));
      }
    }
    foreach($_POST["PLUGINS"] as $p) {
      if(is_file("$subdomFolder/PLUGIN.$p") || touch("$subdomFolder/PLUGIN.$p", 0664)) continue;
      throw new Exception(sprintf(_("Unable to enable plugin in subdom '%s'"), $subdom));
    }
    if($origHash == getDirHash($subdomFolder))
      throw new Exception(sprintf(_("No changes made in '%s'"), $subdom));
    $this->successMsg = sprintf(_("Subdom %s successfully updated"), $subdom);
    return $subdom;
  }

  private function updateVarFile($subdomFolder, $f, $varName, $root = null) {
    if(!isset($_POST[$varName]))
      throw new Exception(sprintf(_("Missing POST data '%s'"), $varName));
    if(!is_null($root)) {
      $bakDir = duplicateDir("$root/".$_POST[$varName]);
      $newDir = incrementalRename($bakDir, "$root/".$_POST[$varName]);
      $newFile = "$varName.".basename($newDir);
    } else {
      $newFile = "$varName.".$_POST[$varName];
    }
    if(rename("$subdomFolder/$f", "$subdomFolder/$newFile")) return;
    throw new Exception(sprintf(_("Unable to update '%s'"), $varName));
  }

  public function getContent(HTMLPlus $content) {
    Cms::getOutputStrategy()->addCssFile($this->getDir()."/SubdomManager.css");
    $qDeacitvate = sprintf(_("Deactivate subdom %s?"), '@subdom');
    $qRename = _("Renaming will make current subdom unaccessible"); #todo: sprintf subdom
    Cms::getOutputStrategy()->addJs("
    var forms = document.getElementsByTagName('form');
    for(var i=0; i<forms.length; i++) {
      if(typeof forms[i]['deactivate'] !== 'object') continue;
      forms[i]['deactivate'].addEventListener('click', function(e){
        var subdom = e.target.form['action'].split('=')[1];
        var question = '$qDeacitvate';
        if(confirm(question.replace('@subdom', subdom))) return;
        e.preventDefault();
      }, false);
    }");

    Cms::getOutputStrategy()->addJs("
      var form = document.getElementById('new_subdom_form');
      form['copy_subdom_dir'].addEventListener('change', selectRadio, false);
      form['copy_subdom_dir'].addEventListener('click', selectRadio, false);
      form['rename_subdom_dir'].addEventListener('change', selectRadio, false);
      form['rename_subdom_dir'].addEventListener('click', selectRadio, false);
      function selectRadio(event) {
        target = event.target.previousSibling;
        while(target != null) {
          if(target.nodeName.toLowerCase() == 'input' && target.type == 'radio') {
            target.checked = true;
            return true;
          }
          target = target.previousSibling;
        }
      }
      form.addEventListener('submit', function(e) {
        if(!form['new_subdom_option'][2].checked) return;
        if(confirm('$qRename')) return;
        e.preventDefault();
      }, false);
    ");

    $newContent = $this->getHTMLPlus();
    $vars["domain"] = getDomain();
    $vars["cbr"] = CMS_BEST_RELEASE;
    $vars["curlink"] = getCurLink()."?".get_class($this);
    if(isset($_POST['force'])) $vars["force"] = "checked";

    if(isset($_POST['new_subdom_option'])) $vars[$_POST['new_subdom_option']] = "checked";
    else $vars["new"] = "checked";

    if(isset($_POST["new_subdom"])) {
      $vars["new_nohide"] = "nohide";
      $vars["new_subdom"] = $_POST["new_subdom"];
    }
    else $vars["new_subdom"] = Cms::getVariable("cms-user_id");

    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->userSubdoms as $subdom => $null) {
      $o = $d->createElement("option", $subdom);
      if(isset($_POST["copy_subdom_dir"]) && $_POST["copy_subdom_dir"] == $subdom)
        $o->setAttribute("selected", "selected");
      #$o->setAttribute("value", $subdom);
      $set->appendChild($o);
    }
    $vars["copySubdomDirs"] = $d->documentElement;

    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->userSubdoms as $subdom => $null) {
      $o = $d->createElement("option", $subdom);
      if(isset($_POST["rename_subdom_dir"]) && $_POST["rename_subdom_dir"] == $subdom)
        $o->setAttribute("selected", "selected");
      #$o->setAttribute("value", $subdom);
      $set->appendChild($o);
    }
    $vars["renameSubdomDirs"] = $d->documentElement;


    $form = $newContent->getElementsByTagName("form")->item(1);
    foreach($this->userSubdoms as $subdom => $null) {
      $doc = new DOMDocumentPlus();
      $doc->appendChild($doc->importNode($form, true));
      $doc->processVariables($this->createVars($subdom));
      $form->parentNode->insertBefore($newContent->importNode($doc->documentElement, true), $form);
    }

    $form->parentNode->removeChild($form);
    $newContent->processVariables($vars);
    return $newContent;
  }

  private function createVars($subdom) {
    $vars = array();
    if(strpos($subdom, ".") === 0) {
      $vars["inactive"] = "";
      $subdom = substr($subdom, 1);
    } else $vars["active"] = "";
    $vars["linkName"] = "<strong>$subdom</strong>.".getDomain();
    $vars["cmsVerId"] = "$subdom-CMS_VER";
    $vars["userDirId"] = "$subdom-USER_DIR";
    $vars["filesDirId"] = "$subdom-FILES_DIR";
    $vars["curlinkSubdom"] = getCurLink()."?".get_class($this)."=$subdom";

    $showSubdom = basename(dirname($_SERVER["PHP_SELF"]));
    if(strlen($_GET[get_class($this)])) $showSubdom = $_GET[get_class($this)];
    if($subdom == $showSubdom) $vars["nohide"] = "nohide";

    // versions
    $current = $this->getSubdomVar("CMS_VER", $subdom);
    $changes = true;
    if(!is_null($current)) {
      if($subdom == CURRENT_SUBDOM_DIR) $vars["linkHref"] = "/";
      else $vars["linkHref"] = "http://$subdom.".getDomain();
      $vars["version"] = "$current/".$this->cmsVersions[$current];
      $vars["CMS_VER"] = "CMS_VER.$current";
    } else {
      $changes = false;
    }
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    $unsupported = false;
    foreach($this->cmsVariants as $variant => $active) {
      if(strtok($this->cmsVersions[$variant], ".") != strtok(CMS_VERSION, ".")) continue;
      if(!$active) {
        if($current == $variant) $unsupported = true;
        continue; // silently skip disabled versions
      }
      $o = $d->createElement("option", "$variant/".$this->cmsVersions[$variant]);
      if($variant == $current) $o->setAttribute("selected", "selected");
      $o->setAttribute("value", $variant);
      $set->appendChild($o);
    }
    if(is_null($current) || strtok($this->cmsVersions[$current], ".") != strtok(CMS_VERSION, ".")) {
      $changes = false;
      $unsupported = true;
    }
    if(!$unsupported) $vars["unsupported"] = "";
    if($set->childNodes->length) $vars["cmsVers"] = $set;
    if(!$changes) {
      $vars["changesAvailable"] = "";
      return $vars;
    }
    $vars["hideable"] = "hideable";

    // user directories
    $current = $this->getSubdomVar("USER_DIR", $subdom);
    if(!is_null($current)) $vars["USER_DIR"] = "USER_DIR.$current";
    else $current = $subdom;
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->userDirs as $dir => $active) {
      if(!$active) continue; // silently skip dirs to del and mirrors
      $o = $set->appendChild($d->createElement("option", $dir));
      if($dir == $current) $o->setAttribute("selected", "selected");
    }
    $vars["userDirs"] = $set;
    if($subdom == $showSubdom && isset($_POST["duplicate"]))
      $vars["checked"] = "checked";

    // files directories
    $current = $this->getSubdomVar("FILES_DIR", $subdom);
    if(!is_null($current)) $vars["FILES_DIR"] = "FILES_DIR.$current";
    else $current = $subdom;
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->filesDirs as $dir => $active) {
      if(!$active) continue; // silently skip disabled dirs
      $o = $set->appendChild($d->createElement("option", $dir));
      if($dir == $current) $o->setAttribute("selected", "selected");
    }
    $vars["filesDirs"] = $set;

    // plugins
    $current = $this->getSubdomVar("CMS_VER", $subdom);
    if(!is_null($current)) {
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
      $vars["plugins"] = $set;
    }

    return $vars;
  }

  private function getSubdirs($dir, $filter = null) {
    $subdirs = array();
    foreach(scandir($dir) as $f) {
      if(is_null($filter) && strpos($f, ".") === 0) continue;
      if(!is_null($filter) && !preg_match($filter, $f)) continue;
      if(!is_dir("$dir/$f")) continue;
      $subdirs[$f] = !file_exists("$dir/.$f") && strpos($f, ".") !== 0;
    }
    return $subdirs;
  }

}

?>
