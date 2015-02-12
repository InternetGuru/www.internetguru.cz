<?php

function __autoload($className) {
  $fp = PLUGINS_FOLDER."/$className/$className.php";
  if(@include $fp) return;
  $fc = CORE_FOLDER."/$className.php";
  if(@include $fc) return;
  #todo: log shortPath
  throw new LoggerException(sprintf(_("Unable to find class '%s' in '%s' nor '%s'"), $className, $fp, $fc));
}

function trimLink($link) {
  $pLink = parse_url($link);
  if($pLink === false) throw new LoggerException(sprintf(_("Unable to parse href '%s'"), $link)); // fail2parse
  if(!isset($pLink["scheme"]) && !isset($pLink["host"])) return $link; // link is relative
  if(isset($pLink["scheme"]) && $pLink["scheme"] != $_SERVER["REQUEST_SCHEME"]) return $link; // different scheme
  if($pLink["host"] != HOST) return $link; // different host
  $link = "";
  if(isset($pLink["path"])) $link = $pLink["path"];
  if(isset($pLink["query"])) $link .= "?".$pLink["query"];
  if(isset($pLink["fragment"])) $link .= "#".$pLink["fragment"];
  return $link;
}

function getUrl($schema=true) {
  $domain = $_SERVER["HTTP_HOST"];
  if($schema) $domain = $_SERVER["REQUEST_SCHEME"]."://".$domain;
  if(IS_LOCALHOST) return $domain . substr(ROOT_URL, 0, -1);
  return $domain;
}

function findFile($file, $user=true, $admin=true, $res=false) {
  #while(strpos($file, "/") === 0) $file = substr($file, 1);
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
    throw new Exception(sprintf(_("Forbidden file name '%s' format to copy to '%s' folder"), $res, $resFolder));
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
  #if($restart && !touch(APACHE_RESTART_FILEPATH))
  if(!$restart) return;
  new Logger(_("Symlink changed; may take time to apply"), Logger::LOGGER_WARNING);
}

function replaceVariables($string, Array $variables, $varPrefix=null) {
  if(!strlen($string)) return $string;
  $pat = '/(@?\$'.VARIABLE_PATTERN.')/i';
  $p = preg_split($pat, $string, -1, PREG_SPLIT_DELIM_CAPTURE);
  if(count($p) < 2) return $string;
  $newString = "";
  foreach($p as $i => $v) {
    if($i % 2 == 0) {
      $newString .= $v;
      continue;
    }
    $vName = substr($v, strpos($v, '$')+1);
    if(!array_key_exists($vName, $variables)) {
      $vName = $varPrefix.$vName;
      if(!array_key_exists($vName, $variables)) {
        if(strpos($v, "@") !== 0)
          new Logger(sprintf(_("Variable '%s' does not exist"), $vName), Logger::LOGGER_WARNING);
        $newString .= $v;
        continue;
      }
    }
    $value = $variables[$vName];
    if(is_array($value)) $value = implode(", ", $value);
    elseif(!is_string($value)) {
      if(strpos($v, "@") !== 0)
        new Logger(sprintf(_("Variable '%s' is not string"), $vName), Logger::LOGGER_WARNING);
      $newString .= $v;
      continue;
    }
    $newString .= $value;
  }
  return $newString;
}

function absoluteLink($link=null) {
  if(is_null($link)) $link = $_SERVER["REQUEST_URI"];
  if(substr($link, 0, 1) == "/") $link = substr($link, 1);
  if(substr($link, -1) == "/") $link = substr($link, 0, -1);
  $pLink = parse_url($link);
  if($pLink === false) throw new LoggerException(sprintf(_("Unable to parse URL '%s'"), $link));
  $scheme = isset($pLink["scheme"]) ? $pLink["scheme"] : $_SERVER["REQUEST_SCHEME"];
  $host = isset($pLink["host"]) ? $pLink["host"] : $_SERVER["HTTP_HOST"];
  $path = isset($pLink["path"]) && $pLink["path"] != "" ? "/".$pLink["path"] : "";
  $query = isset($pLink["query"]) ? "?".$pLink["query"] : "";
  return "$scheme://$host$path$query";
}

