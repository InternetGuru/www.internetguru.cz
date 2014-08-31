<?php

# TODO:log warnings and errors (plugin)
# TODO:e-mail errors (plugin)

// --------------------------------------------------------------------
// IGCMS CORE
// --------------------------------------------------------------------

include('cls/globals.php');

try {

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

} catch(Exception $e) {

  echo "Exception: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine();

}

?>