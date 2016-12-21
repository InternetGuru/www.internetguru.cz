<?php

use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\Logger;

/**
 * @param string $id
 * @return bool
 */
function isValidId($id) {
  return (bool) preg_match("/^[A-Za-z][A-Za-z0-9_\.-]*$/", $id);
}

/**
 * @param $filePath
 * @param bool $user
 * @param bool $admin
 * @return string
 * @throws Exception
 */
function findFile($filePath, $user=true, $admin=true) {
  $inactiveFilePath = dirname($filePath)."/.".basename($filePath);
  $dirs = array(CMS_FOLDER);
  if($admin) array_unshift($dirs, ADMIN_FOLDER);
  if($user) array_unshift($dirs, USER_FOLDER);
  foreach($dirs as $d) {
    if(!is_file("$d/$filePath")) continue;
    if(is_file("$d/$inactiveFilePath")) continue;
    $path = realpath("$d/$filePath");
    if(strpos($path, realpath($d).DIRECTORY_SEPARATOR) !== 0) {
      throw new Exception(sprintf(_("File '%s' is out of working space"), $filePath));
    }
    return $path;
  }
  throw new Exception(sprintf(_("File '%s' not found"), $filePath));
}

/**
 * @param int $i
 * @return string
 * @throws Exception
 */
function getCallerClass($i=1) {
  $backtrace = debug_backtrace();
  if(!array_key_exists("class", $backtrace[$i+1])) throw new Exception(_("Unknown caller class"));
  return (new \ReflectionClass($backtrace[$i+1]["class"]))->getShortName();
}

/**
 * @param string $link
 * @param string $target
 * @throws Exception
 */
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

/**
 * @param string $string
 * @param array $variables
 * @param string|null $varPrefix
 * @return string
 */
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

/**
 * @param string $link
 * @param int|null $code
 * @param string|null $msg
 */
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

/**
 * @param array $pLink
 * @param bool $query
 * @return string
 */
function implodeLink(Array $pLink, $query=true) {
  $url = "";
  if(isset($pLink["scheme"])) {
    $url .= $pLink["scheme"]."://".HOST."/";
    if(isset($pLink["path"])) $pLink["path"] = ltrim($pLink["path"], "/");
  }
  if(isset($pLink["path"])) $url .= $pLink["path"];
  if($query && isset($pLink["query"]) && strlen($pLink["query"])) $url .= "?".$pLink["query"];
  if(isset($pLink["fragment"])) $url .= "#".$pLink["fragment"];
  return $url;
}

/**
 * @param string $link
 * @param string|null $host
 * @return array|null
 * @throws Exception
 */
