<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\ResourceInterface;
use Autoprefixer;
use UglifyPHP\JS;
use Imagick;
use Exception;
use SplObserver;
use SplSubject;

class FileHandler extends Plugin implements SplObserver, ResourceInterface {
  const DEBUG = false;
  private static $imageModes = array(
    "" => array(1000, 1000, 307200, 85), // default, e.g. resources like icons
    "images" => array(1000, 1000, 307200, 85), // 300 kB
    "preview" => array(500, 500, 204800, 85), // 200 kB
    "thumbs" => array(200, 200, 71680, 85), // 70 kB
    "big" => array(1500, 1500, 460800, 75), // 450 kB
    "full" => array(0, 0, 0, 0)
  );
  private static $legalMime = array(
    "inode/x-empty" => array("css", "js"),
    "text/plain" => array("css", "js"),
    "text/html" => array("js"),
    "text/x-c" => array("js"),
    "application/x-elc" => array("js"),
    "application/x-empty" => array("css", "js"),
    "application/octet-stream" => array("woff", "eot", "js"),
    "image/svg+xml" => array("svg"),
    "image/png" => array("png"),
    "image/jpeg" => array("jpg", "jpeg"),
    "image/gif" => array("gif"),
    "application/pdf" => array("pdf"),
    "application/vnd.ms-fontobject" => array("eot"),
    "application/x-font-ttf" => array("ttf"),
    "application/vnd.ms-opentype" => array("otf"),
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document" => array("docx")
  );
  private static $fileFolders = array(
    THEMES_DIR => true, PLUGINS_DIR => true, LIB_DIR => true, VENDOR_DIR => true, FILES_DIR => false
  );
  private $deleteCache;
  private $error = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
    $this->deleteCache = isset($_GET[CACHE_PARAM]) && $_GET[CACHE_PARAM] == CACHE_FILE;
    #if(!is_dir(USER_FOLDER."/".$this->pluginDir)) mkdir_plus(USER_FOLDER."/".$this->pluginDir);
  }

  public static function isSupportedRequest() {
    $ext = strtolower(pathinfo(getCurLink(), PATHINFO_EXTENSION));
    foreach(self::$legalMime as $extensions) {
      if(in_array($ext, $extensions)) return true;
    }
    return false;
  }

  public static function handleRequest() {
    try {
      $dest = getCurLink();
      $fInfo = self::getFileInfo($dest);
      #if(self::DEBUG) var_dump($fInfo);
      if(!is_file($dest)) self::createFile($fInfo["src"], $dest, $fInfo["ext"], $fInfo["imgmode"], $fInfo["isroot"], $fInfo["resdir"]);
      if(in_array($fInfo["ext"], array("css", "js"))) {
        self::outputFile($dest, "text/".$fInfo["ext"]);
        if(self::DEBUG) unlink($dest);
        exit;
      }
      redirTo(ROOT_URL.$dest);
    } catch(Exception $e) {
      $errno = $e->getCode() ? $e->getCode() : 500;
      $msg = strlen($e->getMessage()) ? $e->getMessage() : _("Server error");
      throw new Exception(sprintf(_("Unable to handle file request: %s"), $msg), $errno);
    }
    exit;
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_PROCESS) $this->checkResources();
    if($subject->getStatus() != STATUS_PREINIT) return;
    Cms::setVariable("cache_file", getCurLink()."?".CACHE_PARAM."=".CACHE_FILE);
  }

  private static function outputFile($file, $mime) {
    header("Content-type: $mime");
    echo file_get_contents($file);
  }

  private static function getFileInfo($reqFilePath) {
    $fInfo["src"] = null;
    $fInfo["imgmode"] = null;
    $fInfo["ext"] = strtolower(pathinfo($reqFilePath, PATHINFO_EXTENSION));
    $slashPos = strpos($reqFilePath, "/");
    $rootDir = substr($reqFilePath, 0, $slashPos);
    $fInfo["isroot"] = array_key_exists($rootDir, self::$fileFolders);
    $srcFilePath = $reqFilePath;
    if(!$fInfo["isroot"]) {
      $srcFilePath = substr($reqFilePath, $slashPos+1);
    }
    // check path
    $resDir = null;
    foreach(self::$fileFolders as $dir => $resDir) {
      if(strpos($srcFilePath, "$dir/") !== 0) continue;
      if(!$resDir && !$fInfo["isroot"]) break; // eg. beta/files, res/files/*
      $fInfo["src"] = $srcFilePath;
      $fInfo["resdir"] = $resDir;
      break;
    }
    if(is_null($fInfo["src"])) throw new Exception(_("File illegal path"), 403);
    // search for sorce
    $fInfo["src"] = findFile($fInfo["src"], true, true);
    if(!$resDir && is_null($fInfo["src"]) && self::isImage($fInfo["ext"])) {
      $fInfo["imgmode"] = self::getImageMode($srcFilePath);
      if(strlen($fInfo["imgmode"])) {
        $imgFilePath = self::getImageSource($srcFilePath, $fInfo["imgmode"]);
        $fInfo["src"] = findFile($imgFilePath, true, true);
      }
    }
    if(is_null($fInfo["src"])) throw new Exception(_("File not found"), 404);
    return $fInfo;
  }

  private static function getImageSource($src, $mode) {
    if(!strlen($mode)) return $src;
    return FILES_DIR.substr($src, strlen(FILES_DIR."/".$mode));
  }

  private function checkResources() {
    if(!Cms::isSuperUser()) return;
    if(isset($_GET[CACHE_PARAM]) && $_GET[CACHE_PARAM] == CACHE_IGNORE) return;
    foreach(self::$fileFolders as $sourceFolder => $isResDir) {
      $cacheFolder = $sourceFolder;
      if($isResDir && getRealResDir() != RESOURCES_DIR) $cacheFolder = getRealResDir($sourceFolder);
      if(!is_dir($cacheFolder)) continue;
      $this->doCheckResources($cacheFolder, $sourceFolder, $isResDir);
    }
    if(!$this->deleteCache) return;
    if(count($this->error)) Logger::critical(sprintf(_("Failed to delete cache files: %s"), implode(", ", $this->error)));
    else {
      Logger::user_success(_("Outdated cache files successfully removed"));
    }
  }

  private function doCheckResources($cacheFolder, $sourceFolder, $isResDir) {
    $inotifyCache = $cacheFolder."/".INOTIFY."+".str_replace("/", "+", $sourceFolder);
    $inotifySource = USER_FOLDER."/".$sourceFolder."/".INOTIFY;
    $folderUptodate = true;
    $skipFolder = false;
    if(is_file($inotifyCache) && is_file($inotifySource)) {
      $skipFolder = filemtime($inotifyCache) == filemtime($inotifySource);
    }
    foreach(scandir($cacheFolder) as $f) {
      if(strpos($f, ".") === 0) continue;
      $cacheFilePath = "$cacheFolder/$f";
      $sourceFilePath = "$sourceFolder/$f";
      if(is_dir($cacheFilePath)) {
        $this->doCheckResources($cacheFilePath, $sourceFilePath, $isResDir);
        if(count(scandir($cacheFilePath)) == 2) rmdir($cacheFilePath);
        continue;
      }
      if($skipFolder) continue;
      $sourceFilePath = findFile($sourceFilePath, true, true);
      if(is_null($sourceFilePath) && !$isResDir && self::isImage(pathinfo($cacheFilePath, PATHINFO_EXTENSION))) {
        $sourceFilePath = $this->getImageSource($cacheFilePath, self::getImageMode($cacheFilePath));
        $sourceFilePath = findFile($sourceFilePath, true, true);
      }
      try {
        checkFileCache($sourceFilePath, $cacheFilePath);
        if(getRealResDir() != RESOURCES_DIR) continue;
        $cacheFilePath = getRealResDir($cacheFilePath);
        checkFileCache($sourceFilePath, $cacheFilePath);
      } catch(Exception $e) {
        $folderUptodate = false;
        if($e->getCode() == 1 || $this->deleteCache) { // pass if redundant file or deleteCache
          if(!unlink($cacheFilePath)) $this->error[] = $cacheFilePath;
          if(getRealResDir() != RESOURCES_DIR) continue;
          $cacheFilePath = getRealResDir($cacheFilePath);
          if(!is_file($cacheFilePath)) continue;
          if(!unlink($cacheFilePath)) $this->error[] = $cacheFilePath;
        } elseif(self::DEBUG) {
          Cms::notice(sprintf("%s@%s | %s@%s", $cacheFilePath, $cacheFileMtime, $sourceFilePath, filemtime($sourceFilePath)));
        } else {
          Logger::user_warning(sprintf("%s: %s", $e->getMessage(), $cacheFilePath));
        }
      }
    }
    if(!$folderUptodate || !is_file($inotifySource)) return;
    touch($inotifyCache, filemtime($inotifySource));
  }

  private static function getImageMode($filePath) {
    foreach(self::$imageModes as $mode => $null) {
      if(strpos($filePath, FILES_DIR."/$mode/") === 0) return $mode;
    }
    return "";
  }

  private static function createFile($src, $dest, $ext, $imgmode, $isRoot, $resDir) {
    $fp = lock_file($dest);
    try {
      if(is_file($dest)) return;
      self::checkMime($src, $ext);
      if($isRoot && !$resDir) {
        if(self::isImage($ext)) self::handleImage($src, $dest, $imgmode);
        else copy_plus($src, $dest);
      } else { // resDir
        self::handleResource($src, $dest, $ext, $isRoot);
      }
      touch($dest, filemtime($src));
    } finally {
      unlock_file($fp, $dest);
    }
  }

  private static function handleResource($src, $dest, $ext, $isRoot) {
    if($isRoot) {
      if(!IS_LOCALHOST && strpos($src, CMS_FOLDER."/") === 0 && is_file(CMSRES_FOLDER."/".getCurLink())) { // using default file
        $src = CMSRES_FOLDER."/".getCurLink();
      } else switch($ext) {
        case "css":
        self::buildCss($src, $dest);
        return;
        case "js":
        self::buildJs($src, $dest);
        return;
      }
    }
    copy_plus($src, $dest);
  }

  private static function buildCss($src, $dest) {
    $data = file_get_contents($src);
    $autoprefixer = new Autoprefixer(['last 2 version']);
    $data = $autoprefixer->compile($data);
    file_put_contents($dest, $data);
  }

  private static function buildJs($src, $dest) {
    if(!JS::installed())
      throw new Exception(_("UglifyJS not installed"));
    $js = new JS($src);
    if($js->minify($dest)) return;
    throw new Exception(_("Unable to minify JS"));
  }

  private static function checkMime($src, $ext) {
    $mime = getFileMime($src);
    if(isset(self::$legalMime[$mime]) && in_array($ext, self::$legalMime[$mime])) return;
    throw new Exception(sprintf(_("Unsupported mime type %s"), $mime), 415);
  }

  private static function isImage($ext) {
    return in_array(strtolower($ext), array("jpg", "png", "gif", "jpeg"));
  }

  private static function handleImage($src, $dest, $mode) {
    $mode = self::$imageModes[$mode];
    $src = realpath($src);
    $i = self::getImageSize($src);
    if($i[0] <= $mode[0] && $i[1] <= $mode[1]) {
      $fileSize = filesize($src);
      if($fileSize > $mode[2])
        throw new Exception(sprintf(_("Image size %s is over limit %s"), fileSizeConvert($fileSize), fileSizeConvert($mode[2])));
      copy_plus($src, $dest);
      return;
    }
    if($mode[0] == 0 && $mode[1] == 0) {
      copy_plus($src, $dest);
      return;
    }
    $im = new Imagick($src);
    $im->setImageCompressionQuality($mode[3]);
    if($i[0] > $i[1]) $result = $im->thumbnailImage($mode[0], 0);
    else $result = $im->thumbnailImage(0, $mode[1]);
    #var_dump($im->getImageLength());
    $imBin = $im->__toString();
    if(!$result || !strlen($imBin))
      throw new Exception(_("Unable to resize image"));
    if(strlen($imBin) > $mode[2])
      throw new Exception(_("Generated image size %s is over limit %s"), fileSizeConvert(strlen($imBin)), fileSizeConvert($mode[2]));
    mkdir_plus(dirname($dest));
    $b = file_put_contents($dest, $imBin);
    if($b === false || !touch($dest, filemtime($src))) throw new Exception(_("Unable to create file"));
  }

  private static function getImageSize($imagePath) {
    $i = @getimagesize($imagePath);
    if(is_array($i)) return $i;
    throw new Exception(_("Failed to get image dimensions"));
  }

}