function redirTo($link, $code=null, $force=false) {
  if(!$force) {
    $curLink = absoluteLink();
    $absLink = absoluteLink($link);
    if($curLink == $absLink)
      throw new LoggerException(sprintf(_("Cyclic redirection to '%s'"), $link));
  }
  http_response_code(is_null($code) ? 302 : $code);
  if(class_exists("Logger"))
    new Logger(sprintf(_("Redirecting to '%s' with status code %s"), $link, (is_null($code) ? 302 : $code)));
  if(is_null($code) || !is_numeric($code)) {
    header("Location: $link");
    exit();
  }
  header("Location: $link", true, $code);
  header("Refresh: 0; url=$link");
  exit();
}

function chmodGroup($file, $mode) {
  $oldMask = umask(002);
  $chmod = chmod($file, $mode);
  umask($oldMask);
  return $chmod;
}

function mkdirGroup($dir, $mode=0777, $rec=false) {
  $oldMask = umask(002);
  $dirMade = mkdir($dir, $mode, $rec);
  umask($oldMask);
  return $dirMade;
}

function __toString($o) {
  echo "|";
  if(is_array($o)) return implode(", ", $o);
  return (string) $o;
}

function stableSort(Array &$a) {
  if(count($a) < 2) return;
  $order = range(1, count($a));
  array_multisort($a, SORT_ASC, $order, SORT_ASC);
}

function getCurLink($query=false) {
  if(!$query) return isset($_GET["page"]) ? $_GET["page"] : "";
  $query = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "";
  return substr($query, strlen(ROOT_URL));
}

function normalize($s, $keep=null, $tolower=true, $convertToUtf8=false) {
  if($convertToUtf8) $s = utf8_encode($s);
  if($tolower) $s = mb_strtolower($s, "utf-8");
  $s = iconv("UTF-8", "US-ASCII//TRANSLIT", $s);
  if($tolower) $s = strtolower($s);
  $s = str_replace(" ", "_", $s);
  if(is_null($keep)) $keep = "a-zA-Z0-9_-";
  $s = @preg_replace("~[^$keep]~", "", $s);
  if(is_null($s))
    throw new Exception(_("Invalid parameter 'keep'"));
  return $s;
}

function safeRewriteFile($src, $dest, $keepOld=true) {
  if(!file_exists($src))
    throw new LoggerException(sprinft(_("Source file '%s' not found"), $src));
  if(!file_exists(dirname($dest)) && !@mkdir(dirname($dest), 0775, true))
    throw new LoggerException(_("Unable to create directory structure"));
  if(!is_link($dest) && is_file($dest) && !copy($dest, "$dest.old"))
    throw new LoggerException(_("Unable to backup destination file"));
  if(!copy($src, "$dest.new"))
    throw new LoggerException(_("Unable to copy source file"));
  if(!rename("$dest.new", $dest))
    throw new LoggerException(_("Unable to rename new file to destination"));
  if(!$keepOld && is_file("$dest.old") && !unlink("$dest.old"))
    throw new LoggerException(_("Unable to delete.old file"));
  return true;
}

function safeRewrite($content, $dest) {
  if(!file_exists(dirname($dest)) && !@mkdir(dirname($dest), 0775, true)) return false;
  if(!file_exists($dest)) return file_put_contents($dest, $content);
  $b = file_put_contents("$dest.new", $content);
  if($b === false) return false;
  if(!copy($dest, "$dest.old")) return false;
  if(!rename("$dest.new", $dest)) return false;
  return $b;
}

function safeRemoveDir($dir) {
  if(!is_dir($dir)) return true;
  if(count(scandir($dir)) == 2) return rmdir($dir);
  $i = 1;
  $delDir = "$dir~";
  while(is_dir($delDir.$i)) $i++;
  return rename($dir, $delDir.$i);
}

