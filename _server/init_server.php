<?php

function init_server($subdom, $cms_root_dir, $update = false) {

  // default local values
  $vars = array(
    "USER_ID" => isset($_SERVER["REMOTE_USER"]) ? $user_id = $_SERVER["REMOTE_USER"] : "ig1",
    "CMS_VER" => "0.1",
    "USER_DIR" => $subdom,
    "ADMIN_DIR" => $subdom,
    "FILES_DIR" => $subdom,
    "PLUGIN_DIR" => "plugins",
    "RES_DIR" => "res",
    "PLUGINS" => "user",
  );

  // create subdom
  if(!preg_match("/^[a-z][a-z0-9]+$/", $subdom))
    throw new Exception("Invalid subdom format '$subdom'");
  $serverSubdomDir = "../$subdom";
  if(!is_dir($serverSubdomDir)) {
    if(strpos($subdom, $vars["USER_ID"]) !== 0)
      throw new Exception("New subdom must start with USER_ID '".$vars["USER_ID"]."'");
    if(!@mkdir($serverSubdomDir, 0755, true))
      throw new Exception("Unable to create folder '$serverSubdomDir'");
  }

  // update default values from server subdom
  foreach(scandir($serverSubdomDir) as $f) {
    $vName = substr($f,0,strpos($f,"."));
    if(!array_key_exists($vName, $vars)) continue;
    $vars[$vName] = substr($f,strlen($vName)+1);
  }

  // find newest stable version
  if(!file_exists("CMS_VER.".$vars["CMS_VER"])) {
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
  if(!is_file("$serverSubdomDir/PLUGINS.". $vars["PLUGINS"])) {
    createDefaultPlugins("$cms_root_dir/{$vars["CMS_VER"]}/{$vars["PLUGIN_DIR"]}", $serverSubdomDir);
    $update = true;
  }

  // create missing data files
  foreach($vars as $name => $val) {
    $f = "$serverSubdomDir/$name.$val";
    if(!file_exists($f)) touch($f);
  }

  // create required directories
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
  foreach($dirs as $k => $d) {
    if(!$d) continue; // res/cmsres == false
    if(!is_dir("$serverSubdomDir/$d") && !@mkdir("$serverSubdomDir/$d",0755,true))
      throw new Exception("Unable to create folder '$d'");
  }

  // check ID
  if(!is_file("$serverSubdomDir/USER_ID.". $vars["USER_ID"]))
    throw new Exception("Cannot modify subdom '$subdom', USER_ID mismatch");

  // reset server and sync user if user subdom empty
  $userVar = array("CMS_VER" => $vars["CMS_VER"], "USER_DIR" => $subdom, "FILES_DIR" => $subdom);
  if(count(scandir($dirs["SUBDOM_FOLDER"])) == 2) {
    if(!is_file("$serverSubdomDir/PLUGINS.user"))
      createDefaultPlugins("$cms_root_dir/{$vars["CMS_VER"]}/{$vars["PLUGIN_DIR"]}", $serverSubdomDir);
    foreach(scandir($serverSubdomDir) as $f) {
      if(!is_file("$serverSubdomDir/$f") || strpos($f, ".") === 0) continue;
      $var = explode(".", $f, 2);
      switch($var[0]) {
        case "CMS_VER":
        case "USER_DIR":
        case "FILES_DIR":
        if(!rename("$serverSubdomDir/$f", "$serverSubdomDir/{$var[0]}.". $userVar[$var[0]]))
          throw new Exception("Unable to reset server subdom setup");
        if(!touch($dirs["SUBDOM_FOLDER"] ."/{$var[0]}.". $userVar[$var[0]]))
          throw new Exception("Unable to sync subdom setup");
        break;
        case "PLUGIN":
        if(!is_file("$serverSubdomDir/PLUGINS.user")) continue;
        if(!touch($dirs["SUBDOM_FOLDER"] ."/$f"))
          throw new Exception("Unable to sync subdom setup");
      }
    }
  }

  // define global constants
  if(!$update) {
    define("USER_ID", $vars["USER_ID"]);
    define("PLUGIN_FOLDER", $vars["PLUGIN_DIR"]);
    define('CMS_FOLDER', "$cms_root_dir/{$vars["CMS_VER"]}");
    foreach($dirs as $k => $v) define($k, $v);
    return;
  }

  // copy index and .htaccess
  foreach(array("index.php", ".htaccess") as $f) {
    if(!is_file("$serverSubdomDir/$f") && !@symlink("$cms_root_dir/{$vars["CMS_VER"]}/$f", "$serverSubdomDir/$f"))
      throw new Exception("Unable to link file '$f' into '$serverSubdomDir'");
  }

  // apply user subdom
  foreach(scandir($dirs["SUBDOM_FOLDER"]) as $f) {
    if(!is_file("{$dirs["SUBDOM_FOLDER"]}/$f") || strpos($f, ".") === 0) continue;
    $var = explode(".", $f, 2);
    switch($var[0]) {
      case "CMS_VER":
      if(!is_dir("$cms_root_dir/{$var[1]}") || is_dir("$cms_root_dir/.{$var[1]}"))
        throw new Exception("CMS variant '{$var[1]}' not available");
      case "USER_DIR":
      case "FILES_DIR":
      $userVar[$var[0]] = $var[1];
      break;
      case "PLUGIN":
      if(!is_file("$serverSubdomDir/PLUGINS.user"))
        throw new Exception("Plugin modification is disabled");
      if(!is_dir("$cms_root_dir/{$vars["CMS_VER"]}/{$vars["PLUGIN_DIR"]}/{$var[1]}"))
        throw new Exception("Plugin '{$var[1]}' is not available");
      if(is_file("$serverSubdomDir/.$f"))
        throw new Exception("Plugin '{$var[1]}' is forbidden");
      if(!is_file("$serverSubdomDir/$f") && !touch("$serverSubdomDir/$f"))
        throw new Exception("Unable to enable plugin '{$var[1]}'");
      break;
    }
  }

  // apply server subdom
  foreach(scandir($serverSubdomDir) as $f) {
    if(strpos($f, ".") === 0) continue;
    $var = explode(".", $f, 2);
    switch($var[0]) {
      case "CMS_VER":
      case "USER_DIR":
      case "FILES_DIR":
      $newFile = "{$var[0]}.". $userVar[$var[0]];
      if(!is_file($dirs["SUBDOM_FOLDER"] ."/$newFile") && !touch($dirs["SUBDOM_FOLDER"] ."/$newFile"))
        throw new Exception("Unable to update user subdom setup");
      if($userVar[$var[0]] == $var[1]) continue;
      if(!rename("$serverSubdomDir/$f", "$serverSubdomDir/$newFile"))
        throw new Exception("Unable to setup '$newFile'");
      break;
      case "PLUGIN":
      if(is_file("$serverSubdomDir/PLUGINS.user")
        && !is_file($dirs["SUBDOM_FOLDER"] ."/$f") && !unlink("$serverSubdomDir/$f"))
        throw new Exception("Unable to disable plugin '{$var[1]}'");
      break;
    }
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

function update_subdom($subdom, $cms_root_dir) {
  init_server($subdom, $cms_root_dir, true);
}

?>
