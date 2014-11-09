<?php

function init_server($subdom, $update = false) {

  $cms_root_dir = realpath(CMS_FOLDER ."/..");

  // subdom check
  if(!preg_match("/^".SUBDOM_PATTERN."$/", $subdom))
    throw new Exception("Invalid subdom format '$subdom'");
  $serverSubdomDir = "../$subdom";

  // default local values
  $vars = array(
    "USER_ID" => isset($_SERVER["REMOTE_USER"]) ? $user_id = $_SERVER["REMOTE_USER"] : "ig1",
    "CMS_VER" => "0.1",
    "USER_DIR" => $subdom,
    "ADMIN_DIR" => $subdom,
    "FILES_DIR" => $subdom,
    "PLUGIN_DIR" => "plugins",
    "RES_DIR" => "res",
    "CONFIG" => "user",
  );

  // update default values from server subdom
  if(is_dir($serverSubdomDir)) foreach(scandir($serverSubdomDir) as $f) {
    $vName = substr($f,0,strpos($f,"."));
    if(!array_key_exists($vName, $vars)) continue;
    $vars[$vName] = substr($f,strlen($vName)+1);
  }

  if($update) {

    // pass if dir is new, owned or empty
    if(is_dir($serverSubdomDir)
      && !is_file("$serverSubdomDir/USER_ID.". $vars["USER_ID"])
      && count(scandir($serverSubdomDir)) != 2)
      throw new Exception("Cannot modify subdom '$subdom', USER_ID mismatch");

    // safely remove (rename) subdom if .subdom && user_id match
    $userDotSubdom = "../../" . $vars["USER_ID"] . "/subdom/.$subdom";
    if(is_dir($userDotSubdom)) {
      // safely remove/rename user .subdom folder
      if(count(scandir($userDotSubdom)) == 2) {
        if(!rmdir($userDotSubdom))
          throw new Exception("Unable to remove '.$subdom' folder");
      } else {
        $newSubdom = "~$subdom";
        while(file_exists("$userDotSubdom/../$newSubdom")) $newSubdom = "~$newSubdom";
        if(!rename($userDotSubdom, "$userDotSubdom/../$newSubdom"))
          throw new Exception("Unable to rename '.$subdom' folder");
      }
      if(is_dir($serverSubdomDir)) {
        $newSubdom = "~$subdom";
        while(file_exists("../$newSubdom")) $newSubdom = "~$newSubdom";
        if(!rename($serverSubdomDir,"../$newSubdom"))
          throw new Exception("Unable to remove subdom '$subdom'");
      }

      return; // throw new Exception("Subdom '$subdom' has been removed");
    }

    // create subdom
    if(!is_dir($serverSubdomDir)) {
      if(!preg_match("/^".$vars["USER_ID"].SUBDOM_PATTERN."$/", $subdom))
        throw new Exception("New subdom must start with USER_ID '".$vars["USER_ID"]."'");
      if(!@mkdir($serverSubdomDir, 0755, true))
        throw new Exception("Unable to create folder '$serverSubdomDir'");
    }

    // acquire subdom if new or empty
    if(!touch("$serverSubdomDir/USER_ID.". $vars["USER_ID"]))
      throw new Exception("Unable to create user id file");
  }

  // create folder constants
  $dirs = array(
    'SUBDOM_FOLDER' => "../../" . $vars["USER_ID"] . "/subdom/$subdom",
    'ADMIN_BACKUP' => "../../adm.bak/$subdom",
    'ADMIN_FOLDER' => "../../adm/". $vars["ADMIN_DIR"],
    'USER_FOLDER' => "../../" . $vars["USER_ID"] . "/usr/" . $vars["USER_DIR"],
    'USER_BACKUP' => "../../usr.bak/$subdom",
    'FILES_FOLDER' => "../../" . $vars["USER_ID"] . "/files/" . $vars["FILES_DIR"],
    'TEMP_FOLDER' => "../../" . $vars["USER_ID"] . "/temp",
    'CMSRES_FOLDER' => "cmsres/". $vars["CMS_VER"],
    'RES_FOLDER' => $vars["RES_DIR"],
    'LOG_FOLDER' => "../../log/$subdom",
    'CACHE_FOLDER' => "../../cache/$subdom",
  );

  // find newest stable version
  if(!file_exists("$serverSubdomDir/CMS_VER.".$vars["CMS_VER"])) {
    foreach(scandir($cms_root_dir) as $v) {
      if(!preg_match("/^\d+\.\d+$/",$v)) continue;
      if(version_compare($vars["CMS_VER"],$v) < 0) $vars["CMS_VER"] = $v;
    }
  }

  // create directories {cmsres,res}
  $name = "cmsres";
  $path = "/var/www/cmsres/";
  if(!file_exists("$serverSubdomDir/$name") || readlink("$serverSubdomDir/$name") != $path) {
    symlink($path, "$serverSubdomDir/$name~");
    rename("$serverSubdomDir/$name~", "$serverSubdomDir/$name");
  }

  // create default plugin files
  $disabledPlugins = array("Slider" => null);
  foreach($disabledPlugins as $p => $null) touch("$serverSubdomDir/.PLUGIN.$p");
  if(!is_file("$serverSubdomDir/CONFIG.". $vars["CONFIG"])) {
    createDefaultPlugins("$cms_root_dir/{$vars["CMS_VER"]}/{$vars["PLUGIN_DIR"]}", $serverSubdomDir);
    $update = true;
  }

  // create missing data files
  foreach($vars as $name => $val) {
    $f = "$serverSubdomDir/$name.$val";
    if(!file_exists($f)) touch($f);
  }

  // create required directories
  foreach($dirs as $k => $d) {
    if(!$d) continue; // res/cmsres == false
    if(!is_dir("$serverSubdomDir/$d") && !@mkdir("$serverSubdomDir/$d",0755,true))
      throw new Exception("Unable to create folder '$d'");
  }


  if(!$update) {

    // clone server setup if empty user subdom
    if(count(scandir($dirs["SUBDOM_FOLDER"])) == 2) {
      foreach(scandir($serverSubdomDir) as $f) {
        if(!is_file("$serverSubdomDir/$f") || strpos($f, ".") === 0) continue;
        $var = explode(".", $f, 2);
        switch($var[0]) {
          case "CMS_VER":
          case "USER_DIR":
          case "FILES_DIR":
          case "PLUGIN":
          touch($dirs["SUBDOM_FOLDER"] . "/$f"); // no exception if fail
          break;
          case "robots":
          if($f != "robots.txt" || is_link("$serverSubdomDir/$f")) continue;
          copy("$serverSubdomDir/$f", $dirs["SUBDOM_FOLDER"] . "/$f"); // no exception if fail
        }
      }
    }

    // define global constants
    if(!defined("USER_ID")) {
      define("USER_ID", $vars["USER_ID"]);
      define("PLUGIN_FOLDER", $vars["PLUGIN_DIR"]);
      foreach($dirs as $k => $v) define($k, $v);
    }

    return;
  }

  // check rights to modify files
  if(!is_file("$serverSubdomDir/CONFIG.user"))
    throw new Exception("User subdom setup disabled.");

  // apply user subdom (first update CMS_VER then plugins .. ascending)
  foreach(scandir($dirs["SUBDOM_FOLDER"], SCANDIR_SORT_ASCENDING) as $f) {
    if(!is_file("{$dirs["SUBDOM_FOLDER"]}/$f") || strpos($f, ".") === 0) continue;
    $var = explode(".", $f, 2);
    switch($var[0]) {
      case "CMS_VER":
      if(!is_dir("$cms_root_dir/{$var[1]}") || is_dir("$cms_root_dir/.{$var[1]}"))
        throw new Exception("CMS variant '{$var[1]}' not available");
      case "USER_DIR":
      case "FILES_DIR":
      $vars[$var[0]] = $var[1];
      break;
      case "PLUGIN":
      if(!is_dir("$cms_root_dir/{$vars["CMS_VER"]}/{$vars["PLUGIN_DIR"]}/{$var[1]}"))
        throw new Exception("Plugin '{$var[1]}' is not available");
      if(is_file("$serverSubdomDir/.$f"))
        throw new Exception("Plugin '{$var[1]}' is forbidden");
      if(!is_file("$serverSubdomDir/$f") && !touch("$serverSubdomDir/$f"))
        throw new Exception("Unable to enable plugin '{$var[1]}'");
      break;
      case "robots":
      if($f != "robots.txt") continue;
      if(preg_match("/^ig\d/", $subdom))
        throw new Exception("Cannot modify '$f' in subdom '$subdom'");
      if(!copy("{$dirs["SUBDOM_FOLDER"]}/$f", "$serverSubdomDir/$f"))
        throw new Exception("Unable to copy '$f' into subdom '$subdom'");
      break;
    }
  }

  // reset server and sync user if user subdom empty
  if(count(scandir($dirs["SUBDOM_FOLDER"])) == 2) {
    $vars["USER_DIR"] = $subdom;
    $vars["FILES_DIR"] = $subdom;
    foreach(scandir($serverSubdomDir) as $f) {
      if(!is_file("$serverSubdomDir/$f") || strpos($f, ".") === 0) continue;
      $var = explode(".", $f, 2);
      switch($var[0]) {
        case "CMS_VER":
        case "USER_DIR":
        case "FILES_DIR":
        if(!rename("$serverSubdomDir/$f", "$serverSubdomDir/{$var[0]}.". $vars[$var[0]]))
          throw new Exception("Unable to reset server subdom setup");
        if(!touch($dirs["SUBDOM_FOLDER"] ."/{$var[0]}.". $vars[$var[0]]))
          throw new Exception("Unable to sync subdom setup");
        break;
        case "PLUGIN":
        unlink("$serverSubdomDir/$f"); // delete all plugins (create default below)
      }
    }
    createDefaultPlugins("$cms_root_dir/{$vars["CMS_VER"]}/{$vars["PLUGIN_DIR"]}", $serverSubdomDir);
    createDefaultPlugins("$cms_root_dir/{$vars["CMS_VER"]}/{$vars["PLUGIN_DIR"]}", $dirs["SUBDOM_FOLDER"]);
  }

  // apply server subdom
  foreach(scandir($serverSubdomDir) as $f) {
    if(strpos($f, ".") === 0) continue;
    $var = explode(".", $f, 2);
    switch($var[0]) {
      case "CMS_VER":
      case "USER_DIR":
      case "FILES_DIR":
      $newFile = "{$var[0]}.". $vars[$var[0]];
      if(!is_file($dirs["SUBDOM_FOLDER"] ."/$newFile") && !touch($dirs["SUBDOM_FOLDER"] ."/$newFile"))
        throw new Exception("Unable to update user subdom setup");
      if($vars[$var[0]] == $var[1]) continue;
      if(!rename("$serverSubdomDir/$f", "$serverSubdomDir/$newFile"))
        throw new Exception("Unable to setup '$newFile'");
      break;
      case "PLUGIN":
      if(!is_file($dirs["SUBDOM_FOLDER"] ."/$f") && !unlink("$serverSubdomDir/$f"))
        throw new Exception("Unable to disable plugin '{$var[1]}'");
      break;
    }
  }

  // link root files
  $files = array("index.php" => "index.php", ".htaccess" => ".htaccess", "robots.txt" => "robots_default.txt");
  if(preg_match("/^ig\d/", $subdom)) $files["robots.txt"] = "robots_off.txt";
  foreach($files as $link => $target) {
    #$info["toVersion"] = $vars["CMS_VER"];
    #$info["isLink"] = is_link("$serverSubdomDir/$link");
    #$info["readlink"] = readlink("$serverSubdomDir/$link");
    if(!is_link("$serverSubdomDir/$link")
      || readlink("$serverSubdomDir/$link") != "$cms_root_dir/{$vars["CMS_VER"]}/$target") {
      #$info["settingTo"] = "$cms_root_dir/{$vars["CMS_VER"]}/$target";
      if(!symlink("$cms_root_dir/{$vars["CMS_VER"]}/$target", "$serverSubdomDir/$link~")
      || !rename("$serverSubdomDir/$link~", "$serverSubdomDir/$link"))
        throw new Exception("Unable to link file '$l' into '$subdom'");
      #$info["beforeClear"] = readlink("$serverSubdomDir/$link");
      clearstatcache(true, "$serverSubdomDir/$link");
      #$info["afterClear"] = readlink("$serverSubdomDir/$link");
      if(readlink("$serverSubdomDir/$link") != "$cms_root_dir/{$vars["CMS_VER"]}/$target")
        throw new Exception("Unable to verify symlink (cache).");
    }
    #print_r($info);
    #throw new Exception("ALL OK");
  }

}

function createDefaultPlugins($srcDir, $destDir) {
  $skipedPlugins = array("Slider" => null);
  foreach(scandir($srcDir) as $f) {
    if(strpos($f,".") === 0) continue; // skip folders starting with a dot
    if(array_key_exists($f, $skipedPlugins)) continue;
    if(!touch("$destDir/PLUGIN.$f"))
      throw new Exception("Unable to create plugin files");
  }
}

function update_subdom($subdom) {
  init_server($subdom, true);
}

?>
