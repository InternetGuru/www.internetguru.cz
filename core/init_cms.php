<?php
try {

  $start_time = microtime(true);
  require_once(dirname(__FILE__) .'/global_func.php');
  require_once(dirname(__FILE__) .'/global_const.php');
  $l = new Logger("CMS init " . dirname(__FILE__), null, microtime(true) - $start_time);
  $l->finished();
  $l = new Logger("CMS finished " . dirname(__FILE__), null, 0);

  $plugins = new Plugins();
  $plugins->setStatus("preinit");
  $plugins->notify();

  checkUrl();
  $cms = new Cms();
  $cms->init(); // because of dombulder to set variable into cms
  $plugins->setStatus("init");
  $plugins->notify();

  $cms->buildContent();
  $plugins->setStatus("process");
  $plugins->notify();

  $cms->processVariables();
  $plugins->setStatus("postprocess");
  $plugins->notify();

  echo $cms->getOutput();
  duplicateDir(USER_FOLDER);
  duplicateDir(ADMIN_FOLDER);
  $l->finished();

} catch(Exception $e) {

  $m = $e->getMessage();
  if(isAtLocalhost()) $m = "Exception: ".$m." in ".$e->getFile()." on line ".$e->getLine();
  if(isset($l)) $l->finished();
  new ErrorPage($m, 500, true);

}

?>