function parseLocalLink($link, $host=null) {
  $pLink = parse_url($link);
  if($pLink === false) throw new Exception(sprintf(_("Unable to parse attribute href '%s'"), $link)); // fail2parse
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

/**
 * @param array $pLink
 * @param bool $ignoreCyclic
 * @param bool $addPermParam
 * @return string
 * @throws Exception
 */
function buildLocalUrl(Array $pLink, $ignoreCyclic=false, $addPermParam=true) {
  if($addPermParam) addPermParams($pLink);
  $cyclic = !$ignoreCyclic && isCyclicLink($pLink);
  if($cyclic && !isset($pLink["fragment"]))
    throw new Exception(_("Link is cyclic"));
  if(!isset($pLink["path"])) return implodeLink($pLink);
  $path = $pLink["path"];
  #$path = ltrim($pLink["path"], "/");
  if(count($pLink) > 1 && $cyclic) unset($pLink["path"]);
  else $pLink["path"] = ROOT_URL.$path;
  #if(is_null($path) && isset($pLink["fragment"])) return "#".$pLink["fragment"];
  if(SCRIPT_NAME == "index.php" || strpos($path, FILES_DIR) === 0)
    return implodeLink($pLink);
  $pLink["path"] = ROOT_URL.SCRIPT_NAME;
  if($cyclic) $pLink["path"] = "";
  $query = array();
  if(strlen($path)) $query[] = "q=".$path;
  if(isset($pLink["query"]) && strlen($pLink["query"])) $query[] = $pLink["query"];
  if(count($query)) $pLink["query"] = implode("&", $query);
  return implodeLink($pLink);
}

/**
 * @param array $pLink
 * @return bool
 */
function isCyclicLink(Array $pLink) {
  if(isset($pLink["fragment"])) return false;
  if(isset($pLink["path"]) && $pLink["path"] != getCurLink() && SCRIPT_NAME != $pLink["path"]) return false;
  if(!isset($pLink["query"]) && getCurQuery() != "") return false;
  if(isset($pLink["query"]) && $pLink["query"] != getCurQuery()) return false;
  return true;
}

/**
 * @param array $pLink
 */
function addPermParams(Array &$pLink) {
  foreach(array(PAGESPEED_PARAM, DEBUG_PARAM, CACHE_PARAM) as $parName) {
    if(!isset($_GET[$parName]) || !strlen($_GET[$parName])) continue;
    $parAndVal = "$parName=".$_GET[$parName];
    if(isset($pLink["query"])) {
      if(strpos($pLink["query"], $parName) !== false) continue;
      $pLink["query"] = $pLink["query"]."&".$parAndVal;
      continue;
    }
    $pLink["query"] = $parAndVal;
  }
}

/**
 * @param array $a
 */
function stableSort(Array &$a) {
  if(count($a) < 2) return;
  $order = range(1, count($a));
  array_multisort($a, SORT_ASC, $order, SORT_ASC);
}

/**
 * @param bool $query
 * @return string
 */
function getCurLink($query=false) {
  $link = isset($_GET["q"]) ? $_GET["q"] : "";
  return $link.($query ? getCurQuery(true) : "");
}

/**
 * @param bool $questionMark
 * @return string
 */
function getCurQuery($questionMark=false) {
  if(!isset($_SERVER['QUERY_STRING']) || !strlen($_SERVER['QUERY_STRING'])) return "";
  parse_str($_SERVER['QUERY_STRING'], $pQuery);
  if(isset($pQuery["q"])) unset($pQuery["q"]);
  return buildQuery($pQuery, $questionMark);
}

/**
 * @param array $pQuery
 * @param bool $questionMark
 * @return string
 */
function buildQuery(Array $pQuery, $questionMark=true) {
  if(empty($pQuery)) return "";
  return ($questionMark ? "?" : "").rtrim(urldecode(http_build_query($pQuery)), "=");
}

/**
 * @param string $text
 * @param string|null $keep
 * @param string|null $replace
 * @param bool $tolower
 * @param bool $convertToUtf8
 * @return string
 * @throws Exception
 */
function normalize($text, $keep=null, $replace=null, $tolower=true, $convertToUtf8=false) {
  if(!strlen(trim($text))) return "";
  if($convertToUtf8) $text = utf8_encode($text);
  #$text = preg_replace('#[^\\pL\d _-]+#u', '', $text);
  #if($pred != $text) {var_dump($pred); var_dump($text); }
  #if($tolower) $text = mb_strtolower($text, "utf-8");
  // iconv
  // http://php.net/manual/en/function.iconv.php#74101
  // works with setlocale(LC_ALL, "[any].UTF-8")
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  $text = preg_replace('# +#u', '_', $text);
  $text = preg_replace('#_?-_?#u', '-', $text);
  if($tolower) $text = strtolower($text);
  if(is_null($replace)) $replace = "";
  if(is_null($keep)) $keep = "\w\d_-";
  $text = @preg_replace("~[^$keep]~", $replace, $text);
  if(is_null($text))
    throw new Exception(_("Invalid parameter 'keep'"));
  return $text;
}

/**
 * @param string $dest
 * @param string $string
 * @throws Exception
 */
function file_put_contents_plus($dest, $string) {
  $b = file_put_contents("$dest.new", $string);
  if($b === false) throw new Exception(_("Unable to save content"));
  copy_plus("$dest.new", $dest, false);
}

/**
 * @param string $src
 * @param string $dest
 * @param bool $keepSrc
 * @throws Exception
 */
function copy_plus($src, $dest, $keepSrc=true) {
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

/**
 * @param string $dir
 * @param int $mode
 * @param bool $recursive
 * @throws Exception
 */
function mkdir_plus($dir, $mode=0775, $recursive=true) {
  for($i=0; $i<10; $i++) {
    if(is_dir($dir)) return;
    if(mkdir($dir, $mode, $recursive)) return;
    usleep(20000);
  }
  throw new Exception(_("Unable to create directory"));
}

/**
 * @param string $src
 * @param string|null $dest
 * @return string
 * @throws Exception
 */
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

function initIndexFiles() {
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

/**
 * @param int $timestamp
 * @return string
 */
function timestamptToW3C($timestamp) {
  $date = new DateTime();
  $date->setTimestamp($timestamp);
  return $date->format(DateTime::W3C);
}

/**
 * @param string $src
 * @param string $dest
 * @param bool $hash
 * @return bool
 * @throws Exception
 */
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
    unlock_file($fp, $dest);
  }
  return true;
}

/**
 * @param string $filePath
 * @param string $ext
 * @return resource
 * @throws Exception
 */
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

/**
 * @param resource $fpr
 * @param string|null $fileName
 * @param string $ext
 */
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

/**
 * @param string $xmlSource
 * @param bool $reverse
 * @return string
 */
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

/**
 * @param string $archiveFile
 * @param string $dataFile
 * @return string|bool
 * @throws Exception
 */
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

/**
 * @param string $filePath
 * @return mixed|string
 * @throws Exception
 */
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

/**
 * TODO throw if not numeric?
 * @param int $b
 * @return float|int|mixed
 */
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

/**
 * @param string $filePath
 * @return string
 */
function getFileHash($filePath) {
  if(!is_file($filePath)) return "";
  return hash_file(FILE_HASH_ALGO, $filePath);
}

/**
 * @param string $filePath
 * @return string
 */
function stripDataFolder($filePath) {
  $folders = array(USER_FOLDER, ADMIN_FOLDER, CMS_FOLDER);
  foreach($folders as $folder) {
    if(strpos($filePath, "$folder/") !== 0) continue;
    return substr($filePath, strlen($folder)+1);
  }
  return $filePath;
}

/**
 * @param string $str
 * @param int $lLimit
 * @param int $hLimit
 * @param string $delim
 * @return string
 */
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
  function apc_store($key, $value, $ttl=0) {
    return file_put_contents(apc_get_path($key), json_encode($value)) !== false;
  }
}

