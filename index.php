<?php
try {

  include("init.php");

  if(!Cms::isActive()) {
    $error = null;
    $errno = 0;
    if (!empty($_POST)) {
      $error = "POST";
      $errno = 405;
    }
    if(!is_null(Cms::getLoggedUser())) {
      $error = "Login";
      $errno = 403;
    }
    if(!is_null($error))
      new ErrorPage(sprintf(_("%s disallowed on inactve CMS version"), $error), $errno);
  }

  // prevent unauthorized no-cached requests
  if(is_null(Cms::getLoggedUser()) && isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"],
      $params["secure"], $params["httponly"]
    );
    redirTo($_SERVER["REQUEST_URI"], null, _("Invalid session cookies removed"));
  }

  if(!IS_LOCALHOST) initLinks();
  if(!file_exists(DEBUG_FILE) && !file_exists(".".DEBUG_FILE)) touch(".".DEBUG_FILE);
  if(!file_exists(FORBIDDEN_FILE) && !file_exists(".".FORBIDDEN_FILE)) touch(FORBIDDEN_FILE);

  Cms::checkAuth();
  if(Cms::isSuperUser() && isset($_GET[CACHE_PARAM]) && $_GET[CACHE_PARAM] == CACHE_NGINX) {
    try {
      clearNginxCache();
      Logger::log(_("Cache successfully purged"), Logger::LOGGER_SUCCESS);
    } catch(Exception $e) {
      Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
    }
  }

  // remove ?login form url
  if(isset($_GET["login"])) {
    parse_str($_SERVER['QUERY_STRING'], $query);
    unset($query["login"]);
    unset($query["q"]);
    redirTo(buildLocalUrl(array("path" => getCurLink(), "query" => buildQuery($query, false))));
  }

  $start_time = microtime(true);
  DOMBuilder::setCacheMtime();

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

  Cms::getMessages();
  Cms::contentProcessVariables();
  echo Cms::getOutput();
  Logger::log(sprintf(_("IGCMS successfully finished"), CMS_RELEASE), Logger::LOGGER_INFO, $start_time, false);

} catch(Exception $e) {

  $errno = $e->getCode() ? $e->getCode() : 500;
  $m = $e->getMessage();
  if(CMS_DEBUG) $m = sprintf(_("%s in %s on line %s"), $m, $e->getFile(), $e->getLine());
  $m = sprintf(_("IGCMS failed to finish: %s"), $m);
  Logger::log($m, Logger::LOGGER_FATAL, null, false);
  if(class_exists("ErrorPage")) new ErrorPage($m, $errno, true);

  http_response_code($errno);
  echo $m;

} finally {

  session_write_close();

}

?>
