<?php

use IGCMS\Core\Cms;
use IGCMS\Core\Logger;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\ErrorPage;
use IGCMS\Core\Plugins;

try {

  require 'init.php';

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
      Logger::user_success(_("Cache successfully purged"));
    } catch(Exception $e) {
      Logger::critical($e->getMessage());
    }
  }

  // remove ?login form url
  if(isset($_GET["login"])) {
    parse_str($_SERVER['QUERY_STRING'], $query);
    unset($query["login"]);
    unset($query["q"]);
    redirTo(buildLocalUrl(array("path" => getCurLink(), "query" => buildQuery($query, false))));
  }

  $plugins = new Plugins();
  $plugins->setStatus(STATUS_PREINIT);
  $plugins->notify();

  Cms::init(); // because of dombuilder to set variable into cms
  $plugins->setStatus(STATUS_INIT);
  $plugins->notify();

  Cms::buildContent();
  $plugins->setStatus(STATUS_PROCESS);
  $plugins->notify();


  var_dump(HTMLPlusBuilder::getIdToParentId());
  #var_dump(HTMLPlusBuilder::getUriToInt("pavel_petrzela"));
  #var_dump(HTMLPlusBuilder::getIntToParentInt());
  die("die");

  Cms::contentProcessVariables();
  $plugins->setStatus(STATUS_POSTPROCESS);
  $plugins->notify();

  Cms::getMessages();
  Cms::contentProcessVariables();
  echo Cms::getOutput();

} catch(Exception $e) {

  $errno = $e->getCode() ? $e->getCode() : 500;
  $m = $e->getMessage();
  if(CMS_DEBUG) $m = sprintf(_("%s in %s on line %s"), $m, $e->getFile(), $e->getLine());
  $m = sprintf(_("IGCMS failed to finish: %s"), $m);
  if(class_exists("IGCMS\Core\ErrorPage")) new ErrorPage($m, $errno);

  Logger::alert($m);
  http_response_code($errno);
  echo $m;

} finally {

  session_write_close();

}

?>
