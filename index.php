<?php

# TODO:e-mail errors (plugin)

// --------------------------------------------------------------------
// IGCMS CORE
// --------------------------------------------------------------------

include('cls/globals.php');

try {

  $l = new Logger("CMS init " . dirname(__FILE__));

  // register core variables
  $cms = new Cms();
  $plugins = new Plugins($cms);

  $cms->setPlugins($plugins);
  $plugins->setStatus("preinit");
  $plugins->notify();

  $cms->init();
  $plugins->setStatus("init");
  $plugins->notify();

  $cms->buildContent();
  $plugins->setStatus("process");
  $plugins->notify();

  $plugins->setStatus("postprocess");
  $plugins->notify();

  echo $cms->getOutput();

  $l->finished();

} catch(Exception $e) {

  if(!$e instanceof LoggerException) try {
    new Logger($e->getMessage(),"fatal");
  } catch (Exception $e) {};

  if(isAtLocalhost()) {
    echo "Exception: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine();
    exit;
  }

  http_response_code(500);
  echo file_get_contents(CMS_FOLDER."/500.html");

}

?>