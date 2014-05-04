<?php

#error_reporting(E_ALL);
#ini_set("display_errors", 1);

# Global constants

define('SOURCE_FOLDER', 'src'); // where objects and other src are stored
define('ADMIN_FOLDER', 'adm'); // where admin cfg xml files are stored
define('USER_FOLDER', 'usr'); // where user cfg xml files are stored
define('DEFAULT_CFG_FILE','cms.xml'); // admin and user cms cfg xml file
define('PLUGIN_FOLDER', 'plugins'); // where plugins are stored

/**
 * Autoload classes from source folder
 */
function __autoload($className) {
  if(is_file(SOURCE_FOLDER . "/$className.php"))
    include SOURCE_FOLDER . "/$className.php";
  else
    include PLUGIN_FOLDER . "/$className/$className.php";
}

// plugin attach into class Plugins (Subject)
$plugins = new Plugins();
foreach(scandir(PLUGIN_FOLDER) as $plugin) {
  // omit folders starting with a dot
  if(substr($plugin,0,1) == ".") continue;
  $plugins->attach(new $plugin);
}

// notify plugins, status init
$plugins->setStatus("init");
$plugins->notify();

// init CMS
$cms = new Cms();
$plugins->setCms($cms);

// notify plugins, status process
$plugins->setStatus("process");
$plugins->notify();

?>
