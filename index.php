<?php

define('CMS_ROOT_DIR', "cms");
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
if(is_link(basename(__FILE__))) { // runs on server
  define("LOG_DIR", "log");
  define("ADMIN_BACKUP_DIR", "adm.bak");
  define("USER_BACKUP_DIR", "usr.bak");
  define('CMS_FOLDER', dirname(readlink(basename(__FILE__))));
  define('CMS_ROOT_FOLDER', realpath(CMS_FOLDER."/.."));
  define('CMS_RELEASE', basename(CMS_FOLDER));
  define('CMSRES_ROOT_DIR', "cmsres");
  define('CMSRES_ROOT_FOLDER', realpath(CMS_ROOT_FOLDER."/../".CMSRES_ROOT_DIR));
  define('RES_DIR', "res");
} else { // runs on localhost
  define("LOG_DIR", "_log");
  define("ADMIN_BACKUP_DIR", "_adm.bak");
  define("USER_BACKUP_DIR", "_usr.bak");
  define('USER_ID', "n/a");
  define('CMS_RELEASE', CMS_ROOT_DIR);
  define('CMS_FOLDER', "../".CMS_ROOT_DIR);
  define('ADMIN_FOLDER', "../".ADMIN_ROOT_DIR); // only localhost (server must create)
  define('USER_FOLDER', "../".USER_ROOT_DIR); // only localhost (server must create)
  define('FILES_FOLDER', "../".FILES_ROOT_DIR); // only localhost (server must create)
  define('TEMP_FOLDER', "../".TEMP_DIR); // only localhost (server must create)
}
define('CORE_FOLDER', CMS_FOLDER."/".CORE_DIR);
define('PLUGINS_FOLDER', CMS_FOLDER."/".PLUGINS_DIR);
define('THEMES_FOLDER', CMS_FOLDER."/".THEMES_DIR);
define('VER_FOLDER', CMS_FOLDER."/".VER_DIR);
define('CMS_VERSION', file_get_contents(CMS_FOLDER ."/cms_version.txt"));
define('ADMIN_BACKUP_FOLDER', '../../'.ADMIN_BACKUP_DIR.'/'.basename("."));
define('USER_BACKUP_FOLDER', '../../'.USER_BACKUP_DIR.'/'.basename("."));
define('LOG_FOLDER', '../../'.LOG_DIR.'/'.basename("."));
define('CACHE_FOLDER', '../../'.CACHE_DIR.'/'.basename("."));
#print_r(get_defined_constants(true)); die();

if(substr(CMS_VERSION,-4) == "-dev") {
  error_reporting(E_ALL);
  ini_set("display_errors", 1);
  define('CMS_DEBUG', true);
} else define('CMS_DEBUG', false);

#todo: date_default_timezone_set()
#todo: setlocale(LC_ALL, czech); // cs_CZ.utf8 (localhost)

if(is_file(CORE_FOLDER ."/init_cms.php")) {
  include(CORE_FOLDER ."/init_cms.php");
  return;
}

http_response_code(500);
echo "CMS core init file not found.";

?>