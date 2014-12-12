<?php

#TODO: symlink cache!??

class InitServer {
  private $subdom;
  private $subdomVars;
  private $forbiddenPlugins = array("Slider" => null);
  private $disabledPlugins = array("Slider" => null);

  public function __construct($subdom, $setConst = false, $update = false) {
    if(!preg_match("/^".SUBDOM_PATTERN."$/", $subdom))
      throw new Exception(sprintf(_("Invalid subdom format '%s'"), $subdom));
    $this->subdom = $subdom;
    $this->subdomVars = array(
      "USER_ID" => defined("USER_ID") ? USER_ID : "ig1",
      "CMS_VER" => CMS_RELEASE,
      "CONFIG" => "user",
      "USER_DIR" => $subdom,
      "ADMIN_DIR" => $subdom,
      "FILES_DIR" => $subdom,
    );
    $serverSubdomDir = "../$subdom";
    $serverSubdomDelDir = "../.$subdom";
    $this->updateSubdomVars($serverSubdomDir);
    $userSubdomDir = "../../".$this->subdomVars["USER_ID"]."/subdom/$subdom";
    $userSubdomDelDir = "../../".$this->subdomVars["USER_ID"]."/subdom/.$subdom";
    if(!is_dir($serverSubdomDir)) {
      if(!$update) throw new Exception(sprintf(_("Subdom '%s' does not exist; try forcing updateSubdom"), $subdom));
      if(is_dir($serverSubdomDelDir)) {
        $this->activateSubdom($serverSubdomDelDir, $serverSubdomDir);
        return;
      } else {
        $this->createSubdom($serverSubdomDir);
      }
    }
    if(count(scandir($serverSubdomDir)) == 2) {
      if(!$update) throw new Exception(sprintf(_("Subdom '%s' is not available; try forcing updateSubdom"), $subdom));
      $this->acquireSubdom($serverSubdomDir);
      $this->resetServerSubdom($serverSubdomDir);
    }
    if($update) $this->verifySubdomOwner($serverSubdomDir);
    if($update && is_dir($userSubdomDelDir)) {
      $this->safelyRemoveDir($userSubdomDelDir);
      if(!is_dir($serverSubdomDir)) return;
      $this->safelyRemoveDir($serverSubdomDir, $serverSubdomDelDir);
      return;
    }
    // create CMS_VER file from index or newest stable available version
    if(!file_exists("$serverSubdomDir/CMS_VER.".$this->subdomVars["CMS_VER"])) {
      if(!is_null(CMS_BEST_RELEASE)) $this->subdomVars["CMS_VER"] = CMS_BEST_RELEASE;
      else $this->subdomVars["CMS_VER"] = CMS_RELEASE;
    }
    $folders = $this->setFolderVars($serverSubdomDir);
    if($update && !is_file("$serverSubdomDir/CONFIG.user"))
      throw new Exception(_("User subdom setup disabled; file CONFIG.user not found"));
    if(count(scandir($userSubdomDir)) == 2) {
      #if($update) $this->resetServerSubdom($serverSubdomDir);
      $this->syncServerToUser($serverSubdomDir, $userSubdomDir);
    } elseif($update) {
      $this->updateServerFromUser($userSubdomDir, $serverSubdomDir);
      $folders = $this->setFolderVars($serverSubdomDir);
    }
    $this->completeStructure($serverSubdomDir, $folders["USER_FOLDER"]);
    if($setConst) $this->setConst($folders);
  }

  private function activateSubdom($src, $dest) {
    $this->verifySubdomOwner($src);
    if(rename($src, $dest)) return;
    throw new Exception(sprintf(_("Unable to activate subdom '%s'"), $this->subdom));
  }

  private function verifySubdomOwner($dir) {
    if(is_file("$dir/USER_ID.".$this->subdomVars["USER_ID"])) return;
    throw new Exception(sprintf(_("Unauthorized subdom '%s' modification - user names mismatch"), $this->subdom));
  }

  private function setFolderVars($serverSubdomDir) {
    $root = realpath("../..");
    $folders = array(
      'ADMIN_ROOT_FOLDER' => "$root/".ADMIN_ROOT_DIR,
      'USER_ROOT_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".USER_ROOT_DIR,
      'FILES_ROOT_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".FILES_ROOT_DIR,
      'SUBDOM_ROOT_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".SUBDOM_ROOT_DIR,
      'TEMP_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".TEMP_DIR,
    );
    $folders['ADMIN_FOLDER'] = $folders["ADMIN_ROOT_FOLDER"]."/".$this->subdomVars["ADMIN_DIR"];
    $folders['USER_FOLDER'] = $folders["USER_ROOT_FOLDER"]."/".$this->subdomVars["USER_DIR"];
    $folders['FILES_FOLDER'] = $folders["FILES_ROOT_FOLDER"]."/".$this->subdomVars["FILES_DIR"];
    $folders['SUBDOM_FOLDER'] = $folders["SUBDOM_ROOT_FOLDER"]."/".$this->subdom;
    $folders['RES_FOLDER'] = "$serverSubdomDir/".RES_DIR;
    // create required directories
    foreach($folders as $f => $dirPath) {
      if(!is_dir($dirPath) && !@mkdirGroup($dirPath, 0775, true))
        throw new Exception(sprintf(_("Unable to create folder '%s'"), $f));
      if(chgrp($dirPath, filegroup("$dirPath/.."))) continue;
        throw new Exception(sprintf(_("Unable to set permissions to '%s'"), $f));
    }
    return $folders;
  }

