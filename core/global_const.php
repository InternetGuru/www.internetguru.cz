<?php

define('CURRENT_SUBDOM_DIR',basename(dirname($_SERVER["PHP_SELF"])));
define("PLUGINS_DIR", "plugins");
define("THEMES_DIR", "themes");
define("VER_DIR", "ver");
define('SUBDOM_ROOT_DIR', "subdom");
define("ADMIN_ROOT_DIR", "adm");
define("USER_ROOT_DIR", "usr");
define("FILES_ROOT_DIR", "files");
define("TEMP_DIR", "temp");
define("CACHE_DIR", "cache");
define('CORE_DIR', 'core');
define('SUBDOM_PATTERN', "[a-z][a-z0-9]*");
define('VARIABLE_PATTERN', '(?:[a-z]+-)?[a-z_]+');
define('FILEPATH_PATTERN', "(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9._-]+\.[a-z0-9]{2,4}");
define('FILE_HASH_ALGO', 'crc32b');
define('STATUS_PREINIT', 'preinit');
define('STATUS_INIT', 'init');
define('STATUS_PROCESS', 'process');
define('STATUS_POSTPROCESS', 'postprocess');
define('CORE_FOLDER', dirname(__FILE__));
if(isAtLocalhost()) {
  define("LOG_DIR", "_log");
  define("ADMIN_BACKUP_DIR", "_adm.bak");
  define("USER_BACKUP_DIR", "_usr.bak");
  define('USER_ID', "n/a");
  define('CMS_FOLDER', "../cms");
  define('CMS_RELEASE', "localhost");
  define('ADMIN_FOLDER', ADMIN_ROOT_DIR);
  define('USER_FOLDER', USER_ROOT_DIR);
  define('FILES_FOLDER', FILES_ROOT_DIR);
  define('TEMP_FOLDER', TEMP_DIR);
  define('ADMIN_BACKUP_FOLDER', '../'.ADMIN_BACKUP_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('USER_BACKUP_FOLDER', '../'.USER_BACKUP_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('LOG_FOLDER', '../'.LOG_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('CACHE_FOLDER', '../'.CACHE_DIR.'/'.CURRENT_SUBDOM_DIR);
} else {
  define("LOG_DIR", "log");
  define("ADMIN_BACKUP_DIR", "adm.bak");
  define("USER_BACKUP_DIR", "usr.bak");
  define('CMS_FOLDER', dirname(CORE_FOLDER));
  define('CMS_ROOT_FOLDER', dirname(CMS_FOLDER));
  define('CMS_RELEASE', basename(CMS_FOLDER));
  define('CMSRES_ROOT_DIR', "cmsres");
  define('CMSRES_ROOT_FOLDER', realpath(CMS_ROOT_FOLDER."/../".CMSRES_ROOT_DIR));
  define('RES_DIR', "res");
  define('ADMIN_BACKUP_FOLDER', '../../'.ADMIN_BACKUP_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('USER_BACKUP_FOLDER', '../../'.USER_BACKUP_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('LOG_FOLDER', '../../'.LOG_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('CACHE_FOLDER', '../../'.CACHE_DIR.'/'.CURRENT_SUBDOM_DIR);
}
define('PLUGINS_FOLDER', CMS_FOLDER."/".PLUGINS_DIR);
define('THEMES_FOLDER', CMS_FOLDER."/".THEMES_DIR);
define('VER_FOLDER', CMS_FOLDER."/".VER_DIR);
define('CMS_VERSION', file_get_contents(CMS_FOLDER ."/cms_version.txt"));
#print_r(get_defined_constants(true)); die();

if(substr(CMS_VERSION,-4) == "-dev") {
  error_reporting(E_ALL);
  ini_set("display_errors", 1);
  define('CMS_DEBUG', true);
} else define('CMS_DEBUG', false);
define('CMS_NAME', "IGCMS ".CMS_RELEASE."/".CMS_VERSION.(CMS_DEBUG ? " DEBUG_MODE" : ""));

#todo: date_default_timezone_set()
#todo: setlocale(LC_ALL, czech); // cs_CZ.utf8 (localhost)

function __autoload($className) {
  $fp = PLUGINS_FOLDER."/$className/$className.php";
  if(@include $fp) return;
  $fc = CORE_FOLDER."/$className.php";
  if(@include $fc) return;
  throw new LoggerException("Unable to find class '$className' in '$fp' nor '$fc'");
}

function proceedServerInit($initServerFileName) {
  if(isAtLocalhost()) return;
  if(!is_file(CMS_ROOT_FOLDER."/$initServerFileName"))
    throw new Exception("Missing server init file");
  require_once(CMS_ROOT_FOLDER."/$initServerFileName");
  new InitServer(CURRENT_SUBDOM_DIR, true);
  if(isset($_GET["updateSubdom"])) {
    if(is_file(CMS_ROOT_FOLDER."/.$initServerFileName")) {
      new Logger("Subdom update is disabled.", "warning");
      return;
    }
    $subdom = CURRENT_SUBDOM_DIR;
    if(strlen($_GET["updateSubdom"])) $subdom = $_GET["updateSubdom"];
    new InitServer($subdom, false, true);
    redirTo("http://$subdom.". getDomain());
  }
}

function findFile($file, $user=true, $admin=true, $res=false) {
  while(strpos($file,"/") === 0) $file = substr($file,1);
  try {
    $resFolder = $res && !isAtLocalhost() ? $resFolder = RES_DIR : false;
    $f = USER_FOLDER . "/$file";
    if($user && is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
    $f = ADMIN_FOLDER . "/$file";
    if($admin && is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
    $f = $file;
    if(is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
    if($res && !isAtLocalhost()) $resFolder = CMSRES_ROOT_DIR."/".CMS_RELEASE;
    $f = CMS_FOLDER . "/$file";
    if(is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
  } catch(Exception $e) {
    new Logger($e->getMessage(), "error");
  }
  return false;
}

function getRes($res, $dest, $resFolder) {
  if($resFolder === false) return $res;
  #TODO: check mime==ext, allowed types, max size
  $folders = preg_quote(CMS_FOLDER,"/") ."|". preg_quote(ADMIN_FOLDER,"/") ."|". preg_quote(USER_FOLDER,"/");
  if(!preg_match("/^(?:$folders)\/".FILEPATH_PATTERN."$/", $res)) {
    throw new Exception("Forbidden file name '$res' to copy to '$resFolder' folder");
  }
  $mime = getFileMime($res);
  if(strpos($res, CMS_FOLDER) !== 0 && $mime != "text/plain") {
    throw new Exception("Forbidden mime type '$mime' to copy '$res' to '$resFolder' folder");
  }
  $newRes = $resFolder . "/$dest";
  $newDir = pathinfo($newRes,PATHINFO_DIRNAME);
  if(!is_dir($newDir) && !mkdirGroup($newDir,0775,true)) {
    throw new Exception("Unable to create directory structure '$newDir'");
  }
  if(is_link($newRes) && readlink($newRes) == $res) return $newRes;
  if(!symlink($res, "$newRes~") || !rename("$newRes~", $newRes)) {
    throw new Exception("Unable to create symlink '$newRes' for '$res'");
  }
  if(!chmodGroup($newRes,0664))
    throw new Exception("Unable to chmod resource file '$newRes'");
  return $newRes;
}

?>