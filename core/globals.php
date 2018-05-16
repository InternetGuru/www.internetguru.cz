<?php

use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\Logger;

/**
 * @param string $id
 * @return bool
 */
function is_valid_id ($id) {
  return (bool) preg_match("/^[A-Za-z][A-Za-z0-9_\.-]*$/", $id);
}

/**
 * @param $filePath
 * @param bool $user
 * @param bool $admin
 * @return string
 * @throws Exception
 */
function find_file ($filePath, $user = true, $admin = true) {
  $inactiveFilePath = dirname($filePath)."/.".basename($filePath);
  $dirs = [CMS_FOLDER];
  if ($admin) {
    array_unshift($dirs, ADMIN_FOLDER);
  }
  if ($user) {
    array_unshift($dirs, USER_FOLDER);
  }
  foreach ($dirs as $dir) {
    if (!stream_resolve_include_path("$dir/$filePath")) {
      continue;
    }
    if (stream_resolve_include_path("$dir/$inactiveFilePath")) {
      continue;
    }
    $path = realpath("$dir/$filePath");
    if (strpos($path, realpath($dir).DIRECTORY_SEPARATOR) !== 0) {
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
function get_caller_class ($i = 1) {
  $backtrace = debug_backtrace();
  if (!array_key_exists("class", $backtrace[$i + 1])) {
    throw new Exception(_("Unknown caller class"));
  }
  return (new \ReflectionClass($backtrace[$i + 1]["class"]))->getShortName();
}

/**
 * @param string $link
 * @param string $target
 * @throws Exception
 */
function create_symlink ($link, $target) {
  $restart = false;
  if (is_link($link) && readlink($link) == $target) {
    return;
  } elseif (is_link($link)) {
    $restart = true;
  }
  if (!symlink($target, "$link~") || !rename("$link~", $link)) {
    throw new Exception(sprintf(_("Unable to create symlink '%s'"), $link));
  }
  #if($restart && !touch(APACHE_RESTART_FILEPATH))
  if (!$restart) {
    return;
  }
  Logger::warning(_("Symlink changed; may take time to apply"));
}

/**
 * @param string $string
 * @param array $variables
 * @param string|null $varPrefix
 * @return string
 */
function replace_vars ($string, Array $variables, $varPrefix = null) {
  if (!strlen($string)) {
    return $string;
  }
  $pat = '/(@?\$'.VARIABLE_PATTERN.')/i';
  $arr = preg_split($pat, $string, -1, PREG_SPLIT_DELIM_CAPTURE);
  if (count($arr) < 2) {
    return $string;
  }
  $newString = "";
  foreach ($arr as $pos => $chunk) {
    if ($pos % 2 == 0) {
      $newString .= $chunk;
      continue;
    }
    $vName = substr($chunk, strpos($chunk, '$') + 1);
    if (!array_key_exists($vName, $variables)) {
      $vName = $varPrefix.$vName;
      if (!array_key_exists($vName, $variables)) {
        if (strpos($chunk, "@") !== 0) {
          Logger::user_warning(sprintf(_("Variable '%s' does not exist"), $vName));
        }
        $newString .= $chunk;
        continue;
      }
    }
    $value = $variables[$vName]["value"];
    if (is_array($value)) {
      $value = implode(", ", $value);
    } elseif ($value instanceof DOMElementPlus) {
      $value = $value->nodeValue;
    } elseif (!is_string($value)) {
      if (strpos($chunk, "@") !== 0) {
        Logger::user_warning(sprintf(_("Variable '%s' is not string"), $vName));
      }
      $newString .= $chunk;
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
function redir_to ($link, $code = null, $msg = null) {
  http_response_code(is_null($code) ? 302 : $code);
  if (!strlen($link)) {
    $link = ROOT_URL;
    if (class_exists("IGCMS\Core\Logger")) {
      Logger::user_notice(_("Redirecting to empty string changed to root"));
    }
  }
  if (class_exists("IGCMS\Core\Logger")) {
    Logger::user_info(sprintf(_("Redirecting to '%s'"), $link).(!is_null($msg) ? ": $msg" : ""));
  }
  #var_dump($link); die();
  if (is_null($code) || !is_numeric($code)) {
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
function implode_link (Array $pLink, $query = true) {
  $url = "";
  if (isset($pLink["scheme"])) {
    $url .= $pLink["scheme"]."://".HTTP_HOST."/";
    if (isset($pLink["path"])) {
      $pLink["path"] = ltrim($pLink["path"], "/");
    }
  }
  if (isset($pLink["path"])) {
    $url .= $pLink["path"];
  }
  if ($query && isset($pLink["query"]) && strlen($pLink["query"])) {
    $url .= "?".$pLink["query"];
  }
  if (isset($pLink["fragment"])) {
    $url .= "#".$pLink["fragment"];
  }
  return $url;
}

/**
 * @param string $link
 * @param string|null $host
 * @return array|null
 * @throws Exception
 */
function parse_local_link ($link, $host = null) {
  $pLink = parse_url($link);
  if ($pLink === false) {
    throw new Exception(sprintf(_("Unable to parse attribute href '%s'"), $link));
  } // fail2parse
  foreach ($pLink as $key => $value) {
    if (!strlen($value)) {
      unset($pLink[$key]);
    }
  }
  if (isset($pLink["path"])) {
    $pLink["path"] = trim($pLink["path"], "/");
  }
  if (isset($pLink["scheme"])) {
    if ($pLink["scheme"] != SCHEME) {
      return null;
    } // different scheme
    unset($pLink["scheme"]);
  }
  if (isset($pLink["host"])) {
    if ($pLink["host"] != (is_null($host) ? HTTP_HOST : $host)) {
      return null;
    } // different ns
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
function build_local_url (Array $pLink, $ignoreCyclic = false, $addPermParam = true) {
  if ($addPermParam) {
    add_perm_param($pLink);
  }
  $cyclic = !$ignoreCyclic && is_cyclic_link($pLink);
  if ($cyclic && !isset($pLink["fragment"])) {
    throw new Exception(_("Link is cyclic"));
  }
  if (!isset($pLink["path"])) {
    return implode_link($pLink);
  }
  $path = $pLink["path"];
  #$path = ltrim($pLink["path"], "/");
  if (count($pLink) > 1 && $cyclic) {
    unset($pLink["path"]);
  } else {
    $pLink["path"] = ROOT_URL.$path;
  }
  #if(is_null($path) && isset($pLink["fragment"])) return "#".$pLink["fragment"];
  if (SCRIPT_NAME == FINDEX_PHP || SCRIPT_NAME == INDEX_PHP || strpos($path, FILES_DIR) === 0) {
    return implode_link($pLink);
  }
  $pLink["path"] = ROOT_URL.SCRIPT_NAME;
  if ($cyclic) {
    $pLink["path"] = "";
  }
  $query = [];
  if (strlen($path)) {
    $query[] = "q=".$path;
  }
  if (isset($pLink["query"]) && strlen($pLink["query"])) {
    $query[] = $pLink["query"];
  }
  if (count($query)) {
    $pLink["query"] = implode("&", $query);
  }
  return implode_link($pLink);
}

/**
 * @param array $pLink
 * @return bool
 */
function is_cyclic_link (Array $pLink) {
  if (isset($pLink["fragment"])) {
    return false;
  }
  if (isset($pLink["path"]) && $pLink["path"] != get_link()
    && SCRIPT_NAME != $pLink["path"]) {
    return false;
  }
  if (!isset($pLink["query"]) && get_query() != "") {
    return false;
  }
  if (isset($pLink["query"]) && $pLink["query"] != get_query()) {
    return false;
  }
  return true;
}

/**
 * @param array $pLink
 */
function add_perm_param (Array &$pLink) {
  foreach ([PAGESPEED_PARAM, DEBUG_PARAM, CACHE_PARAM] as $parName) {
    if (!isset($_GET[$parName]) || !strlen($_GET[$parName])) {
      continue;
    }
    $parAndVal = "$parName=".$_GET[$parName];
    if (isset($pLink["query"])) {
      if (strpos($pLink["query"], $parName) !== false) {
        continue;
      }
      $pLink["query"] = $pLink["query"]."&".$parAndVal;
      continue;
    }
    $pLink["query"] = $parAndVal;
  }
}

/**
 * @param array $array
 * @param int $order
 */
function stable_sort (Array &$array, $order = SORT_ASC) {
  if (count($array) < 2) {
    return;
  }
  $orderArray = range(1, count($array));
  array_multisort($array, $order, $orderArray, $order);
}

/**
 * @param bool $query
 * @return string
 */
function get_link ($query = false) {
  return get_query($query, true);
}

/**
 * @param bool $questionMark
 * @param bool $link
 * @return string
 */
function get_query ($questionMark = false, $link = false) {
  if (!isset($_SERVER['QUERY_STRING']) || !strlen($_SERVER['QUERY_STRING'])) {
    return "";
  }
  parse_str($_SERVER['QUERY_STRING'], $pQuery);
  $curLink = "";
  if (isset($pQuery["q"])) {
    $curLink = $pQuery["q"];
    unset($pQuery["q"]);
  }
  $query = build_query($pQuery, $questionMark);
  if (!$link) {
    return $query; // no link with/without questionmark (true/false, false)
  }
  if (!$questionMark) {
    return $curLink; // just a link (false, true)
  }
  return $curLink.$query; // link and query (true, true)
}

/**
 * @param array $pQuery
 * @param bool $questionMark
 * @return string
 */
function build_query (Array $pQuery, $questionMark = true) {
  if (empty($pQuery)) {
    return "";
  }
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
function normalize ($text, $keep = null, $replace = null, $tolower = true, $convertToUtf8 = false) {
  if (!strlen(trim($text))) {
    return "";
  }
  if ($convertToUtf8) {
    $text = utf8_encode($text);
  }
  #$text = preg_replace('#[^\\pL\d _-]+#u', '', $text);
  #if($pred != $text) {var_dump($pred); var_dump($text); }
  #if($tolower) $text = mb_strtolower($text, "utf-8");
  // iconv
  // http://php.net/manual/en/function.iconv.php#74101
  // works with setlocale(LC_ALL, "[any].UTF-8")
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  $text = preg_replace('# +#u', '_', $text);
  $text = preg_replace('#_?-_?#u', '-', $text);
  if ($tolower) {
    $text = strtolower($text);
  }
  if (is_null($replace)) {
    $replace = "";
  }
  if (is_null($keep)) {
    $keep = "\w\d_-";
  }
  /** @noinspection PhpUsageOfSilenceOperatorInspection */
  $text = @preg_replace("~[^$keep]~", $replace, $text);
  if (is_null($text)) {
    throw new Exception(_("Invalid parameter 'keep'"));
  }
  return $text;
}

/**
 * @param string $dest
 * @param string $string
 * @throws Exception
 */
function fput_contents ($dest, $string) {
  $bytes = file_put_contents("$dest.new", $string);
  if ($bytes === false) {
    throw new Exception(_("Unable to save content"));
  }
  copy_plus("$dest.new", $dest, false);
}

/**
 * @param string $src
 * @param string $dest
 * @param bool $keepSrc
 * @throws Exception
 */
function copy_plus ($src, $dest, $keepSrc = true) {
  if (is_link($src)) {
    throw new Exception(_("Source file is a link"));
  }
  if (!is_file($src)) {
    throw new Exception(_("Source file not found"));
  }
  mkdir_plus(dirname($dest));
  if (!is_link($dest) && is_file($dest) && !copy($dest, "$dest.old")) {
    throw new Exception(_("Unable to backup destination file"));
  }
  $srcMtime = filemtime($src);
  if ($keepSrc) {
    if (!copy($src, "$dest.new")) {
      throw new Exception(_("Unable to copy source file"));
    }
    $src = "$dest.new";
  }
  if (!rename($src, $dest)) {
    throw new Exception(_("Unable to rename new file to destination"));
  }
  if (!touch($dest, $srcMtime)) {
    throw new Exception(_("Unable to set new file modification time"));
  }
}

/**
 * @param string $dir
 * @param int $mode
 * @param bool $recursive
 * @throws Exception
 */
function mkdir_plus ($dir, $mode = 0775, $recursive = true) {
  for ($iter = 0; $iter < 10; $iter++) {
    if (is_dir($dir)) {
      return;
    }
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (@mkdir($dir, $mode, $recursive)) {
      return;
    }
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
function rename_incr ($src, $dest = null) {
  if (!stream_resolve_include_path($src)) {
    throw new Exception(sprintf(_("Source file '%s' not found"), basename($src)));
  }
  if (is_null($dest)) {
    $dest = $src;
  }
  $iter = 0;
  while (stream_resolve_include_path($dest.$iter)) {
    $iter++;
  }
  if (!rename($src, $dest.$iter)) {
    throw new Exception(sprintf(_("Unable to rename directory '%s'"), basename($src)));
  }
  return $dest.$iter;
}

/**
 * @throws Exception
 */
function init_index_files () {
  $links = [];
  foreach (scandir(CMS_ROOT_FOLDER) as $filename) {
    if (strpos($filename, ".") === 0) {
      continue;
    }
    if (!is_dir(CMS_ROOT_FOLDER."/$filename")) {
      continue;
    }
    if (stream_resolve_include_path(CMS_ROOT_FOLDER."/.$filename")) {
      continue;
    }
    if (!is_file(CMS_ROOT_FOLDER."/$filename/".INDEX_PHP)) {
      continue;
    }
    $links["$filename.php"] = CMS_ROOT_FOLDER."/$filename/".INDEX_PHP;
  }
  foreach (scandir(getcwd()) as $filename) {
    if (!is_link($filename)) {
      continue;
    }
    if (array_key_exists($filename, $links)) {
      continue;
    }
    if ($filename == CMS_RELEASE.".php") {
      continue;
    }
    unlink($filename);
  }
  foreach ($links as $link => $target) {
    create_symlink($link, $target);
  }
}

/**
 * @param int $timestamp
 * @return string
 */
function w3c_timestamp ($timestamp) {
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
function update_file ($src, $dest, $hash = false) {
  if (is_link($dest)) {
    return false;
  }
  if (!is_file($src)) {
    throw new Exception("Source file not found");
  }
  if (is_file($dest)) {
    if ($hash && file_hash($src) == file_hash($dest)) {
      return false;
    }
    if (!$hash && filemtime($src) == filemtime($dest)) {
      return false;
    }
  }
  $filePath = lock_file($dest);
  try {
    copy_plus($src, $dest);
  } finally {
    unlock_file($filePath, $dest);
  }
  return true;
}

/**
 * @param string $filePath
 * @param string $ext
 * @return resource
 * @throws Exception
 */
function lock_file ($filePath, $ext = "lock") {
  if (strlen($ext)) {
    $filePath = "$filePath.$ext";
  }
  mkdir_plus(dirname($filePath));
  /** @noinspection PhpUsageOfSilenceOperatorInspection */
  $fpr = @fopen($filePath, "c+");
  if (!$fpr) {
    throw new Exception(sprintf(_("Unable to open file %s"), $filePath));
  }
  $start_time = microtime(true);
  do {
    if (flock($fpr, LOCK_EX | LOCK_NB)) {
      return $fpr;
    }
    usleep(rand(10, 500));
  } while (microtime(true) < $start_time + FILE_LOCK_WAIT_SEC);
  throw new Exception(_("Unable to acquire file lock"));
}

/**
 * @param resource $fpr
 * @param string|null $fileName
 * @param string $ext
 */
function unlock_file ($fpr, $fileName = null, $ext = "lock") {
  if (is_null($fpr)) {
    return;
  }
  flock($fpr, LOCK_UN);
  fclose($fpr);
  if (!strlen($fileName) || !strlen($ext)) {
    return;
  }
  for ($iter = 0; $iter < 20; $iter++) {
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (@unlink("$fileName.$ext")) {
      return;
    }
    usleep(100000);
  }
}

/**
 * @param string $xmlSource
 * @param bool $reverse
 * @return string
 */
function to_utf8 ($xmlSource, $reverse = false) {
  static $literalToNumeric;
  if (empty($literalToNumeric)) {
    $transTbl = get_html_translation_table(HTML_ENTITIES);
    foreach ($transTbl as $char => $entity) {
      if (strpos('&"<>', $char) !== false) {
        continue;
      }
      #$literal2NumericEntity[$entity] = '&#'.ord($char).';';
      $literalToNumeric[$entity] = $char;
    }
  }
  if ($reverse) {
    return strtr($xmlSource, array_flip($literalToNumeric));
  }
  return strtr($xmlSource, $literalToNumeric);
}

/**
 * @param string $archiveFile
 * @param string $dataFile
 * @return string|bool
 * @throws Exception
 */
function read_zip ($archiveFile, $dataFile) {
  // Create new ZIP archive
  $zip = new ZipArchive;
  // Open received archive file
  if (!$zip->open($archiveFile)) {
    throw new Exception(_("Unable to open file"));
  }
  // If done, search for the data file in the archive
  $index = $zip->locateName($dataFile);
  // If file not found, return null
  if ($index === false) {
    return null;
  }
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
function get_mime ($filePath) {
  /** @noinspection PhpUsageOfSilenceOperatorInspection */
  $handle = @fopen($filePath, 'rb');
  if (!$handle) {
    throw new Exception(_("Unable to open file"));
  }
  try {
    $bytes6 = fread($handle, 6);
    if ($bytes6 === false) {
      throw new Exception(_("Unable to read file"));
    }
    if (substr($bytes6, 0, 3) == "\xff\xd8\xff") {
      return 'image/jpeg';
    }
    if ($bytes6 == "\x89PNG\x0d\x0a") {
      return 'image/png';
    }
    if ($bytes6 == "GIF87a" || $bytes6 == "GIF89a") {
      return 'image/gif';
    }
    if (!function_exists("finfo_file")) {
      throw new Exception(_("Function finfo_file() not supported"));
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
    $mime = finfo_file($finfo, $filePath); // avg. 2ms
    finfo_close($finfo);
    return $mime;
  } finally {
    fclose($handle);
  }
}

/**
 * TODO throw if not numeric?
 * @param int $bytes
 * @return float|int|mixed
 */
function size_unit ($bytes) {
  if (!is_numeric($bytes)) {
    return $bytes;
  }
  $iter = 0;
  $iec = ["B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];
  while (($bytes / 1024) > 1) {
    $bytes = $bytes / 1024;
    $iter++;
  }
  return round($bytes, 1)." ".$iec[$iter];
}

/**
 * @param string $filePath
 * @return string
 */
function file_hash ($filePath) {
  if (!is_file($filePath)) {
    return "";
  }
  return hash_file(FILE_HASH_ALGO, $filePath);
}

/**
 * @param string $filePath
 * @return string
 */
function strip_data_dir ($filePath) {
  $folders = [USER_FOLDER, ADMIN_FOLDER, CMS_FOLDER];
  foreach ($folders as $folder) {
    if (strpos($filePath, "$folder/") !== 0) {
      continue;
    }
    return substr($filePath, strlen($folder) + 1);
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
function shorten ($str, $lLimit = 60, $hLimit = 80, $delim = " ") {
  if (strlen($str) < $hLimit) {
    return $str;
  }
  $words = explode($delim, $str);
  $sStr = $words[0];
  $iter = 1;
  while (strlen($sStr) < $lLimit) {
    if (!isset($words[$iter])) {
      break;
    }
    $sStr .= $delim.$words[$iter++];
  }
  if (strlen($str) - strlen($sStr) < $hLimit - $lLimit) {
    return $str;
  }
  return $sStr."…";
}

/**
 * @throws Exception
 */
function login_redir () {
  $aQuery = [];
  parse_str(get_query(), $aQuery);
  $query = build_query(array_merge($aQuery, ["login" => ""]), false);
  $pLink = ["scheme" => "https", "path" => get_link(), "query" => $query];
  redir_to(build_local_url($pLink, 401, _("Authorization required")));
}

if (!function_exists("apc_exists")) {
  /**
   * @param $key
   * @return bool|string
   * @throws Exception
   */
  function apc_exists ($key) {
    return stream_resolve_include_path(apc_get_path($key));
  }
}

if (!function_exists("apc_fetch")) {
  /**
   * @param $key
   * @return mixed
   * @throws Exception
   */
  function apc_fetch ($key) {
    return json_decode(file_get_contents(apc_get_path($key)), true);
  }
}

/**
 * @param string $key
 * @return string
 * @throws Exception
 */
function apc_get_path ($key) {
  $apcDir = getcwd()."/../tmp_apc/";
  mkdir_plus($apcDir);
  return $apcDir.normalize($key, "a-zA-Z0-9_.-", "+");
}

/**
 * @param string $key
 * @return string
 */
function apc_get_key ($key) {
  $class = "core";
  $callers = debug_backtrace();
  if (isset($callers[1]['class'])) {
    $class = $callers[1]['class'];
  }
  return APC_PREFIX."/".HTTP_HOST."/".$class."/".Cms::isSuperUser()."/".$key;
}

/**
 * @param string $cacheKey
 * @param mixed $value
 * @param string $name
 * @throws Exception
 */
function apc_store_cache ($cacheKey, $value, $name) {
  $stored = apc_store($cacheKey, $value, rand(3600 * 24 * 30 * 3, 3600 * 24 * 30 * 6));
  if (!$stored) {
    Logger::critical(sprintf(_("Unable to cache variable %s"), $name));
  }
}

/**
 * @param string $cacheKey
 * @param mixed $value
 * @return bool
 * @throws Exception
 */
function apc_is_valid_cache ($cacheKey, $value) {
  if (!apc_exists($cacheKey)) {
    return false;
  }
  if (apc_fetch($cacheKey) != $value) {
    return false;
  }
  return true;
}

/**
 * @throws Exception
 */
function clear_nginx () {
  foreach (get_nginx_cache() as $fPath) {
    if (!unlink($fPath)) {
      throw new Exception(_("Failed to purge cache"));
    }
  }
}

/**
 * @param string|null $folder
 * @param string $link
 * @return array
 */
function get_nginx_cache ($folder = null, $link = "") {
  if (is_null($folder)) {
    $folder = NGINX_CACHE_FOLDER;
  }
  $fPaths = [];
  $iterator = new DirectoryIterator($folder);
  foreach ($iterator as $fileinfo) {
    if ($fileinfo->isDot()) {
      continue;
    }
    $filepath = "$folder/".$fileinfo->getFilename();
    if ($fileinfo->isDir()) {
      $fPaths = array_merge($fPaths, get_nginx_cache($filepath, $link));
      continue;
    }
    if (empty(preg_grep("/KEY: https?".HTTP_HOST."/$link", file($filepath)))) {
      continue;
    }
    $fPaths[] = $filepath;
  }
  return $fPaths;
}

/**
 * @return string
 */
function get_ip () {
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    return $_SERVER['HTTP_CLIENT_IP'];
  }
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    return $_SERVER['HTTP_X_FORWARDED_FOR'];
  }
  return $_SERVER['REMOTE_ADDR'];
}

/**
 * @param string $file
 * @return string
 */
function get_real_resdir ($file = "") {
  $resDir = RESOURCES_DIR;
  if (basename(SCRIPT_NAME) != INDEX_PHP) {
    $resDir = pathinfo(SCRIPT_NAME, PATHINFO_FILENAME);
  }
  return $resDir.(strlen($file) ? "/$file" : "");
}

/**
 * @param string $file
 * @return string
 */
function get_resdir ($file = "") {
  if (is_null(Cms::getLoggedUser())) {
    return $file;
  }
  if (get_real_resdir() != RESOURCES_DIR) {
    return get_real_resdir($file);
  }
  if (!isset($_GET[DEBUG_PARAM]) || $_GET[DEBUG_PARAM] != DEBUG_ON) {
    return $file;
  } // Debug is off
  return get_real_resdir($file);
}

/**
 * @param string $sourceFile
 * @param string $cacheFile
 * @return bool
 */
function is_uptodate ($sourceFile, $cacheFile) {
  if (filemtime($cacheFile) == filemtime($sourceFile)) {
    return true;
  }
  if (file_hash($cacheFile) != file_hash($sourceFile)) {
    return false;
  }
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
function validate_callstatic ($methodName, Array $arguments, Array $functions, $nonEmptyArgumentsCount = 0) {
  if (!array_key_exists($methodName, $functions)) {
    throw new Exception(sprintf(_("Undefined method name %s"), $methodName));
  }
  for ($iter = 0; $iter < $nonEmptyArgumentsCount; $iter++) {
    if (array_key_exists($iter, $arguments) && strlen($arguments[$iter])) {
      continue;
    }
    throw new Exception(sprintf(_("Argument[%s] empty or missing"), $iter));
  }
}

/**
 * @param $dir
 */
function rmdir_plus ($dir) {
  $files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );
  foreach ($files as $fileinfo) {
    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
    $todo($fileinfo->getRealPath());
  }
  rmdir($dir);
}