function incrementalRename($src, $dest) {
  if(!file_exists($src))
    throw new Exception(sprintf(_("Source file '%s' not found"), basename($src)));
  preg_match("/\d*$/", $dest, $m);
  $iLength = strlen($m[0]);
  if($iLength > 0) $dest = substr($dest, 0, -$iLength);
  $i = (int) $m[0];
  while(file_exists($dest.$i)) $i++;
  if(!rename($src, $dest.$i))
    throw new Exception(sprintf(_("Unable to rename directory '%s'"), basename($src)));
  return $dest.$i;
}

function duplicateDir($dir, $deep=true) {
  if(!is_dir($dir))
    throw new Exception(sprintf(_("Directory '%s' not found"), basename($dir)));
  $info = pathinfo($dir);
  $bakDir = $info["dirname"]."/~".$info["basename"];
  copyFiles($dir, $bakDir, $deep);
  deleteRedundantFiles($bakDir, $dir);
  #new Logger("Active data backup updated");
  return $bakDir;
}

function initStructure() {
  $dirs = array(ADMIN_FOLDER, USER_FOLDER, ADMIN_BACKUP_FOLDER, USER_BACKUP_FOLDER,
    LOG_FOLDER, FILES_FOLDER);
  foreach($dirs as $d) {
    if(is_dir($d)) continue;
    if(mkdir($d, 0755, true)) continue;
    throw new Exception(sprintf(_("Unable to create folder %s"), $d));
  }
}

function smartCopy($src, $dest, $delay=0) {
  if(!file_exists($src)) throw new Exception(sprintf(_("File '%s' not found"), basename($src)));
  if(file_exists($dest)) {
    // both are links with same target
    if(is_link($dest) && is_link($src)
      && readlink($dest) == readlink($src)) return;
    // both are files within given age gap (delay)
    elseif(!is_link($dest) && !is_link($src)
      && $delay && filectime($dest) > time()-$delay) return;
  }
  $destDir = dirname($dest);
  if(!is_dir($destDir) && !mkdir($destDir, 0755, true))
    throw new Exception(sprintf(_("Unable to create directory '%s'"), $destDir));
  if(is_link($src)) {
    createSymlink($dest, readlink($src));
    return;
  }
  if(!copy($src, $dest)) {
    throw new Exception(sprintf(_("Unable to copy '%s' to '%s'"), $src, $dest));
  }
}

function deleteRedundantFiles($in, $according) {
  if(!is_dir($in)) return;
  foreach(scandir($in) as $f) {
    if(in_array($f, array(".", ".."))) continue;
    if(is_dir("$in/$f")) {
      deleteRedundantFiles("$in/$f", "$according/$f");
      if(!is_dir("$according/$f")) rmdir("$in/$f");
      continue;
    }
    if(!is_file("$according/$f")) unlink("$in/$f");
  }
}

function copyFiles($src, $dest, $deep=false) {
  if(!is_dir($dest) && !mkdir($dest))
    throw new LoggerException(sprintf(_("Unable to create '%s' folder"), $dest));
  foreach(scandir($src) as $f) {
    if(in_array($f, array(".", ".."))) continue;
    if(is_dir("$src/$f") && !is_link("$src/$f")) {
      if($deep) copyFiles("$src/$f", "$dest/$f", $deep);
      continue;
    }
    smartCopy("$src/$f", "$dest/$f");
  }
}

function translateUtf8Entities($xmlSource, $reverse = FALSE) {
  static $literal2NumericEntity;

  if (empty($literal2NumericEntity)) {
    $transTbl = get_html_translation_table(HTML_ENTITIES);
    foreach ($transTbl as $char => $entity) {
      if (strpos('&"<>', $char) !== FALSE) continue;
      #$literal2NumericEntity[$entity] = '&#'.ord($char).';';
      $literal2NumericEntity[$entity] = $char;
    }
  }
  if ($reverse) {
    return strtr($xmlSource, array_flip($literal2NumericEntity));
  } else {
    return strtr($xmlSource, $literal2NumericEntity);
  }
}

