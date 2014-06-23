<?php

// --------------------------------------------------------------------
// TODOS
// --------------------------------------------------------------------

# log warnings and errors (plugin)
# e-mail errors (plugin)


// --------------------------------------------------------------------
// GLOBAL CONSTANTS
// --------------------------------------------------------------------

define('CMS_FOLDER', "cms");
define('CLASS_FOLDER', 'cls'); // where objects and other src are stored
define('ADMIN_FOLDER', 'adm'); // where admin cfg xml files are stored
define('USER_FOLDER', 'usr'); // where user cfg xml files are stored
define('PLUGIN_FOLDER', 'plugins'); // where plugins are stored
define('TEMPLATE_FOLDER', 'template'); // where templates are stored

#print_r($_SERVER);

// --------------------------------------------------------------------
// GLOBAL FUNCTIONS
// --------------------------------------------------------------------

function isAtLocalhost() {
  if($_SERVER["REMOTE_ADDR"] == "127.0.0.1"
  || substr($_SERVER["REMOTE_ADDR"],0,8) == "192.168."
  || substr($_SERVER["REMOTE_ADDR"],0,3) == "10."
  || $_SERVER["REMOTE_ADDR"] == "::1") {
    return true;
  }
  return false;
}

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


// --------------------------------------------------------------------
// CORE
// --------------------------------------------------------------------

try {

  // register core variables
  $cms = new Cms();
  $plugins = new Plugins($cms);
  $plugins->setStatus("preinit");
  $plugins->notify();

  $cms->init();
  $plugins->setStatus("init");
  $plugins->notify();

  $cms->buildContent();
  $plugins->setStatus("process");
  $plugins->notify();

  $cms->insertCmsVars();
  $plugins->setStatus("postprocess");
  $plugins->notify();

  echo $cms->getOutput();

} catch(Exception $e) {

  echo "Exception: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine();

}

?>