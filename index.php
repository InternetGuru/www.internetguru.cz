<?php

$initCmsFile = "core/init_cms.php";

if(is_link(basename(__FILE__))) $initCmsFile = dirname(__FILE__)."/$initCmsFile";
else $initCmsFile = "../cms/$initCmsFile";

if(!is_file($initCmsFile)) {
  http_response_code(500);
  echo "CMS core init file not found.";
  return;
}

include($initCmsFile);

?>