<?php

// --------------------------------------------------------------------
// GLOBAL CONSTANTS
// --------------------------------------------------------------------

@define('CMS_FOLDER', "../cms");
@define('CLASS_FOLDER', 'cls'); // where objects and other src are stored
@define('ADMIN_FOLDER', 'adm'); // where admin cfg xml files are stored
@define('USER_FOLDER', 'usr'); // where user cfg xml files are stored
@define('PLUGIN_FOLDER', 'plugins'); // where plugins are stored
@define('THEMES_FOLDER', 'themes'); // where templates are stored
@define('BACKUP_FOLDER', 'bak'); // where backup files are stored

#print_r($_SERVER);

// --------------------------------------------------------------------
// GLOBAL FUNCTIONS
// --------------------------------------------------------------------

function isAtLocalhost() {
  return false;
  if($_SERVER["REMOTE_ADDR"] == "127.0.0.1"
  || substr($_SERVER["REMOTE_ADDR"],0,8) == "192.168."
  || substr($_SERVER["REMOTE_ADDR"],0,3) == "10."
  || $_SERVER["REMOTE_ADDR"] == "::1") {
    return true;
  }
  return false;
}

function __autoload($className) {
  if(is_file(CMS_FOLDER ."/". PLUGIN_FOLDER . "/$className/$className.php"))
    include CMS_FOLDER ."/". PLUGIN_FOLDER . "/$className/$className.php";
  elseif(is_file(CMS_FOLDER ."/". CLASS_FOLDER . "/$className.php"))
    include CMS_FOLDER ."/". CLASS_FOLDER . "/$className.php";
  else
    throw new Exception("Unable to find class $className");
}

function stableSort(Array &$a) {
  if(count($a) < 2) return;
  $order = range(1,count($a));
  array_multisort($a,SORT_ASC,$order,SORT_ASC);
}

function getRoot() {
  if(!isAtLocalhost()) return "/";
  $d = explode("/", $_SERVER["SCRIPT_NAME"]);
  return "/{$d[1]}/";
}

function findFile($filePath,$userFolder=true,$adminFolder=true) {
  if(strpos($filePath,"/") === 0) $filePath = substr($filePath,1); // remove trailing slash
  if($userFolder && is_file(USER_FOLDER ."/". $filePath)) return USER_FOLDER ."/". $filePath;
  if($adminFolder && is_file(ADMIN_FOLDER ."/". $filePath)) return ADMIN_FOLDER ."/". $filePath;
  if(is_file($filePath)) return $filePath;
  if(is_file(CMS_FOLDER . "/" . $filePath)) return CMS_FOLDER . "/" . $filePath;
  return false;
}

function normalize($s) {
  $s = mb_strtolower($s,"utf-8");
  $s = iconv("UTF-8", "US-ASCII//TRANSLIT", $s);
  $s = strtolower($s);
  $s = str_replace(" ","_",$s);
  $s = preg_replace("~[^a-z0-9/_-]~","",$s);
  return $s;
}

function saveRewriteFile($dest,$src) {
  if(!file_exists($src))
    throw new Exception("Source file '$src' not found");
  if(!file_exists(dirname($dest)) && !@mkdir(dirname($dest),0755,true))
    throw new Exception("Unable to create directory structure");
  if(!touch($dest))
    throw new Exception("Unable to touch destination file");
  if(!copy($dest,"$dest.old"))
    throw new Exception("Unable to backup destination file");
  if(!rename("$dest.new",$dest))
    throw new Exception("Unable to rename new file to destination");
  return true;
}

function saveRewrite($dest,$content) {
  if(!file_exists(dirname($dest)) && !@mkdir(dirname($dest),0755,true)) return false;
  if(!file_exists($dest)) return file_put_contents($dest, $content);
  $b = file_put_contents("$dest.new", $content);
  if($b === false) return false;
  if(!copy($dest,"$dest.old")) return false;
  if(!rename("$dest.new",$dest)) return false;
  return $b;
}

?>