<?php

function __autoload($className) {
  $fp = PLUGINS_FOLDER."/$className/$className.php";
  if(@include $fp) return;
  $fc = CORE_FOLDER."/$className.php";
  if(@include $fc) return;
  #todo: log shortPath
  throw new LoggerException(sprintf(_("Unable to find class '%s' in '%s' nor '%s'"), $className, $fp, $fc));
}

function findFile($filePath, $user=true, $admin=true) {
  if($user && is_file(USER_FOLDER."/$filePath")) return USER_FOLDER."/$filePath";
  if($admin && is_file(ADMIN_FOLDER."/$filePath")) return ADMIN_FOLDER."/$filePath";
  if(is_file($filePath)) return $filePath;
  if(is_file(CMS_FOLDER."/$filePath")) return CMS_FOLDER."/$filePath";
  #todo: return null (keep type)
  return false;
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

function redirTo($link, $code=null, $msg=null) {
  http_response_code(is_null($code) ? 302 : $code);
  if(!strlen($link)) {
    $link = ROOT_URL;
    if(class_exists("Logger"))
      new Logger(_("Redirecting to empty string changed to root"), Logger::LOGGER_WARNING);
  }
  if(class_exists("Logger"))
    new Logger(sprintf(_("Redirecting to '%s'"), $link).(!is_null($msg) ? ": $msg" : ""));
  #var_dump($link); die();
  if(is_null($code) || !is_numeric($code)) {
    header("Location: $link");
    exit();
  }
  header("Location: $link", true, $code);
  header("Refresh: 0; url=$link");
  exit();
}

function implodeLink(Array $p, $query=true) {
  $url = "";
  #if(isset($p["path"])) $url .= trim($p["path"], "/");
  if(isset($p["path"])) $url .= $p["path"];
  if($query && isset($p["query"]) && strlen($p["query"])) $url .= "?".$p["query"];
  if(isset($p["fragment"])) $url .= "#".$p["fragment"];
  return $url;
}

function parseLocalLink($link, $host=null) {
  $pLink = parse_url($link);
  if($pLink === false) throw new LoggerExceptoin(sprintf(_("Unable to parse href '%s'"), $link)); // fail2parse
  foreach($pLink as $k => $v) if(!strlen($v)) unset($pLink[$k]);
  if(isset($pLink["path"])) $pLink["path"] = trim($pLink["path"], "/");
  if(isset($pLink["scheme"])) {
    if($pLink["scheme"] != SCHEME) return null; // different scheme
    unset($pLink["scheme"]);
  }
  if(isset($pLink["host"])) {
    if($pLink["host"] != (is_null($host) ? HOST : $host)) return null; // different ns
    unset($pLink["host"]);
  }
  return $pLink;
}

function buildLocalUrl(Array $pLink, $ignoreCyclic = false) {
  addPageSpeedOff($pLink);
  $cyclic = isCyclicLink($pLink);
  if(!$ignoreCyclic && $cyclic && !isset($pLink["fragment"]))
    throw new Exception(_("Link is cyclic"));
  $path = null;
  if(isset($pLink["path"])) {
    $path = ltrim($pLink["path"], "/");
    if(count($pLink) > 1 && $cyclic) unset($pLink["path"]);
    else $pLink["path"] = ROOT_URL.$pLink["path"];
  } else return implodeLink($pLink);
  if(is_null($path) && isset($pLink["fragment"])) return "#".$pLink["fragment"];
  $scriptFile = SCRIPT_NAME;
  if($scriptFile == "index.php") return implodeLink($pLink);
  $pLink["path"] = ROOT_URL.$scriptFile;
  if($cyclic) $pLink["path"] = "";
  $q = array();
  if(strlen($path)) $q[] = "q=".$path;
  if(isset($pLink["query"]) && strlen($pLink["query"])) $q[] = $pLink["query"];
  if(count($q)) $pLink["query"] = implode("&", $q);
  return implodeLink($pLink);
}

function isCyclicLink(Array $pLink) {
  if(isset($pLink["fragment"])) return false;
  if(isset($pLink["path"]) && $pLink["path"] != getCurLink()) return false;
  if(!isset($pLink["query"]) && getCurQuery() != "") return false;
  if(isset($pLink["query"]) && $pLink["query"] != getCurQuery()) return false;
  return true;
}

function addPageSpeedOff(Array &$pLink) {
  $psoff = "PageSpeed=off";
  $pson = "PageSpeed=start";
  if(!isset($_GET["PageSpeed"]) || $_GET["PageSpeed"] != "off") return;
  if(isset($pLink["query"])) {
    if(strpos($pLink["query"], $pson) !== false) return;
    $pLink["query"] = $pLink["query"]."&".$psoff;
  } else $pLink["query"] = $psoff;
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
  $link = isset($_GET["q"]) ? $_GET["q"] : "";
  return $link.($query ? getCurQuery(true) : "");
}

function getCurQuery($qm=false) {
  if(!isset($_SERVER['QUERY_STRING']) || !strlen($_SERVER['QUERY_STRING'])) return "";
  parse_str($_SERVER['QUERY_STRING'], $pQuery);
  if(isset($pQuery["q"])) unset($pQuery["q"]);
  return buildQuery($pQuery, $qm);
}

function buildQuery($pQuery, $qm=true) {
  if(empty($pQuery)) return "";
  return ($qm ? "?" : "").rtrim(urldecode(http_build_query($pQuery)), "=");
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

function copy_plus($src, $dest, $keepOld=true) {
  if(!is_file($src))
    throw new LoggerException(sprinft(_("Source file '%s' not found"), $src));
  mkdir_plus(dirname($dest));
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

function mkdir_plus($dir, $mode=0775, $recursive=true) {
  if(is_dir($dir)) return;
  @mkdir($dir, $mode, $recursive); // race condition
  if(!is_dir($dir)) throw new Exception(_("Unable to create directory"));
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

function initDirs() {
  $dirs = array(USER_FOLDER, LOG_FOLDER, FILES_FOLDER, THEMES_FOLDER,
    LIB_DIR, FILES_DIR, THEMES_DIR, PLUGINS_DIR);
  foreach($dirs as $d) mkdir_plus($d);
}

function initLinks() {
  $links = array();
  foreach(scandir(CMS_ROOT_FOLDER) as $f) {
    if(strpos($f, ".") === 0) continue;
    if(!is_dir(CMS_ROOT_FOLDER."/$f")) continue;
    if(file_exists(CMS_ROOT_FOLDER."/.$f")) continue;
    if(!is_file(CMS_ROOT_FOLDER."/$f/index.php")) continue;
    $links["$f.php"] = CMS_ROOT_FOLDER."/$f/index.php";
  }
  foreach(scandir(getcwd()) as $f) {
    if(!is_link($f)) continue;
    if(array_key_exists($f, $links)) continue;
    if($f == CMS_RELEASE.".php") continue;
    unlink($f);
  }
  foreach($links as $l => $t) {
    createSymlink($l, $t);
  }
}

function initFiles() {
  if(!file_exists(DEBUG_FILE) && !file_exists(".".DEBUG_FILE)) touch(".".DEBUG_FILE);
  if(!file_exists(FORBIDDEN_FILE) && !file_exists(".".FORBIDDEN_FILE)) touch(FORBIDDEN_FILE);
  $f = "index.php";
  if(SCRIPT_NAME != $f) return;
  $src = CMS_FOLDER."/".SERVER_FILES_DIR."/$f";
  if(filemtime($src) == filemtime($f)) return;
  $fp = lockFile($src);
  if(filemtime($src) == filemtime($f)) {
    unlockFile($fp);
    return;
  }
  copy_plus($src, $f);
  touch($f, filemtime($src));
  unlockFile($fp);
  redirTo(buildLocalUrl(array("path" => getCurLink())), null, sprintf(_("Subdom file %s updated"), $f));
}

function smartCopy($src, $dest) {
  throw new Exception(sprintf(METHOD_NA, __CLASS__.".".__FUNCTION__));
}

function lockFile($filePath) {
  if(!is_file($filePath)) throw new Exception(_("File does not exist"));
  $fp = @fopen($filePath, "r+");
  if(!$fp) throw new Exception(_("Unable to open file"));
  $start_time = microtime(true);
  do {
    if(flock($fp, LOCK_EX|LOCK_NB)) return $fp;
    usleep(rand(10, 500));
  } while(microtime(true) < $start_time+FILE_LOCK_WAIT_SEC);
  throw new Exception(_("Unable to acquire file lock"));
}

function unlockFile($fp) {
  if(is_null($fp)) return;
  flock($fp, LOCK_UN);
  fclose($fp);
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
  mkdir_plus($dest);
  foreach(scandir($src) as $f) {
    if(in_array($f, array(".", ".."))) continue;
    if(is_dir("$src/$f") && !is_link("$src/$f")) {
      if($deep) copyFiles("$src/$f", "$dest/$f", $deep);
      continue;
    }
    #if(!empty($allowedExt) && !in_array(pathinfo($f, PATHINFO_EXTENSION), $allowedExt)) continue;
    if(is_file("$dest/$f") && filemtime("$dest/$f") == filemtime("$src/$f")) continue;
    $fp = lockFile("$src/$f");
    if(is_file("$dest/$f") && filemtime("$dest/$f") == filemtime("$src/$f")) {
      unlockFile($fp);
      continue;
    }
    copy_plus("$src/$f", "$dest/$f");
    unlockFile($fp);
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

function getFileHash($filePath) {
  if(!is_file($filePath)) return "";
  return hash_file(FILE_HASH_ALGO, $filePath);
}

function getDirHash($dirPath) {
  if(!is_dir($dirPath)) return "";
  return hash(FILE_HASH_ALGO, implode("", scandir($dirPath)));
}

function getShortString($str) {
  $lLimit = 60;
  $hLimit = 80;
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

function loginRedir() {
  redirTo(buildLocalUrl(array("path" => getCurLink(), "query" => "login"), 401, _("Authorization required")));
}

?>