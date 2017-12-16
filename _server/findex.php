<?php

try {

  // localhost
  $findex = "../cms/findex.php";
  if (is_file($findex)) {
    include($findex);
    exit;
  }

  if (!is_file("VERSION")) {
    throw new Exception("File VERSION not found");
  }
  $version = null;
  $query = null;
  if (isset($_GET["q"])) {
    $query = $_GET["q"];
    $version = substr($query, 0, strpos($query, "/"));
  }
  if (is_null($version) || !is_link("$version.php")) {
    $version = trim(file_get_contents("VERSION"));
  }

  $findex = "/var/www/cms/$version/findex.php";
  if (!is_file($findex)) {
    header("Location: /index.php?q=$query");
    exit;
  }
  include($findex);

} catch (Exception $exc) {

  http_response_code(500);
  echo "File fatal exception: ".$exc->getMessage();

}

?>
