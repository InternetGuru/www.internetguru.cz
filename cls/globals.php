<?php

// --------------------------------------------------------------------
// GLOBAL CONSTANTS
// --------------------------------------------------------------------

define('CMS_FOLDER', "cms");
define('CLASS_FOLDER', 'cls'); // where objects and other src are stored
define('ADMIN_FOLDER', 'adm'); // where admin cfg xml files are stored
define('USER_FOLDER', 'usr'); // where user cfg xml files are stored
define('PLUGIN_FOLDER', 'plugins'); // where plugins are stored
define('TEMPLATE_FOLDER', 'template'); // where templates are stored

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

function __autoload($className) {
  if(is_file(CLASS_FOLDER . "/$className.php"))
    include CLASS_FOLDER . "/$className.php";
  elseif(is_file(PLUGIN_FOLDER . "/$className/$className.php"))
    include PLUGIN_FOLDER . "/$className/$className.php";
  elseif(is_file("../" . CMS_FOLDER . "/". CLASS_FOLDER . "/$className.php"))
    include "../" . CMS_FOLDER . "/". CLASS_FOLDER . "/$className.php";
  elseif(is_file("../" . CMS_FOLDER . "/". PLUGIN_FOLDER . "/$className/$className.php"))
    include "../" . CMS_FOLDER . "/". PLUGIN_FOLDER . "/$className/$className.php";
  else
    throw new Exception("Unable to find class $className");
}

function stableSort(Array &$a) {
  if(count($a) < 2) return;
  $order = range(1,count($a));
  array_multisort($a,SORT_ASC,$order,SORT_ASC);
}

function findFilePath($fileName,$plugin="",$userFolder=true,$adminFolder=true) {
  if(strpos($fileName,"/") === 0) $fileName = substr($fileName,1); // remove trailing slash
  $f = ($plugin == "" ? "" : PLUGIN_FOLDER . "/$plugin/" ) . $fileName;
  if($userFolder && is_file(USER_FOLDER ."/". $f)) return USER_FOLDER ."/". $f;
  if($adminFolder && is_file(ADMIN_FOLDER ."/". $f)) return ADMIN_FOLDER ."/". $f;
  if(is_file($f)) return $f;
  if(is_file("../" . CMS_FOLDER . "/" . $f)) return "../" . CMS_FOLDER . "/" . $f;
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

function saveRewrite($f,$s) {
  $b = file_put_contents("$f.new", $s);
  if($b === false) return false;
  if(!copy($f,"$f.old")) return false;
  if(!rename("$f.new",$f)) return false;
  return $b;
}

?>