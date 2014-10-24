<?php

# TODO:e-mail errors (plugin)

// --------------------------------------------------------------------
// IGCMS CORE
// --------------------------------------------------------------------

include('cls/globals.php');

try {

  $l = new Logger("CMS finished " . dirname(__FILE__),null,false);

  // register core variables
  $cms = new Cms();
  $plugins = new Plugins();

  $cms->setPlugins($plugins);
  $plugins->setStatus("preinit");
  $plugins->notify();

  $cms->init();
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

  if(!$e instanceof LoggerException) try {
    new Logger($e->getMessage(),"fatal");
  } catch (Exception $e) {};

  #http_response_code(500);
  #if(!@include(CMS_FOLDER . "/error.php")) echo $e->getMessage();
  $m = $e->getMessage();
  if(isAtLocalhost()) $m = "Exception: ".$m." in ".$e->getFile()." on line ".$e->getLine();
  errorPage($m,500);

}

?>