  private function setConst(Array $folders) {
    define("USER_ID", $this->subdomVars["USER_ID"]);
    foreach($folders as $fName => $fPath) define($fName, $fPath);
  }

  private function updateSubdomVars($dir) {
    if(!is_dir($dir)) return;
    foreach(scandir($dir) as $f) {
      $var = explode(".", $f, 2);
      if(!array_key_exists($var[0], $this->subdomVars)) continue;
      $this->subdomVars[$var[0]] = $var[1];
    }
  }

  private function safelyRemoveDir($src, $dest=null) {
    if(count(scandir($src)) == 2) {
      if(rmdir($src)) return;
      throw new Exception(sprintf(_("Unable to remove folder '%s'"), ".".$this->subdom));
    }
    if(is_null($dest)) return;
    if(!is_dir($dest) && rename($src, $dest)) return;
    throw new Exception(sprintf(_("Unable to deactivate subdom '%s'"), $this->subdom));
  }

  private function createSubdom($dir) {
    if(!preg_match("/^".$this->subdomVars["USER_ID"].SUBDOM_PATTERN."$/", $this->subdom))
      throw new Exception(sprintf(_("New subdom must start with user id '%s'"), $this->subdomVars["USER_ID"]));
    if(!@mkdir($dir, 0755, true))
      throw new Exception(sprintf(_("Unable to create folder '%s'"), $dir));
  }

  private function acquireSubdom($dir) {
    if(!touch("$dir/USER_ID.".$this->subdomVars["USER_ID"]))
      throw new Exception(_("Unable to create USER_ID file"));
    if(!touch("$dir/CONFIG.user"))
      throw new Exception(_("Unable to create CONFIG file"));
  }

  private function completeStructure($subdomDir, $userFolder) {
    // create symlinks
    if(preg_match("/^ig\d/", $this->subdom))
      $rob = CMS_ROOT_FOLDER."/".$this->subdomVars["CMS_VER"] ."/robots_off.txt";
    else {
      if(is_file("$userFolder/robots.txt"))
        $rob = "$userFolder/robots.txt";
      $rob = CMS_ROOT_FOLDER."/".$this->subdomVars["CMS_VER"] ."/robots_default.txt";
    }
    $files = array(
      "$subdomDir/robots.txt" => $rob,
      "$subdomDir/index.php" => CMS_ROOT_FOLDER."/".$this->subdomVars["CMS_VER"]."/index.php",
      "$subdomDir/.htaccess" => CMS_ROOT_FOLDER."/".$this->subdomVars["CMS_VER"]."/.htaccess",
    );
    foreach($files as $dest => $source) {
      if(is_link($dest) || !is_file($dest)
      || getFileHash($source) != getFileHash($dest))
      safeRewriteFile($source, $dest, false);
    }
    $files = array(
      "$subdomDir/".CMSRES_ROOT_DIR => CMSRES_ROOT_FOLDER
    );
    foreach($files as $link => $target) {
      if(is_file($link) && !is_link($link)) continue; // skip individual files
      createSymlink($link, $target);
    }
    // init default plugin files
    if(!is_file("$subdomDir/CONFIG.".$this->subdomVars["CONFIG"])) {
      $this->createDefaultPlugins(CMS_ROOT_FOLDER."/{$this->subdomVars["CMS_VER"]}/".PLUGINS_DIR, $subdomDir);
    }
    // create missing data files
    foreach($this->subdomVars as $name => $val) {
      $f = "$subdomDir/$name.$val";
      if(!file_exists($f)) touch($f);
    }
  }

  private function resetServerSubdom($dir) {
    $this->subdomVars["USER_DIR"] = $this->subdom;
    $this->subdomVars["FILES_DIR"] = $this->subdom;
    foreach(scandir($dir) as $f) {
      if(!is_file("$dir/$f") || strpos($f, ".") === 0) continue;
      $var = explode(".", $f, 2);
      switch($var[0]) {
        case "USER_DIR":
        case "FILES_DIR":
        if(!rename("$dir/$f", "$dir/{$var[0]}.".$this->subdomVars[$var[0]]))
          throw new Exception(_("Unable to reset server subdom setup"));
        break;
        case "PLUGIN":
        unlink("$dir/$f"); // delete all plugins (create default below)
      }
    }
    if(!touch("$dir/CMS_VER.".$this->subdomVars["CMS_VER"])
      || !touch("$dir/USER_DIR.".$this->subdomVars["USER_DIR"])
      || !touch("$dir/FILES_DIR.".$this->subdomVars["FILES_DIR"]))
      throw new Exception(_("Unable to create configuration file(s)"));
    $this->createDefaultPlugins(CMS_ROOT_FOLDER."/{$this->subdomVars["CMS_VER"]}/".PLUGINS_DIR, $dir);
  }

