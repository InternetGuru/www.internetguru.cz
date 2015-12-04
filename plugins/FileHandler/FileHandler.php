<?php

class FileHandler extends Plugin implements SplObserver {
  private $imageModes;
  private $restartFile;
  private $fileFolders;
  const FILE_TYPE_RESOURCE = 1;
  const FILE_TYPE_IMAGE = 2;
  const FILE_TYPE_OTHER = 3;
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
    $this->setVariables();
    if(!is_dir(USER_FOLDER."/".$this->pluginDir)) mkdir_plus(USER_FOLDER."/".$this->pluginDir);
  }

  /**
   * SOURCE FILES
   * ------------
   * themes/igcms.js ... copy to res/themes/igcms.js, Grunt (restart/touch)
   * res/themes/igcms.js ... copy, Grunt (restart/touch)
   * beta/themes/igcms.js ... copy, no Grunt
   * else/themes/igcms.js ... 403 illegal
   *
   * IMAGES files/mfkh2015_80pr.png
   * ------
   * files/logo.jpg ... create default mode from files/logo.jpg
   * files/full/logo.jpg ... copy files/logo.jpg
   * files/thumb/logo.jpg ... create thumb from files/logo.jpg
   * files/else/logo.jpg ... 404 not found
   *
   */
  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_PROCESS) $this->checkResources();
    if($subject->getStatus() != STATUS_PREINIT) return;
    $reqFilePath = getCurLink();
    if(!preg_match("/".FILEPATH_PATTERN."/", $reqFilePath)) {
      Cms::setVariable("file_cache_update", "$reqFilePath?".CACHE_PARAM."=".CACHE_FILE);
      return;
    }
    try {
      $fInfo = $this->getFileInfo($reqFilePath);
      $this->handleFile($fInfo["src"], $fInfo["dest"], $fInfo["ext"], $fInfo["type"], $fInfo["mode"]);
      if(self::DEBUG) var_dump($fInfo);
      if($fInfo["type"] == self::FILE_TYPE_RESOURCE) {
        if(!is_file($fInfo["dest"])) throw new Exception();
        if(self::DEBUG) var_dump("OUTPUTTING FILE...");
        else $this->outputFile($fInfo["dest"], $fInfo["mime"]);
      } else {
        if(!is_file($reqFilePath)) throw new Exception();
        if(self::DEBUG) var_dump("REDIRECTING TO REQUEST");
        else redirTo(ROOT_URL.$reqFilePath);
      }
      exit();
    } catch(Exception $e) {
      $errno = $e->getCode() ? $e->getCode() : 500;
      $msg = strlen($e->getMessage()) ? $e->getMessage() : _("Server error");
      new ErrorPage(sprintf(_("Unable to handle file request: %s"), $msg), $errno);
    }
  }

  private function outputFile($file, $mime) {
    header("Content-type: $mime");
    echo file_get_contents($file);
    exit;
  }

  private function getFileInfo($reqFilePath) {
    $fInfo["src"] = null;
    $fInfo["dest"] = null;
    $fInfo["ext"] = strtolower(pathinfo($reqFilePath, PATHINFO_EXTENSION));
    $fInfo["type"] = $this->getFileType($fInfo["ext"]);
    $fInfo["mode"] = $this->getImageMode($reqFilePath);
    foreach($this->fileFolders as $dir => $resDir) {
      if(strpos($reqFilePath, "$dir/") === 0) {
        if($resDir && getRealResDir() != RESOURCES_DIR) break;
        $fInfo["src"] = $fInfo["dest"] = $reqFilePath;
        if($resDir || !IS_LOCALHOST) $fInfo["dest"] = RESOURCES_DIR."/$reqFilePath";
      } elseif(strpos($reqFilePath, getRealResDir($dir)) === 0) {
        if(!$resDir) break;
        $fInfo["src"] = substr($reqFilePath, strlen(getRealResDir())+1);
        $fInfo["dest"] = $reqFilePath;
      }
      if(!is_null($fInfo["src"])) break;
    }
    if(is_null($fInfo["src"])) throw new Exception(_("File illegal path"), 403);
    if($fInfo["type"] == self::FILE_TYPE_IMAGE) {
      $fInfo["src"] = $this->getImageSource($fInfo["src"], $fInfo["mode"]);
      $fInfo["dest"] = $reqFilePath;
    }
    $fInfo["src"] = findFile($fInfo["src"]);
    if(is_null($fInfo["src"])) throw new Exception(_("File not found"), 404);
    return $fInfo;
  }

  private function getImageSource($src, $mode) {
    if(!strlen($mode) || !is_null(findFile($src, true, true, false))) return $src;
    return FILES_DIR.substr($src, strlen(FILES_DIR."/".$mode));
  }

  private function setVariables() {
    $this->fileFolders = array(THEMES_DIR => true, PLUGINS_DIR => true, LIB_DIR => true, FILES_DIR => false);
    $this->restartFile = USER_FOLDER."/".$this->pluginDir."/restart.touch";
    $this->imageModes = array(
      "" => array(1000, 1000, 300*1024, 85), // default, e.g. resources like icons
      "images" => array(1000, 1000, 300*1024, 85),
      "preview" => array(500, 500, 200*1024, 85),
      "thumb" => array(200, 200, 70*1024, 85),
      "big" => array(1500, 1500, 450*1024, 75),
      "full" => array(0, 0, 0, 0)
    );
  }

  private function checkResources() {
    if(!Cms::isSuperUser()) return;
    if(isset($_GET[CACHE_PARAM]) && $_GET[CACHE_PARAM] == CACHE_IGNORE) return;
    foreach($this->fileFolders as $dir => $resDir) {
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
      if(!IS_LOCALHOST && $resDir) return substr($cacheFilePath, strlen(getRealResDir())+1);
      $sourceFilePath = findFile($this->getImageSource($rawFilePath, $this->getImageMode($rawFilePath)), true, true, false);
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

  private function getImageMode($filePath) {
    foreach($this->imageModes as $mode => $null) {
      if(strpos($filePath, FILES_DIR."/$mode/") === 0) return $mode;
    }
    return "";
  }

  private function handleFile($src, $dest, $ext, $type, $mode) {
    $gruntAware = $type == self::FILE_TYPE_RESOURCE && getRealResDir() == RESOURCES_DIR;
    $isDestDir = is_dir(dirname($dest));
    $fp = lockFile($dest);
    try {
      if(is_file($dest)) {
        if(!$gruntAware || $dest == getCurLink()) return;
        // evoke grunt by touching the file (keep mtime)
        if(!touch($dest, filemtime($dest))) throw new Exception("Unable to touch Grunt cache file");
        if(self::DEBUG) var_dump("cache file touched");
        return;
      }
      $this->checkMime($src, $ext);
      switch($type) {
        case self::FILE_TYPE_IMAGE:
        $this->handleImage($src, $dest, $mode);
        break;
        default:
        if($gruntAware && !$isDestDir) $this->restartGrunt($dest);
        copy_plus($src, $dest);
      }
    } catch(Exception $e) {
      throw $e;
    } finally {
      unlockFile($fp, $dest);
    }
  }

  private function checkMime($src, $ext) {
    $mime = getFileMime($src);
    $legalMime = array(
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
    if(isset($legalMime[$mime]) && in_array($ext, $legalMime[$mime])) return;
    throw new Exception(sprintf(_("Unsupported mime type %s"), $mime), 415);
  }

  private function getFileType($ext) {
    if(!IS_LOCALHOST && in_array($ext, array("css", "js"))) return self::FILE_TYPE_RESOURCE;
    if(in_array($ext, array("jpg", "png", "gif", "jpeg"))) return self::FILE_TYPE_IMAGE;
    return self::FILE_TYPE_OTHER;
  }

  private function gruntRestarted() {
    return is_file($this->restartFile) && (time() - filemtime($this->restartFile)) < 35;
  }

  private function restartGrunt($filePath) {
    if($this->gruntRestarted()) return;
    $rfp = lockFile($this->restartFile);
    try {
      if($this->gruntRestarted()) return;
      mkdir_plus(dirname($filePath));
      if(!touch($this->restartFile)) throw new Exception("Unable to touch Grunt restart file");
      exec('/etc/init.d/gruntwatch stop');
    } catch(Exception $e) {
      throw $e;
    } finally {
      unlockFile($rfp, $this->restartFile);
    }
    if(self::DEBUG) var_dump("grunt restarted");
  }

  private function handleImage($src, $dest, $mode) {
    $mode = $this->imageModes[$mode];
    $src = realpath($src);
    $i = $this->getImageSize($src);
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

  private function getImageSize($imagePath) {
    $i = @getimagesize($imagePath);
    if(is_array($i)) return $i;
    throw new Exception(_("Failed to get image dimensions"));
  }

}

