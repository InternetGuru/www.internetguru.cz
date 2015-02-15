<?php
try {

  $start_time = microtime(true);
  session_cache_limiter("");
  session_start();

  define("SERVER_IP", "46.28.109.142");
  define("INDEX_HTML", "index.html");
  define("PLUGINS_DIR", "plugins");
  define("THEMES_DIR", "themes");
  define("CMS_DIR", "cms");
  define("VER_DIR", "ver");
  define("LIB_DIR", "lib");
  define("CORE_DIR", "core");
  define("FILES_DIR", "files");
  define("LOG_DIR", "cmslog");
  define("DEBUG_FILE", "DEBUG");
  define("FORBIDDEN_FILE", "FORBIDDEN");
  define('SUBDOM_ROOT_DIR', "subdom");
  define("ADMIN_ROOT_DIR", "cmsadmin");
  define("USER_ROOT_DIR", "cmsuser");
  define("ADMIN_BACKUP_DIR", ADMIN_ROOT_DIR.".bak");
  define("USER_BACKUP_DIR", USER_ROOT_DIR.".bak");
  define('EMAIL_PATTERN', "[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}");
  define('SUBDOM_PATTERN', "[a-z][a-z0-9]*");
  define('VARIABLE_PATTERN', '(?:[a-z]+-)?[a-z_]+');
  define('FILEPATH_PATTERN', "(?:[.a-zA-Z0-9_-]+\/)*[a-zA-Z0-9._-]+\.[a-zA-Z0-9]{2,4}");
  define('FILE_HASH_ALGO', 'crc32b');
  define('STATUS_PREINIT', 'preinit');
  define('STATUS_INIT', 'init');
  define('STATUS_PROCESS', 'process');
  define('STATUS_POSTPROCESS', 'postprocess');
  define('CURRENT_SUBDOM_FOLDER', getcwd());
  define('CURRENT_SUBDOM_DIR', basename(CURRENT_SUBDOM_FOLDER));
  define("IS_LOCALHOST", (!isset($_SERVER["REMOTE_ADDR"])
    || $_SERVER["REMOTE_ADDR"] == "127.0.0.1"
    || strpos($_SERVER["REMOTE_ADDR"], "192.168.") === 0
    || strpos($_SERVER["REMOTE_ADDR"], "10.") === 0
    || $_SERVER["REMOTE_ADDR"] == "::1"));

  if(IS_LOCALHOST) {
    define('DOMAIN', "localhost");
    $dir = explode("/", $_SERVER["SCRIPT_NAME"]);
    define('ROOT_URL', "/".$dir[1]."/");
    define('HOST', DOMAIN."/".CURRENT_SUBDOM_DIR);
    define('CMS_RELEASE', "localhost");
    define("WWW_FOLDER", "..");
    define("CMS_FOLDER", WWW_FOLDER."/".CMS_DIR);
    define('CMS_DEBUG', true);
    define('ADMIN_ID', "Localhost");
    define('ADMIN_FOLDER', ADMIN_ROOT_DIR);
    define('USER_FOLDER', USER_ROOT_DIR);
    define('ADMIN_BACKUP_FOLDER', ADMIN_BACKUP_DIR);
    define('USER_BACKUP_FOLDER', USER_BACKUP_DIR);
    define('LOG_FOLDER', LOG_DIR);
    #define("APACHE_RESTART_FILEPATH", null);
  } else {
    $cwdArray = explode("/", CURRENT_SUBDOM_FOLDER);
    define('DOMAIN', $cwdArray[4]);
    define('ROOT_URL', "/");
    define('HOST', CURRENT_SUBDOM_DIR.".".DOMAIN);
    define('CMS_RELEASE', basename(dirname(__FILE__)));
    define("WWW_FOLDER", "/var/www");
    define("CMS_ROOT_FOLDER", WWW_FOLDER."/".CMS_DIR);
    define("CMS_FOLDER", CMS_ROOT_FOLDER."/".CMS_RELEASE);
    define('CMS_DEBUG', is_file(DEBUG_FILE));
    $userId = null;
    foreach(scandir(getcwd()) as $f) {
      $varName = substr($f, 0, 6);
      if($varName != "ADMIN.") continue; // eg. ADMIN.ig1
      $userId = substr($f, 6);
    }
    define('ADMIN_ID', $userId);
    define('CMSRES_ROOT_DIR', "cmsres");
    define('CMSRES_ROOT_FOLDER', WWW_FOLDER."/".CMSRES_ROOT_DIR);
    #define("APACHE_RESTART_FILEPATH", CMSRES_ROOT_FOLDER."/APACHE_RESTART");
    define('RES_DIR', "res");
    define('ADMIN_ROOT_FOLDER', WWW_FOLDER."/".ADMIN_ROOT_DIR);
    define('USER_ROOT_FOLDER', WWW_FOLDER."/".USER_ROOT_DIR);
    define('ADMIN_FOLDER', ADMIN_ROOT_FOLDER."/".HOST);
    define('USER_FOLDER', USER_ROOT_FOLDER."/".ADMIN_ID."/".HOST);
    define('ADMIN_BACKUP_FOLDER', WWW_FOLDER."/".ADMIN_BACKUP_DIR."/".HOST);
    define('USER_BACKUP_FOLDER', WWW_FOLDER."/".USER_BACKUP_DIR."/".HOST);
    define('LOG_FOLDER', WWW_FOLDER."/".LOG_DIR."/".HOST);
  }
  $protocol = "http";
  if(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") $protocol = "https";
  define("URL", "$protocol://".HOST);
  define("URI", URL.(isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : ""));
  define("CORE_FOLDER", CMS_FOLDER."/".CORE_DIR);
  define('PLUGINS_FOLDER', CMS_FOLDER."/".PLUGINS_DIR);
  define('LIB_FOLDER', CMS_FOLDER."/".LIB_DIR);
  define('VER_FOLDER', CMS_FOLDER."/".VER_DIR);
  define('THEMES_FOLDER', USER_FOLDER."/".THEMES_DIR);
  define('FILES_FOLDER', USER_FOLDER."/".FILES_DIR);
  define('CMS_VERSION_FILENAME', "cms_version.txt");
  define('CMS_VERSION', file_get_contents(CMS_FOLDER."/".CMS_VERSION_FILENAME));
  define('CMS_NAME', "IGCMS ".CMS_RELEASE."/".CMS_VERSION.(CMS_DEBUG ? " DEBUG_MODE" : ""));
  #print_r(get_defined_constants(true)); die();
  #todo: date_default_timezone_set()
  #todo: localize lang

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

  define('METHOD_NA', _("Method %s is no longer available"));
  if(is_null(ADMIN_ID)) die(_("Domain is ready to be acquired"));

  require_once(CORE_FOLDER.'/globals.php');
  new Logger(CMS_NAME, Logger::LOGGER_INFO, $start_time, false);
  initDirs();
  if(!IS_LOCALHOST) initLinks();
  initFiles();

  Cms::checkAuth();
  $start_time = microtime(true);
  $plugins = new Plugins();
  $plugins->setStatus(STATUS_PREINIT);
  $plugins->notify();

  Cms::init(); // because of dombuilder to set variable into cms
  $plugins->setStatus(STATUS_INIT);
  $plugins->notify();

  Cms::buildContent();
  $plugins->setStatus(STATUS_PROCESS);
  $plugins->notify();

  Cms::contentProcessVariables();
  $plugins->setStatus(STATUS_POSTPROCESS);
  $plugins->notify();

  duplicateDir(USER_FOLDER);
  duplicateDir(ADMIN_FOLDER);

  #header("Cache-Control: public, max-age=10800, must-revalidate");
  $out = Cms::getOutput();
  //get a unique hash of this file (etag)
  $etagFile = hash("md5", $out);
  //get the HTTP_IF_NONE_MATCH header if set (etag: unique file hash)
  $etagHeader=(isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false);
  //set etag-header
  header("Etag: $etagFile");
  //check if page has changed. If not, send 304 and exit
  if(IS_LOCALHOST || CMS_DEBUG || $etagHeader != $etagFile
    || preg_match("/^\D/", CMS_RELEASE)) {
    echo $out;
  } else {
    header("HTTP/1.1 304 Not Modified");
    http_response_code(304);
  }

  new Logger(sprintf(_("IGCMS successfully finished"), CMS_RELEASE), Logger::LOGGER_INFO, $start_time, false);

} catch(Exception $e) {

  $m = $e->getMessage();
  if(CMS_DEBUG) $m = sprintf(_("%s in %s on line %s"), $m, $e->getFile(), $e->getLine());
  $m = sprintf(_("IGCMS failed to finish: %s"), $m);
  new Logger($m, Logger::LOGGER_FATAL, $start_time, false);
  if(class_exists("ErrorPage")) new ErrorPage($m, 500, true);

  http_response_code(500);
  echo $m;

}

?>