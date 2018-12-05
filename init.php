<?php

require __DIR__.'/vendor/autoload.php';

session_cache_limiter("");

define("SERVER_USER", "server");
define("INDEX_HTML", "index.html");
define("INDEX_PHP", "index.php");
define("FINDEX_PHP", "findex.php");
define("ROBOTS_TXT", "robots.txt");
define("INOTIFY", ".inotify");
define("NGINX_CACHE_FOLDER", "/var/cache/nginx");
define("PLUGINS_DIR", "plugins");
define("THEMES_DIR", "themes");
define("RESOURCES_DIR", "res");
define("CMS_DIR", "cms");
define("CMSRES_DIR", "cmsres");
define("VER_DIR", "ver");
define("LIB_DIR", "lib");
define("VENDOR_DIR", "vendor");
define("CORE_DIR", "core");
define("FILES_DIR", "files");
define("SERVER_FILES_DIR", "_server");
define("LOG_DIR", "log");
define("DEBUG_FILE", "DEBUG");
define('CMS_VERSION_FILENAME', "VERSION");
define('CMS_CHANGELOG_FILENAME', "CHANGELOG.md");
define('NOT_FOUND_IMG_FILENAME', "notfound.png");
define("DEFAULT_LANG", "en_US");
define("LANG_FILE", "LANG");
define("HTTPS_FILE", "HTTPS");
define('CACHE_PARAM', "Cache");
define('CACHE_IGNORE', "ignore");
define('CACHE_FILE', "file");
define('CACHE_NGINX', "nginx");
define('PAGESPEED_PARAM', "PageSpeed");
define('PAGESPEED_OFF', "off");
define('DEBUG_PARAM', "Debug");
define('DEBUG_ON', "on");
define("PROTECTED_FILE", "PROTECTED");
define("AUTOCORRECT_FILE", "AUTOCORRECT");
define("ADMIN_ROOT_DIR", "admin");
define("USER_ROOT_DIR", "user");
define("FILE_LOCK_WAIT_SEC", 4);
define(
  'W3C_DATETIME_PATTERN',
  '(19|20)\d\d(-(0[1-9]|1[012])(-(0[1-9]|[12]\d|3[01])(T([01]\d|2[0-3]):[0-5]\d:[0-5]\d[+-][01]\d:00)?)?)?'
);
define('EMAIL_PATTERN', '([_a-zA-Z0-9-]+(?:\.[_a-zA-Z0-9-]+)*)@([a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*)\.([a-zA-Z]{2,})');
define('VARIABLE_PATTERN', '(?:[a-z]+-)?[a-z0-9_-]+');
#define('FILEPATH_PATTERN', '(?:[a-zA-Z0-9_-][a-zA-Z0-9._-]*\/)*[a-zA-Z0-9_-][a-zA-Z0-9._-]*\.[a-zA-Z0-9]{2,4}');
define('FILEPATH_PATTERN', '(?:[.a-zA-Z0-9_-]+\/)*[a-zA-Z0-9._-]+\.[a-zA-Z0-9]{2,4}');
define('FILE_HASH_ALGO', 'crc32b');
define('SCRIPT_NAME', basename($_SERVER["SCRIPT_NAME"]));
define('STATUS_PREINDEX', 'preindex');
define('STATUS_PREINIT', 'preinit');
define('STATUS_INIT', 'init');
define('STATUS_PROCESS', 'process');
define('STATUS_POSTPROCESS', 'postprocess');
define('APC_PREFIX', 2); // change if APC structure changes
define('HTTP_HOST', $_SERVER["HTTP_HOST"]);
define('DOMAIN', substr(HTTP_HOST, strpos(HTTP_HOST, ".")+1));
define('ROOT_URL', "/");
define('CMS_RELEASE', basename(dirname(__FILE__)));
define("WWW_FOLDER", "/var/www");
define("CMS_ROOT_FOLDER", WWW_FOLDER."/".CMS_DIR);
define("CMS_FOLDER", CMS_ROOT_FOLDER."/".CMS_RELEASE);
define("CMSRES_FOLDER", WWW_FOLDER."/".CMSRES_DIR."/".CMS_RELEASE);
define('ADMIN_ID', is_file("ADMIN") ? trim(file_get_contents("ADMIN")) : null);
define('ADMIN_ROOT_FOLDER', WWW_FOLDER."/".ADMIN_ROOT_DIR);
define('USER_ROOT_FOLDER', WWW_FOLDER."/".USER_ROOT_DIR);
define('ADMIN_FOLDER', ADMIN_ROOT_FOLDER."/".HTTP_HOST);
define('USER_FOLDER', USER_ROOT_FOLDER."/".ADMIN_ID."/".HTTP_HOST);
define("WATCH_USER_FILEPATH", USER_FOLDER."/.watch_user");
define("WATCH_USER_FILEPATH_TMP", WATCH_USER_FILEPATH.".tmp");
define('LOG_FOLDER', WWW_FOLDER."/".LOG_DIR."/".HTTP_HOST);
define('CMS_DEBUG', is_file(DEBUG_FILE));
define("SCHEME", (@$_SERVER["HTTPS"] == "on" ? "https" : "http"));
define("HTTP_URL", SCHEME."://".HTTP_HOST);
define("HTTP_URI", HTTP_URL.@$_SERVER["REQUEST_URI"]);
define("CORE_FOLDER", CMS_FOLDER."/".CORE_DIR);
define('PLUGINS_FOLDER', CMS_FOLDER."/".PLUGINS_DIR);
define('LIB_FOLDER', CMS_FOLDER."/".LIB_DIR);
define('VER_FOLDER', CMS_FOLDER."/".VER_DIR);
define('THEMES_FOLDER', USER_FOLDER."/".THEMES_DIR);
define('FILES_FOLDER', USER_FOLDER."/".FILES_DIR);
define('CMS_VERSION', trim(file_get_contents(CMS_FOLDER."/".CMS_VERSION_FILENAME)));
$verfile = getcwd()."/".CMS_VERSION_FILENAME;
define('DEFAULT_RELEASE', is_file($verfile) ? trim(file_get_contents($verfile)) : CMS_RELEASE);
define('CMS_LANG', is_file(LANG_FILE) ? trim(file_get_contents(LANG_FILE)) : DEFAULT_LANG);
define('CMS_STAGE', strpos(CMS_VERSION, CMS_RELEASE) === 0 ? "stable" : CMS_RELEASE);
define('CMS_NAME', "IGCMS ".CMS_VERSION."-".CMS_STAGE."-".CMS_LANG.(CMS_DEBUG ? "-debug" : ""));
define('AUTOCORRECT', stream_resolve_include_path(AUTOCORRECT_FILE));
define('REQUEST_TOKEN', "rt".rand());
#print_r(get_defined_constants(true)); die();
date_default_timezone_set("Europe/Prague");

