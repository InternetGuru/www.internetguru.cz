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

  try {
    new Logger($e->getMessage(),"error");
  } catch (Exception $e) {};
  echo "Exception: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine();

}

?>