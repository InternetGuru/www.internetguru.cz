<?php

function isAtLocalhost() {
  if($_SERVER["REMOTE_ADDR"] == "127.0.0.1"
  || substr($_SERVER["REMOTE_ADDR"],0,8) == "192.168."
  || substr($_SERVER["REMOTE_ADDR"],0,3) == "10."
  || $_SERVER["REMOTE_ADDR"] == "::1") {
    return true;
  }
  return false;
}

function absoluteLink($link=null) {
  if(is_null($link)) $link = $_SERVER["REQUEST_URI"];
  if(substr($link,0,1) == "/") $link = substr($link,1);
  if(substr($link,-1) == "/") $link = substr($link,0,-1);
  $pLink = parse_url($link);
  if($pLink === false) throw new LoggerException("Unable to parse href '$link'");
  $scheme = isset($pLink["scheme"]) ? $pLink["scheme"] : $_SERVER["REQUEST_SCHEME"];
  $host = isset($pLink["host"]) ? $pLink["host"] : $_SERVER["HTTP_HOST"];
  $path = isset($pLink["path"]) && $pLink["path"] != "" ? "/".$pLink["path"] : "";
  $query = isset($pLink["query"]) ? "?".$pLink["query"] : "";
  return "$scheme://$host$path$query";
}

function redirTo($link,$code=null,$force=false) {
  if(!$force) {
    $curLink = absoluteLink();
    $absLink = absoluteLink($link);
    if($curLink == $absLink)
      throw new LoggerException("Cyclic redirection to '$link'");
  }
  if(class_exists("Logger"))
    new Logger("Redirecting to '$link' with status code '".(is_null($code) ? 302 : $code)."'");
  if(is_null($code) || !is_numeric($code)) {
    header("Location: $link");
    exit();
  }
  header("Location: $link",true,$code);
  header("Refresh: 0; url=$link");
  exit();
}

function chmodGroup($file,$mode) {
  $oldMask = umask(002);
  $chmod = @chmod($file,$mode);
  umask($oldMask);
  return $chmod;
}

function mkdirGroup($dir,$mode=0777,$rec=false) {
  $oldMask = umask(002);
  $dirMade = @mkdir($dir,$mode,$rec);
  umask($oldMask);
  return $dirMade;
}

function __toString($o) {
  echo "|";
  if(is_array($o)) return implode(",",$o);
  return (string) $o;
}

function stableSort(Array &$a) {
  if(count($a) < 2) return;
  $order = range(1,count($a));
  array_multisort($a,SORT_ASC,$order,SORT_ASC);
}

function getCurLink($query=false) {
  if(!$query) return isset($_GET["page"]) ? $_GET["page"] : "";
  $query = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "";
  return substr($query,strlen(getRoot()));
}

function getRoot() {
  if(isAtLocalhost()) {
    $dir = explode("/", $_SERVER["SCRIPT_NAME"]);
    return "/".$dir[1]."/";
  }
  return "/";
}

function getDomain() {
  $d = explode(".",$_SERVER["HTTP_HOST"]);
  while(count($d) > 2) array_shift($d);
  return implode(".",$d);
}

function normalize($s,$extKeep="",$tolower=true,$convertToUtf8=false) {
  if($convertToUtf8) $s = utf8_encode($s);
  if($tolower) $s = mb_strtolower($s,"utf-8");
  $s = iconv("UTF-8", "US-ASCII//TRANSLIT", $s);
  if($tolower) $s = strtolower($s);
  $s = str_replace(" ","_",$s);
  $keep = "~[^a-zA-Z0-9/_%s-]~";
  if(is_null($ext = @preg_replace(sprintf($keep,$extKeep),"",$s))) {
    new Logger("Normalize extended keep expression '".sprintf($keep,$extKeep)."' is invalid","error");
    return preg_replace(sprintf($keep,""),"",$s);
  }
  return $ext;
}

function saveRewriteFile($dest,$src) {
  if(!file_exists($src))
    throw new LoggerException("Source file '$src' not found");
  if(!file_exists(dirname($dest)) && !@mkdir(dirname($dest),0775,true))
    throw new LoggerException("Unable to create directory structure");
  if(!touch($dest))
    throw new LoggerException("Unable to touch destination file");
  if(!copy($dest,"$dest.old"))
    throw new LoggerException("Unable to backup destination file");
  if(!rename("$dest.new",$dest))
    throw new LoggerException("Unable to rename new file to destination");
  return true;
}

