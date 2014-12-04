<?php

define("CORE_DIR", "core");
$initCmsFile = "init_cms.php";

$cmsVer = null;
foreach(scandir(".") as $f) {
  $var = explode(".", $f, 2);
  if($var[0] != "CMS_VER") continue;
  $cmsVer = $ver[1];
  break;
}

if(!is_null($cmsVer)) {
  define("CMS_ROOT_FOLDER", "/var/www/cms");
  define('CMS_RELEASE', "$cmsVer");
  define("CMS_FOLDER", CMS_ROOT_FOLDER."/".CMS_RELEASE);
} else {
  define("CMS_ROOT_FOLDER", "../cms");
  define("CMS_FOLDER", CMS_ROOT_FOLDER);
  define('CMS_RELEASE', "localhost");
}

define("CORE_FOLDER", CMS_FOLDER."/".CORE_DIR);

$initCmsFile = CORE_FOLDER."/$initCmsFile";

if(!is_file($initCmsFile)) {
  http_response_code(500);
  echo "CMS core init file not found.";
  return;
}

include($initCmsFile);

?>