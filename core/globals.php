<?php

use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\LoggerException;

function isValidId($id) {
  return (bool) preg_match("/^[A-Za-z][A-Za-z0-9_\.-]*$/", $id);
}

function findFile($filePath, $user=true, $admin=true) {
  $inactiveFilePath = dirname($filePath)."/.".basename($filePath);
  $dirs = array(CMS_FOLDER);
  if($admin) array_unshift($dirs, ADMIN_FOLDER);
  if($user) array_unshift($dirs, USER_FOLDER);
  foreach($dirs as $dir) {
    if(!is_file("$dir/$filePath")) continue;
    if(is_file("$dir/$inactiveFilePath")) continue;
    return "$dir/$filePath";
  }
  throw new Exception(sprintf(_("File '%s' not found"), $filePath));
}

function createSymlink($link, $target) {
  $restart = false;
  if(is_link($link) && readlink($link) == $target) return;
  elseif(is_link($link)) $restart = true;
  if(!symlink($target, "$link~") || !rename("$link~", $link))
    throw new Exception(sprintf(_("Unable to create symlink '%s'"), $link));
  #if($restart && !touch(APACHE_RESTART_FILEPATH))
  if(!$restart) return;
  Logger::warning(_("Symlink changed; may take time to apply"));
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
          Logger::user_warning(sprintf(_("Variable '%s' does not exist"), $vName));
        $newString .= $v;
        continue;
      }
    }
    $value = $variables[$vName];
    if(is_array($value)) $value = implode(", ", $value);
    elseif($value instanceof DOMElementPlus) $value = $value->nodeValue;
    elseif(!is_string($value)) {
      if(strpos($v, "@") !== 0)
        Logger::user_warning(sprintf(_("Variable '%s' is not string"), $vName));
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
    if(class_exists("IGCMS\Core\Logger"))
      Logger::user_notice(_("Redirecting to empty string changed to root"));
  }
  if(class_exists("IGCMS\Core\Logger"))
    Logger::user_info(sprintf(_("Redirecting to '%s'"), $link).(!is_null($msg) ? ": $msg" : ""));
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
  if(isset($p["scheme"])) {
    $url .= $p["scheme"]."://".HOST."/";
    if(isset($p["path"])) $p["path"] = ltrim($p["path"], "/");
  }
  if(isset($p["path"])) $url .= $p["path"];
  if($query && isset($p["query"]) && strlen($p["query"])) $url .= "?".$p["query"];
  if(isset($p["fragment"])) $url .= "#".$p["fragment"];
  return $url;
}

