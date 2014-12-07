<?php

function __autoload($className) {
  $fp = PLUGINS_FOLDER."/$className/$className.php";
  if(@include $fp) return;
  $fc = CORE_FOLDER."/$className.php";
  if(@include $fc) return;
  #todo: log shortPath
  throw new LoggerException(sprintf(_("Unable to find class '%s' in '%s' nor '%s'"), $className, $fp, $fc));
}

function proceedServerInit($initServerFileName) {
  if(IS_LOCALHOST) return;
  if(isset($_GET["updateSubdom"])) {
    $subdom = CURRENT_SUBDOM_DIR;
    if(strlen($_GET["updateSubdom"])) $subdom = $_GET["updateSubdom"];
    new InitServer($subdom, false, true);
    redirTo("http://$subdom.".getDomain());
  }
  new InitServer(CURRENT_SUBDOM_DIR, true);
}

function findFile($file, $user=true, $admin=true, $res=false) {
  while(strpos($file, "/") === 0) $file = substr($file, 1);
  try {
    $resFolder = $res && !IS_LOCALHOST ? $resFolder = RES_DIR : false;
    $f = USER_FOLDER."/$file";
    if($user && is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
    $f = ADMIN_FOLDER."/$file";
    if($admin && is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
    $f = $file;
    if(is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
    if($res && !IS_LOCALHOST) $resFolder = CMSRES_ROOT_DIR."/".CMS_RELEASE;
    $f = CMS_FOLDER."/$file";
    if(is_file($f)) return $resFolder ? getRes($f, $file, $resFolder) : $f;
  } catch(Exception $e) {
    new Logger($e->getMessage(), "error");
  }
  return false;
}

function getRes($res, $dest, $resFolder) {
  if($resFolder === false) return $res;
  #TODO: check mime==ext, allowed types, max size
  $folders = array(preg_quote(CMS_FOLDER, "/"));
  if(defined("ADMIN_FOLDER")) $folders[] = preg_quote(ADMIN_FOLDER, "/");
  if(defined("USER_FOLDER")) $folders[] = preg_quote(USER_FOLDER, "/");
  if(!preg_match("/^(?:".implode("|", $folders).")\/(".FILEPATH_PATTERN.")$/", $res, $m)) {
    throw new Exception(sprintf(_("Forbidden file name '%s' format to copy to '%s' folder"), $m[1], $resFolder));
  }
  $mime = getFileMime($res);
  if(strpos($res, CMS_FOLDER) !== 0 && $mime != "text/plain" && strpos($mime, "image/") !== 0) {
    throw new Exception(sprintf(_("Forbidden MIME type '%s' to copy '%s' to '%s' folder"), $mime, $m[1], $resFolder));
  }
  $newRes = $resFolder."/$dest";
  $newDir = pathinfo($newRes, PATHINFO_DIRNAME);
  if(!is_dir($newDir) && !mkdirGroup($newDir, 0775, true)) {
    throw new Exception(sprintf(_("Unable to create directory structure '%s'"), $newDir));
  }
  if(is_link($newRes) && readlink($newRes) == $res) return $newRes;
  if(!symlink($res, "$newRes~") || !rename("$newRes~", $newRes)) {
    throw new Exception(sprintf(_("Unable to create symlink '%s' for '%s'"), $newRes, $m[1]));
  }
  #if(!chmodGroup($newRes, 0664))
  #  throw new Exception(sprintf(_("Unable to chmod resource file '%x'"), $newRes);
  return $newRes;
}

function createSymlink($link, $target) {
  $restart = false;
  if(is_link($link) && readlink($link) == $target) return;
  elseif(is_link($link)) $restart = true;
  if(!symlink($target, "$link~") || !rename("$link~", $link))
    throw new Exception(sprintf(_("Unable to create symlink '%s'"), $link));
  if($restart && !touch(APACHE_RESTART_FILEPATH))
    new Logger(_("Unable to force symlink cache update: may take longer to apply"), "error");
}

?>