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

define('FILES_FOLDER', 'files'); // where web files are stored
define('IMPORT_FOLDER', FILES_FOLDER .'/import'); // where imported files are stored
define('THUMBS_FOLDER', FILES_FOLDER .'/thumbs'); // where thumbs files are stored
define('PICTURES_FOLDER', FILES_FOLDER .'/pictures'); // where pictures files are stored
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

function absoluteLink($link=null) {
  if(is_null($link)) $link = $_SERVER["REQUEST_URI"];
  if(substr($link,0,1) == "/") $link = substr($link,1);
  if(substr($link,-1) == "/") $link = substr($link,0,-1);
  $pLink = parse_url($link);
  if($pLink === false) throw new LoggerException("Unable to parse href '$link'");
  $scheme = isset($pLink["scheme"]) ? $pLink["scheme"] : $_SERVER["REQUEST_SCHEME"];
  $host = isset($pLink["host"]) ? $pLink["host"] : $_SERVER["HTTP_HOST"];
  $path = isset($pLink["path"]) && $pLink["path"] != "" ? "/".$pLink["path"] : "";
  $query = isset($pLink["query"]) ? "?".$pLink["query"] : "";
  return "$scheme://$host$path$query";
}

function redirTo($link,$code=null,$force=false) {
  if(!$force) {
    $curLink = absoluteLink();
    $absLink = absoluteLink($link);
    if($curLink == $absLink)
      throw new LoggerException("Cyclic redirection to '$link'");
  }
  if(is_null($code) || !is_numeric($code)) {
    header("Location: $link");
    exit();
  }
  header("Location: $link",true,$code);
  header("Refresh: 0; url=$link");
  exit();
}

function getRes($res,$dest,$resFolder) {
  if(!$resFolder) return $res;
  if(strpos(pathinfo($res,PATHINFO_FILENAME), ".") === 0)
    throw new LoggerException("Forbidden file name");
  $newRes = $resFolder . "/$dest";
  $newDir = pathinfo($newRes,PATHINFO_DIRNAME);
  if(!is_dir($newDir) && !@mkdir($newDir,0755,true))
    throw new LoggerException("Unable to create directory structure '$newDir'");
  if(file_exists($newRes)) {
    if(filectime($newRes) >= filectime($res)) return $newRes;
  }
  if(!copy($res, $newRes))
    throw new LoggerException("Unable to copy resource file to '$newRes'");
  return $newRes;
}

function __autoload($className) {
  $fp = CMS_FOLDER ."/". PLUGIN_FOLDER . "/$className/$className.php";
  if(@include $fp) return;
  $fc = CMS_FOLDER ."/". CLASS_FOLDER . "/$className.php";
  if(@include $fc) return;
  throw new LoggerException("Unable to find class '$className' in '$fp' nor '$fc'");
}

function stableSort(Array &$a) {
  if(count($a) < 2) return;
  $order = range(1,count($a));
  array_multisort($a,SORT_ASC,$order,SORT_ASC);
}

function getLocalLink($link=null,$force=false) {
  if(strpos($link, "#") === 0) return $link;
  if(is_null($link)) $link = getCurLink();
  $pLink = parse_url($link);
  if($pLink === false) throw new LoggerException("Unable to parse href '$link'");
  if(!$force && isset($pLink["scheme"])) return true; // link is absolute
  if(isset($pLink["fragment"])) return "#".$pLink["fragment"];
  $localLink = array();
  if(isAtLocalhost() && !isset($pLink["scheme"])) {
    $dir = explode("/", $_SERVER["SCRIPT_NAME"]);
    $localLink[] = $dir[1];
  }
  $root = "/".implode("/",$localLink);
  if(strpos($link,"$root/") === 0 || $link == $root) return true; // link is correct
  $query = isset($pLink["query"]) ? "?" . $pLink["query"] : "";
  $fragment = isset($pLink["fragment"]) ? "#" . $pLink["fragment"] : "";
  if(isset($pLink["path"])) {
    foreach(explode("/",$pLink["path"]) as $pp) {
      if(!strlen($pp) || $pp == ".") continue; // "/mylink"
      $localLink[] = $pp;
    }
  }
  return "/".implode("/",$localLink) . $query . $fragment;
}

function getCurLink() {
  if(isset($_GET["page"])) return $_GET["page"];
  return "";
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
    throw new LoggerException("Source file '$src' not found");
  if(!file_exists(dirname($dest)) && !@mkdir(dirname($dest),0755,true))
    throw new LoggerException("Unable to create directory structure");
  if(!touch($dest))
    throw new LoggerException("Unable to touch destination file");
  if(!copy($dest,"$dest.old"))
    throw new LoggerException("Unable to backup destination file");
  if(!rename("$dest.new",$dest))
    throw new LoggerException("Unable to rename new file to destination");
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
    $f = findFile($val);
    if($f) {
      $files[] = $f;
      continue;
    }
    if(strpos($val,"*") === false) continue;
    $f = "$dir/$val";
    $d = pathinfo($f ,PATHINFO_DIRNAME);
    if(!file_exists($d)) continue;
    $fp = str_replace("\*",".*",preg_quote(pathinfo($f ,PATHINFO_BASENAME)));
    foreach(scandir($d) as $f) {
      if(!preg_match("/^$fp$/", $f)) continue;
      $files[getFileHash("$d/$f")] = "$d/$f"; // disallowe import same content
    }
  }

  return $files;
}



?>