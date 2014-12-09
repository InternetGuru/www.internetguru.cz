<?php

define("INDEX_HTML", "index.html");
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
define('SUBDOM_PATTERN', "[a-z][a-z0-9]*");
define('VARIABLE_PATTERN', '(?:[a-z]+-)?[a-z_]+');
define('FILEPATH_PATTERN', "(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9._-]+\.[a-z0-9]{2,4}");
define('FILE_HASH_ALGO', 'crc32b');
define('STATUS_PREINIT', 'preinit');
define('STATUS_INIT', 'init');
define('STATUS_PROCESS', 'process');
define('STATUS_POSTPROCESS', 'postprocess');
define('CURRENT_SUBDOM_FOLDER', getcwd());
define('CURRENT_SUBDOM_DIR', basename(CURRENT_SUBDOM_FOLDER));
define("CORE_DIR", "core");
define("IS_LOCALHOST", (!isset($_SERVER["REMOTE_ADDR"])
  || $_SERVER["REMOTE_ADDR"] == "127.0.0.1"
  || strpos($_SERVER["REMOTE_ADDR"], "192.168.") === 0
  || strpos($_SERVER["REMOTE_ADDR"], "10.") === 0
  || $_SERVER["REMOTE_ADDR"] == "::1"));

if(IS_LOCALHOST) {
  define('CMS_RELEASE', "localhost");
  define("CMS_ROOT_FOLDER", "../cms");
  define("CMS_FOLDER", CMS_ROOT_FOLDER);
  define('CMS_DEBUG', true);
  define('USER_ID', "LocalUser");
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
  define("CMS_ROOT_FOLDER", "/var/www/cms");
  $cmsVer = null;
  foreach(scandir(CMS_ROOT_FOLDER) as $v) {
    if(!preg_match("/^\d+\.\d+$/", $v)) continue;
    if(version_compare($cmsVer, $v) < 0) $cmsVer = $v;
  }
  define('CMS_BEST_RELEASE', $cmsVer);
  foreach(scandir(CURRENT_SUBDOM_FOLDER) as $f) {
    $var = explode(".", $f, 2);
    if($var[0] != "CMS_VER") continue;
    $cmsVer = $var[1];
    break;
  }
  if(is_null($cmsVer)) {
    http_response_code(500);
    echo "No stable CMS release found.";
    exit;
  }
  define('CMS_RELEASE', $cmsVer);
  define("CMS_FOLDER", CMS_ROOT_FOLDER."/".CMS_RELEASE);
  define('CMS_DEBUG', is_file("CMS_DEBUG"));
  define('CMSRES_ROOT_DIR', "cmsres");
  define('CMSRES_ROOT_FOLDER', realpath(CMS_ROOT_FOLDER."/../".CMSRES_ROOT_DIR));
  define("APACHE_RESTART_FILEPATH", CMSRES_ROOT_FOLDER."/APACHE_RESTART");
  define('RES_DIR', "res");
  define('ADMIN_BACKUP_FOLDER', '../../'.ADMIN_BACKUP_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('USER_BACKUP_FOLDER', '../../'.USER_BACKUP_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('LOG_FOLDER', '../../'.LOG_DIR.'/'.CURRENT_SUBDOM_DIR);
  define('CACHE_FOLDER', '../../'.CACHE_DIR.'/'.CURRENT_SUBDOM_DIR);
}

if(CMS_DEBUG) {
  error_reporting(E_ALL);
  ini_set("display_errors", 1);
} else {
  #if(!IS_LOCALHOST)
  setlocale(LC_ALL, "cs_CZ.UTF-8");
  #else setlocale(LC_ALL, "czech");
  putenv("LANG=cs_CZ.UTF-8"); // for gettext
  bindtextdomain("messages", LIB_FOLDER."/locale");
  textdomain("messages");
}

define("CORE_FOLDER", CMS_FOLDER."/".CORE_DIR);
define('PLUGINS_FOLDER', CMS_FOLDER."/".PLUGINS_DIR);
define('THEMES_FOLDER', CMS_FOLDER."/".THEMES_DIR);
define('LIB_FOLDER', CMS_FOLDER."/".LIB_DIR);
define('VER_FOLDER', CMS_FOLDER."/".VER_DIR);
define('CMS_VERSION_FILENAME', "cms_version.txt");
define('CMS_VERSION', file_get_contents(CMS_FOLDER."/".CMS_VERSION_FILENAME));
define('CMS_NAME', "IGCMS ".CMS_RELEASE."/".CMS_VERSION.(CMS_DEBUG ? " DEBUG_MODE" : ""));
#print_r(get_defined_constants(true)); die();
#todo: date_default_timezone_set()
#todo: localize lang

$initCmsFile = CORE_FOLDER."/init_cms.php";
if(!is_file($initCmsFile)) {
  http_response_code(500);
  echo "CMS core init file not found.";
  exit;
}
include($initCmsFile);

?>