<?php

# TODO:log warnings and errors (plugin)
# TODO:e-mail errors (plugin)

// --------------------------------------------------------------------
// IGCMS CORE
// --------------------------------------------------------------------

include('cls/globals.php');

try {

  #log:start
  $start_time = microtime(true);
  new Logger("CMS init","info");

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

  $durms = round((microtime(true) - $start_time)*1000);
  new Logger("CMS finished in $durms ms","info");

} catch(Exception $e) {

  new Logger($e->getMessage(),"error");
  echo "Exception: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine();

}

?>