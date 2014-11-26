<?php

define('CURRENT_SUBDOM_DIR',basename(dirname($_SERVER["PHP_SELF"])));
define("PLUGINS_DIR", "plugins");
define("THEMES_DIR", "themes");
define("VER_DIR", "ver");
define("LOG_DIR", "log");
define("LIB_DIR", "lib");
define("ADMIN_BACKUP_DIR", "adm.bak");
define("USER_BACKUP_DIR", "usr.bak");
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
  define('CMS_DEBUG', true);
  define('USER_ID', "n/a");
  define('CMS_FOLDER', "../cms");
  define('CMS_RELEASE', "localhost");
  define('ADMIN_FOLDER', ADMIN_ROOT_DIR);
  define('USER_FOLDER', USER_ROOT_DIR);
  define('FILES_FOLDER', FILES_ROOT_DIR);
  define('TEMP_FOLDER', TEMP_DIR);
  define('ADMIN_BACKUP_FOLDER', '../'.CURRENT_SUBDOM_DIR.'/'.ADMIN_BACKUP_DIR);
  define('USER_BACKUP_FOLDER', '../'.CURRENT_SUBDOM_DIR.'/'.USER_BACKUP_DIR);
  define('LOG_FOLDER', '../'.CURRENT_SUBDOM_DIR.'/'.LOG_DIR);
  define('CACHE_FOLDER', '../'.CURRENT_SUBDOM_DIR.'/'.CACHE_DIR);
  define("APACHE_RESTART_FILEPATH", null);
} else {
  define('CMS_DEBUG', is_file("CMS_DEBUG"));
  define('CMS_FOLDER', dirname(CORE_FOLDER));
  define('CMS_ROOT_FOLDER', dirname(CMS_FOLDER));
  define('CMS_RELEASE', basename(CMS_FOLDER));
  define('CMSRES_ROOT_DIR', "cmsres");
  define('CMSRES_ROOT_FOLDER', realpath(CMS_ROOT_FOLDER."/../".CMSRES_ROOT_DIR));
  define("APACHE_RESTART_FILEPATH", CMSRES_ROOT_FOLDER."/APACHE_RESTART");
  define('RES_DIR', "res");
  define('ADMIN_BACKUP_FOLDER', '../../'.ADMIN_BACKUP_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('USER_BACKUP_FOLDER', '../../'.USER_BACKUP_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('LOG_FOLDER', '../../'.LOG_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('CACHE_FOLDER', '../../'.CACHE_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('CMS_BEST_RELEASE', setCmsBestRelease());
}
define('PLUGINS_FOLDER', CMS_FOLDER."/".PLUGINS_DIR);
define('THEMES_FOLDER', CMS_FOLDER."/".THEMES_DIR);
define('LIB_FOLDER', CMS_FOLDER."/".LIB_DIR);
define('VER_FOLDER', CMS_FOLDER."/".VER_DIR);
define('CMS_VERSION_FILENAME', "cms_version.txt");
define('CMS_VERSION', file_get_contents(CMS_FOLDER."/".CMS_VERSION_FILENAME));
#print_r(get_defined_constants(true)); die();

if(CMS_DEBUG) {
  error_reporting(E_ALL);
  ini_set("display_errors", 1);
} else {
  if(isAtLocalhost()) setlocale(LC_ALL, "cs_CZ.utf8");
  else setlocale(LC_ALL, "czech");
  putenv("LANG=cs_CZ"); // for gettext
  bindtextdomain("messages", LIB_FOLDER."/locale");
  textdomain("messages");
}
define('CMS_NAME', "IGCMS ".CMS_RELEASE."/".CMS_VERSION.(CMS_DEBUG ? " DEBUG_MODE" : ""));

#todo: date_default_timezone_set()
#todo: localize lang

function setCmsBestRelease() {
  $cbr = null;
  foreach(scandir(CMS_ROOT_FOLDER) as $v) {
    if(!preg_match("/^\d+\.\d+$/",$v)) continue;
    if(version_compare($cbr, $v) < 0) $cbr = $v;
  }
  if(is_null($cbr)) throw new LoggerException(_("No stable IGCMS variant found"));
  return $cbr;
}

function __autoload($className) {
  $fp = PLUGINS_FOLDER."/$className/$className.php";
  if(@include $fp) return;
  $fc = CORE_FOLDER."/$className.php";
  if(@include $fc) return;
  #todo: log shortPath
  throw new LoggerException(sprintf(_("Unable to find class '%s' in '%s' nor '%s'"), $className, $fp, $fc));
}

function proceedServerInit($initServerFileName) {
  if(isAtLocalhost()) return;
  if(isset($_GET["updateSubdom"])) {
    $subdom = CURRENT_SUBDOM_DIR;
    if(strlen($_GET["updateSubdom"])) $subdom = $_GET["updateSubdom"];
    new InitServer($subdom, false, true);
    redirTo("http://$subdom.". getDomain());
  }
  new InitServer(CURRENT_SUBDOM_DIR, true);
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
  $folders = array(preg_quote(CMS_FOLDER,"/"));
  if(defined("ADMIN_FOLDER")) $folders[] = preg_quote(ADMIN_FOLDER,"/");
  if(defined("USER_FOLDER")) $folders[] = preg_quote(USER_FOLDER,"/");
  if(!preg_match("/^(?:".implode("|",$folders).")\/".FILEPATH_PATTERN."$/", $res)) {
    throw new Exception(sprintf(_("Forbidden file name '%s' format to copy to '%s' folder"), $res, $resFolder));
  }
  $mime = getFileMime($res);
  if(strpos($res, CMS_FOLDER) !== 0 && $mime != "text/plain" && strpos($mime, "image/") !== 0) {
    throw new Exception(sprintf(_("Forbidden MIME type '%s' to copy '%s' to '%s' folder"), $mime, $res, $resFolder));
  }
  $newRes = $resFolder . "/$dest";
  $newDir = pathinfo($newRes,PATHINFO_DIRNAME);
  if(!is_dir($newDir) && !mkdirGroup($newDir,0775,true)) {
    throw new Exception(sprintf(_("Unable to create directory structure '%s'"), $newDir));
  }
  if(is_link($newRes) && readlink($newRes) == $res) return $newRes;
  if(!symlink($res, "$newRes~") || !rename("$newRes~", $newRes)) {
    throw new Exception(sprintf(_("Unable to create symlink '%s' for '%s'"), $newRes, $res));
  }
  #if(!chmodGroup($newRes,0664))
  #  throw new Exception(sprintf(_("Unable to chmod resource file '%x'"), $newRes);
  return $newRes;
}

function createSymlink($link, $target) {
  $restart = false;
  if(is_link($link) && readlink($link) == $target) return;
  elseif(is_link($link)) $restart = true;
  if(!symlink($target, "$link~") || !rename("$link~", $link))
    throw new Exception(sprintf(_("Unable to create symlink '%s'"), $link));
  if($restart && !touch(APACHE_RESTART_FILEPATH))
    new Logger(_("Unable to force symlink cache update"), "error");
}

?>