/**
 * @param string $key
 * @return string
 */
function apc_get_path($key) {
  $apcDir = getcwd()."/../tmp_apc/";
  mkdir_plus($apcDir);
  return $apcDir.normalize($key, "a-zA-Z0-9_.-", "+");
}

/**
 * @param string $key
 * @return string
 */
function apc_get_key($key) {
  $class = "core";
  $callers = debug_backtrace();
  if(isset($callers[1]['class'])) $class = $callers[1]['class'];
  return APC_PREFIX."/".HOST."/".$class."/".Cms::isSuperUser()."/".$key;
}

/**
 * @param string $cacheKey
 * @param mixed $value
 * @param string $name
 */
function apc_store_cache($cacheKey, $value, $name) {
  $stored = apc_store($cacheKey, $value, rand(3600*24*30*3, 3600*24*30*6));
  if(!$stored) Logger::critical(sprintf(_("Unable to cache variable %s"), $name));
}

/**
 * @param string $cacheKey
 * @param mixed $value
 * @return bool
 */
function apc_is_valid_cache($cacheKey, $value) {
  if(!apc_exists($cacheKey)) return false;
  if(apc_fetch($cacheKey) != $value) return false;
  return true;
}

/**
 * @throws Exception
 */
function clearNginxCache() {
  foreach(getNginxCacheFiles() as $fPath) {
    if(!unlink($fPath)) throw new Exception(_("Failed to purge cache"));
  }
}

/**
 * @param string|null $folder
 * @param string $link
 * @return array
 */
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

/**
 * @return string
 */
function getIP() {
  if(!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
  if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
  return $_SERVER['REMOTE_ADDR'];
}

/**
 * @param string $file
 * @return string
 */
function getRealResDir($file="") {
  $resDir = RESOURCES_DIR;
  if(basename(SCRIPT_NAME) != "index.php") $resDir = pathinfo(SCRIPT_NAME, PATHINFO_FILENAME);
  return $resDir.(strlen($file) ? "/$file" : "");
}

/**
 * @param string $file
 * @return string
 */
function getResDir($file="") {
  if(is_null(Cms::getLoggedUser())) return $file;
  if(getRealResDir() != RESOURCES_DIR) return getRealResDir($file);
  if(!isset($_GET[DEBUG_PARAM]) || $_GET[DEBUG_PARAM] != DEBUG_ON) return $file; // Debug is off
  return getRealResDir($file);
}

/**
 * @param string $sourceFile
 * @param string $cacheFile
 * @return bool
 */
function isUptodate($sourceFile, $cacheFile) {
  if(filemtime($cacheFile) == filemtime($sourceFile)) return true;
  if(getFileHash($cacheFile) != getFileHash($sourceFile)) return false;
  touch($cacheFile, filemtime($sourceFile));
  return true;
}

/**
 * @param string $methodName
 * @param array $arguments
 * @param array $functions
 * @param int $nonEmptyArgumentsCount
 * @throws Exception
 */
function validate_callStatic($methodName, Array $arguments, Array $functions, $nonEmptyArgumentsCount=0) {
  if(!array_key_exists($methodName, $functions))
    throw new Exception(sprintf(_("Undefined method name %s"), $methodName));
  for($i=0; $i<$nonEmptyArgumentsCount; $i++) {
    if(array_key_exists($i, $arguments) && strlen($arguments[$i])) continue;
    throw new Exception(sprintf(_("Argument[%s] empty or missing"), $i));
  }
}
