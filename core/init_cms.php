<?php
try {

  $start_time = microtime(true);
  session_cache_limiter("public");
  session_start();
  require_once('global_func.php');
  require_once('global_const.php');
  proceedServerInit("InitServer.php");
  //////////////////////
  // patch 0.3 to 0.4
  if(is_file(USER_FOLDER."/Content.html") && !is_file(USER_FOLDER."/".INDEX_HTML))
    rename(USER_FOLDER."/Content.html", USER_FOLDER."/".INDEX_HTML);
  //////////////////////
  new Logger(CMS_NAME, Logger::LOGGER_INFO, $start_time);

  $start_time = microtime(true);
  $plugins = new Plugins();
  $plugins->setStatus(STATUS_PREINIT);
  $plugins->notify();

  checkUrl();
  Cms::init(); // because of dombulder to set variable into cms
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
  if(defined("SUBDOM_FOLDER")) duplicateDir(SUBDOM_FOLDER, false);

  $out = Cms::getOutput();
  //get a unique hash of this file (etag)
  $etagFile = hash("md5", $out);
  //get the HTTP_IF_NONE_MATCH header if set (etag: unique file hash)
  $etagHeader=(isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false);
  //set etag-header
  header("Etag: $etagFile");
  //check if page has changed. If not, send 304 and exit
  if ($etagHeader == $etagFile) {
    header("HTTP/1.1 304 Not Modified");
    new Logger(_("Page not modified"), Logger::LOGGER_INFO);
  } else {
    echo $out;
  }

  new Logger(sprintf(_("IGCMS successfully finished"), CMS_RELEASE), Logger::LOGGER_INFO, $start_time);

} catch(Exception $e) {

  $m = $e->getMessage();
  if(CMS_DEBUG) $m = sprintf(_("Exception: %s in %s on line %s"), $m, $e->getFile(), $e->getLine());
  new Logger(sprintf(_("IGCMS failed to finish"), CMS_RELEASE), Logger::LOGGER_FATAL, $start_time);
  if(class_exists("ErrorPage")) new ErrorPage($m, 500, true);

  http_response_code(500);
  echo $m;

}

?>