<?php

#TODO: symlink cache!??

class InitServer {
  private $subdom;
  private $subdomVars;
  private $apacheGraceful = false;
  const FORBIDDEN_PLUGINS = array("Slider" => null);
  const DISABLED_PLUGINS = array("Slider" => null);
  const APACHE_RESTART_FILE = "APACHE_RESTART";

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
    if(!is_dir($serverSubdomDir)) {
      if(!$updte) throw new Exception("Subdom '$subdom' does not exist (update?)");
      $this->createSubdom($serverSubdomDir);
    }
    if(count(scandir($serverSubdomDir)) == 2) {
      if(!$updte) throw new Exception("Subdom '$subdom' is not available (update?)");
      $this->acquireSubdom($serverSubdomDir);
    }
    if($update && !is_file("$serverSubdomDir/USER_ID.". $this->subdomVars["USER_ID"]))
      throw new Exception("Unauthorized subdom modification '{$this->subdom}' - USER_ID mismatch");
    $userDelDir = dirname($userSubdomDir)."/.$subdom";
    if($update && is_dir($userDelDir)) {
      $this->safeDeleteSubdom($userDelDir, $serverSubdomDir);
      return;
    }
    // create CMS_VER file from index or newest stable available version
    if(!file_exists("$serverSubdomDir/CMS_VER.".$this->subdomVars["CMS_VER"])) {
      if(!$update && is_link("$serverSubdomDir/index.php"))
        $this->subdomVars["CMS_VER"] = basename(dirname(readlink("$serverSubdomDir/index.php")));
      else $this->subdomVars["CMS_VER"] = $this->getNewestStableVersion();
    }
    $folders = $this->setFolderVars($serverSubdomDir);
    if($update && !is_file("$serverSubdomDir/CONFIG.user"))
      throw new Exception("User subdom setup disabled (missing $serverSubdomDir/CONFIG.user)");
    if(count(scandir($userSubdomDir)) == 2) {
      if($update) $this->userResetServerSubdom($serverSubdomDir);
      $this->syncServerToUser($serverSubdomDir, $userSubdomDir);
    } elseif($update) {
      $this->updateServerFromUser($userSubdomDir, $serverSubdomDir);
      $folders = $this->setFolderVars($serverSubdomDir);
    }
    $this->completeStructure($serverSubdomDir);
    if($setConst) $this->setConst($folders);
  }

  private function setFolderVars($serverSubdomDir) {
    $root = realpath("../..");
    $folders = array(
      'ADMIN_FOLDER' => "$root/".ADMIN_ROOT_DIR."/".$this->subdomVars["ADMIN_DIR"],
      'USER_ROOT_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".USER_ROOT_DIR,
      'FILES_ROOT_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".FILES_ROOT_DIR,
      'SUBDOM_ROOT_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".SUBDOM_ROOT_DIR,
      'TEMP_FOLDER' => "$root/".$this->subdomVars["USER_ID"]."/".TEMP_DIR,
    );
    $folders['USER_FOLDER'] = $folders["USER_ROOT_FOLDER"]."/".$this->subdomVars["USER_DIR"];
    $folders['FILES_FOLDER'] = $folders["FILES_ROOT_FOLDER"]."/".$this->subdomVars["FILES_DIR"];
    $folders['SUBDOM_FOLDER'] = $folders["SUBDOM_ROOT_FOLDER"]."/".$this->subdom;
    $folders['RES_FOLDER'] = "$serverSubdomDir/".RES_DIR;
    // create required directories
    foreach($folders as $dirPath) {
      if(is_dir($dirPath) || @mkdir($dirPath,0755,true)) continue;
      throw new Exception("Unable to create folder '$dirPath'");
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

  private function safeDeleteSubdom($userDotSubdom, $destDir) {
    // safely remove/rename user .subdom folder
    $this->safelyRemoveDir($userDotSubdom);
    // disable server subdom
    if(!is_dir($destDir)) return;
    $this->safelyRemoveDir($destDir);
  }

  private function safelyRemoveDir($dir) {
    if(count(scandir($dir)) == 2) {
      if(rmdir($dir)) return;
      throw new Exception("Unable to remove '.{$this->subdom}' folder");
    }
    $i = 0;
    $newDir = basename($dir)."~";
    while(file_exists(dirname($dir)."/$newDir")) $newDir = basename($dir)."~".++$i;
    if(rename($dir, dirname($dir)."/$newDir")) return;
    throw new Exception("Unable to rename '".basename($dir)."' folder");
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
    if(!touch("$dir/CONFIG.user"))
      throw new Exception("Unable to create CONFIG file");
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
    // restart apache if symlink changed
    if($this->apacheGraceful) {
      if(!touch(CMS_ROOT_FOLDER."/".self::APACHE_RESTART_FILE))
        new Logger("Unable to force Apache cache symlink target update", "error");
    }
    // init default plugin files
    if(!is_file("$subdomDir/CONFIG.". $this->subdomVars["CONFIG"])) {
      $this->createDefaultPlugins(CMS_ROOT_FOLDER."/{$this->subdomVars["CMS_VER"]}/".PLUGINS_DIR, $subdomDir);
    }
    // create missing data files
    foreach($this->subdomVars as $name => $val) {
      $f = "$subdomDir/$name.$val";
      if(!file_exists($f)) touch($f);
    }
  }

  #todo: https support
  private function createSymlink($link, $target) {
    #$info["toVersion"] = $this->subdomVars["CMS_VER"];
    #$info["isLink"] = is_link($link);
    #$info["readlink"] = readlink($link);
    if(file_exists($link)) {
      if(!is_link($link) || readlink($link) == $target) return;
      #if(!symlink("$target~".microtime(true), "$link~") || !rename("$link~", $link))
      #  throw new Exception("Unable to create symlink '$link'");
      #$url = "http://".$this->subdom.".".getDomain()."/".basename($link);
      #@get_headers($url);
      #new Logger("get_headers($url) = ".implode(" | ", $h), "info");
      $this->apacheGraceful = true;
    }
    if(!symlink($target, "$link~") || !rename("$link~", $link))
      throw new Exception("Unable to create symlink '$link'");
    clearstatcache(true, $link);
    #$info["afterClear"] = readlink($link);
    #print_r($info);
    if(readlink($link) == $target) return;
    throw new Exception("Unable to verify symlink (cache).");
  }

  private function userResetServerSubdom($dir) {
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
    if(!touch("$dir/CMS_VER.".$this->subdomVars["CMS_VER"])
      || !touch("$dir/USER_DIR.".$this->subdomVars["USER_DIR"])
      || !touch("$dir/FILES_DIR.".$this->subdomVars["FILES_DIR"]))
      throw new Exception("Unable to create configuration file(s)");
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
