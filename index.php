<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

# Global constants

define('SOURCE_FOLDER', __DIR__ . '/src'); // where objects and other src are stored
define('ADMIN_FOLDER', __DIR__ . '/adm'); // where admin cfg xml files are stored
define('USER_FOLDER', __DIR__ . '/usr'); // where user cfg xml files are stored
define('PLUGIN_FOLDER', __DIR__ . '/plugins'); // where plugins are stored
define('BACKUP_FOLDER', __DIR__ . '/bck'); // where user backup files are stored

/**
 * Autoload classes from source folder
 */
function __autoload($className) {
  if(is_file(SOURCE_FOLDER . "/$className.php"))
    include SOURCE_FOLDER . "/$className.php";
  else
    include PLUGIN_FOLDER . "/$className/$className.php";
}

try {

  // register core variables
  $domBuilder = new DOMBuilder();
  $cms = new Cms($domBuilder);
  $plugins = new Plugins($cms);

  // notify plugins, status init
  $plugins->setStatus("preinit");
  $plugins->notify();

  // init CMS
  $cms->init();

  // notify plugins, status init
  $plugins->setStatus("init");
  $plugins->notify();

  // notify plugins, status process
  $plugins->setStatus("process");
  $plugins->notify();

  echo $cms->getOutput();

} catch(Exception $e) {

  echo "Exception: ".$e->getMessage();

}

?>
