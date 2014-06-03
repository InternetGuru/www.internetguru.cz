<?php

/**
 * TODOS:
 * - plugin subset @localhost (@vps)
 * - errory @localhost, @dev
 * - timezone...
 *
 */
#error_reporting(E_ALL);
#ini_set("display_errors", 1);

# Global constants

define('CMS_FOLDER', "cms");
define('CLASS_FOLDER', 'cls'); // where objects and other src are stored
define('ADMIN_FOLDER', 'adm'); // where admin cfg xml files are stored
define('USER_FOLDER', 'usr'); // where user cfg xml files are stored
define('PLUGIN_FOLDER', 'plugins'); // where plugins are stored

/**
 * Autoload classes from source folder
 */
function __autoload($className) {
  if(is_file(CLASS_FOLDER . "/$className.php"))
    include CLASS_FOLDER . "/$className.php";
  elseif(is_file(PLUGIN_FOLDER . "/$className/$className.php"))
    include PLUGIN_FOLDER . "/$className/$className.php";
  elseif(is_file("../" . CMS_FOLDER . "/". CLASS_FOLDER . "/$className.php"))
    include "../" . CMS_FOLDER . "/". CLASS_FOLDER . "/$className.php";
  elseif(is_file("../" . CMS_FOLDER . "/". PLUGIN_FOLDER . "/$className/$className.php"))
    include "../" . CMS_FOLDER . "/". PLUGIN_FOLDER . "/$className/$className.php";
  else
    throw new Exception("Unable to find class $className");
}

try {

  // register core variables
  $cms = new Cms();
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

  echo "Exception: ".$e->getMessage()." in ".$e->getFile()." @ ".$e->getLine();

}

?>
