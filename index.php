<?php

// self
$init_cms_path = "core/init_cms.php";
if(is_file($init_cms_path)) {
  include($init_cms_path);
  return;
}

// localhost
$init_cms_path = "../cms/core/init_cms.php";
if(is_file($init_cms_path)) {
  include($init_cms_path);
  return;
}

// update subdom
/*
$update_subdom_path = "/var/www/cms/update_subdom.php";
if(is_set($_GET["updateSubdom"]) && is_file($update_subdom_path)) {
  include($update_subdom_path);
  update_subdom($_GET["updateSubdom"]);
  #todo: redir
  echo "UPDATE DONE, REDIR";
  return;
}
*/

// server
$init_server_path = "/var/www/cms/init_server.php";
if(is_file($init_server_path)) {
  require_once($init_server_path);
  init_server();
  $init_cms_path = CMS_FOLDER ."/core/init_cms.php";
  if(is_file($init_cms_path)) {
    include($init_cms_path);
    return;
  }
}

http_response_code(500);
echo "CMS core init file not found.";

?>