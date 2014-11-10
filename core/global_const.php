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
  $resFolder = $res && !isAtLocalhost() ? $resFolder = RES_FOLDER : false;
  $f = USER_ROOT_DIR . "/$file";
  if($user && is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
  $f = ADMIN_ROOT_DIR . "/$file";
  if($admin && is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
  $f = $file;
  if(is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
  if($res && !isAtLocalhost()) $resFolder = CMSRES_FOLDER;
  $f = CMS_FOLDER . "/$file";
  if(is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
  return false;
}

function getRes($res, $dest, $resFolder) {
  if($resFolder === false) return $res;
  #TODO: check mime==ext, allowed types, max size
  $folders = preg_quote(CMS_FOLDER,"/") ."|". preg_quote(ADMIN_FOLDER,"/") ."|". preg_quote(USER_FOLDER,"/");
  if(!preg_match("/^(?:$folders)\/".FILEPATH_PATTERN."$/", $res)) {
    new Logger("Forbidden file name '$res' to copy to '$resFolder' folder","error");
    return false;
  }
  $mime = getFileMime($res);
  if($resFolder != CMSRES_FOLDER && $mime != "text/plain") {
    new Logger("Forbidden mime type '$mime' to copy '$res' to '$resFolder' folder","error");
    return false;
  }
  $newRes = $resFolder . "/$dest";
  $newDir = pathinfo($newRes,PATHINFO_DIRNAME);
  if(!is_dir($newDir) && !mkdirGroup($newDir,0775,true)) {
    new Logger("Unable to create directory structure '$newDir'","error");
    return false;
  }
  if(file_exists($newRes)) return $newRes;
  if(!symlink(realpath($res), $newRes . "~") || !rename($newRes . "~", $newRes)) {
    new Logger("Unable to create symlink '$newRes' for '$res'","error");
    return false;
  }
  if(!chmodGroup($newRes,0664))
    new Logger("Unable to chmod resource file '$newRes'","error");
  return $newRes;
}

?>