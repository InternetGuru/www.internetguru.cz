<?php
try {

  $start_time = microtime(true);
  require_once('global_func.php');
  require_once('global_const.php');
  $l = new Logger(CMS_NAME, null, microtime(true) - $start_time);
  proceedServerInit("InitServer.php");
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