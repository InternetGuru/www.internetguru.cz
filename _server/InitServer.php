<?php

class InitServer {
  private $subdom;
  private $subdomVars;
  private $folderVars;
  const FORBIDDEN_PLUGINS = array("Slider" => null);
  const DISABLED_PLUGINS = array("Slider" => null);

  public function __construct($subdom, $setConst = false, $update = false) {
    if(!preg_match("/^" . SUBDOM_PATTERN . "$/", $subdom))
      throw new Exception("Invalid subdom format '$subdom'");
    $this->subdom = $subdom;
    $this->subdomVars = array(
      #fixme: due to "admin" username
      #"USER_ID" => isset($_SERVER["REMOTE_USER"]) ? $_SERVER["REMOTE_USER"] : "ig1",
      "USER_ID" => "ig1",
      "CMS_VER" => CMS_RELEASE,
      "CONFIG" => "user",
      "USER_DIR" => $subdom,
      "ADMIN_DIR" => $subdom,
      "FILES_DIR" => $subdom,
    );
    $serverSubdomDir = "../$subdom";
    $this->updateSubdomVars($serverSubdomDir);
    $userSubdomDir = "../../" . $this->subdomVars["USER_ID"] . "/subdom/$subdom";
    if($update) {
      $this->authorizeUpdate($serverSubdomDir);
      $userDelDir = dirname($userSubdomDir)."/.$subdom";
      if(is_dir($userDelDir)) {
        $this->safeDeleteSubdom($userDelDir, $serverSubdomDir);
        return;
      }
      if(!is_dir($serverSubdomDir)) $this->createSubdom($serverSubdomDir);
      if(count(scandir($serverSubdomDir)) == 2) $this->acquireSubdom($serverSubdomDir);
    }
    // create CMS_VER file from index or newest stable available version
    if(!file_exists("$serverSubdomDir/CMS_VER.".$this->subdomVars["CMS_VER"])) {
      if(is_link("$serverSubdomDir/index.php"))
        $this->subdomVars["CMS_VER"] = basename(dirname(readlink("$serverSubdomDir/index.php")));
      else $this->subdomVars["CMS_VER"] = $this->getNewestStableVersion();
    }
    $root = "../..";
    $this->folderVars = array(
      'ADMIN_FOLDER' => "$root/".ADMIN_ROOT_DIR."/".$this->subdomVars["ADMIN_DIR"],
      'USER_ROOT_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".USER_ROOT_DIR,
      'FILES_ROOT_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".FILES_ROOT_DIR,
      'SUBDOM_ROOT_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".SUBDOM_ROOT_DIR,
      'TEMP_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".TEMP_DIR,
    );
    $this->folderVars['USER_FOLDER'] = $this->folderVars["USER_ROOT_FOLDER"]."/".$this->subdomVars["USER_DIR"];
    $this->folderVars['FILES_FOLDER'] = $this->folderVars["FILES_ROOT_FOLDER"]."/".$this->subdomVars["FILES_DIR"];
    $this->folderVars['SUBDOM_FOLDER'] = $this->folderVars["SUBDOM_ROOT_FOLDER"]."/".$this->subdom;
    $this->folderVars['RES_FOLDER'] = "$serverSubdomDir/".RES_DIR;
    $this->completeStructure($serverSubdomDir);
    if($setConst) $this->setConst();
    if(!$update && count(scandir($userSubdomDir)) == 2)
      $this->syncServerToUser($serverSubdomDir, $userSubdomDir);
    if(!$update) return;
    // check rights to modify files
    if(!is_file("$serverSubdomDir/CONFIG.user"))
      throw new Exception("User subdom setup disabled.");
    if(count(scandir($userSubdomDir)) == 2) {
      $this->resetServerSubdom($serverSubdomDir);
      $this->syncServerToUser($serverSubdomDir, $userSubdomDir);
    } else {
      $this->updateServerFromUser($userSubdomDir, $serverSubdomDir);
    }
  }

  private function setConst() {
    define("USER_ID", $this->subdomVars["USER_ID"]);
    foreach($this->folderVars as $fName => $fPath) define($fName, $fPath);
  }

