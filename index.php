<?php

if(is_link(basename(__FILE__))) define('CMS_FOLDER', dirname(readlink(basename(__FILE__))));
else define('CMS_FOLDER', "../cms");
define('CMS_VERSION', file_get_contents(CMS_FOLDER ."/cms_version.txt"));
define('CORE_FOLDER', 'core');
define('SUBDOM_PATTERN', "[a-z][a-z0-9]*");
define('VARIABLE_PATTERN', '(?:[a-z]+-)?[a-z_]+');
define('FILEPATH_PATTERN', "(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9._-]+\.[a-z0-9]{2,4}");
define('FILE_HASH_ALGO', 'crc32b');

if(substr(CMS_VERSION,-4) == "-dev") {
  error_reporting(E_ALL);
  ini_set("display_errors", 1);
}

#todo: date_default_timezone_set()
#todo: setlocale(LC_ALL, czech); // cs_CZ.utf8 (localhost)

$init_cms_path = CMS_FOLDER ."/". CORE_FOLDER ."/init_cms.php";
if(is_file($init_cms_path)) {
  include($init_cms_path);
  return;
}

http_response_code(500);
echo "CMS core init file not found.";

?>