<?php

try {

  // localhost
  $cmsIndex = "../cms/index.php";
  if(is_file($cmsIndex)) {
    include($cmsIndex);
    exit;
  }

  // source file (js, css)
  if(strlen($_GET["q"]) && in_array(pathinfo($_GET["q"], PATHINFO_EXTENSION), array("css", "js"))) {
    $slashPos = strpos($_GET["q"], "/");
    $version = substr($_GET["q"], 0, $slashPos);
    $cmsIndex = "$version.php";
    if(is_link($cmsIndex)) {
      $srcPath = substr($_GET["q"], $slashPos+1);
      $url = "http".($_SERVER["HTTPS"] == "on" ? "s" : "")."://".$_SERVER["SERVER_NAME"]."/$cmsIndex?q=$srcPath";
      header("Location: $url");
      exit;
    }
  }

  // find version file and best release
  $cmsIndex = null;
  $cmsVersion = null;
  $redir = null;
  foreach(scandir(getcwd()) as $f) {
    if(is_dir($f)) continue;
    if(strpos($f, "REDIR.") === 0) { // eg. REDIR.www.internetguru.cz
      header("Location: http://".substr($f, 6)."/".$_GET["q"]);
      exit;
    }
    if(strpos($f, "VERSION.") === 0) { // eg. VERSION.1.0
      $cmsVersion = substr($f, 8).".php";
    }
    if(!preg_match("/^\d+\.\d+\.php$/", $f)) continue;
    if(version_compare(substr($cmsIndex, 0, -4), substr($f, 0, -4)) > 0) continue;
    $cmsIndex = $f;
  }
  if(!is_null($cmsVersion) && is_file($cmsVersion)) $cmsIndex = $cmsVersion;

  // else throw
  if(!is_file($cmsIndex)) throw new Exception("Unable to find stable CMS version");
  if(is_null($cmsVersion)) touch("VERSION.".substr($cmsIndex, 0, -4));

  // include link given version
  include($cmsIndex);

} catch(Exception $e) {

  http_response_code(500);
  echo "Core fatal exception: ".$e->getMessage();

}

?>
