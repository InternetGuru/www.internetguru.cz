<?php 

# Global constants

define('SOURCE_FOLDER', 'src'); // where objects and other src are stored
define('ADMIN_FOLDER', 'adm'); // where admin cfg xml files are stored
define('USER_FOLDER', 'usr'); // where user cfg xml files are stored

/**
 * Autoload classes from source folder
 */
function __autoload($className) {
  include SOURCE_FOLDER . "/$className.php";
}

try {

  $cfg = new Config();

} catch(Exception $e) {

  echo "Exception: ".$e->getMessage();

}

?>