<?php

try {

  // localhost
  $cmsIndex = "../cms/index.php";
  if(!is_file($cmsIndex)) {
    // find version file and best release
    foreach(scandir(getcwd()) as $f) {
      $varName = strtok($f, ".");
      if($varName == "VERSION") { // eg. VERSION.1.0
        $cmsIndex = substr($f, strlen($varName)+1).".php";
        break;
      }
      if(!preg_match("/^\d+\.\d+\.php$/", $f)) continue;
      if(version_compare(substr($cmsIndex, -4), substr($f, -4)) > 0) continue;
      $cmsIndex = $f;
    }
    // else throw
    if(!is_file($cmsIndex)) throw new Exception("Unable to find CMS version");
  }

  // include link given version
  include($cmsIndex);

} catch(Exception $e) {

  http_response_code(500);
  echo "Core fatal exception: ".$e->getMessage();

}

?>