<?php 

# Global constants

define('SOURCE_FOLDER', 'src');


/**
 * Autoload classes from source folder
 */
function __autoload($className) {
  include "src" . $className;
}



?>