function parseLocalLink($link, $host=null) {
  $pLink = parse_url($link);
  if($pLink === false) throw new LoggerException(sprintf(_("Unable to parse href '%s'"), $link)); // fail2parse
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
  addPermParam($pLink, PAGESPEED_PARAM);
  addPermParam($pLink, DEBUG_PARAM);
  addPermParam($pLink, CACHE_PARAM);
  $cyclic = !$ignoreCyclic && isCyclicLink($pLink);
  if($cyclic && !isset($pLink["fragment"]))
    throw new Exception(_("Link is cyclic"));
  $path = null;
  if(isset($pLink["path"])) {
    $path = ltrim($pLink["path"], "/");
    if(count($pLink) > 1 && $cyclic) unset($pLink["path"]);
    else $pLink["path"] = ROOT_URL.$path;
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

function addPermParam(Array &$pLink, $parName) {
  if(!isset($_GET[$parName])) return;
  $parAndVal = "$parName=".$_GET[$parName];
  if(isset($pLink["query"])) {
    if(strpos($pLink["query"], $parName) !== false) return;
    $pLink["query"] = $pLink["query"]."&".$parAndVal;
    return;
  }
  $pLink["query"] = $parAndVal;
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

function getCurQuery($questionMark=false) {
  if(!isset($_SERVER['QUERY_STRING']) || !strlen($_SERVER['QUERY_STRING'])) return "";
  parse_str($_SERVER['QUERY_STRING'], $pQuery);
  if(isset($pQuery["q"])) unset($pQuery["q"]);
  return buildQuery($pQuery, $questionMark);
}

function buildQuery($pQuery, $questionMark=true) {
  if(empty($pQuery)) return "";
  return ($questionMark ? "?" : "").rtrim(urldecode(http_build_query($pQuery)), "=");
}

function slugify($text) {
  $text = str_replace(array(" ", " - "), array("_", "-"), trim($text));
  $text = preg_replace('#[^\\pL\d_-]+#u', '', $text);
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  $text = strtolower($text);
  $text = preg_replace('#[^-\w]+#', '', $text);
  return $text;
}

function normalize($s, $keep=null, $replace=null, $tolower=true, $convertToUtf8=false) {
  if($convertToUtf8) $s = utf8_encode($s);
  if($tolower) $s = mb_strtolower($s, "utf-8");
  // iconv
  // http://php.net/manual/en/function.iconv.php#74101
  // works with setlocale(LC_ALL, "[any].UTF-8")
  $s = iconv("UTF-8", "US-ASCII//TRANSLIT", $s);
  if($tolower) $s = strtolower($s);
  if(is_null($replace)) $replace = "_";
  if(is_null($keep)) $keep = "a-zA-Z0-9_-";
  $s = @preg_replace("~[^$keep]~", $replace, $s);
  if(is_null($s))
    throw new Exception(_("Invalid parameter 'keep'"));
  return $s;
}

function file_put_contents_plus($dest, $string) {
  $b = file_put_contents("$dest.new", $string);
  if($b === false) throw new Exception(_("Unable to save content"));
  copy_plus("$dest.new", $dest, false);
}

function copy_plus($src, $dest, $keepSrc = true) {
  if(is_link($src)) throw new Exception(_("Source file is a link"));
  if(!is_file($src)) throw new Exception(_("Source file not found"));
  mkdir_plus(dirname($dest));
  if(!is_link($dest) && is_file($dest) && !copy($dest, "$dest.old"))
    throw new Exception(_("Unable to backup destination file"));
  $srcMtime = filemtime($src);
  if($keepSrc) {
    if(!copy($src, "$dest.new"))
      throw new Exception(_("Unable to copy source file"));
    $src = "$dest.new";
  }
  if(!rename($src, $dest))
    throw new Exception(_("Unable to rename new file to destination"));
  if(!touch($dest, $srcMtime))
    throw new Exception(_("Unable to set new file modification time"));
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

function incrementalRename($src, $dest=null) {
  if(!file_exists($src))
    throw new Exception(sprintf(_("Source file '%s' not found"), basename($src)));
  if(is_null($dest)) $dest = $src;
  $i = 0;
  while(file_exists($dest.$i)) $i++;
  if(!rename($src, $dest.$i))
    throw new Exception(sprintf(_("Unable to rename directory '%s'"), basename($src)));
  return $dest.$i;
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

function update_file($src, $dest, $hash=false) {
  if(is_link($dest)) return false;
  if(!is_file($src)) throw new Exception("Source file not found");
  if(is_file($dest)) {
    if($hash && getFileHash($src) == getFileHash($dest)) return false;
    if(!$hash && filemtime($src) == filemtime($dest)) return false;
  }
  $fp = lock_file($dest);
  try {
    copy_plus($src, $dest);
  } finally {
    unlock_file($fp);
  }
  return true;
}

function lock_file($filePath, $ext="lock") {
  if(strlen($ext)) $filePath = "$filePath.$ext";
  mkdir_plus(dirname($filePath));
  $fpr = @fopen($filePath, "c+");
  if(!$fpr) throw new Exception(sprintf(_("Unable to open file %s"), $filePath));
  $start_time = microtime(true);
  do {
    if(flock($fpr, LOCK_EX|LOCK_NB)) return $fpr;
    usleep(rand(10, 500));
  } while(microtime(true) < $start_time+FILE_LOCK_WAIT_SEC);
  throw new Exception(_("Unable to acquire file lock"));
}

function unlock_file($fpr, $fileName=null, $ext="lock") {
  if(is_null($fpr)) return;
  flock($fpr, LOCK_UN);
  fclose($fpr);
  if(!strlen($fileName) || !strlen($ext)) return;
  for($i=0; $i<20; $i++) {
    if(@unlink("$fileName.$ext")) return;
    usleep(100000);
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

function translateUtf8Entities($xmlSource, $reverse = FALSE) {
  static $literal2NumericEntity;
  if(empty($literal2NumericEntity)) {
    $transTbl = get_html_translation_table(HTML_ENTITIES);
    foreach ($transTbl as $char => $entity) {
      if (strpos('&"<>', $char) !== FALSE) continue;
      #$literal2NumericEntity[$entity] = '&#'.ord($char).';';
      $literal2NumericEntity[$entity] = $char;
    }
  }
  if($reverse) return strtr($xmlSource, array_flip($literal2NumericEntity));
  return strtr($xmlSource, $literal2NumericEntity);
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

function getFileMime($filePath) {
  $fh=fopen($filePath,'rb');
  if(!$fh) throw new Exception(_("Unable to open file"));
  try {
    $bytes6 = fread($fh, 6);
    if($bytes6 === false) throw new Exception(_("Unable to read file"));
    if(substr($bytes6, 0, 3) == "\xff\xd8\xff") return 'image/jpeg';
    if($bytes6 == "\x89PNG\x0d\x0a") return 'image/png';
    if($bytes6 == "GIF87a" || $bytes6 == "GIF89a") return 'image/gif';
    if(!function_exists("finfo_file")) throw new Exception(_("Function finfo_file() not supported"));
    $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
    $mime = finfo_file($finfo, $filePath); // avg. 2ms
    finfo_close($finfo);
    return $mime;
  } finally {
    fclose($fh);
  }
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

function stripDataFolder($filePath) {
  $folders = array(USER_FOLDER, ADMIN_FOLDER, CMS_FOLDER);
  foreach($folders as $folder) {
    if(strpos($filePath, "$folder/") !== 0) continue;
    return substr($filePath, strlen($folder)+1);
  }
  return $filePath;
}

function getShortString($str, $lLimit=60, $hLimit=80, $delim=" ") {
  if(strlen($str) < $hLimit) return $str;
  $w = explode($delim, $str);
  $sStr = $w[0];
  $i = 1;
  while(strlen($sStr) < $lLimit) {
    if(!isset($w[$i])) break;
    $sStr .= $delim.$w[$i++];
  }
  if(strlen($str) - strlen($sStr) < $hLimit - $lLimit) return $str;
  return $sStr."…";
}

function loginRedir() {
  $aQuery = array();
  parse_str(getCurQuery(), $aQuery);
  $q = buildQuery(array_merge($aQuery, array("login" => "")), false);
  $pLink = array("scheme" => "https", "path" => getCurLink(), "query" => $q);
  redirTo(buildLocalUrl($pLink, 401, _("Authorization required")));
}

if(!function_exists("apc_exists")) {
  function apc_exists($key) {
    return file_exists(apc_get_path($key));
  }
}

if(!function_exists("apc_fetch")) {
  function apc_fetch($key) {
    return json_decode(file_get_contents(apc_get_path($key)), true);
  }
}

if(!function_exists("apc_store")) {
  function apc_store($key, $value) {
    return file_put_contents(apc_get_path($key), json_encode($value)) !== false;
  }
}

function apc_get_path($key) {
  $apcDir = getcwd()."/../tmp_apc/";
  mkdir_plus($apcDir);
  return $apcDir.normalize($key, "a-zA-Z0-9_.-", "+");
}

function apc_get_key($key) {
  $class = "core";
  $callers = debug_backtrace();
  if(isset($callers[1]['class'])) $class = $callers[1]['class'];
  return APC_PREFIX."/".HOST."/".$class."/".Cms::isSuperUser()."/".$key;
}

function apc_store_cache($cacheKey, $value, $name) {
  $stored = apc_store($cacheKey, $value, rand(3600*24*30*3, 3600*24*30*6));
  if(!$stored) Logger::critical(sprintf(_("Unable to cache variable %s"), $name));
}

function apc_is_valid_cache($cacheKey, $value) {
  if(!apc_exists($cacheKey)) return false;
  if(apc_fetch($cacheKey) != $value) return false;
  return true;
}

function clearNginxCache() {
  if(IS_LOCALHOST) return;
  foreach(getNginxCacheFiles() as $fPath) {
    if(!unlink($fPath)) throw new Exception(_("Failed to purge cache"));
  }
}

function getNginxCacheFiles($folder = null, $link = "") {
  if(is_null($folder)) $folder = NGINX_CACHE_FOLDER;
  $fPaths = array();
  foreach(scandir($folder) as $f) {
    if(strpos($f, ".") === 0) continue;
    $ff = "$folder/$f";
    if(is_dir($ff)) {
      $fPaths = array_merge($fPaths, getNginxCacheFiles($ff, $link));
      continue;
    }
    if(empty(preg_grep("/KEY: https?".HOST."/$link", file($ff)))) continue;
    $fPaths[] = $ff;
  }
  return $fPaths;
}

function getIP() {
  if(!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
  if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
  return $_SERVER['REMOTE_ADDR'];
}

function getRealResDir($file="") {
  $resDir = RESOURCES_DIR;
  if(basename(SCRIPT_NAME) != "index.php") $resDir = pathinfo(SCRIPT_NAME, PATHINFO_FILENAME);
  return $resDir.(strlen($file) ? "/$file" : "");
}

function getResDir($file="") {
  if(is_null(Cms::getLoggedUser())) return $file;
  if(getRealResDir() != RESOURCES_DIR) return getRealResDir($file);
  if(!isset($_GET[DEBUG_PARAM]) || $_GET[DEBUG_PARAM] != DEBUG_ON) return $file; // Debug is off
  return getRealResDir($file);
}

function isUptodate($sourceFile, $cacheFile) {
  if(filemtime($cacheFile) == filemtime($sourceFile)) return true;
  if(getFileHash($cacheFile) != getFileHash($sourceFile)) return false;
  touch($cacheFile, filemtime($sourceFile));
  return true;
}

function validate_callStatic($methodName, Array $arguments, Array $functions, $nonEmptyArgumentsCount=0) {
  if(!array_key_exists($methodName, $functions))
    throw new Exception(sprintf(_("Undefined method name %s"), $methodName));
  for($i=0; $i<$nonEmptyArgumentsCount; $i++) {
    if(array_key_exists($i, $arguments) && strlen($arguments[$i])) continue;
    throw new Exception(sprintf(_("Argument[%s] empty or missing"), $i));
  }
}

// UNUSED
function clearApcCache() {
  $cache_info = apc_cache_info();
  foreach($cache_info["cache_list"] as $data) {
    if(strpos($data["info"], HOST) === 0) apc_delete($data["info"]);
  }
}

?>
