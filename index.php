  <?php

  error_reporting(E_ALL);
  ini_set("display_errors", 1);

  # Global constants

  define('SOURCE_FOLDER', dirname(__FILE__) . '/src'); // where objects and other src are stored
  define('ADMIN_FOLDER',  dirname(__FILE__) . '/adm'); // where admin cfg xml files are stored
  define('USER_FOLDER',   dirname(__FILE__) . '/usr'); // where user cfg xml files are stored
  define('PLUGIN_FOLDER', dirname(__FILE__) . '/plugins'); // where plugins are stored

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

    // init CMS
    $cms = new Cms();

    // plugin attach into class Plugins (Subject)
    $plugins = new Plugins();
    $plugins->setCms($cms);
    foreach(scandir(PLUGIN_FOLDER) as $plugin) {
      // omit folders starting with a dot
      if(substr($plugin,0,1) == ".") continue;
      $plugins->attach(new $plugin);
    }

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