  private function updateSubdomVars($dir) {
    if(!is_dir($dir)) return;
    foreach(scandir($dir) as $f) {
      $var = explode(".", $f, 2);
      if(!array_key_exists($var[0], $this->subdomVars)) continue;
      $this->subdomVars[$var[0]] = $var[1];
    }
  }

  private function authorizeUpdate($dir) {
    if(!is_dir($dir)) return;
    if(count(scandir($dir)) == 2) return;
    if(is_file("$dir/USER_ID.". $this->subdomVars["USER_ID"])) return;
    throw new Exception("Unauthorized subdom modification '{$this->subdom}' - USER_ID mismatch");
  }

  private function safeDeleteSubdom($userDotSubdom, $destDir) {
    // safely remove/rename user .subdom folder
    if(count(scandir($userDotSubdom)) == 2) {
      if(!rmdir($userDotSubdom))
        throw new Exception("Unable to remove '.{$this->subdom}' folder");
    } else {
      $newSubdom = "~{$this->subdom}";
      while(file_exists("$userDotSubdom/../$newSubdom")) $newSubdom = "~$newSubdom";
      if(!rename($userDotSubdom, "$userDotSubdom/../$newSubdom"))
        throw new Exception("Unable to rename '.{$this->subdom}' folder");
    }
    // disable server subdom
    if(!is_dir($destDir)) return;
    $newSubdom = "~{$this->subdom}";
    while(file_exists("../$newSubdom")) $newSubdom = "~$newSubdom";
    if(!rename($destDir,"../$newSubdom"))
      throw new Exception("Unable to remove subdom '{$this->subdom}'");
  }

  private function createSubdom($dir) {
    if(!preg_match("/^".$this->subdomVars["USER_ID"].SUBDOM_PATTERN."$/", $this->subdom))
      throw new Exception("New subdom must start with USER_ID '".$this->subdomVars["USER_ID"]."'");
    if(!@mkdir($dir, 0755, true))
      throw new Exception("Unable to create folder '$dir'");
  }

  private function acquireSubdom($dir) {
    if(!touch("$dir/USER_ID.". $this->subdomVars["USER_ID"]))
      throw new Exception("Unable to create user id file");
  }

  private function getNewestStableVersion() {
    $nsv = null;
    foreach(scandir(CMS_ROOT_FOLDER) as $v) {
      if(!preg_match("/^\d+\.\d+$/",$v)) continue;
      if(version_compare($nsv, $v) < 0) $nsv = $v;
    }
    if(is_null($nsv)) throw new Exception("No stable IGCMS variant found");
    return $nsv;
  }

  private function completeStructure($subdomDir) {
    // create symlinks
    $rob = preg_match("/^ig\d/", $this->subdom) ? "robots_off.txt" : "robots_default.txt";
    $files = array(
      "$subdomDir/".CMSRES_ROOT_DIR => CMSRES_ROOT_FOLDER,
      "$subdomDir/index.php" => CMS_ROOT_FOLDER."/".$this->subdomVars["CMS_VER"]."/index.php",
      "$subdomDir/.htaccess" => CMS_ROOT_FOLDER."/".$this->subdomVars["CMS_VER"]."/.htaccess",
      "$subdomDir/robots.txt" => CMS_ROOT_FOLDER."/".$this->subdomVars["CMS_VER"]."/$rob",
    );
    foreach($files as $link => $target) $this->createSymlink($link, $target);
    // init default plugin files
    if(!is_file("$subdomDir/CONFIG.". $this->subdomVars["CONFIG"])) {
      $this->createDefaultPlugins(CMS_ROOT_FOLDER."/{$this->subdomVars["CMS_VER"]}/".PLUGINS_DIR, $subdomDir);
    }
    // create missing data files
    foreach($this->subdomVars as $name => $val) {
      $f = "$subdomDir/$name.$val";
      if(!file_exists($f)) touch($f);
    }
    // create required directories
    foreach($this->folderVars as $dirPath) {
      if(is_dir($dirPath) || @mkdir($dirPath,0755,true)) continue;
      throw new Exception("Unable to create folder '$dirPath'");
    }
  }

