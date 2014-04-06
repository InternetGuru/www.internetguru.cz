<?php 

# Global constants

define('SOURCE_FOLDER', 'src');


/**
 * Autoload classes from source folder
 */
function __autoload($className) {
  include SOURCE_FOLDER . "/$className.php";
}

$cfg = new Config();

?>