<?php

function __autoload($className) {
  $fp = PLUGINS_FOLDER."/$className/$className.php";
  if(@include $fp) return;
  $fc = CORE_FOLDER."/$className.php";
  if(@include $fc) return;
  throw new LoggerException("Unable to find class '$className' in '$fp' nor '$fc'");
}

function findFile($file, $user=true, $admin=true, $res=false) {
  while(strpos($file,"/") === 0) $file = substr($file,1);
  try {
    $resFolder = $res && !isAtLocalhost() ? $resFolder = RES_DIR : false;
    $f = USER_FOLDER . "/$file";
    if($user && is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
    $f = ADMIN_FOLDER . "/$file";
    if($admin && is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
    $f = $file;
    if(is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
    if($res && !isAtLocalhost()) $resFolder = CMSRES_ROOT_DIR."/".CMSRES_DIR;
    $f = CMS_FOLDER . "/$file";
    if(is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
  } catch(Exception $e) {
    new Logger($e->getMessage(), "error");
  }
  return false;
}

function getRes($res, $dest, $resFolder) {
  if($resFolder === false) return $res;
  #TODO: check mime==ext, allowed types, max size
  $folders = preg_quote(CMS_FOLDER,"/") ."|". preg_quote(ADMIN_FOLDER,"/") ."|". preg_quote(USER_FOLDER,"/");
  if(!preg_match("/^(?:$folders)\/".FILEPATH_PATTERN."$/", $res)) {
    throw new Exception("Forbidden file name '$res' to copy to '$resFolder' folder");
  }
  $mime = getFileMime($res);
  if(strpos($res, CMS_FOLDER) !== 0 && $mime != "text/plain") {
    throw new Exception("Forbidden mime type '$mime' to copy '$res' to '$resFolder' folder");
  }
  $newRes = $resFolder . "/$dest";
  $newDir = pathinfo($newRes,PATHINFO_DIRNAME);
  if(!is_dir($newDir) && !mkdirGroup($newDir,0775,true)) {
    throw new Exception("Unable to create directory structure '$newDir'");
  }
  if(is_link($newRes) && readlink($newRes) == $res) return $newRes;
  if(!symlink($res, "$newRes~") || !rename("$newRes~", $newRes)) {
    throw new Exception("Unable to create symlink '$newRes' for '$res'");
  }
  if(!chmodGroup($newRes,0664))
    throw new Exception("Unable to chmod resource file '$newRes'");
  return $newRes;
}

?>