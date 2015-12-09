<?php

class FileHandler extends Plugin implements SplObserver, ResourceInterface {
  private static $imageModes = array(
    "" => array(1000, 1000, 307200, 85), // default, e.g. resources like icons
    "images" => array(1000, 1000, 307200, 85), // 300 kB
    "preview" => array(500, 500, 204800, 85), // 200 kB
    "thumb" => array(200, 200, 71680, 85), // 70 kB
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
    "application/octet-stream" => array("woff", "js"),
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
    THEMES_DIR => true, PLUGINS_DIR => true, LIB_DIR => true, FILES_DIR => false
  );
  const FILE_TYPE_RESOURCE = 1;
  const FILE_TYPE_IMAGE = 2;
  const FILE_TYPE_OTHER = 3;
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
    #if(!is_dir(USER_FOLDER."/".$this->pluginDir)) mkdir_plus(USER_FOLDER."/".$this->pluginDir);
  }

  public static function isSupportedRequest() {
    $ext = pathinfo(getCurLink(), PATHINFO_EXTENSION);
    foreach(self::$legalMime as $extensions) {
      if(!in_array($ext, $extensions)) continue;
      return true;
    }
    return false;
  }

  public static function handleRequest() {
    try {
      $fInfo = self::getFileInfo(getCurLink());
      if(self::DEBUG) var_dump($fInfo);
      if(!is_file($fInfo["dest"])) self::createFile($fInfo["src"], $fInfo["dest"], $fInfo["ext"], $fInfo["type"], $fInfo["mode"]);
      if($fInfo["type"] == self::FILE_TYPE_RESOURCE) {
        self::outputFile($fInfo["dest"], "text/".$fInfo["ext"]);
        #todo: optimize to root
      }
      redirTo(ROOT_URL.getCurLink());
    } catch(Exception $e) {
      $errno = $e->getCode() ? $e->getCode() : 500;
      $msg = strlen($e->getMessage()) ? $e->getMessage() : _("Server error");
      throw new Exception(sprintf(_("Unable to handle file request: %s"), $msg), $errno);
    }
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_PROCESS) $this->checkResources();
    if($subject->getStatus() != STATUS_PREINIT) return;
    Cms::setVariable("file_cache_update", getCurLink()."?".CACHE_PARAM."=".CACHE_FILE);
  }

  private static function outputFile($file, $mime) {
    #todo: lock?
    header("Content-type: $mime");
    echo file_get_contents($file);
    exit;
  }

  private static function getFileInfo($reqFilePath) {
    $fInfo["src"] = null;
    $fInfo["dest"] = null;
    $fInfo["ext"] = strtolower(pathinfo($reqFilePath, PATHINFO_EXTENSION));
    $fInfo["type"] = self::getFileType($fInfo["ext"]);
    $fInfo["mode"] = self::getImageMode($reqFilePath);
    $slashPos = strpos($reqFilePath, "/");
    $rootDir = substr($reqFilePath, 0, $slashPos);
    $srcFilePath = $reqFilePath;
    if($rootDir == RESOURCES_DIR || is_link("$rootDir.php")) {
      $srcFilePath = substr($reqFilePath, $slashPos+1);
    }
    foreach(self::$fileFolders as $dir => $resDir) {
      if(strpos($srcFilePath, "$dir/") !== 0) continue;
      if(!$resDir && $rootDir == RESOURCES_DIR) break; // eg. res/files/*
      $fInfo["src"] = $srcFilePath;
      $fInfo["dest"] = $reqFilePath;
      if(!$resDir || $srcFilePath != $reqFilePath) break;
      $fInfo["dest"] = RESOURCES_DIR."/$reqFilePath";
      break;
    }
    if(is_null($fInfo["src"])) throw new Exception(_("File illegal path"), 403);
    if($fInfo["type"] == self::FILE_TYPE_IMAGE) {
      $fInfo["src"] = self::getImageSource($fInfo["src"], $fInfo["mode"]);
    }
    $fInfo["src"] = findFile($fInfo["src"]);
    if(is_null($fInfo["src"])) throw new Exception(_("File not found"), 404);
    return $fInfo;
  }

  private static function getImageSource($src, $mode) {
    if(!strlen($mode) || !is_null(findFile($src, true, true, false))) return $src;
    return FILES_DIR.substr($src, strlen(FILES_DIR."/".$mode));
  }

  private function checkResources() {
    if(!Cms::isSuperUser()) return;
    if(isset($_GET[CACHE_PARAM]) && $_GET[CACHE_PARAM] == CACHE_IGNORE) return;
    foreach(self::$fileFolders as $dir => $resDir) {
      $passed = $this->doCheckResources(($resDir ? getRealResDir($dir) : $dir), $resDir);
    }
    if($passed) Logger::log(_("Outdated cache files successfully removed"), Logger::LOGGER_SUCCESS);
  }

  private function doCheckResources($folder, $resDir, $passed=true) {
    foreach(scandir($folder) as $f) {
      if(strpos($f, ".") === 0) continue;
      $cacheFilePath = "$folder/$f";
      if(is_dir($cacheFilePath)) {
        $passed = $this->doCheckResources($cacheFilePath, $resDir, $passed);
        if(count(scandir($cacheFilePath)) == 2) rmdir($cacheFilePath);
        continue;
      }
      $rawFilePath = $cacheFilePath;
      if($resDir) return substr($cacheFilePath, strlen(getRealResDir())+1);
      $sourceFilePath = findFile($this->getImageSource($rawFilePath, self::getImageMode($rawFilePath)), true, true, false);
      $cacheFileMtime = filemtime($cacheFilePath);
      if(!is_null($sourceFilePath) && $cacheFileMtime == filemtime($sourceFilePath)) continue;
      if(isset($_GET[CACHE_PARAM]) && $_GET[CACHE_PARAM] == CACHE_FILE) {
        try {
          removeResourceFileCache($rawFilePath);
        } catch(Exception $e) {
          $passed = false;
          Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
        }
        return $passed;
      }
      if(self::DEBUG) {
        Cms::addMessage(sprintf("%s@%s | %s:%s@%s", $cacheFilePath, $cacheFileMtime, $rawFilePath, $sourceFilePath, filemtime($sourceFilePath)), Cms::MSG_WARNING);
      } elseif(is_null($sourceFilePath)) {
        Cms::addMessage(sprintf(_("Redundant cache file: %s"), $cacheFilePath), Cms::MSG_WARNING);
      } else {
        Cms::addMessage(sprintf(_("File cache is outdated: %s"), $cacheFilePath), Cms::MSG_WARNING);
      }
    }
  }

  private static function getImageMode($filePath) {
    foreach(self::$imageModes as $mode => $null) {
      if(strpos($filePath, FILES_DIR."/$mode/") === 0) return $mode;
    }
    return "";
  }

  private static function createFile($src, $dest, $ext, $type, $mode) {
    $fp = lockFile($dest);
    try {
      if(is_file($dest)) return;
      self::checkMime($src, $ext);
      if($type == self::FILE_TYPE_IMAGE) self::handleImage($src, $dest, $mode);
      else copy_plus($src, $dest);
    } catch(Exception $e) {
      throw $e;
    } finally {
      unlockFile($fp, $dest);
    }
  }

  private static function checkMime($src, $ext) {
    $mime = getFileMime($src);
    if(isset(self::$legalMime[$mime]) && in_array($ext, self::$legalMime[$mime])) return;
    throw new Exception(sprintf(_("Unsupported mime type %s"), $mime), 415);
  }

  private static function getFileType($ext) {
    if(in_array($ext, array("css", "js"))) return self::FILE_TYPE_RESOURCE;
    if(in_array($ext, array("jpg", "png", "gif", "jpeg"))) return self::FILE_TYPE_IMAGE;
    return self::FILE_TYPE_OTHER;
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