function saveRewrite($dest,$content) {
  if(!file_exists(dirname($dest)) && !@mkdir(dirname($dest),0775,true)) return false;
  if(!file_exists($dest)) return file_put_contents($dest, $content);
  $b = file_put_contents("$dest.new", $content);
  if($b === false) return false;
  if(!copy($dest,"$dest.old")) return false;
  if(!rename("$dest.new",$dest)) return false;
  return $b;
}

function matchFiles($pattern, $dir) {
  $files = array();
  $values = explode(" ",$pattern);

  foreach($values as $val) {
    $f = findFile($val);
    if($f) {
      $files[] = $f;
      continue;
    }
    if(strpos($val,"*") === false) continue;
    $f = "$dir/$val";
    $d = pathinfo($f ,PATHINFO_DIRNAME);
    if(!file_exists($d)) continue;
    $fp = str_replace("\*",".*",preg_quote(pathinfo($f ,PATHINFO_BASENAME)));
    foreach(scandir($d) as $f) {
      if(!preg_match("/^$fp$/", $f)) continue;
      $files[getFileHash("$d/$f")] = "$d/$f"; // disallowe import same content
    }
  }

  return $files;
}

function duplicateDir($dir) {
  if(!is_dir($dir)) return;
  $info = pathinfo($dir);
  $bakDir = $info["dirname"]."/~".$info["basename"];
  copyFiles($dir, $bakDir);
  deleteRedundantFiles($bakDir, $dir);
  #new Logger("Active data backup updated");
}

function smartCopy($src, $dest, $delay=0) {
  if(file_exists($dest)) return;
  $destDir = pathinfo($dest,PATHINFO_DIRNAME);
  if(is_dir($destDir)) foreach(scandir($destDir) as $f) {
    if(pathinfo($f,PATHINFO_FILENAME) != pathinfo($src,PATHINFO_BASENAME)) continue;
    if($delay && filectime("$destDir/$f") > time() - $delay) return;
  }
  if(!is_dir($destDir) && !mkdir($destDir,0755,true))
    throw new Exception("Unable to create directory '$destDir'");
  if(!copy($src,$dest)) {
    throw new Exception("Unable to copy '$src' to '$dest'");
  }
}

function deleteRedundantFiles($in, $according) {
  if(!is_dir($in)) return;
  foreach(scandir($in) as $f) {
    if(in_array($f,array(".",".."))) continue;
    if(is_dir("$in/$f")) {
      deleteRedundantFiles("$in/$f","$according/$f");
      if(!is_dir("$according/$f")) rmdir("$in/$f");
      continue;
    }
    if(!is_file("$according/$f")) unlink("$in/$f");
  }
}

function copyFiles($src, $dest) {
  if(!is_dir($dest) && !@mkdir($dest))
    throw new LoggerException("Unable to create '$dest' folder");
  foreach(scandir($src) as $f) {
    if(in_array($f,array(".",".."))) continue;
    if(is_dir("$src/$f")) {
      copyFiles("$src/$f", "$dest/$f");
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
    throw new Exception("Unable to open file");
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
  if(!function_exists("finfo_file")) throw new Exception("Function finfo_file() not supported");
  $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
  $mime = finfo_file($finfo, $file);
  finfo_close($finfo);
  return $mime;
}

function fileSizeConvert($b) {
    if(!is_numeric($b)) return $b;
    $i = 0;
    $iec = array("B","KB","MB","GB","TB","PB","EB","ZB","YB");
    while(($b/1024) > 1) {
        $b = $b/1024;
        $i++;
    }
    return round($b,1)." ".$iec[$i];
}

function checkUrl($folder = null) {
  $rUri = $_SERVER["REQUEST_URI"];
  $pUrl = parse_url($rUri);
  if($pUrl === false || strpos($pUrl["path"], "//") !== false)
    new ErrorPage("The requested URL '$rUri' was not understood by this server.", 400);
  if(!preg_match("/^".preg_quote(getRoot(), "/")."(".FILEPATH_PATTERN.")(\?.+)?$/",$rUri,$m)) return null;

  $fInfo["filepath"] = "$folder/". $m[1];
  if(!is_file($fInfo["filepath"]))
    new ErrorPage("The requested URL '$rUri' was not found on this server.", 404);

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
    new ErrorPage("Unsupported mime type '".$fInfo["filemime"]."'", 415);
  return $fInfo;
}

function getFileHash($filePath) {
  if(!file_exists($filePath)) return "";
  return hash_file(FILE_HASH_ALGO,$filePath);
}

?>