function readZippedFile($archiveFile, $dataFile) {
  // Create new ZIP archive
  $zip = new ZipArchive;
  // Open received archive file
  if(!$zip->open($archiveFile))
    throw new Exception(_("Unable to open file"));
  // If done, search for the data file in the archive
  $index = $zip->locateName($dataFile);
  // If file not found, return null
  if($index === false) return null;
  // If found, read it to the string
  $data = $zip->getFromIndex($index);
  // Close archive file
  $zip->close();
  // Load data from a string
  return $data;
}

function getFileMime($file) {
  #if(IS_LOCALHOST) {
    if(!function_exists("finfo_file")) throw new Exception(_("Function finfo_file() not supported"));
    $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
    $mime = finfo_file($finfo, $file); // avg. 2ms
    finfo_close($finfo);
    return $mime;
  #}
  #$file = escapeshellarg($file);
  #$mime = shell_exec("file -bi ".$file." 2>&1"); // command file not found: TODO create script in cms
  #var_dump($mime);
  #$mime = explode(";", $mime);
  #return $mime[0];
}

function fileSizeConvert($b) {
    if(!is_numeric($b)) return $b;
    $i = 0;
    $iec = array("B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB");
    while(($b/1024) > 1) {
        $b = $b/1024;
        $i++;
    }
    return round($b, 1)." ".$iec[$i];
}

function checkUrl($folder = null) {
  $rUri = $_SERVER["REQUEST_URI"];
  $pUrl = parse_url($rUri);
  if($pUrl === false || strpos($pUrl["path"], "//") !== false)
    new ErrorPage(sprintf(_("The requested URL '%s' was not understood by this server."), $rUri), 400);
  if(!preg_match("/^".preg_quote(ROOT_URL, "/")."(".FILEPATH_PATTERN.")(\?.*)?$/", $rUri, $m))
    return null;

  $fInfo["filepath"] = "$folder/".$m[1];
  if(!is_file($fInfo["filepath"]))
    new ErrorPage(sprintf(_("The requested URL '%s' was not found on this server."), $rUri), 404);

  $disallowedMime = array(
    "application/x-msdownload" => null,
    "application/x-msdos-program" => null,
    "application/x-msdos-windows" => null,
    "application/x-download" => null,
    "application/bat" => null,
    "application/x-bat" => null,
    "application/com" => null,
    "application/x-com" => null,
    "application/exe" => null,
    "application/x-exe" => null,
    "application/x-winexe" => null,
    "application/x-winhlp" => null,
    "application/x-winhelp" => null,
    "application/x-javascript" => null,
    "application/hta" => null,
    "application/x-ms-shortcut" => null,
    "application/octet-stream" => null,
    "vms/exe" => null,
  );
  $fInfo["filemime"] = getFileMime($fInfo["filepath"]);
  if(array_key_exists($fInfo["filemime"], $disallowedMime))
    new ErrorPage(sprintf(_("Unsupported mime type '%s'"), $fInfo["filemime"]), 415);
  return $fInfo;
}

function getFileHash($filePath) {
  if(!is_file($filePath)) return "";
  return hash_file(FILE_HASH_ALGO, $filePath);
}

function getDirHash($dirPath) {
  if(!is_dir($dirPath)) return "";
  return hash(FILE_HASH_ALGO, implode("", scandir($dirPath)));
}

function getShortString($str) {
  $lLimit = 9;
  $hLimit = 16;
  if(strlen($str) < $hLimit) return $str;
  $w = explode(" ", $str);
  $sStr = $w[0];
  $i = 1;
  while(strlen($sStr) < $lLimit) {
    if(!isset($w[$i])) break;
    $sStr .= " ".$w[$i++];
  }
  if(strlen($str) - strlen($sStr) < $hLimit - $lLimit) return $str;
  return $sStr."…";
}

?>