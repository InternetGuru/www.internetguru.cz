<?php

class FileHandler extends Plugin implements SplObserver {
  private $registeredMime;
  private $imageModes;
  private $restartFile;
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

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_PROCESS) $this->checkResources();
    if($subject->getStatus() != STATUS_PREINIT) return;
    $filePath = getCurLink();
    if(!preg_match("/".FILEPATH_PATTERN."/", $filePath)) {
      Cms::setVariable("file_cache_update", "$filePath?".CACHE_PARAM."=".CACHE_FILE);
      return;
    }
    try {
      $legal = false;
      foreach(array(FILES_DIR, LIB_DIR, THEMES_DIR, PLUGINS_DIR) as $dir) {
        if(strpos($filePath, "$dir/") === 0) $legal = true;
        elseif(strpos($filePath, RESOURCES_DIR."/$dir/") === 0) $legal = true;
        if($legal) break;
      }
      if(!$legal) throw new Exception(_("File illegal path"), 404);
      $destFile = $this->handleFile($filePath);
      redirTo(ROOT_URL.$destFile);
    } catch(Exception $e) {
      $errno = 500;
      if($e->getCode() != 0) $errno = $e->getCode();
      new ErrorPage(sprintf(_("Unable to handle file request: %s"), $e->getMessage()), $errno);
    }
  }

  private function setVariables() {
    $this->restartFile = USER_FOLDER."/".$this->pluginDir."/restart.touch";
    $this->registeredMime = array(
      "inode/x-empty" => array("css", "js"),
      "text/plain" => array("css", "js"),
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
    $this->imageModes = array(
      "" => array(1000, 1000, 300*1024, 85), // default, e.g. resources like icons
      "images" => array(1000, 1000, 300*1024, 85),
      "preview" => array(500, 500, 200*1024, 85),
      "thumbs" => array(200, 200, 70*1024, 85),
      "big" => array(1500, 1500, 450*1024, 75),
      "full" => array(0, 0, 0, 0)
    );
  }

  private function checkResources() {
    if(!Cms::isSuperUser()) return;
    if(isset($_GET[CACHE_PARAM]) && $_GET[CACHE_PARAM] == CACHE_IGNORE) return;
    $dirs = array(THEMES_DIR => true, PLUGINS_DIR => true, LIB_DIR => true, FILES_DIR => false);
    foreach($dirs as $dir => $resDir) {
      $this->doCheckResources(($resDir ? getRealResDir($dir) : $dir), $resDir);
    }
  }

  private function doCheckResources($folder, $resDir) {
    foreach(scandir($folder) as $f) {
      if(strpos($f, ".") === 0) continue;
      $cacheFilePath = "$folder/$f";
      if(is_dir($cacheFilePath)) {
        $this->doCheckResources($cacheFilePath, $resDir);
        if(count(scandir($cacheFilePath)) == 2) rmdir($cacheFilePath);
        continue;
      }
      $rawSourceFilePath = $cacheFilePath;
      if(!IS_LOCALHOST && $resDir) $rawSourceFilePath = substr($cacheFilePath, strlen(getRealResDir())+1);
      $sourceFilePath = findFile($rawSourceFilePath, true, true, false);
      if(is_null($sourceFilePath) && !$resDir) $sourceFilePath = $this->getSourceFile($rawSourceFilePath);
      $cacheFileMtime = filemtime($cacheFilePath);
      if(!is_null($sourceFilePath) && $cacheFileMtime == filemtime($sourceFilePath)) continue;
      if(isset($_GET[CACHE_PARAM]) && $_GET[CACHE_PARAM] == CACHE_FILE) {
        $passed = true;
        try {
          removeResourceFileCache($rawSourceFilePath);
        } catch(Exception $e) {
          $passed = false;
          Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
        }
        if($passed) Logger::log(_("Outdated cache files successfully removed"), Logger::LOGGER_SUCCESS);
        return;
      }
      if(self::DEBUG) {
        Cms::addMessage(sprintf("%s@%s | %s:%s@%s", $cacheFilePath, $cacheFileMtime, $rawSourceFilePath, $sourceFilePath, filemtime($sourceFilePath)), Cms::MSG_WARNING);
      } elseif(is_null($sourceFilePath)) {
        Cms::addMessage(sprintf(_("Redundant cache file: %s"), $cacheFilePath), Cms::MSG_WARNING);
      } else {
        Cms::addMessage(sprintf(_("File cache is outdated: %s"), $cacheFilePath), Cms::MSG_WARNING);
      }
    }
  }

  private function getSourceFile($dest, &$mode=null) {
    $src = !is_null($mode) ? findFile($dest) : findFile($dest, true, true, false);
    $pLink = explode("/", $dest);
    if(count($pLink) > 2) {
      if(!is_null($mode)) $mode = $pLink[1];
      if(is_null($src)) {
        unset($pLink[1]);
        $dest = implode("/", $pLink);
        $src = !is_null($mode) ? findFile($dest) : findFile($dest, true, true, false);
      }
    }
    return $src;
  }

  private function handleFile($reqFilePath) {
    $src = $this->getSourceFile($reqFilePath, $mode);
    if(!$src) throw new Exception(_("File not found"), 404);
    $extension = strtolower(pathinfo($reqFilePath, PATHINFO_EXTENSION));
    $fileType = $this->getFileType($extension);
    $filePath = $reqFilePath;
    if($fileType == self::FILE_TYPE_RESOURCE) {
      // always work in resource folder
      if(strpos($filePath, RESOURCES_DIR."/") !== 0) $filePath = getRealResDir($filePath);
      // restart grunt iff resource folder does not exist
      $restartGrunt = !is_dir(dirname($filePath));
    }
    $fp = lockFile($filePath);
    try {
      if(is_file($filePath)) {
        if($fileType == self::FILE_TYPE_RESOURCE && $reqFilePath != $filePath) {
          // evoke grunt by touching the file (keep mtime)
          touch($filePath, filemtime($filePath));
          #if(self::DEBUG) throw new Exception("TOUCHING RES...", 555);
          // does it have to sleep? or how long?
          usleep(200000);
          // if it helps, return the requested file
          if(is_file($reqFilePath)) return $reqFilePath;
        }
        return $filePath;
      }
      $mimeType = getFileMime($src);
      if(!isset($this->registeredMime[$mimeType]) || !in_array($extension, $this->registeredMime[$mimeType]))
        throw new Exception(sprintf(_("Unsupported mime type %s"), $mimeType), 415);
      switch($fileType) {
        case self::FILE_TYPE_IMAGE:
        if(!isset($this->imageModes[$mode])) $mode = "";
        $this->handleImage(realpath($src), $filePath, $this->imageModes[$mode]);
        break;
        case self::FILE_TYPE_RESOURCE:
        if($restartGrunt && !$this->gruntRestarted()) $this->restartGrunt($filePath);
        copy_plus($src, $filePath);
        usleep(200000);
        if(is_file($reqFilePath)) return $reqFilePath;
        break;
        case self::FILE_TYPE_OTHER:
        copy_plus($src, $filePath);
      }
      return $filePath;
    } catch(Exception $e) {
      throw $e;
    } finally {
      unlockFile($fp, $filePath);
    }
  }

  private function getFileType($extension) {
    if(!IS_LOCALHOST && in_array($extension, array("css", "js"))) return self::FILE_TYPE_RESOURCE;
    if(in_array($extension, array("jpg", "png", "gif", "jpeg"))) return self::FILE_TYPE_IMAGE;
    return self::FILE_TYPE_OTHER;
  }

  private function gruntRestarted() {
    return is_file($this->restartFile) && (time() - filemtime($this->restartFile)) < 35;
  }

  private function restartGrunt($filePath) {
    #if(self::DEBUG) throw new Exception("RESTARTING GRUNT", 555);
    $rfp = lockFile($this->restartFile);
    try {
      if($this->gruntRestarted()) return;
      mkdir_plus(dirname($filePath));
      touch($this->restartFile);
      exec('/etc/init.d/gruntwatch stop');
    } catch(Exception $e) {
      throw $e;
    } finally {
      unlockFile($rfp, $this->restartFile);
    }
  }

  private function handleImage($src, $dest, $mode) {
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

