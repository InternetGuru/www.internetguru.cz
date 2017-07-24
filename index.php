<?php

use IGCMS\Core\Cms;
use IGCMS\Core\DOMBuilder;
use IGCMS\Core\ErrorPage;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugins;

try {

  require 'init.php';

  if (!Cms::isActive()) {
    $error = null;
    $errno = 0;
    if (count($_GET) > 1) {
      $error = _("Query string");
      $errno = 403;
    }
    if (!empty($_POST)) {
      $error = _("POST request");
      $errno = 405;
    }
    if (!is_null(Cms::getLoggedUser())) {
      $error = _("Login");
      $errno = 403;
    }
    if (!is_null($error)) {
      new ErrorPage(sprintf(_("Not supported by inactive system version: %s"), $error), $errno);
    }
  }

  // prevent unauthorized no-cached requests
  if (is_null(Cms::getLoggedUser()) && isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params["path"],
      $params["domain"],
      $params["secure"],
      $params["httponly"]
    );
    redirTo($_SERVER["REQUEST_URI"], null, _("Invalid session cookies removed"));
  }

  if (!stream_resolve_include_path(DEBUG_FILE) && !stream_resolve_include_path(".".DEBUG_FILE)) {
    touch(".".DEBUG_FILE);
  }
  if (!stream_resolve_include_path(PROTECTED_FILE) && !stream_resolve_include_path(".".PROTECTED_FILE)) {
    touch(PROTECTED_FILE);
  }

  Cms::checkAuth();

  if (!stream_resolve_include_path(LANG_FILE)) {
    Logger::warning(sprintf("%s file not found, using default lang %s", LANG_FILE, DEFAULT_LANG));
  }

  if (Cms::isSuperUser()) {
    initIndexFiles();
    if (isset($_GET[CACHE_PARAM]) && $_GET[CACHE_PARAM] == CACHE_NGINX) {
      try {
        clearNginxCache();
        Logger::user_success(_("Cache successfully purged"));
      } catch (Exception $e) {
        Logger::critical($e->getMessage());
      }
    }
  }

  // remove ?login form url
  if (isset($_GET["login"])) {
    parse_str($_SERVER['QUERY_STRING'], $query);
    unset($query["login"]);
    unset($query["q"]);
    redirTo(buildLocalUrl(["path" => getCurLink(), "query" => buildQuery($query, false)]));
  }

  $plugins = new Plugins();
  HTMLPlusBuilder::register(INDEX_HTML);
  $plugins->setStatus(STATUS_PREINIT);
  $plugins->notify();

  Cms::init(); // because of dombuilder to set variable into cms
  $plugins->setStatus(STATUS_INIT);
  $plugins->notify();

  $content = Cms::buildContent();
  $plugins->setStatus(STATUS_PROCESS);
  $plugins->notify();

  #var_dump(HTMLPlusBuilder::getIdToLink());
  #var_dump(HTMLPlusBuilder::getIdToParentId());
  #var_dump(HTMLPlusBuilder::getUriToInt("pavel_petrzela"));
  #var_dump(HTMLPlusBuilder::getIntToParentInt());
  #die("die");

  $content = Cms::contentProcessVariables($content);
  $plugins->setStatus(STATUS_POSTPROCESS);
  $plugins->notify();

  if (Cms::isSuperUser()
    && Cms::getLoggedUser() != SERVER_USER
    && DOMBuilder::isCacheOutdated()
  ) {
    Logger::user_notice(_("Server cache (nginx) is outdated"));
  }

  Cms::getMessages();
  $content = Cms::contentProcessVariables($content);
  echo Cms::getOutput($content);

} catch (Exception $e) {

  $errno = $e->getCode() ? $e->getCode() : 500;
  $m = $e->getMessage();
  if (CMS_DEBUG) {
    $m = sprintf(_("%s in %s on line %s"), $m, $e->getFile(), $e->getLine());
  }
  $m = sprintf(_("IGCMS failed to finish: %s"), $m);
  if (class_exists("IGCMS\\Core\\ErrorPage")) {
    new ErrorPage($m, $errno);
  }

  Logger::alert($m);
  http_response_code($errno);
  echo $m;

} finally {

  session_write_close();

}

?>
