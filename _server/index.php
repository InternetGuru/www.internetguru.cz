<?php

try {

  // localhost
  $index = "../cms/index.php";
  if(is_file($index)) {
    include($index);
    exit;
  }

  if(is_file("REDIR")) {
    header("Location: ".file_get_contents("REDIR")."/".$_GET["q"]);
    exit;
  }

  if(is_file("VERSION")) throw new Exception("File VERSION not found");
  $version = file_get_contents("VERSION");
  $index = "/var/www/cms/$version/index.php";
  if(is_file($index)) throw new Exception("CMS $version index.php not found");
  include($index);

} catch(Exception $e) {

  http_response_code(500);
  echo "Core fatal exception: ".$e->getMessage();

}

?>
