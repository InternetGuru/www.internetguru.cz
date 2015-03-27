<?php
try {

  $start_time = microtime(true);
  define("INDEX_HTML", "index.html");
  define("NGINX_CACHE_FOLDER", "/var/cache/nginx");
  define("PLUGINS_DIR", "plugins");
  define("THEMES_DIR", "themes");
  define("CMS_DIR", "cms");
  define("VER_DIR", "ver");
  define("LIB_DIR", "lib");
  define("CORE_DIR", "core");
  define("FILES_DIR", "files");
  define("SERVER_FILES_DIR", "_server");
  define("LOG_DIR", "cmslog");
  define("DEBUG_FILE", "DEBUG");
  define("FORBIDDEN_FILE", "FORBIDDEN");
  define("ADMIN_ROOT_DIR", "cmsadmin");
  define("USER_ROOT_DIR", "cmsuser");
  define("FILE_LOCK_WAIT_SEC", 2);
  define('EMAIL_PATTERN', "[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}");
  define('SUBDOM_PATTERN', "[a-z][a-z0-9]*");
  define('VARIABLE_PATTERN', '(?:[a-z]+-)?[a-z_]+');
  define('FILEPATH_PATTERN', "(?:[.a-zA-Z0-9_-]+\/)*[a-zA-Z0-9._-]+\.[a-zA-Z0-9]{2,4}");
  define('FILE_HASH_ALGO', 'crc32b');
  define('STATUS_PREINIT', 'preinit');
  define('STATUS_INIT', 'init');
  define('STATUS_PROCESS', 'process');
  define('STATUS_POSTPROCESS', 'postprocess');
  define("IS_LOCALHOST", (!isset($_SERVER["REMOTE_ADDR"])
    || $_SERVER["REMOTE_ADDR"] == "127.0.0.1"
    || strpos($_SERVER["REMOTE_ADDR"], "192.168.") === 0
    || strpos($_SERVER["REMOTE_ADDR"], "10.") === 0
    || $_SERVER["REMOTE_ADDR"] == "::1"));

  if(IS_LOCALHOST) {
    define('CURRENT_SUBDOM', basename(getcwd()));
    define('DOMAIN', "localhost");
    $dir = explode("/", $_SERVER["SCRIPT_NAME"]);
    define('ROOT_URL', "/".$dir[1]."/");
    define('HOST', DOMAIN."/".CURRENT_SUBDOM);
    define('CMS_RELEASE', "localhost");
    define("WWW_FOLDER", "..");
    define("CMS_FOLDER", WWW_FOLDER."/".CMS_DIR);
    define('CMS_DEBUG', true);
    define('ADMIN_ID', "localhost");
    define('ADMIN_FOLDER', ADMIN_ROOT_DIR);
    define('USER_FOLDER', USER_ROOT_DIR);
    define('LOG_FOLDER', LOG_DIR);
    #define("APACHE_RESTART_FILEPATH", null);
  } else {
    define('HOST', basename(getcwd()));
    $hostArr = explode(".", HOST);
    define('DOMAIN', $hostArr[count($hostArr)-2].".".$hostArr[count($hostArr)-1]);
    define('CURRENT_SUBDOM', substr(HOST, 0, -(strlen(DOMAIN)+1)));
    define('ROOT_URL', "/");
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
    define('ADMIN_ROOT_FOLDER', WWW_FOLDER."/".ADMIN_ROOT_DIR);
    define('USER_ROOT_FOLDER', WWW_FOLDER."/".USER_ROOT_DIR);
    define('ADMIN_FOLDER', ADMIN_ROOT_FOLDER."/".HOST);
    define('USER_FOLDER', USER_ROOT_FOLDER."/".ADMIN_ID."/".HOST);
    define('LOG_FOLDER', WWW_FOLDER."/".LOG_DIR."/".HOST);
  }
  define("SCHEME", (@$_SERVER["HTTPS"] == "on" ? "https" : "http"));
  define("URL", SCHEME."://".HOST);
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

  #if(Cms::isSuperUser()) duplicateDir(USER_FOLDER);
  #if(Cms::isSuperUser()) duplicateDir(ADMIN_FOLDER);
  echo Cms::getOutput();
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

session_write_close();

?>