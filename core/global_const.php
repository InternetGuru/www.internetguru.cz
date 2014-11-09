<?php

if(!defined('SUBDOM_FOLDER')) define('SUBDOM_FOLDER', false);
if(!defined('ADMIN_BACKUP')) define('ADMIN_BACKUP', 'adm.bak');
if(!defined('ADMIN_FOLDER')) define('ADMIN_FOLDER', 'adm');
if(!defined('USER_FOLDER')) define('USER_FOLDER', 'usr');
if(!defined('USER_BACKUP')) define('USER_BACKUP', 'usr.bak');
if(!defined('FILES_FOLDER')) define('FILES_FOLDER', 'files');
if(!defined('TEMP_FOLDER')) define('TEMP_FOLDER', 'temp');
if(!defined('THEMES_FOLDER')) define('THEMES_FOLDER', 'themes');
if(!defined('CMSRES_FOLDER')) define('CMSRES_FOLDER', false);
if(!defined('RES_FOLDER')) define('RES_FOLDER', false);
if(!defined('LOG_FOLDER')) define('LOG_FOLDER', 'log');
if(!defined('VER_FOLDER')) define('VER_FOLDER', 'ver');
if(!defined('CACHE_FOLDER')) define('CACHE_FOLDER', 'cache');
if(!defined('PLUGIN_FOLDER')) define('PLUGIN_FOLDER', 'plugins');

function __autoload($className) {
  $fp = CMS_FOLDER ."/". PLUGIN_FOLDER . "/$className/$className.php";
  if(@include $fp) return;
  $fc = CMS_FOLDER ."/". CORE_FOLDER . "/$className.php";
  if(@include $fc) return;
  throw new LoggerException("Unable to find class '$className' in '$fp' nor '$fc'");
}

function getRes($res,$dest,$resFolder) {
  if(!$resFolder) return $res;
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

function findFile($file,$user=true,$admin=true,$res=false) {
  while(strpos($file,"/") === 0) $file = substr($file,1);
  $f = USER_FOLDER . "/$file";
  if($user && is_file($f)) return ($res ? getRes($f,$file,RES_FOLDER) : $f);
  $f = ADMIN_FOLDER . "/$file";
  if($admin && is_file($f)) return ($res ? getRes($f,$file,RES_FOLDER) : $f);
  $f = $file;
  if(is_file($f)) return ($res ? getRes($f,$file,RES_FOLDER) : $f);
  $f = CMS_FOLDER . "/$file";
  if(is_file($f)) return ($res ? getRes($f,$file,CMSRES_FOLDER) : $f);
  return false;
}

?>