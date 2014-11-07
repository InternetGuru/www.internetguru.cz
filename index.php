<?php

$errorMessage = "CMS core init file not found.";
error_reporting(E_ALL);
ini_set("display_errors", 1);

// run cms directly
$init_cms_path = "core/init_cms.php";
if(is_file($init_cms_path)) {
  include($init_cms_path);
  return;
}

// run cms on localhost
$init_cms_path = "../cms/core/init_cms.php";
if(is_file($init_cms_path)) {
  include($init_cms_path);
  return;
}

// run/update cms on server
$cms_root_dir = "/var/www/cms";
$init_server_path = "$cms_root_dir/init_server.php";
if(is_file($init_server_path)) try {
  $subdom = basename(dirname($_SERVER["PHP_SELF"]));
  require_once($init_server_path);
  init_server($subdom, $cms_root_dir);
  // update subdom
  if(isset($_GET["updateSubdom"])) {
    if(strlen($_GET["updateSubdom"])) $subdom = $_GET["updateSubdom"];
    update_subdom($subdom, $cms_root_dir);
    $d = explode(".",$_SERVER["HTTP_HOST"]);
    while(count($d) > 2) array_shift($d);
    header("Location: http://$subdom.". implode(".",$d));
    exit();
  }
  $init_cms_path = CMS_FOLDER ."/core/init_cms.php";
  if(is_file($init_cms_path)) {
    include($init_cms_path);
    return;
  }
} catch(Exception $e) {
  $errorMessage = $e->getMessage();
}

http_response_code(500);
echo $errorMessage;

?>