<?php

// --------------------------------------------------------------------
// GLOBAL CONSTANTS
// --------------------------------------------------------------------

if(!defined('CMS_FOLDER')) define('CMS_FOLDER', "../cms");
if(!defined('ADMIN_BACKUP')) define('ADMIN_BACKUP', 'adm.bak'); // where backup files are stored
if(!defined('ADMIN_FOLDER')) define('ADMIN_FOLDER', 'adm'); // where admin cfg xml files are stored
if(!defined('USER_FOLDER')) define('USER_FOLDER', 'usr'); // where user cfg xml files are stored
if(!defined('USER_BACKUP')) define('USER_BACKUP', 'usr.bak'); // where backup files are stored
if(!defined('THEMES_FOLDER')) define('THEMES_FOLDER', 'themes'); // where templates are stored
if(!defined('CMSRES_FOLDER')) define('CMSRES_FOLDER', false); // where cmsres files are stored
if(!defined('RES_FOLDER')) define('RES_FOLDER', false); // where res files are stored
if(!defined('LOG_FOLDER')) define('LOG_FOLDER', 'log'); // where log files are stored
if(!defined('CACHE_FOLDER')) define('CACHE_FOLDER', 'cache'); // where log files are stored
if(!defined('FILES_FOLDER')) define('FILES_FOLDER', 'files'); // where web files are stored

define('CLASS_FOLDER', 'cls'); // where objects and other src are stored
define('PLUGIN_FOLDER', 'plugins'); // where plugins are stored
define('FILE_HASH_ALGO', 'crc32b');

#print_r($_SERVER);

// --------------------------------------------------------------------
// GLOBAL FUNCTIONS
// --------------------------------------------------------------------

function isAtLocalhost() {
  if($_SERVER["REMOTE_ADDR"] == "127.0.0.1"
  || substr($_SERVER["REMOTE_ADDR"],0,8) == "192.168."
  || substr($_SERVER["REMOTE_ADDR"],0,3) == "10."
  || $_SERVER["REMOTE_ADDR"] == "::1") {
    return true;
  }
  return false;
}

function getRes($res,$dest,$resFolder) {
  if(!$resFolder) return $res;
  if(strpos(pathinfo($res,PATHINFO_FILENAME), ".") === 0)
    throw new Exception("Forbidden file name");
  $newRes = $resFolder . "/$dest";
  $newDir = pathinfo($newRes,PATHINFO_DIRNAME);
  if(!is_dir($newDir) && !@mkdir($newDir,0755,true))
    throw new Exception("Unable to create directory structure '$newDir'");
  if(file_exists($newRes)) {
    if(filectime($newRes) >= filectime($res)) return $newRes;
  }
  if(!copy($res, $newRes))
    throw new Exception("Unable to copy resource file to '$newRes'");
  return $newRes;
}

function __autoload($className) {
  $fp = CMS_FOLDER ."/". PLUGIN_FOLDER . "/$className/$className.php";
  if(@include $fp) return;
  $fc = CMS_FOLDER ."/". CLASS_FOLDER . "/$className.php";
  if(@include $fc) return;
  throw new Exception("Unable to find class '$className' in '$fp' nor '$fc'");
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

function getSubdom() {
  $d = explode(".",$_SERVER["HTTP_HOST"]);
  return $d[0];
}

function getDomain() {
  $d = explode(".",$_SERVER["HTTP_HOST"]);
  while(count($d) > 2) array_shift($d);
  return implode(".",$d);
}

function findFile($file,$user=true,$admin=true,$res=false) {
  if(strpos($file,"/") === 0) $file = substr($file,1); // remove trailing slash
  $f = USER_FOLDER . "/$file";
  if($user && is_file($f)) return ($res ? getRes($f,$file,RES_FOLDER) : $f);
  $f = ADMIN_FOLDER . "/$file";
  if($admin && is_file($f)) return ($res ? getRes($f,$file,RES_FOLDER) : $f);
  $f = $file;
  if(is_file($f)) return ($res ? getRes($f,$file,RES_FOLDER) : $f);
  $f = CMS_FOLDER . "/$file";
  if(is_file($f)) return ($res ? getRes($f,$file,CMSRES_FOLDER) : $f);
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

function getFileHash($filePath) {
  if(!file_exists($filePath)) return "";
  return hash_file(FILE_HASH_ALGO,$filePath);
}

function matchFiles($pattern, $dir) {
  $files = array();
  $values = explode(" ",$pattern);
  foreach($values as $val) {
    $f = "$dir/$val";
    if(file_exists($f)) {
      $files[] = $f;
      continue;
    }
    if(strpos($val,"*") !== false) {
      $d = pathinfo($f ,PATHINFO_DIRNAME);
      if(!file_exists($d)) continue;
      $fp = str_replace("\*",".*",preg_quote(pathinfo($f ,PATHINFO_BASENAME)));
      foreach(scandir($d) as $f) {
        if(!preg_match("/^$fp$/", $f)) continue;
        $files[getFileHash("$d/$f")] = "$d/$f"; // disallowe import same content
      }
    }
  }
  return $files;
}



?>