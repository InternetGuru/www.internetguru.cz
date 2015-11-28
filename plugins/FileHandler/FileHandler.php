<?php

class FileHandler extends Plugin implements SplObserver {
  const DEBUG = false;
  const CLEAR_CACHE_PARAM = "clearfilecache";

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
    if(!is_dir(USER_FOLDER."/".$this->pluginDir)) mkdir_plus(USER_FOLDER."/".$this->pluginDir);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_PROCESS && Cms::isSuperUser()) {
      try {
        $this->checkResources();
        if(isset($_GET[self::CLEAR_CACHE_PARAM]))
          Logger::log(_("Outdated files successfully removed"), Logger::LOGGER_SUCCESS);
      } catch(Exception $e) {
        Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
      }
    }
    if($subject->getStatus() != STATUS_PREINIT) return;
    $filePath = getCurLink();
    if(!preg_match("/".FILEPATH_PATTERN."/", $filePath)) {
      Cms::setVariable("cfcurl", "$filePath?".self::CLEAR_CACHE_PARAM);
      return;
    }
    if(strpos($filePath, RESOURCES_DIR) === 0) $filePath = substr($filePath, strlen(RESOURCES_DIR)+1);
    try {
      $legal = false;
      foreach(array(FILES_DIR, LIB_DIR, THEMES_DIR, PLUGINS_DIR) as $dir) {
        if(strpos($filePath, "$dir/") === 0) $legal = true;
      }
      if(!$legal) throw new Exception(_("File illegal path"), 404);
      $this->handleFile($filePath);
      redirTo(ROOT_URL.getCurLink());
    } catch(Exception $e) {
      $errno = 500;
      if($e->getCode() != 0) $errno = $e->getCode();
      new ErrorPage(sprintf(_("Unable to handle file request: %s"), $e->getMessage()), $errno);
    }
  }

  private function checkResources() {
    $dirs = array(THEMES_DIR => false, PLUGINS_DIR => false, LIB_DIR => false, FILES_DIR => true);
    $e = null;
    foreach($dirs as $dir => $checkSource) {
      try {
        $this->doCheckResources($dir, $checkSource);
      } catch(Exception $e) {}
    }
    if(!is_null($e)) throw new Exception($e->getMessage());
  }

  private function doCheckResources($folder, $checkSource) {
    foreach(scandir($folder) as $f) {
      if(strpos($f, ".") === 0) continue;
      $ff = "$folder/$f";
      if(is_dir($ff)) {
        $this->doCheckResources($ff, $checkSource);
        if(count(scandir($ff)) == 2) rmdir($ff);
        continue;
      }
      $filePath = findFile($ff, true, true, false);
      if(is_null($filePath) && $checkSource) $filePath = $this->getSourceFile($ff);
      if(is_file(RESOURCES_DIR."/$ff")) $mtime = filemtime(RESOURCES_DIR."/$ff");
      else $mtime = filemtime($ff);
      if(!is_null($filePath) && $mtime == filemtime($filePath)) continue;
      if(isset($_GET[self::CLEAR_CACHE_PARAM])) {
        try {
          removeResourceFileCache($ff);
        } catch(Exception $e) {
          Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
        }
      } else {
        if(self::DEBUG && !is_null($filePath)) {
          Cms::addMessage(sprintf("%s:%s | %s:%s", $ff, $mtime, $filePath, filemtime($filePath)), Cms::MSG_WARNING);
        } elseif(is_null($filePath)) {
          Cms::addMessage(sprintf(_("Redundant cache file: %s"), $ff), Cms::MSG_WARNING);
        } else {
          Cms::addMessage(sprintf(_("File cache is outdated: %s"), $ff), Cms::MSG_WARNING);
        }
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

  private function handleFile($dest) {
    $mode = "";
    $src = $this->getSourceFile($dest, $mode);
    if(!$src) throw new Exception(_("Requested URL not found on this server"), 404);
    $fp = lockFile("$src.lock");
    try {
      if(is_file($dest)) return;
      $mimeType = getFileMime($src);
      if($mimeType != "image/svg+xml" && strpos($mimeType, "image/") === 0) {
        $modes = array(
          "" => array(1000, 1000, 300*1024, 85), // default, e.g. resources like icons
          "images" => array(1000, 1000, 300*1024, 85),
          "preview" => array(500, 500, 200*1024, 85),
          "thumbs" => array(200, 200, 70*1024, 85),
          "big" => array(1500, 1500, 450*1024, 75),
          "full" => array(0, 0, 0, 0)
        );
        if(!isset($modes[$mode])) $mode = "";
        $this->handleImage(realpath($src), $dest, $modes[$mode]);
        return;
      }
      $registeredMime = array(
        "inode/x-empty" => array(), // empty file with any ext
        "text/plain" => array("css", "js"),
        "text/x-c" => array("js"),
        "application/x-elc" => array("js"),
        "application/x-empty" => array("css", "js"),
        "application/octet-stream" => array("woff", "js"),
        "image/svg+xml" => array("svg"),
        "application/pdf" => array("pdf"),
        "application/vnd.ms-fontobject" => array("eot"),
        "application/x-font-ttf" => array("ttf"),
        "application/vnd.ms-opentype" => array("otf"),
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document" => array("docx"),
      );
      $ext = pathinfo($src, PATHINFO_EXTENSION);
      if(!isset($registeredMime[$mimeType]) || (!empty($registeredMime[$mimeType]) && !in_array($ext, $registeredMime[$mimeType])))
        throw new Exception(sprintf(_("Unsupported mime type %s"), $mimeType), 415);
      if(IS_LOCALHOST || !$this->isResource($src)) {
        if(is_file($dest)) return;
        copy_plus($src, $dest);
        return;
      }
      $dest = getResourcePath($dest);
      $destDir = dirname($dest);
      if($destDir == RESOURCES_DIR) $this->restartGrunt($dest);
      if(!is_file($dest)) {
        copy_plus($src, $dest);
      }
      usleep(rand(5,15)*100000);
    } catch(Exception $e) {
      throw $e;
    } finally {
      unlockFile($fp);
      unlink("$src.lock");
    }
  }

  private function restartGrunt($filePath) {
    $restartFile = USER_FOLDER."/".$this->pluginDir."/restart.touch";
    $rfp = lockFile("$restartFile.lock");
    if(!is_dir(RESOURCES_DIR)) {
      mkdir_plus(RESOURCES_DIR);
      touch($restartFile);
      exec('/etc/init.d/gruntwatch stop');
    }
    if(!is_dir(RESOURCES_DIR) && (!is_file($restartFile) || (time() - filemtime($restartFile)) >= 35 )) {
      touch($restartFile);
      exec('/etc/init.d/gruntwatch stop');
    }
    if(is_file($filePath)) unlink($filePath);
    unlockFile($rfp);
  }

  private function isResource($src) {
    //return in_array(pathinfo($src, PATHINFO_EXTENSION), array("scss", "less", "css", "js", "coffee"));
    return in_array(pathinfo($src, PATHINFO_EXTENSION), array("css", "js"));
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

