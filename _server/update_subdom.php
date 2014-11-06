<?php

function update_subdom($subdom, $) {
  if(!strlen($subdom)) $subdom = basename(dirname($_SERVER["PHP_SELF"]));
  $cms_dir = "/var/www/cms";
  global $var;
  include()
  $dir = "../$subdom";
  if(!is_dir($dir)) {
    if(!@mkdir($dir, 0755, true))
      throw new Exception("Unable to create folder '$dir'");
    if(!@copy("$cms_dir/index.php", "$dir/index.php"))
      throw new Exception("Unable to copy file '$cms_dir/index.php' into '$dir'");
    if(!@copy("$cms_dir/.htaccess", "$dir/.htaccess"))
      throw new Exception("Unable to copy file '$cms_dir/.htaccess' into '$dir'");
    $init_server_path = "/var/www/cms/init_server.php";
  }
  #todo: applyUserData($subdom);
  #todo: init_server
  #todo: require_once init_const
  mkStructure($s);
}

function mkStructure($subdom) {
  $dirs = array(
    'ADMIN_BACKUP' => ADMIN_BACKUP,
    'ADMIN_FOLDER' => ADMIN_FOLDER,
    'USER_FOLDER' => USER_FOLDER,
    'USER_BACKUP' => USER_BACKUP,
    'FILES_FOLDER' => FILES_FOLDER,
    'TEMP_FOLDER' => TEMP_FOLDER,
    'CMSRES_FOLDER' => CMSRES_FOLDER,
    'RES_FOLDER' => RES_FOLDER,
    'LOG_FOLDER' => LOG_FOLDER,
    'CACHE_FOLDER' => CACHE_FOLDER,
    );
  foreach($dirs as $k => $d) {
    if(!$d) continue; // res/cmsres == false
    if(!is_dir($d) && !@mkdir($d,0755,true))
      throw new Exception("Unable to create folder '$d' (const '$k')");
  }
}

?>