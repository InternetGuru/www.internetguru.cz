<?php
try {
  $initCmsFile = "../cms/core/init_cms.php";
  if(is_file($initCmsFile)) {
    include($initCmsFile);
    exit;
  }
  foreach(scandir(getCwd()) as $f) {
    $var = explode(".", $f, 2);
    if($var[0] != "CMS_VER") continue;
    $cmsVer = $var[1];
    break;
  }
  if(is_null($cmsVer))
    throw new Exception("No stable CMS release found.");
  $initCmsFile = "/var/www/cms/$cmsVer/core/init_cms.php";
  if(!is_file($initCmsFile))
    throw new Exception("CMS core init file not found.");
  include($initCmsFile);
} catch(Exception $e) {
  http_response_code(500);
  echo "Core fatal exception: ".$e->getMessage();
  exit;
}
?>