<?php

try {

  // localhost
  $cmsFindex = "../cms/findex.php";
  if(is_file($cmsFindex)) {
    include($cmsFindex);
    exit;
  }

  $cmsVersion = null;
  $query = null;
  if(isset($_GET["q"])) {
    $query = $_GET["q"];
    $cmsVersion = substr($query, 0, strpos($query, "/"));
  }
  if(is_null($cmsVersion) || !is_link("$cmsVersion.php")) {
    foreach(scandir(getcwd()) as $f) {
      if(strpos($f, "VERSION.") !== 0) continue;
      $cmsVersion = substr($f, 8);
    }
  }
  if(is_null($cmsVersion)) throw new Exception("Unable to detect version");

  $cmsFindex = dirname(readlink("$cmsVersion.php"))."/findex.php";
  if(!is_file($cmsFindex)) {
    header("Location: /index.php?q=$query");
    exit;
  }
  include($cmsFindex);

} catch(Exception $e) {

  http_response_code(500);
  echo "File fatal exception: ".$e->getMessage();

}

?>