  private function createSymlink($link, $target) {
    #$info["toVersion"] = $this->subdomVars["CMS_VER"];
    #$info["isLink"] = is_link($link);
    #$info["readlink"] = readlink($link);
    if(file_exists($link) && readlink($link) == $target) return;
    if(!symlink($target, "$link~") || !rename("$link~", $link))
      throw new Exception("Unable to create symlink '$link'");
    clearstatcache(true, $link);
    #$info["afterClear"] = readlink($link);
    #print_r($info);
    if(readlink($link) == $target) return;
    throw new Exception("Unable to verify symlink (cache).");
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
        if(!rename("$dir/$f", "$dir/{$var[0]}.". $this->subdomVars[$var[0]]))
          throw new Exception("Unable to reset server subdom setup");
        break;
        case "PLUGIN":
        unlink("$dir/$f"); // delete all plugins (create default below)
      }
    }
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
          throw new Exception("CMS variant '{$var[1]}' not available");
        case "USER_DIR":
        case "FILES_DIR":
        $this->subdomVars[$var[0]] = $var[1];
        break;
        case "PLUGIN":
        if(!is_dir(CMS_ROOT_FOLDER."/{$this->subdomVars["CMS_VER"]}/".PLUGINS_DIR."/{$var[1]}"))
          throw new Exception("Plugin '{$var[1]}' is not available");
        if(is_file("$destDir/.$f"))
          throw new Exception("Plugin '{$var[1]}' is forbidden");
        if(!is_file("$destDir/$f") && !touch("$destDir/$f"))
          throw new Exception("Unable to enable plugin '{$var[1]}'");
        break;
        case "robots":
        if($f != "robots.txt") continue;
        if(preg_match("/^ig\d/", $this->subdom))
          throw new Exception("Cannot modify '$f' in subdom '{$this->subdom}'");
        if(!copy("$srcDir/$f", "$destDir/$f"))
          throw new Exception("Unable to copy '$f' into subdom '{$this->subdom}'");
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
        $newFile = "{$var[0]}.". $this->subdomVars[$var[0]];
        if(!is_file("$destDir/$newFile") && !touch("$destDir/$newFile"))
          throw new Exception("Unable to update user subdom setup");
        if($this->subdomVars[$var[0]] == $var[1]) continue;
        if(!rename("$destDir/$f", "$destDir/$newFile"))
          throw new Exception("Unable to setup '$newFile'");
        break;
        case "PLUGIN":
        if(!is_file("$srcDir/$f") && !unlink("$destDir/$f"))
          throw new Exception("Unable to disable plugin '{$var[1]}'");
        break;
      }
    }
  }

  private function syncServerToUser($srcDir, $destDir) {
    foreach(scandir($srcDir) as $f) {
      if(!is_file("$srcDir/$f") || strpos($f, ".") === 0) continue;
      $var = explode(".", $f, 2);
      switch($var[0]) {
        case "CMS_VER":
        case "USER_DIR":
        case "FILES_DIR":
        case "PLUGIN":
        touch("$destDir/$f"); // no exception if fail
        break;
        case "robots":
        if($f != "robots.txt" || is_link("$srcDir/$f")) continue;
        copy("$srcDir/$f", "$destDir/$f"); // no exception if fail
      }
    }
  }

  private function createDefaultPlugins($srcDir, $destDir) {
    foreach(self::FORBIDDEN_PLUGINS as $p => $null) {
      if(!is_file("$destDir/.PLUGIN.$p") && !touch("$destDir/.PLUGIN.$p"))
        throw new Exception("Unable to create forbidden plugin files");
    }
    foreach(scandir($srcDir) as $f) {
      if(strpos($f,".") === 0) continue; // skip folders starting with a dot
      if(array_key_exists($f, self::DISABLED_PLUGINS)) continue;
      if(!touch("$destDir/PLUGIN.$f"))
        throw new Exception("Unable to create plugin files");
    }
  }

}

?>
