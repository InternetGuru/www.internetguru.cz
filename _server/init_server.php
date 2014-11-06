<?php

function init_server($subdom=null) {
  $cur_path = explode("/", realpath("."));
  $s = array_pop($cur_path);
  if(is_null($subdom)) $subdom = $s;
  $subdom_dir = implode("/", $cur_path);
  $cms_root_dir = dirname(__FILE__);
  array_pop($cur_path);

  // default local values
  $var = array(
    "USER_ID" => isset($_SERVER["REMOTE_USER"]) ? $user_id = $_SERVER["REMOTE_USER"] : "ig1",
    "CMS_VER" => "0.1",
    "USER_DIR" => $subdom,
    "ADMIN_DIR" => $subdom,
    "FILES_DIR" => $subdom,
    "PLUGINS" => "user",
  );

  // overwrite local values
  foreach(scandir("$subdom") as $f) {
    $vName = substr($f,0,strpos($f,"."));
    if(!array_key_exists($vName, $var)) continue;
    $var[$vName] = substr($f,strlen($vName)+1);
  }

  // find newest stable version
  if(!file_exists("CMS_VER.".$var["CMS_VER"])) {
    foreach(scandir($cms_root_dir) as $v) {
      if(!preg_match("/^\d+\.\d+$/",$v)) continue;
      if(version_compare($var["CMS_VER"],$v) < 0) $var["CMS_VER"] = $v;
    }
  }

  // define global constants
  define("CMS_FOLDER", "$cms_root_dir/{$var["CMS_VER"]}");
  define("SUBDOM_FOLDER", "../../" . $var["USER_ID"] . "/subdom");
  define("USER_FOLDER", "../../" . $var["USER_ID"] . "/usr/" . $var["USER_DIR"]);
  define("USER_BACKUP", "../../usr.bak/$subdom");
  define("ADMIN_FOLDER", "../../adm/" . $var["ADMIN_DIR"]);
  define("ADMIN_BACKUP", "../../adm.bak/$subdom");
  define("FILES_FOLDER", "../../" . $var["USER_ID"] . "/files/" . $var["FILES_DIR"]);
  define("TEMP_FOLDER", "../../" . $var["USER_ID"] . "/temp");
  define("CMSRES_FOLDER", "cmsres/". $var["CMS_VER"]);
  define("RES_FOLDER", "res");
  define("LOG_FOLDER", "../../log/$subdom");
  define("CACHE_FOLDER", "../../cache/$subdom");
  define("PLUGIN_FOLDER", "plugins");

  // create directories {cmsres,res}
  $name = "cmsres";
  $path = "/var/www/cmsres/";
  if(!file_exists($name) || readlink($name) != $path) {
    symlink($path, "$name~");
    rename("$name~", $name);
  }

  // create default plugin files
  $disabledPlugins = array("Slider" => null);
  foreach($disabledPlugins as $p => $null) touch(".PLUGIN.$p");
  if(!file_exists("PLUGINS.".$var["PLUGINS"])) {
    $skipedPlugins = array("Slider" => null);
    foreach(scandir(CMS_FOLDER ."/". PLUGIN_FOLDER) as $f) {
      if(strpos($f,".") === 0) continue; // skip folders starting with a dot
      if(array_key_exists($f, $skipedPlugins)) continue;
      touch("PLUGIN.$f");
    }
  }

  // create missing data files
  foreach($var as $name => $val) {
    $f = "$name.$val";
    if(!file_exists($f)) touch($f);
  }

}
?>