<?php
try {

  $start_time = microtime(true);
  require_once('global_func.php');
  require_once('global_const.php');
  if(!isAtLocalhost()) {
    if(!is_file(CMS_ROOT_FOLDER."/InitServer.php")) throw new Exception("Missing server init file");
    require_once(CMS_ROOT_FOLDER."/InitServer.php");
    new InitServer(CURRENT_SUBDOM_DIR, true);
    if(isset($_GET["updateSubdom"])) {
      $subdom = CURRENT_SUBDOM_DIR;
      if(strlen($_GET["updateSubdom"])) $subdom = $_GET["updateSubdom"];
      new InitServer($subdom, false, true);
      redirTo("http://$subdom.". getDomain());
    }
  }
  #require_once(CORE_FOLDER.'/global_func2.php');

  $l = new Logger("CMS init ".CMS_RELEASE.", v. ".CMS_VERSION
    .(CMS_DEBUG ? " (DEBUG)" : ""), null, microtime(true) - $start_time);
  $l->finished();
  $l = new Logger("CMS finished ".CMS_RELEASE, null, 0);

  $plugins = new Plugins();
  $plugins->setStatus(STATUS_PREINIT);
  $plugins->notify();

  checkUrl();
  $cms = new Cms();
  $cms->init(); // because of dombulder to set variable into cms
  $plugins->setStatus(STATUS_INIT);
  $plugins->notify();

  $cms->buildContent();
  $plugins->setStatus(STATUS_PROCESS);
  $plugins->notify();

  $cms->processVariables();
  $plugins->setStatus(STATUS_POSTPROCESS);
  $plugins->notify();

  echo $cms->getOutput();
  duplicateDir(USER_FOLDER);
  duplicateDir(ADMIN_FOLDER);
  $l->finished();

} catch(Exception $e) {

  $m = $e->getMessage();
  if(isAtLocalhost()) $m = "Exception: ".$m." in ".$e->getFile()." on line ".$e->getLine();
  if(isset($l)) $l->finished();
  if(class_exists("ErrorPage")) new ErrorPage($m, 500, true);

  http_response_code(500);
  echo $m;

}

?>