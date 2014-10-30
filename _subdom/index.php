<?php

error_reporting(E_ALL);
ini_set("display_errors",1);

$currentSubdom = basename(dirname($_SERVER["PHP_SELF"]));

// default local values
$var = array(
  "USER_ID" => "ig1",
  "CMS_VER" => "0.1",
  "USER_DIR" => $currentSubdom,
  "ADMIN_DIR" => $currentSubdom,
  "FILES_DIR" => $currentSubdom,
  "PLUGINS" => "user",
);

// overwrite local values
foreach(scandir(dirname(__FILE__)) as $f) {
  $vName = substr($f,0,strpos($f,"."));
  if(!array_key_exists($vName, $var)) continue;
  $var[$vName] = substr($f,strlen($vName)+1);
}

// define global constants
define("CMS_FOLDER", "/var/www/cms/" . $var["CMS_VER"]);
define("USER_FOLDER", "../../" . $var["USER_ID"] . "/usr/" . $var["USER_DIR"]);
define("USER_BACKUP", "../../usr.bak/$currentSubdom");
define("ADMIN_FOLDER", "../../adm/" . $var["ADMIN_DIR"]);
define("ADMIN_BACKUP", "../../adm.bak/$currentSubdom");
define("FILES_FOLDER", "../../" . $var["USER_ID"] . "/files/" . $var["FILES_DIR"]);
define("CMSRES_FOLDER", "cmsres/". $var["CMS_VER"]);
define("RES_FOLDER", "res");
define("LOG_FOLDER", "../../log/$currentSubdom");
define("CACHE_FOLDER", "../../cache/$currentSubdom");
define("PLUGIN_FOLDER", "plugins");

// create directories {cmsres,res}
$name = "cmsres";
$path = "/var/www/cmsres/";
if(!file_exists($name) || readlink($name) != $path) {
  symlink($path, $name . "~");
  rename($name . "~", $name);
}
if(!file_exists(RES_FOLDER)) mkdir(RES_FOLDER,0755);

// create default plugin files
if(!file_exists("PLUGINS.".$var["PLUGINS"])) {
  $skipPlugins = array("Slider" => null);
  foreach(scandir(CMS_FOLDER ."/". PLUGIN_FOLDER) as $f) {
    if(strpos($f,".") === 0) continue; // skip folders starting with a dot
    if(array_key_exists($f, $skipPlugins)) continue;
    touch("PLUGIN.".$f);
  }
}

// create data files
foreach($var as $name => $val) {
  $f = "$name.$val";
  if(!file_exists($f)) touch($f);
}

// run cms
include(CMS_FOLDER . "/index.php");

?>
