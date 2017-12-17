<?php

try {

  if (is_file("REDIR")) {
    header("Location: ".trim(file_get_contents("REDIR"))."/".$_GET["q"]);
    exit;
  }

  if (!is_file("VERSION")) {
    throw new Exception("File VERSION not found");
  }
  $version = trim(file_get_contents("VERSION"));
  $index = "/var/www/cms/$version/index.php";
  if (!is_file($index)) {
    throw new Exception("CMS $version index.php not found");
  }
  include "$index";

} catch (Exception $exc) {

  http_response_code(500);
  echo "Core fatal exception: ".$exc->getMessage();

}
