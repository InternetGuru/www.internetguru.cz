<?php

# TODO:e-mail errors (plugin)

// --------------------------------------------------------------------
// IGCMS CORE
// --------------------------------------------------------------------

include('cls/globals.php');

try {

  $l = new Logger("CMS finished " . dirname(__FILE__),null,false);

  $plugins = new Plugins();
  $plugins->setStatus("preinit");
  $plugins->notify();

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
  backupDir(USER_FOLDER);
  backupDir(ADMIN_FOLDER);
  $l->finished();

} catch(Exception $e) {

  $m = $e->getMessage();
  if(isAtLocalhost()) $m = "Exception: ".$m." in ".$e->getFile()." on line ".$e->getLine();
  errorPage($m,500);
  if(isset($l)) $l->finished();

}

?>