  private function updateServerFromUser($srcDir, $destDir) {
    // read from user subdom
    foreach(scandir($srcDir, SCANDIR_SORT_ASCENDING) as $f) {
      if(!is_file("$srcDir/$f") || strpos($f, ".") === 0) continue;
      $var = explode(".", $f, 2);
      switch($var[0]) {
        case "CMS_VER":
        if(!is_dir(CMS_ROOT_FOLDER."/{$var[1]}") || is_dir(CMS_ROOT_FOLDER."/.{$var[1]}"))
          throw new Exception(sprintf(_("CMS variant '%s' is not available"), $var[1]));
        case "USER_DIR":
        case "FILES_DIR":
        $this->subdomVars[$var[0]] = $var[1];
        break;
        case "PLUGIN":
        if(!is_dir(CMS_ROOT_FOLDER."/{$this->subdomVars["CMS_VER"]}/".PLUGINS_DIR."/{$var[1]}"))
          throw new Exception(sprintf(_("Plugin '%s' is not available"), $var[1]));
        if(is_file("$destDir/.$f"))
          throw new Exception(sprintf(_("Plugin '%s' is forbidden"), $var[1]));
        if(!is_file("$destDir/$f") && !touch("$destDir/$f"))
          throw new Exception(sprintf(_("Unable to enable plugin '%s'"), $var[1]));
        break;
        case "robots":
        if($f != "robots.txt") continue;
        if(preg_match("/^ig\d/", $this->subdom))
          throw new Exception(sprintf(_("User cannot modify '%s' in user subdoms"), $f));
        if(!copy("$srcDir/$f", "$destDir/$f"))
          throw new Exception(sprintf(_("Unable to copy '%s' into subdom '%s'"), $f, $this->subdom));
        break;
      }
    }
    // apply "touches" to server subdom
    foreach(scandir($destDir) as $f) {
      if(strpos($f, ".") === 0) continue;
      $var = explode(".", $f, 2);
      switch($var[0]) {
        case "CMS_VER":
        case "USER_DIR":
        case "FILES_DIR":
        $newFile = "{$var[0]}.".$this->subdomVars[$var[0]];
        if(!is_file("$destDir/$newFile") && !touch("$destDir/$newFile"))
          throw new Exception(_("Unable to update user subdom setup"));
        if($this->subdomVars[$var[0]] == $var[1]) continue;
        if(!rename("$destDir/$f", "$destDir/$newFile"))
          throw new Exception(sprintf(_("Unable to setup '%s'"), $var[0]));
        break;
        case "PLUGIN":
        if(!is_file("$srcDir/$f") && !unlink("$destDir/$f"))
          throw new Exception(sprintf(_("Unable to disable plugin '%s'"), $var[1]));
        break;
      }
    }
  }

  private function syncServerToUser($srcDir, $destDir) {
    foreach(scandir($srcDir) as $f) {
      if(!is_file("$srcDir/$f") || strpos($f, ".") === 0) continue;
      $var = explode(".", $f, 2);
      $fGroup = filegroup("$destDir/../..");
      $ch = false;
      switch($var[0]) {
        case "CMS_VER":
        case "USER_DIR":
        case "FILES_DIR":
        case "PLUGIN":
        if(touch("$destDir/$f")) $ch = true;
        break;
        #case "robots":
        #if($f != "robots.txt" || is_link("$srcDir/$f")) continue;
        #if(copy("$srcDir/$f", "$destDir/$f")) $ch = true;
      }
      if(!$ch) continue;
      if(chgrp("$destDir/$f", $fGroup) && chmodGroup("$destDir/$f", 0664)) continue;
      throw new Exception(sprintf(_("Unable to set permissions to '%s'"), $f));
    }
  }

  private function createDefaultPlugins($srcDir, $destDir) {
    foreach($this->forbiddenPlugins as $p => $null) {
      if(!is_file("$destDir/.PLUGIN.$p") && !touch("$destDir/.PLUGIN.$p"))
        throw new Exception(_("Unable to create forbidden plugin files"));
    }
    foreach(scandir($srcDir) as $f) {
      if(strpos($f, ".") === 0) continue; // skip folders starting with a dot
      if(array_key_exists($f, $this->disabledPlugins)) continue;
      if(!touch("$destDir/PLUGIN.$f"))
        throw new Exception(_("Unable to create plugin files"));
    }
  }

}

?>