if (CMS_DEBUG) {
  error_reporting(E_ALL);
  ini_set("display_errors", 1);
  setlocale(LC_ALL, DEFAULT_LANG.".UTF-8");
} else {
  setlocale(LC_ALL, CMS_LANG.".UTF-8");
  putenv("LANG=".CMS_LANG.".UTF-8"); // for gettext
  bindtextdomain("messages", LIB_FOLDER."/locale");
  textdomain("messages");
}

define('METHOD_NA', _("Method %s is no longer available"));
if (is_null(ADMIN_ID)) {
  die(_("Domain is ready to be acquired"));
}
require_once CORE_FOLDER.'/globals.php';
if (isset($_GET["login"]) && SCHEME == "http") {
  login_redir();
}
if (update_file(CMS_FOLDER."/".SERVER_FILES_DIR."/".SCRIPT_NAME, SCRIPT_NAME, true)
  || update_file(CMS_FOLDER."/".SERVER_FILES_DIR."/".FINDEX_PHP, FINDEX_PHP, true)
) {
  redir_to($_SERVER["REQUEST_URI"], null, _("Root file(s) updated"));
}
$dirs = [
  USER_FOLDER, LOG_FOLDER, FILES_FOLDER, THEMES_FOLDER,
  LIB_DIR, FILES_DIR, THEMES_DIR, PLUGINS_DIR, VENDOR_DIR,
];
foreach ($dirs as $dir) {
  mkdir_plus($dir);
}
